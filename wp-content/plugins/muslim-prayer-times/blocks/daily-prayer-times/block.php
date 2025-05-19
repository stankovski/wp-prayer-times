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
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
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
        false // No actual CSS file
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
    $time_format = isset($opts['time_format']) ? $opts['time_format'] : '12hour';
    
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
        
        // Query the database for current day's prayer times
        $prayer_times = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE day = %s",
            $current_date
        ), ARRAY_A);
        
        // If no times available for this day, try finding the next available date
        if (!$prayer_times && $i === 0) {
            $future_time = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE day >= %s ORDER BY day ASC LIMIT 1",
                $current_date
            ), ARRAY_A);
            
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
            $next_available = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE day > %s ORDER BY day ASC LIMIT 1",
                $current_date
            ), ARRAY_A);
            
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
            <p>No prayer times available for today or future dates.</p>
        </div>';
    }
    
    // Extract attributes for styling
    $className = isset($attributes['className']) ? $attributes['className'] : '';
    $align = isset($attributes['align']) ? $attributes['align'] : 'center';
    $textColor = isset($attributes['textColor']) ? $attributes['textColor'] : '';
    $backgroundColor = isset($attributes['backgroundColor']) ? $attributes['backgroundColor'] : '';
    $headerColor = isset($attributes['headerColor']) ? $attributes['headerColor'] : '';
    $showDate = isset($attributes['showDate']) ? $attributes['showDate'] : true;
    $showHijriDate = isset($attributes['showHijriDate']) ? $attributes['showHijriDate'] : true;
    $showSunrise = isset($attributes['showSunrise']) ? $attributes['showSunrise'] : true;
    $tableStyle = isset($attributes['tableStyle']) ? $attributes['tableStyle'] : 'default';
    $fontSize = isset($attributes['fontSize']) ? $attributes['fontSize'] : 16;
    $showArrows = isset($attributes['showArrows']) ? $attributes['showArrows'] : true;
    
    // Get time format from settings
    $opts = get_option('muslprti_settings', []);
    $time_format = isset($opts['time_format']) ? $opts['time_format'] : '12hour';
    
    // Create inline styles
    $container_style = "text-align: {$align};";
    if ($fontSize) {
        $container_style .= "font-size: {$fontSize}px;";
    }
    
    $table_style = '';
    if ($backgroundColor) {
        $table_style .= "background-color: {$backgroundColor};";
    }
    if ($textColor) {
        $table_style .= "color: {$textColor};";
    }
    
    $header_style = '';
    if ($headerColor) {
        $header_style .= "background-color: {$headerColor};";
    }
    $header_style .= "text-transform: uppercase;";
    
    // Define icons for each prayer
    $icons_dir = plugins_url('assets/icons/', dirname(__DIR__));
    $prayer_icons = array(
        'fajr' => $icons_dir . 'fajr.svg',
        'sunrise' => $icons_dir . 'sunrise.svg',
        'dhuhr' => $icons_dir . 'dhuhr.svg',
        'asr' => $icons_dir . 'asr.svg',
        'maghrib' => $icons_dir . 'maghrib.svg',
        'isha' => $icons_dir . 'isha.svg'
    );
    
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
            $output .= '<p>No prayer times available for ' . muslprti_date('l, F j, Y', strtotime($current_date)) . '</p>';
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
        $output .= '<th></th><th>Athan</th><th>Iqama</th>';
        $output .= '</tr></thead>';
        
        // Table body
        $output .= '<tbody>';
        
        // Fajr row
        $output .= '<tr>';
        $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['fajr']) . '" alt="Fajr" class="prayer-icon"> Fajr</td>';
        $output .= '<td>' . (isset($formatted_times['fajr_athan']) ? esc_html($formatted_times['fajr_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['fajr_iqama']) ? esc_html($formatted_times['fajr_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        // Sunrise row (if enabled)
        if ($showSunrise) {
            $output .= '<tr class="sunrise-row">';
            $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['sunrise']) . '" alt="Sunrise" class="prayer-icon"> Sunrise</td>';
            $output .= '<td colspan="2">' . (isset($formatted_times['sunrise']) ? esc_html($formatted_times['sunrise']) : '-') . '</td>';
            $output .= '</tr>';
        }
        
        // Dhuhr row
        $output .= '<tr>';
        $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['dhuhr']) . '" alt="Dhuhr" class="prayer-icon"> Dhuhr</td>';
        $output .= '<td>' . (isset($formatted_times['dhuhr_athan']) ? esc_html($formatted_times['dhuhr_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['dhuhr_iqama']) ? esc_html($formatted_times['dhuhr_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        // Asr row
        $output .= '<tr>';
        $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['asr']) . '" alt="Asr" class="prayer-icon"> Asr</td>';
        $output .= '<td>' . (isset($formatted_times['asr_athan']) ? esc_html($formatted_times['asr_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['asr_iqama']) ? esc_html($formatted_times['asr_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        // Maghrib row
        $output .= '<tr>';
        $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['maghrib']) . '" alt="Maghrib" class="prayer-icon"> Maghrib</td>';
        $output .= '<td>' . (isset($formatted_times['maghrib_athan']) ? esc_html($formatted_times['maghrib_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['maghrib_iqama']) ? esc_html($formatted_times['maghrib_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        // Isha row
        $output .= '<tr>';
        $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['isha']) . '" alt="Isha" class="prayer-icon"> Isha</td>';
        $output .= '<td>' . (isset($formatted_times['isha_athan']) ? esc_html($formatted_times['isha_athan']) : '-') . '</td>';
        $output .= '<td class="iqama-time">' . (isset($formatted_times['isha_iqama']) ? esc_html($formatted_times['isha_iqama']) : '-') . '</td>';
        $output .= '</tr>';
        
        $output .= '</tbody></table>';
        
        // Add Jumuah times if available in settings
        $opts = get_option('muslprti_settings', []);
        $jumuah1 = isset($opts['jumuah1']) && !empty($opts['jumuah1']) ? $opts['jumuah1'] : '';
        $jumuah2 = isset($opts['jumuah2']) && !empty($opts['jumuah2']) ? $opts['jumuah2'] : '';
        $jumuah3 = isset($opts['jumuah3']) && !empty($opts['jumuah3']) ? $opts['jumuah3'] : '';
        
        // Get custom Jumuah names
        $jumuah1_name = isset($opts['jumuah1_name']) ? $opts['jumuah1_name'] : 'Jumuah 1';
        $jumuah2_name = isset($opts['jumuah2_name']) ? $opts['jumuah2_name'] : 'Jumuah 2';
        $jumuah3_name = isset($opts['jumuah3_name']) ? $opts['jumuah3_name'] : 'Jumuah 3';

        // Format Jumuah times to 12-hour format
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
