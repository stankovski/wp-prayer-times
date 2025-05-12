<?php
/**
 * Registers any additional scripts or data needed for the Live Prayer Times block
 */

if (!defined('ABSPATH')) exit;

/**
 * Enqueues the block editor script and adds plugin URL data
 */
function prayertimes_live_prayer_times_editor_assets() {
    // Get the block script
    $block_script = plugins_url('block.js', __FILE__);
    
    // Register the script with WordPress
    wp_register_script(
        'prayertimes-live-prayer-times-block',
        $block_script,
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
    );
    
    // Add plugin URL data to be used in JavaScript
    wp_localize_script('prayertimes-live-prayer-times-block', 'wpPrayerTimesData', array(
        'pluginUrl' => plugins_url('', dirname(dirname(__FILE__)))
    ));
}
add_action('enqueue_block_editor_assets', 'prayertimes_live_prayer_times_editor_assets');

/**
 * Enqueues frontend scripts for the block
 */
function prayertimes_live_prayer_times_frontend_assets() {
    // Only enqueue on frontend, not in admin
    if (!is_admin()) {
        wp_enqueue_script(
            'prayertimes-live-prayer-times-frontend',
            plugins_url('frontend.js', __FILE__),
            array('jquery'),
            filemtime(plugin_dir_path(__FILE__) . 'frontend.js'),
            true
        );
        
        // Add AJAX URL and nonce for frontend use
        wp_localize_script('prayertimes-live-prayer-times-frontend', 'prayerTimesLiveData', array(
            'ajaxUrl' => rest_url('prayer-times/v1/times'),
            'nonce' => wp_create_nonce('wp_rest'),
            'timezone' => wp_timezone_string()
        ));
    }
}
add_action('wp_enqueue_scripts', 'prayertimes_live_prayer_times_frontend_assets');

/**
 * Register REST API endpoint for prayer times
 */
function prayertimes_register_prayer_times_endpoints() {
    register_rest_route('prayer-times/v1', '/times/(?P<date>\d{4}-\d{2}-\d{2})', array(
        'methods' => 'GET',
        'callback' => 'prayertimes_get_times_for_date',
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
add_action('rest_api_init', 'prayertimes_register_prayer_times_endpoints');

/**
 * Handle the REST API request for prayer times
 */
function prayertimes_get_times_for_date($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . PRAYERTIMES_IQAMA_TABLE;
    
    // Get requested date
    $date = $request->get_param('date');
    
    // Get settings
    $opts = get_option('prayertimes_settings', []);
    $timeFormat = isset($opts['time_format']) ? $opts['time_format'] : '12hour';
    
    // Get prayer times for the requested date
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
            'No prayer times available for the requested date or future dates.',
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
                $formatted_times[$column] = prayertimes_date('H:i', $time);
            } else {
                $formatted_times[$column] = prayertimes_date('g:i A', $time);
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
    
    if (function_exists('prayertimes_convert_to_hijri')) {
        $hijri_date = prayertimes_convert_to_hijri($prayer_times['day'], true, 'en', $hijri_offset);
        $hijri_date_arabic = prayertimes_convert_to_hijri($prayer_times['day'], true, 'ar', $hijri_offset);
    }
    
    // Check for upcoming changes in the next 3 days
    $future_changes = array();
    
    // Get the next 3 days' prayer times
    $next_days = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE day > %s ORDER BY day ASC LIMIT 3",
        $prayer_times['day']
    ), ARRAY_A);
    
    // Check for changes in prayer times
    if ($next_days) {
        foreach ($next_days as $next_day) {
            $changes_for_day = array();
            $date_formatted = prayertimes_date('D, M j', strtotime($next_day['day']));
            
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
                        $formatted_time = prayertimes_date('H:i', $time);
                    } else {
                        $formatted_time = prayertimes_date('g:i A', $time);
                    }
                    
                    $changes_for_day[$column] = array(
                        'new_time' => $formatted_time,
                        'date' => $date_formatted,
                        'day' => $next_day['day']
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
    $display_date = prayertimes_date('l, F j, Y', strtotime($prayer_times['day']));
    
    // Get Jumuah times from settings
    $jumuah_times = array();
    
    $jumuah1 = isset($opts['jumuah1']) && !empty($opts['jumuah1']) ? $opts['jumuah1'] : '';
    $jumuah2 = isset($opts['jumuah2']) && !empty($opts['jumuah2']) ? $opts['jumuah2'] : '';
    $jumuah3 = isset($opts['jumuah3']) && !empty($opts['jumuah3']) ? $opts['jumuah3'] : '';
    
    $jumuah1_name = isset($opts['jumuah1_name']) ? $opts['jumuah1_name'] : 'Jumuah 1';
    $jumuah2_name = isset($opts['jumuah2_name']) ? $opts['jumuah2_name'] : 'Jumuah 2';
    $jumuah3_name = isset($opts['jumuah3_name']) ? $opts['jumuah3_name'] : 'Jumuah 3';
    
    if (!empty($jumuah1)) {
        $jumuah1_time = strtotime($jumuah1);
        if ($timeFormat === '24hour') {
            $jumuah_times[] = array(
                'name' => $jumuah1_name,
                'time' => prayertimes_date('H:i', $jumuah1_time)
            );
        } else {
            $jumuah_times[] = array(
                'name' => $jumuah1_name,
                'time' => prayertimes_date('g:i A', $jumuah1_time)
            );
        }
    }
    
    if (!empty($jumuah2)) {
        $jumuah2_time = strtotime($jumuah2);
        if ($timeFormat === '24hour') {
            $jumuah_times[] = array(
                'name' => $jumuah2_name,
                'time' => prayertimes_date('H:i', $jumuah2_time)
            );
        } else {
            $jumuah_times[] = array(
                'name' => $jumuah2_name,
                'time' => prayertimes_date('g:i A', $jumuah2_time)
            );
        }
    }
    
    if (!empty($jumuah3)) {
        $jumuah3_time = strtotime($jumuah3);
        if ($timeFormat === '24hour') {
            $jumuah_times[] = array(
                'name' => $jumuah3_name,
                'time' => prayertimes_date('H:i', $jumuah3_time)
            );
        } else {
            $jumuah_times[] = array(
                'name' => $jumuah3_name,
                'time' => prayertimes_date('g:i A', $jumuah3_time)
            );
        }
    }
    
    // Prepare the response
    $response = array(
        'date' => $prayer_times['day'],
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
