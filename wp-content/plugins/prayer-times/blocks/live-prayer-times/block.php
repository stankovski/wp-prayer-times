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
            'timeFormat' => array(
                'type' => 'string', 
                'default' => '12hour', // 12hour or 24hour
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
    
    // Get today's date
    $today = date('Y-m-d');
    
    // Query the database for today's prayer times
    $prayer_times = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE day = %s",
        $today
    ), ARRAY_A);
    
    // If no times available for today, try finding the next available date
    if (!$prayer_times) {
        $future_time = $wpdb->get_row(
            "SELECT * FROM $table_name WHERE day >= CURDATE() ORDER BY day ASC LIMIT 1",
            ARRAY_A
        );
        
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
    
    // Get time format from global settings (override attribute if exists)
    $opts = get_option('prayertimes_settings', []);
    $timeFormat = isset($opts['time_format']) ? $opts['time_format'] : '12hour';
    
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
    if ($headerTextColor) {
        $header_style .= "color: {$headerTextColor};";
    }
    
    $highlight_style = '';
    if ($highlightColor) {
        $highlight_style .= "color: {$highlightColor};";
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
                $formatted_times[$column] = date('H:i', $time);
            } else {
                $formatted_times[$column] = date('g:i A', $time);
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
    
    // Add the current time clock
    $output .= '<div class="live-prayer-clock" style="' . esc_attr($clock_style) . '">';
    $output .= '<span class="live-time">00:00:00</span>';
    $output .= '</div>';
    
    // Format date for display
    $display_date = date('l, F j, Y', strtotime($today));
    
    // Get Hijri date if needed
    $hijri_date = '';
    $hijri_date_arabic = '';
    if ($showHijriDate) {
        $hijri_date = function_exists('prayertimes_convert_to_hijri') ? prayertimes_convert_to_hijri($today, true, 'en') : '';
        $hijri_date_arabic = function_exists('prayertimes_convert_to_hijri') ? prayertimes_convert_to_hijri($today, true, 'ar') : '';
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
    $output .= '<th style="' . esc_attr($header_style) . '"></th><th style="' . esc_attr($header_style) . '"><img src="' . esc_url($prayer_icons['athan']) . '" alt="Athan" class="header-icon">Athan</th><th style="' . esc_attr($header_style) . '"><img src="' . esc_url($prayer_icons['iqama']) . '" alt="Iqama" class="header-icon">Iqama</th>';
    $output .= '</tr></thead>';
    
    // Table body
    $output .= '<tbody>';
    
    // Fajr row
    $output .= '<tr>';
    $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['fajr']) . '" alt="Fajr" class="prayer-icon"> Fajr</td>';
    $output .= '<td class="athan-time" style="' . esc_attr($highlight_style) . '">' . (isset($formatted_times['fajr_athan']) ? esc_html($formatted_times['fajr_athan']) : '-') . '</td>';
    $output .= '<td class="iqama-time">' . (isset($formatted_times['fajr_iqama']) ? esc_html($formatted_times['fajr_iqama']) : '-') . '</td>';
    $output .= '</tr>';
    
    // Sunrise row (if enabled)
    if ($showSunrise) {
        $output .= '<tr class="sunrise-row">';
        $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['sunrise']) . '" alt="Sunrise" class="prayer-icon"> Sunrise</td>';
        $output .= '<td class="athan-time" colspan="2">' . (isset($formatted_times['sunrise']) ? esc_html($formatted_times['sunrise']) : '-') . '</td>';
        $output .= '</tr>';
    }
    
    // Dhuhr row
    $output .= '<tr>';
    $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['dhuhr']) . '" alt="Dhuhr" class="prayer-icon"> Dhuhr</td>';
    $output .= '<td class="athan-time" style="' . esc_attr($highlight_style) . '">' . (isset($formatted_times['dhuhr_athan']) ? esc_html($formatted_times['dhuhr_athan']) : '-') . '</td>';
    $output .= '<td class="iqama-time">' . (isset($formatted_times['dhuhr_iqama']) ? esc_html($formatted_times['dhuhr_iqama']) : '-') . '</td>';
    $output .= '</tr>';
    
    // Asr row
    $output .= '<tr>';
    $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['asr']) . '" alt="Asr" class="prayer-icon"> Asr</td>';
    $output .= '<td class="athan-time" style="' . esc_attr($highlight_style) . '">' . (isset($formatted_times['asr_athan']) ? esc_html($formatted_times['asr_athan']) : '-') . '</td>';
    $output .= '<td class="iqama-time">' . (isset($formatted_times['asr_iqama']) ? esc_html($formatted_times['asr_iqama']) : '-') . '</td>';
    $output .= '</tr>';
    
    // Maghrib row
    $output .= '<tr>';
    $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['maghrib']) . '" alt="Maghrib" class="prayer-icon"> Maghrib</td>';
    $output .= '<td class="athan-time" style="' . esc_attr($highlight_style) . '">' . (isset($formatted_times['maghrib_athan']) ? esc_html($formatted_times['maghrib_athan']) : '-') . '</td>';
    $output .= '<td class="iqama-time">' . (isset($formatted_times['maghrib_iqama']) ? esc_html($formatted_times['maghrib_iqama']) : '-') . '</td>';
    $output .= '</tr>';
    
    // Isha row
    $output .= '<tr>';
    $output .= '<td class="prayer-name"><img src="' . esc_url($prayer_icons['isha']) . '" alt="Isha" class="prayer-icon"> Isha</td>';
    $output .= '<td class="athan-time" style="' . esc_attr($highlight_style) . '">' . (isset($formatted_times['isha_athan']) ? esc_html($formatted_times['isha_athan']) : '-') . '</td>';
    $output .= '<td class="iqama-time">' . (isset($formatted_times['isha_iqama']) ? esc_html($formatted_times['isha_iqama']) : '-') . '</td>';
    $output .= '</tr>';
    
    $output .= '</tbody></table>';
    
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
            $jumuah1 = date('H:i', $jumuah1_time);
        } else {
            $jumuah1 = date('g:i A', $jumuah1_time);
        }
    }
    if (!empty($jumuah2)) {
        $jumuah2_time = strtotime($jumuah2);
        if ($timeFormat === '24hour') {
            $jumuah2 = date('H:i', $jumuah2_time);
        } else {
            $jumuah2 = date('g:i A', $jumuah2_time);
        }
    }
    if (!empty($jumuah3)) {
        $jumuah3_time = strtotime($jumuah3);
        if ($timeFormat === '24hour') {
            $jumuah3 = date('H:i', $jumuah3_time);
        } else {
            $jumuah3 = date('g:i A', $jumuah3_time);
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
