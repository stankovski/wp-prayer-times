<?php
/**
 * Registers any additional scripts or data needed for the Live Prayer Times block
 */

if (!defined('ABSPATH')) exit;

// Require the Hijri date converter
require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/hijri-date-converter.php';

/**
 * Enqueues the block editor script and adds plugin URL data
 */
function muslprti_live_prayer_times_editor_assets() {
    // Get the block script
    $block_script = plugins_url('block.js', __FILE__);
    
    // Register the script with WordPress
    wp_register_script(
        'muslprti-live-prayer-times-block',
        $block_script,
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
    );
    
    // Add plugin URL data to be used in JavaScript - sanitize the URL
    wp_localize_script('muslprti-live-prayer-times-block', 'wpPrayerTimesData', array(
        'pluginUrl' => esc_url(plugins_url('', dirname(dirname(__FILE__))))
    ));
}
add_action('enqueue_block_editor_assets', 'muslprti_live_prayer_times_editor_assets');

/**
 * Enqueues frontend scripts for the block
 */
function muslprti_live_prayer_times_frontend_assets() {
    // Only enqueue on frontend, not in admin
    if (!is_admin()) {
        wp_enqueue_script(
            'muslprti-live-prayer-times-frontend',
            plugins_url('frontend.js', __FILE__),
            array('jquery'),
            filemtime(plugin_dir_path(__FILE__) . 'frontend.js'),
            true
        );
        
        // Add AJAX URL and nonce for frontend use - properly escaped
        wp_localize_script('muslprti-live-prayer-times-frontend', 'prayerTimesLiveData', array(
            'ajaxUrl' => esc_url_raw(rest_url('prayer-times/v1/times')),
            'nonce' => wp_create_nonce('wp_rest'),
            'timezone' => sanitize_text_field(wp_timezone_string())
        ));
    }
}
add_action('wp_enqueue_scripts', 'muslprti_live_prayer_times_frontend_assets');

/**
 * Register REST API endpoint for prayer times
 */
function muslprti_register_prayer_times_endpoints() {
    register_rest_route('prayer-times/v1', '/times/(?P<date>\d{4}-\d{2}-\d{2})', array(
        'methods' => 'GET',
        'callback' => 'muslprti_get_times_for_date',
        // This is a public endpoint, so we don't need authentication
        'permission_callback' => '__return_true',
        'args' => array(
            'date' => array(
                'validate_callback' => function($param) {
                    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                }
            ),
        ),
    ));
}
add_action('rest_api_init', 'muslprti_register_prayer_times_endpoints');

/**
 * Handle the REST API request for prayer times
 */
