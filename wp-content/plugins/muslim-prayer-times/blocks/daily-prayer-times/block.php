<?php
/**
 * Daily Muslim Prayer Times Gutenberg Block
 */

if (!defined('ABSPATH')) exit;

/**
 * Register the block
 */
function muslprti_register_daily_prayer_times_block() {
    // Register block script
    wp_register_script(
        'muslprti-daily-prayer-times-block',
        plugins_url('block.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js'),
        false
    );

    // Register carousel script
    wp_register_script(
        'muslprti-prayer-times-carousel',
        plugins_url('carousel.js', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'carousel.js'),
        true
    );

    // Register block styles
    wp_register_style(
        'muslprti-daily-prayer-times-style',
        plugins_url('style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'style.css')
    );
    
    // Register dynamic styles handle that will receive inline CSS
    wp_register_style(
        'muslprti-daily-prayer-times-dynamic-style',
        false, // No actual CSS file
        array(),
        '1.0.0' // Version parameter to avoid caching issues
    );
    wp_enqueue_style('muslprti-daily-prayer-times-dynamic-style');

    // Register the block
    register_block_type('prayer-times/daily-prayer-times', array(
        'editor_script' => 'muslprti-daily-prayer-times-block',
        'editor_style' => 'muslprti-daily-prayer-times-style',
        'style' => 'muslprti-daily-prayer-times-style',
        'script' => 'muslprti-prayer-times-carousel',
        'render_callback' => 'muslprti_render_daily_prayer_times_block',
        'attributes' => array(
            'className' => array(
                'type' => 'string',
                'default' => '',
            ),
            'align' => array(
                'type' => 'string',
                'default' => 'center',
            ),
            'textColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'backgroundColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'headerColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'showDate' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showHijriDate' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showSunrise' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'tableStyle' => array(
                'type' => 'string',
                'default' => 'default',
            ),
            'fontSize' => array(
                'type' => 'number',
                'default' => 16,
            ),
            'showArrows' => array(
                'type' => 'boolean',
                'default' => true,
            ),
        ),
    ));
}
add_action('init', 'muslprti_register_daily_prayer_times_block');

/**
 * Render the Daily Muslim Prayer Times block on the frontend
 */
