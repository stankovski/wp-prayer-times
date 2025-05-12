<?php
/**
 * Live Prayer Times Gutenberg Block
 */

if (!defined('ABSPATH')) exit;

/**
 * Register the block
 */
function prayertimes_register_live_prayer_times_block() {
    // Register block script
    wp_register_script(
        'prayertimes-live-prayer-times-block',
        plugins_url('block.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
    );

    // Register frontend script
    wp_register_script(
        'prayertimes-live-prayer-times-frontend',
        plugins_url('frontend.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'frontend.js'),
        true
    );

    // Register block styles
    wp_register_style(
        'prayertimes-live-prayer-times-style',
        plugins_url('style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'style.css')
    );

    // Register the block
    register_block_type('prayer-times/live-prayer-times', array(
        'editor_script' => 'prayertimes-live-prayer-times-block',
        'editor_style' => 'prayertimes-live-prayer-times-style',
        'style' => 'prayertimes-live-prayer-times-style',
        'script' => 'prayertimes-live-prayer-times-frontend',
        'render_callback' => 'prayertimes_render_live_prayer_times_block',
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
            'headerTextColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'highlightColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'clockColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'clockSize' => array(
                'type' => 'number',
                'default' => 40,
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
            'showSeconds' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showChanges' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'changeColor' => array(
                'type' => 'string',
                'default' => '#ff0000',
            ),
        ),
    ));
}
add_action('init', 'prayertimes_register_live_prayer_times_block');

/**
 * Render the Live Prayer Times block on the frontend
 */
function prayertimes_render_live_prayer_times_block($attributes) {
    global $wpdb;
    $table_name = $wpdb->prefix . PRAYERTIMES_IQAMA_TABLE;
    
    // Load Hijri date converter if needed
    if (isset($attributes['showHijriDate']) && $attributes['showHijriDate']) {
        require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/hijri-date-converter.php';
    }
    
    // Get timezone from settings
    $opts = get_option('prayertimes_settings', []);
    $timezone = prayertimes_get_timezone();
    $timeFormat = isset($opts['time_format']) ? $opts['time_format'] : '12hour';
    
    // Create DateTime object with timezone
    $datetime_zone = new DateTimeZone($timezone);
    $now = new DateTime('now', $datetime_zone);
    $today = $now->format('Y-m-d');
    
    // Query the database for today's prayer times
    $prayer_times = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE day = %s",
        $today
    ), ARRAY_A);
    
    // If no times available for today, try finding the next available date
    if (!$prayer_times) {
        $future_time = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE day >= %s ORDER BY day ASC LIMIT 1",
            $today
        ), ARRAY_A);
        
        if ($future_time) {
            $prayer_times = $future_time;
            $today = $prayer_times['day']; // Update today's date to match found prayer times
        }
    }
    
    // If no prayer times available, return a message
    if (!$prayer_times) {
        return '<div class="wp-block-prayer-times-live-prayer-times">
            <p>No prayer times available for today or future dates.</p>
        </div>';
    }

    // Check for the next 3 days' prayer times to detect changes
    $future_changes = array();
    $has_changes = false;
    $show_changes = isset($attributes['showChanges']) ? $attributes['showChanges'] : true;
    
    if ($show_changes) {
        // Get the next 3 days' prayer times
        $next_days = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE day > %s ORDER BY day ASC LIMIT 3",
            $today
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
                        $has_changes = true;
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
    }
    
    // Extract attributes for styling
    $className = isset($attributes['className']) ? $attributes['className'] : '';
    $align = isset($attributes['align']) ? $attributes['align'] : 'center';
    $textColor = isset($attributes['textColor']) ? $attributes['textColor'] : '';
    $backgroundColor = isset($attributes['backgroundColor']) ? $attributes['backgroundColor'] : '';
    $headerColor = isset($attributes['headerColor']) ? $attributes['headerColor'] : '';
    $headerTextColor = isset($attributes['headerTextColor']) ? $attributes['headerTextColor'] : '';
    $highlightColor = isset($attributes['highlightColor']) ? $attributes['highlightColor'] : '';
    $clockColor = isset($attributes['clockColor']) ? $attributes['clockColor'] : '';
    $clockSize = isset($attributes['clockSize']) ? $attributes['clockSize'] : 40;
    $showDate = isset($attributes['showDate']) ? $attributes['showDate'] : true;
    $showHijriDate = isset($attributes['showHijriDate']) ? $attributes['showHijriDate'] : true;
    $showSunrise = isset($attributes['showSunrise']) ? $attributes['showSunrise'] : true;
    $tableStyle = isset($attributes['tableStyle']) ? $attributes['tableStyle'] : 'default';
    $fontSize = isset($attributes['fontSize']) ? $attributes['fontSize'] : 16;
    $showSeconds = isset($attributes['showSeconds']) ? $attributes['showSeconds'] : true;
    $changeColor = isset($attributes['changeColor']) ? $attributes['changeColor'] : '#ff0000';
    
    // Create inline styles
    $container_style = "text-align: {$align};";
    if ($fontSize) {
        $container_style .= "font-size: {$fontSize}px;";
    }
    
    $row_style = '';
    if ($backgroundColor) {
        $row_style .= "background-color: {$backgroundColor};";
    }

    $table_style = '';
    if ($textColor) {
        $table_style .= "color: {$textColor};";
    }
    
    $header_style = '';
    if ($headerColor) {
        $header_style .= "background-color: {$headerColor};";
    }
    if ($headerTextColor) {
        $header_style .= "color: {$headerTextColor};";
    }
    
    $highlight_style = '';
    if ($highlightColor) {
        $highlight_style .= "color: {$highlightColor};";
    }
    
    $change_style = "color: {$changeColor};";
    if ($backgroundColor) {
        $change_style .= "background-color: {$backgroundColor};";
    }

    $change_header_style = "color: {$changeColor};";
    if ($headerColor) {
        $change_header_style .= "background-color: {$headerColor};";
    }

    $clock_style = '';
    if ($clockColor) {
        $clock_style .= "color: {$clockColor};";
    }
    if ($clockSize) {
        $clock_style .= "font-size: {$clockSize}px;";
    }
    
    // Define icons for each prayer
    $icons_dir = plugins_url('assets/icons/', dirname(__DIR__));
    $prayer_icons = array(
        'fajr' => $icons_dir . 'fajr.svg',
        'sunrise' => $icons_dir . 'sunrise.svg',
        'dhuhr' => $icons_dir . 'dhuhr.svg',
        'asr' => $icons_dir . 'asr.svg',
        'maghrib' => $icons_dir . 'maghrib.svg',
        'isha' => $icons_dir . 'isha.svg',
        'athan' => $icons_dir . 'athan.svg',
        'iqama' => $icons_dir . 'iqama.svg',
    );
    
    // Format times for display (convert from 24h to 12h format if needed)
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
            // Format based on time format
            if ($timeFormat === '24hour') {
                $formatted_times[$column] = prayertimes_date('H:i', $time);
            } else {
                $formatted_times[$column] = prayertimes_date('g:i A', $time);
            }
        }
    }
    
    // Generate a unique ID for this block instance
    $block_id = 'prayertimes-live-' . uniqid();
    
    // Build the HTML output
    $output = '<div id="' . esc_attr($block_id) . '" class="wp-block-prayer-times-live-prayer-times ' . esc_attr($className) . '" 
               style="' . esc_attr($container_style) . '"
               data-show-seconds="' . esc_attr($showSeconds ? '1' : '0') . '"
               data-time-format="' . esc_attr($timeFormat) . '">';
    
    // Add SVG filter for icon coloring
    $headerTextColorHex = $headerTextColor ?: '#000000';
    $rgb = prayertimes_hex2rgb($headerTextColorHex);
    $red = isset($rgb['r']) ? ($rgb['r'] / 255) : 0;
    $green = isset($rgb['g']) ? ($rgb['g'] / 255) : 0;
    $blue = isset($rgb['b']) ? ($rgb['b'] / 255) : 0;
    
    // Add SVG filter definition
    $output .= '<svg width="0" height="0" style="position:absolute">
      <filter id="' . esc_attr($block_id) . '-icon-color">
        <feColorMatrix type="matrix" values="0 0 0 0 ' . esc_attr($red) . ' 0 0 0 0 ' . esc_attr($green) . ' 0 0 0 0 ' . esc_attr($blue) . ' 0 0 0 1 0" />
      </filter>
    </svg>';
    
    // Add the current time clock
    $output .= '<div class="live-prayer-clock" style="' . esc_attr($clock_style) . '">';
    $output .= '<span class="live-time">00:00:00</span>';
    $output .= '</div>';
    
    // Format date for display
    $display_date = prayertimes_date('l, F j, Y', strtotime($today));
    
    // Get Hijri date if needed
    $hijri_date = '';
    $hijri_date_arabic = '';
    if ($showHijriDate) {
        // Get hijri offset from settings
        $opts = get_option('prayertimes_settings', []);
        $hijri_offset = isset($opts['hijri_offset']) ? intval($opts['hijri_offset']) : 0;
        $hijri_date = function_exists('prayertimes_convert_to_hijri') ? prayertimes_convert_to_hijri($today, true, 'en', $hijri_offset) : '';
        $hijri_date_arabic = function_exists('prayertimes_convert_to_hijri') ? prayertimes_convert_to_hijri($today, true, 'ar', $hijri_offset) : '';
    }
    
    // Add date if enabled
    if ($showDate) {
        $output .= '<div class="prayer-times-date">';
        $output .= '<div class="gregorian-date">' . esc_html($display_date) . '</div>';
        if ($showHijriDate && !empty($hijri_date)) {
            $output .= '<div class="hijri-date">' . esc_html($hijri_date) . '</div>';
            $output .= '<div class="hijri-date-arabic" style="' . esc_attr($highlight_style) . '">' . esc_html($hijri_date_arabic) . '</div>';
        }
        $output .= '</div>';
    }
    
    // Prayer times table
    $output .= '<table class="prayer-times-live-table ' . esc_attr('table-style-' . $tableStyle) . '" style="' . esc_attr($table_style) . '">';
    
    // Table header
    $output .= '<thead><tr style="' . esc_attr($header_style) . '">';
    $output .= '<th style="' . esc_attr($header_style) . '"></th><th style="' . esc_attr($header_style) . '"><img src="' . esc_url($prayer_icons['athan']) . '" alt="Athan" class="header-icon" style="filter:url(#' . esc_attr($block_id) . '-icon-color)">Athan</th><th style="' . esc_attr($header_style) . '"><img src="' . esc_url($prayer_icons['iqama']) . '" alt="Iqama" class="header-icon" style="filter:url(#' . esc_attr($block_id) . '-icon-color)">Iqama</th>';
    
    // Add the changes column header if we have changes
    if ($has_changes && $show_changes) {
        // Find the earliest date with changes
        // Find the earliest date with changes
        $earliest_change_date = '';
        if (!empty($future_changes)) {
            // Sort by date
            ksort($future_changes);
            // Get the first change date
            $first_change = reset($future_changes);
            $earliest_change_date = $first_change['date'];
        }
        
        $header_text = !empty($earliest_change_date) ? prayertimes_date('M j', strtotime($earliest_change_date)) : '';
        $output .= '<th style="' . esc_attr($change_header_style) . '" class="changes-column">' . esc_html($header_text) . '</th>';
    }
    
    $output .= '</tr></thead>';
    
    // Table body
    $output .= '<tbody>';
    
    // Prayer rows - now including potential changes
    $prayer_map = array(
        'fajr' => array('athan' => 'fajr_athan', 'iqama' => 'fajr_iqama', 'name' => 'Fajr'),
        'sunrise' => array('time' => 'sunrise', 'name' => 'Sunrise'),
        'dhuhr' => array('athan' => 'dhuhr_athan', 'iqama' => 'dhuhr_iqama', 'name' => 'Dhuhr'),
        'asr' => array('athan' => 'asr_athan', 'iqama' => 'asr_iqama', 'name' => 'Asr'),
        'maghrib' => array('athan' => 'maghrib_athan', 'iqama' => 'maghrib_iqama', 'name' => 'Maghrib'),
        'isha' => array('athan' => 'isha_athan', 'iqama' => 'isha_iqama', 'name' => 'Isha')
    );
    
    foreach ($prayer_map as $prayer_key => $prayer_data) {
        // Skip sunrise if not showing
        if ($prayer_key === 'sunrise' && !$showSunrise) {
            continue;
        }
        
        $output .= '<tr' . ($prayer_key === 'sunrise' ? ' class="sunrise-row"' : '') . ' style="' . esc_attr($row_style) . '">';
        $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons[$prayer_key]) . '" alt="' . esc_attr($prayer_data['name']) . '" class="prayer-icon"> ' . esc_html($prayer_data['name']) . '</td>';
        
        if ($prayer_key === 'sunrise') {
            // Sunrise has a single time column that spans both athan and iqama
            $output .= '<td class="athan-time" colspan="2">' . (isset($formatted_times['sunrise']) ? esc_html($formatted_times['sunrise']) : '-') . '</td>';
            
            // Add empty changes cell for sunrise if we have changes
            if ($has_changes && $show_changes) {
                $changes_html = '';
                
                // Check if sunrise time is changing
                foreach ($future_changes as $day_data) {
                    if (isset($day_data['changes']['sunrise'])) {
                        $change = $day_data['changes']['sunrise'];
                        $changes_html = '<span class="time-change" style="' . esc_attr($change_style) . '">' 
                            . esc_html($change['new_time']) 
                            . '</span>';
                        break; // Only show the nearest change
                    }
                }
                
                $output .= '<td class="changes-column">' . $changes_html . '</td>';
            }
        } else {
            // Regular prayer with athan and iqama times
            $athan_col = $prayer_data['athan'];
            $iqama_col = $prayer_data['iqama'];
            
            $output .= '<td class="athan-time" style="' . esc_attr($highlight_style) . '">' . (isset($formatted_times[$athan_col]) ? esc_html($formatted_times[$athan_col]) : '-') . '</td>';
            $output .= '<td class="iqama-time">' . (isset($formatted_times[$iqama_col]) ? esc_html($formatted_times[$iqama_col]) : '-') . '</td>';
            
            // Add changes cell if we have changes
            if ($has_changes && $show_changes) {
                $changes_html = '';
                
                // Check for iqama time changes (prioritizing these as they're most relevant)
                foreach ($future_changes as $day_data) {
                    if (isset($day_data['changes'][$iqama_col])) {
                        $change = $day_data['changes'][$iqama_col];
                        $changes_html = '<span class="time-change" style="' . esc_attr($change_style) . '">' 
                            . esc_html($change['new_time']) 
                            . '</span>';
                        break; // Only show the nearest change
                    }
                }
                
                // If no iqama changes, check for athan changes
                if (empty($changes_html)) {
                    foreach ($future_changes as $day_data) {
                        if (isset($day_data['changes'][$athan_col])) {
                            $change = $day_data['changes'][$athan_col];
                            $changes_html = '<span class="time-change" style="' . esc_attr($change_style) . '">' 
                                . esc_html($change['new_time']) 
                                . '</span>';
                            break; // Only show the nearest change
                        }
                    }
                }
                
                $output .= '<td class="changes-column">' . $changes_html . '</td>';
            }
        }
        
        $output .= '</tr>';
    }
    
    // Add Jumuah times if available in settings
    $opts = get_option('prayertimes_settings', []);
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
        if ($timeFormat === '24hour') {
            $jumuah1 = prayertimes_date('H:i', $jumuah1_time);
        } else {
            $jumuah1 = prayertimes_date('g:i A', $jumuah1_time);
        }
    }
    if (!empty($jumuah2)) {
        $jumuah2_time = strtotime($jumuah2);
        if ($timeFormat === '24hour') {
            $jumuah2 = prayertimes_date('H:i', $jumuah2_time);
        } else {
            $jumuah2 = prayertimes_date('g:i A', $jumuah2_time);
        }
    }
    if (!empty($jumuah3)) {
        $jumuah3_time = strtotime($jumuah3);
        if ($timeFormat === '24hour') {
            $jumuah3 = prayertimes_date('H:i', $jumuah3_time);
        } else {
            $jumuah3 = prayertimes_date('g:i A', $jumuah3_time);
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
            $output .= '<span class="jumuah-label">' . esc_html($jumuah1_name) . '</span>';
            $output .= '<span class="jumuah-time-value">' . esc_html($jumuah1) . '</span>';
            $output .= '</td>';
        }
        
        if (!empty($jumuah2)) {
            $output .= '<td class="jumuah-time">';
            $output .= '<span class="jumuah-label">' . esc_html($jumuah2_name) . '</span>';
            $output .= '<span class="jumuah-time-value">' . esc_html($jumuah2) . '</span>';
            $output .= '</td>';
        }
        
        if (!empty($jumuah3)) {
            $output .= '<td class="jumuah-time">';
            $output .= '<span class="jumuah-label">' . esc_html($jumuah3_name) . '</span>';
            $output .= '<span class="jumuah-time-value">' . esc_html($jumuah3) . '</span>';
            $output .= '</td>';
        }
        
        $output .= '</tr>';
        $output .= '</table>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Helper function to convert hex color to RGB
 */
function prayertimes_hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    
    return array('r' => $r, 'g' => $g, 'b' => $b);
}