function muslprti_get_times_for_date($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . MUSLPRTI_IQAMA_TABLE;
    
    // Get requested date and sanitize it
    $date = sanitize_text_field($request->get_param('date'));
    
    // Get settings
    $opts = get_option('muslprti_settings', []);
    $timeFormat = isset($opts['time_format']) ? sanitize_text_field($opts['time_format']) : '12hour';
    
    // Get prayer times for the requested date using prepared query
    $prayer_times = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE day = %s",
        $date
    ), ARRAY_A);
    
    // If no times available for requested date, try finding the next available date
    if (!$prayer_times) {
        $prayer_times = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE day >= %s ORDER BY day ASC LIMIT 1",
            $date
        ), ARRAY_A);
    }
    
    // If still no prayer times available, return error
    if (!$prayer_times) {
        return new WP_Error(
            'no_times_found',
            esc_html__('No prayer times available for the requested date or future dates.', 'muslim-prayer-times'),
            array('status' => 404)
        );
    }
    
    // Format the prayer times for display
    $formatted_times = array();
    $prayer_columns = array(
        'fajr_athan' => 'Fajr Athan',
        'fajr_iqama' => 'Fajr Iqama',
        'sunrise' => 'Sunrise',
        'dhuhr_athan' => 'Dhuhr Athan',
        'dhuhr_iqama' => 'Dhuhr Iqama',
        'asr_athan' => 'Asr Athan',
        'asr_iqama' => 'Asr Iqama',
        'maghrib_athan' => 'Maghrib Athan',
        'maghrib_iqama' => 'Maghrib Iqama',
        'isha_athan' => 'Isha Athan',
        'isha_iqama' => 'Isha Iqama'
    );
    
    foreach ($prayer_columns as $column => $label) {
        if (isset($prayer_times[$column]) && $prayer_times[$column]) {
            $time = strtotime($prayer_times[$column]);
            
            if ($timeFormat === '24hour') {
                $formatted_times[$column] = muslprti_date('H:i', $time);
            } else {
                $formatted_times[$column] = muslprti_date('g:i A', $time);
            }
        } else {
            $formatted_times[$column] = '-';
        }
    }
    
    // Get Hijri date if needed
    $hijri_date = '';
    $hijri_date_arabic = '';
    // Get hijri offset from settings
    $hijri_offset = isset($opts['hijri_offset']) ? intval($opts['hijri_offset']) : 0;
    
    if (function_exists('muslprti_convert_to_hijri')) {
        $hijri_date = muslprti_convert_to_hijri($prayer_times['day'], true, 'en', $hijri_offset);
        $hijri_date_arabic = muslprti_convert_to_hijri($prayer_times['day'], true, 'ar', $hijri_offset);
    }
    
    // Check for upcoming changes in the next 3 days
    $future_changes = array();
    
    // Get the next 3 days' prayer times using prepared query
    $next_days = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE day > %s ORDER BY day ASC LIMIT 3",
        $prayer_times['day']
    ), ARRAY_A);
    
    // Check for changes in prayer times
    if ($next_days) {
        foreach ($next_days as $next_day) {
            $changes_for_day = array();
            $date_formatted = muslprti_date('D, M j', strtotime($next_day['day']));
            
            // Loop through each prayer time column
            foreach ($next_day as $column => $value) {
                // Skip non-prayer time columns
                if (in_array($column, array('id', 'day'))) continue;
                
                // Skip sunrise time changes
                if ($column === 'sunrise') continue;
                
                // Only check for iqama time changes
                if (strpos($column, '_iqama') === false) continue;
                
                // Check if this time differs from today's time
                if (isset($prayer_times[$column]) && $prayer_times[$column] != $value && !empty($value)) {
                    // Format the time for display
                    $time = strtotime($value);
                    
                    if ($timeFormat === '24hour') {
                        $formatted_time = muslprti_date('H:i', $time);
                    } else {
                        $formatted_time = muslprti_date('g:i A', $time);
                    }
                    
                    $changes_for_day[$column] = array(
                        'new_time' => $formatted_time,
                        'date' => $date_formatted,
                        'day' => sanitize_text_field($next_day['day'])
                    );
                }
            }
            
            // If we found changes for this day, add them to our changes array
            if (!empty($changes_for_day)) {
                $future_changes[$next_day['day']] = array(
                    'date' => $date_formatted,
                    'changes' => $changes_for_day
                );
            }
        }
    }
    
    // Format date for display
    $display_date = muslprti_date('l, F j, Y', strtotime($prayer_times['day']));
    
    // Get Jumuah times from settings with sanitization
    $jumuah_times = array();
    
    $jumuah1 = isset($opts['jumuah1']) && !empty($opts['jumuah1']) ? sanitize_text_field($opts['jumuah1']) : '';
    $jumuah2 = isset($opts['jumuah2']) && !empty($opts['jumuah2']) ? sanitize_text_field($opts['jumuah2']) : '';
    $jumuah3 = isset($opts['jumuah3']) && !empty($opts['jumuah3']) ? sanitize_text_field($opts['jumuah3']) : '';
    
    $jumuah1_name = isset($opts['jumuah1_name']) ? sanitize_text_field($opts['jumuah1_name']) : esc_html__('Jumuah 1', 'muslim-prayer-times');
    $jumuah2_name = isset($opts['jumuah2_name']) ? sanitize_text_field($opts['jumuah2_name']) : esc_html__('Jumuah 2', 'muslim-prayer-times');
    $jumuah3_name = isset($opts['jumuah3_name']) ? sanitize_text_field($opts['jumuah3_name']) : esc_html__('Jumuah 3', 'muslim-prayer-times');
    
    if (!empty($jumuah1)) {
        $jumuah1_time = strtotime($jumuah1);
        if ($timeFormat === '24hour') {
            $jumuah_times[] = array(
                'name' => $jumuah1_name,
                'time' => muslprti_date('H:i', $jumuah1_time)
            );
        } else {
            $jumuah_times[] = array(
                'name' => $jumuah1_name,
                'time' => muslprti_date('g:i A', $jumuah1_time)
            );
        }
    }
    
    if (!empty($jumuah2)) {
        $jumuah2_time = strtotime($jumuah2);
        if ($timeFormat === '24hour') {
            $jumuah_times[] = array(
                'name' => $jumuah2_name,
                'time' => muslprti_date('H:i', $jumuah2_time)
            );
        } else {
            $jumuah_times[] = array(
                'name' => $jumuah2_name,
                'time' => muslprti_date('g:i A', $jumuah2_time)
            );
        }
    }
    
    if (!empty($jumuah3)) {
        $jumuah3_time = strtotime($jumuah3);
        if ($timeFormat === '24hour') {
            $jumuah_times[] = array(
                'name' => $jumuah3_name,
                'time' => muslprti_date('H:i', $jumuah3_time)
            );
        } else {
            $jumuah_times[] = array(
                'name' => $jumuah3_name,
                'time' => muslprti_date('g:i A', $jumuah3_time)
            );
        }
    }
    
    // Prepare the response with sanitized data
    $response = array(
        'date' => sanitize_text_field($prayer_times['day']),
        'display_date' => $display_date,
        'hijri_date' => $hijri_date,
        'hijri_date_arabic' => $hijri_date_arabic,
        'time_format' => $timeFormat,
        'times' => $formatted_times,
        'future_changes' => $future_changes,
        'jumuah_times' => $jumuah_times
    );
    
    return rest_ensure_response($response);
}