function muslprti_render_daily_prayer_times_block($attributes) {
    global $wpdb;
    $table_name = $wpdb->prefix . MUSLPRTI_IQAMA_TABLE;
    
    // Load Hijri date converter
    require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/hijri-date-converter.php';
    
    // Get timezone from settings
    $opts = get_option('muslprti_settings', []);
    $timezone = muslprti_get_timezone();
    $time_format = isset($opts['time_format']) ? sanitize_text_field($opts['time_format']) : '12hour';
    
    // Create DateTime object with timezone
    $datetime_zone = new DateTimeZone($timezone);
    $now = new DateTime('now', $datetime_zone);
    $today = $now->format('Y-m-d');
    $days_to_display = 5; // Show prayer times for 5 days
    
    // Array to store prayer times for multiple days
    $days_prayer_times = array();
    
    // Get prayer times for the next X days
    for ($i = 0; $i < $days_to_display; $i++) {
        $current_date_obj = clone $now;
        $current_date_obj->modify("+$i days");
        $current_date = $current_date_obj->format('Y-m-d');
        
        // Check cache first
        $cache_key = 'muslprti_prayer_times_' . $current_date;
        $prayer_times = wp_cache_get($cache_key, 'muslim_prayer_times');
        
        if (false === $prayer_times) {
            // Query the database for current day's prayer times using prepared statement
            $prayer_times = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE day = %s",
                $current_date
            ), ARRAY_A);
            
            // Cache the result for 1 hour (3600 seconds)
            wp_cache_set($cache_key, $prayer_times, 'muslim_prayer_times', 3600);
        }
        
        // If no times available for this day, try finding the next available date
        if (!$prayer_times && $i === 0) {
            $future_cache_key = 'muslprti_future_prayer_times_' . $current_date;
            $future_time = wp_cache_get($future_cache_key, 'muslim_prayer_times');
            
            if (false === $future_time) {
                $future_time = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE day >= %s ORDER BY day ASC LIMIT 1",
                    $current_date
                ), ARRAY_A);
                
                // Cache the result for 1 hour
                wp_cache_set($future_cache_key, $future_time, 'muslim_prayer_times', 3600);
            }
            
            if ($future_time) {
                $prayer_times = $future_time;
                $current_date = $prayer_times['day']; // Update current date to match found prayer times
                $today = $current_date; // Update today's date for calculating next days
            }
        }
        
        // If we have prayer times for this day, add to our array
        if ($prayer_times) {
            $days_prayer_times[] = array(
                'date' => $current_date,
                'prayer_times' => $prayer_times
            );
        } else {
            // Try to find next available date after current_date
            $next_cache_key = 'muslprti_next_prayer_times_' . $current_date;
            $next_available = wp_cache_get($next_cache_key, 'muslim_prayer_times');
            
            if (false === $next_available) {
                $next_available = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE day > %s ORDER BY day ASC LIMIT 1",
                    $current_date
                ), ARRAY_A);
                
                // Cache the result for 1 hour
                wp_cache_set($next_cache_key, $next_available, 'muslim_prayer_times', 3600);
            }
            
            if ($next_available) {
                $days_prayer_times[] = array(
                    'date' => $next_available['day'],
                    'prayer_times' => $next_available
                );
                $current_date = $next_available['day']; // Update current date for next iteration
            } else {
                // If no more future dates available, just add a placeholder
                $days_prayer_times[] = array(
                    'date' => $current_date,
                    'prayer_times' => null
                );
            }
        }
    }
    
    // If no prayer times at all, return a message
    if (empty($days_prayer_times) || !$days_prayer_times[0]['prayer_times']) {
        return '<div class="wp-block-prayer-times-daily-prayer-times">
            <p>' . esc_html__('No prayer times available for today or future dates.', 'muslim-prayer-times') . '</p>
        </div>';
    }
    
    // Extract attributes for styling with sanitization
    $className = isset($attributes['className']) ? sanitize_html_class($attributes['className']) : '';
    $align = isset($attributes['align']) ? sanitize_text_field($attributes['align']) : 'center';
    $textColor = isset($attributes['textColor']) ? sanitize_hex_color($attributes['textColor']) : '';
    $backgroundColor = isset($attributes['backgroundColor']) ? sanitize_hex_color($attributes['backgroundColor']) : '';
    $headerColor = isset($attributes['headerColor']) ? sanitize_hex_color($attributes['headerColor']) : '';
    $showDate = isset($attributes['showDate']) ? (bool)$attributes['showDate'] : true;
    $showHijriDate = isset($attributes['showHijriDate']) ? (bool)$attributes['showHijriDate'] : true;
    $showSunrise = isset($attributes['showSunrise']) ? (bool)$attributes['showSunrise'] : true;
    $tableStyle = isset($attributes['tableStyle']) ? sanitize_text_field($attributes['tableStyle']) : 'default';
    $fontSize = isset($attributes['fontSize']) ? absint($attributes['fontSize']) : 16;
    $showArrows = isset($attributes['showArrows']) ? (bool)$attributes['showArrows'] : true;
    
    // Create inline styles
    $container_style = "text-align: " . esc_attr($align) . ";";
    if ($fontSize) {
        $container_style .= "font-size: " . esc_attr($fontSize) . "px;";
    }
    
    $table_style = '';
    if ($backgroundColor) {
        $table_style .= "background-color: " . esc_attr($backgroundColor) . ";";
    }
    if ($textColor) {
        $table_style .= "color: " . esc_attr($textColor) . ";";
    }
    
    $header_style = '';
    if ($headerColor) {
        $header_style .= "background-color: " . esc_attr($headerColor) . ";";
    }
    $header_style .= "text-transform: uppercase;";
    
    // Define icons for each prayer
    $icons_dir = plugins_url('assets/icons/', dirname(__DIR__));
    $prayer_icons = array(
        'fajr' => esc_url($icons_dir . 'fajr.svg'),
        'sunrise' => esc_url($icons_dir . 'sunrise.svg'),
        'dhuhr' => esc_url($icons_dir . 'dhuhr.svg'),
        'asr' => esc_url($icons_dir . 'asr.svg'),
        'maghrib' => esc_url($icons_dir . 'maghrib.svg'),
        'isha' => esc_url($icons_dir . 'isha.svg')
    );
    
    // Helper function to get prayer icon HTML
    $get_prayer_icon = function($prayer_name) use ($prayer_icons) {
        if (isset($prayer_icons[$prayer_name])) {
            return '<span class="prayer-icon" style="background-image: url(' . esc_url($prayer_icons[$prayer_name]) . ');" aria-hidden="true"></span>';
        }
        return '';
    };

    // Build the HTML output
    $output = '<div class="wp-block-prayer-times-daily-prayer-times ' . esc_attr($className) . '" style="' . esc_attr($container_style) . '">';
    
    // Create carousel container
    $output .= '<div class="prayer-times-carousel">';
    
    // Create carousel inner container for slides
    $output .= '<div class="prayer-times-carousel-inner">';
    
    // Generate carousel items for each day
    foreach ($days_prayer_times as $day_data) {
        $prayer_times = $day_data['prayer_times'];
        $current_date = $day_data['date'];
        
        // If no prayer times for this day, create an empty slide with a message
        if (!$prayer_times) {
            $output .= '<div class="prayer-times-carousel-item">';
            // translators: %s is a date in the format "Day of week, Month Day, Year" (e.g. "Monday, January 1, 2023")
            $output .= '<p>' . esc_html(sprintf(__('No prayer times available for %s', 'muslim-prayer-times'), 
                muslprti_date('l, F j, Y', strtotime($current_date)))) . '</p>';
            $output .= '</div>';
            continue;
        }
        
        // Format times for display (convert from 24h to 12h format)
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
                // Skip sunrise if not showing
                if ($column === 'sunrise' && !$showSunrise) {
                    continue;
                }
                
                $time = strtotime($prayer_times[$column]);
                // Format the time based on the global time format setting
                if ($time_format === '24hour') {
                    $formatted_times[$column] = muslprti_date('H:i', $time);
                } else {
                    $formatted_times[$column] = muslprti_date('g:i A', $time);
                }
            }
        }
        
        // Start carousel item
        $output .= '<div class="prayer-times-carousel-item">';
        
        // Format date for display
        $display_date = muslprti_date('l, F j, Y', strtotime($current_date));
        
        // Get Hijri date
        $hijri_date = '';
        if ($showHijriDate) {
            // Get hijri offset from settings
            $opts = get_option('muslprti_settings', []);
            $hijri_offset = isset($opts['hijri_offset']) ? intval($opts['hijri_offset']) : 0;
            $hijri_date = muslprti_convert_to_hijri($current_date, true, 'en', $hijri_offset);
        }
        
        // Add date if enabled
        if ($showDate) {
            $output .= '<div class="prayer-times-date">';
            $output .= '<div class="gregorian-date">' . esc_html($display_date) . '</div>';
            if ($showHijriDate && !empty($hijri_date)) {
                $output .= '<div class="hijri-date">' . esc_html($hijri_date) . '</div>';
            }
            $output .= '</div>';
        }
        
        // Start table
        $output .= '<table class="prayer-times-table ' . esc_attr('table-style-' . $tableStyle) . '" style="' . esc_attr($table_style) . '">';
        
        // Table header
        $output .= '<thead><tr style="' . esc_attr($header_style) . '">';
        $output .= '<th></th><th>' . esc_html__('Athan', 'muslim-prayer-times') . '</th><th>' . esc_html__('Iqama', 'muslim-prayer-times') . '</th>';
        $output .= '</tr></thead>';
        
        // Table body
        $output .= '<tbody>';
        
        // Fajr row
        $output .= '<tr>';
        $output .= '<td class="prayer-name">' . $get_prayer_icon('fajr') . ' ' . esc_html__('Fajr', 'muslim-prayer-times') . '</td>';
        $output .= '<td>' . (isset($formatted_times['fajr_athan']) ? esc_html($formatted_times['fajr_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['fajr_iqama']) ? esc_html($formatted_times['fajr_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        // Sunrise row (if enabled)
        if ($showSunrise) {
            $output .= '<tr class="sunrise-row">';
            $output .= '<td class="prayer-name">' . $get_prayer_icon('sunrise') . ' ' . esc_html__('Sunrise', 'muslim-prayer-times') . '</td>';
            $output .= '<td colspan="2">' . (isset($formatted_times['sunrise']) ? esc_html($formatted_times['sunrise']) : '-') . '</td>';
            $output .= '</tr>';
        }
        
        // Dhuhr row
        $output .= '<tr>';
        $output .= '<td class="prayer-name">' . $get_prayer_icon('dhuhr') . ' ' . esc_html__('Dhuhr', 'muslim-prayer-times') . '</td>';
        $output .= '<td>' . (isset($formatted_times['dhuhr_athan']) ? esc_html($formatted_times['dhuhr_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['dhuhr_iqama']) ? esc_html($formatted_times['dhuhr_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        // Asr row
        $output .= '<tr>';
        $output .= '<td class="prayer-name">' . $get_prayer_icon('asr') . ' ' . esc_html__('Asr', 'muslim-prayer-times') . '</td>';
        $output .= '<td>' . (isset($formatted_times['asr_athan']) ? esc_html($formatted_times['asr_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['asr_iqama']) ? esc_html($formatted_times['asr_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        // Maghrib row
        $output .= '<tr>';
        $output .= '<td class="prayer-name">' . $get_prayer_icon('maghrib') . ' ' . esc_html__('Maghrib', 'muslim-prayer-times') . '</td>';
        $output .= '<td>' . (isset($formatted_times['maghrib_athan']) ? esc_html($formatted_times['maghrib_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['maghrib_iqama']) ? esc_html($formatted_times['maghrib_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        // Isha row
        $output .= '<tr>';
        $output .= '<td class="prayer-name">' . $get_prayer_icon('isha') . ' ' . esc_html__('Isha', 'muslim-prayer-times') . '</td>';
        $output .= '<td>' . (isset($formatted_times['isha_athan']) ? esc_html($formatted_times['isha_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['isha_iqama']) ? esc_html($formatted_times['isha_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        $output .= '</tbody></table>';
        
        // Add Jumuah times if available in settings
        $opts = get_option('muslprti_settings', []);
        $jumuah1 = isset($opts['jumuah1']) && !empty($opts['jumuah1']) ? sanitize_text_field($opts['jumuah1']) : '';
        $jumuah2 = isset($opts['jumuah2']) && !empty($opts['jumuah2']) ? sanitize_text_field($opts['jumuah2']) : '';
        $jumuah3 = isset($opts['jumuah3']) && !empty($opts['jumuah3']) ? sanitize_text_field($opts['jumuah3']) : '';
        
        // Get custom Jumuah names
        $jumuah1_name = isset($opts['jumuah1_name']) ? sanitize_text_field($opts['jumuah1_name']) : 'Jumuah 1';
        $jumuah2_name = isset($opts['jumuah2_name']) ? sanitize_text_field($opts['jumuah2_name']) : 'Jumuah 2';
        $jumuah3_name = isset($opts['jumuah3_name']) ? sanitize_text_field($opts['jumuah3_name']) : 'Jumuah 3';

        // Format Jumuah times to the proper format
        if (!empty($jumuah1)) {
            $jumuah1_time = strtotime($jumuah1);
            if ($time_format === '24hour') {
                $jumuah1 = muslprti_date('H:i', $jumuah1_time);
            } else {
                $jumuah1 = muslprti_date('g:i A', $jumuah1_time);
            }
        }
        if (!empty($jumuah2)) {
            $jumuah2_time = strtotime($jumuah2);
            if ($time_format === '24hour') {
                $jumuah2 = muslprti_date('H:i', $jumuah2_time);
            } else {
                $jumuah2 = muslprti_date('g:i A', $jumuah2_time);
            }
        }
        if (!empty($jumuah3)) {
            $jumuah3_time = strtotime($jumuah3);
            if ($time_format === '24hour') {
                $jumuah3 = muslprti_date('H:i', $jumuah3_time);
            } else {
                $jumuah3 = muslprti_date('g:i A', $jumuah3_time);
            }
        }

        // Only display Jumuah times if at least one is set
        if (!empty($jumuah1) || !empty($jumuah2) || !empty($jumuah3)) {
            $output .= '<div class="prayer-times-jumuah">';
            $output .= '<table class="jumuah-times-table ' . esc_attr('table-style-' . $tableStyle) . '" style="' . esc_attr($table_style) . '">';
            $output .= '<tr>';
            
            // Add cells for each available Jumuah time
            if (!empty($jumuah1)) {
                $output .= '<td class="jumuah-time">';
                $output .= '<span class="jumuah-time-value">' . esc_html($jumuah1) . '</span>';
                $output .= '<span class="jumuah-label">' . esc_html($jumuah1_name) . '</span>';
                $output .= '</td>';
            }
            
            if (!empty($jumuah2)) {
                $output .= '<td class="jumuah-time">';
                $output .= '<span class="jumuah-time-value">' . esc_html($jumuah2) . '</span>';
                $output .= '<span class="jumuah-label">' . esc_html($jumuah2_name) . '</span>';
                $output .= '</td>';
            }
            
            if (!empty($jumuah3)) {
                $output .= '<td class="jumuah-time">';
                $output .= '<span class="jumuah-time-value">' . esc_html($jumuah3) . '</span>';
                $output .= '<span class="jumuah-label">' . esc_html($jumuah3_name) . '</span>';
                $output .= '</td>';
            }
            
            $output .= '</tr>';
            $output .= '</table>';
            $output .= '</div>';
        }
        
        $output .= '</div>'; // End carousel item
    }
    
    $output .= '</div>'; // End carousel inner
    
    // Add carousel navigation dots
    $output .= '<div class="prayer-times-carousel-dots">';
    for ($i = 0; $i < count($days_prayer_times); $i++) {
        $output .= '<div class="prayer-times-carousel-dot' . ($i === 0 ? ' active' : '') . '"></div>';
    }
    $output .= '</div>'; // End carousel dots
    
    $output .= '</div>'; // End carousel container
    $output .= '</div>'; // End main container
    
    return $output;
}
