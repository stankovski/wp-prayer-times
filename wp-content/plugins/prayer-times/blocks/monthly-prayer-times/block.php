<?php
/**
 * Monthly Prayer Times Gutenberg Block
 */

if (!defined('ABSPATH')) exit;

/**
 * Register the block
 */
function prayertimes_register_monthly_prayer_times_block() {
    // Register block script
    wp_register_script(
        'prayertimes-monthly-prayer-times-block',
        plugins_url('block.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
    );

    // Register block styles
    wp_register_style(
        'prayertimes-monthly-prayer-times-style',
        plugins_url('style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'style.css')
    );

    // Register the block
    register_block_type('prayer-times/monthly-prayer-times', array(
        'editor_script' => 'prayertimes-monthly-prayer-times-block',
        'editor_style' => 'prayertimes-monthly-prayer-times-style',
        'style' => 'prayertimes-monthly-prayer-times-style',
        'render_callback' => 'prayertimes_render_monthly_prayer_times_block',
        'attributes' => array(
            'className' => array(
                'type' => 'string',
                'default' => '',
            ),
            'align' => array(
                'type' => 'string',
                'default' => 'center',
            ),
            'headerTextColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'headerColor' => array(
                'type' => 'string',
                'default' => '',
            ),
            'tableStyle' => array(
                'type' => 'string',
                'default' => 'default',
            ),
            'fontSize' => array(
                'type' => 'number',
                'default' => 16,
            ),
            'showSunrise' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showHijriDate' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'highlightFridays' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'reportType' => array(
                'type' => 'string',
                'default' => 'monthly',
            ),
        ),
    ));
}
add_action('init', 'prayertimes_register_monthly_prayer_times_block');

/**
 * Render the Monthly Prayer Times block on the frontend
 */
function prayertimes_render_monthly_prayer_times_block($attributes) {
    global $wpdb;
    $table_name = $wpdb->prefix . PRAYERTIMES_IQAMA_TABLE;
    
    // Enqueue the frontend script
    wp_enqueue_script('prayertimes-monthly-prayer-times-frontend');
    
    // Get block attributes
    $className = isset($attributes['className']) ? $attributes['className'] : '';
    $align = isset($attributes['align']) ? $attributes['align'] : 'center';
    $headerTextColor = isset($attributes['headerTextColor']) ? $attributes['headerTextColor'] : '';
    $headerColor = isset($attributes['headerColor']) ? $attributes['headerColor'] : '';
    $tableStyle = isset($attributes['tableStyle']) ? $attributes['tableStyle'] : 'default';
    $fontSize = isset($attributes['fontSize']) ? $attributes['fontSize'] : 16;
    $showSunrise = isset($attributes['showSunrise']) ? $attributes['showSunrise'] : true;
    $showIqama = isset($attributes['showIqama']) ? $attributes['showIqama'] : true;
    $highlightToday = isset($attributes['highlightToday']) ? $attributes['highlightToday'] : true;
    $reportType = isset($attributes['reportType']) ? $attributes['reportType'] : 'monthly';
    
    // Generate a unique ID for this instance
    $block_id = 'prayertimes-monthly-' . uniqid();
    
    // Create inline styles
    $container_style = "text-align: {$align};";
    if ($fontSize) {
        $container_style .= "font-size: {$fontSize}px;";
    }
    
    $table_style = '';
    
    $header_style = '';
    if ($headerColor) {
        $header_style .= "background-color: {$headerColor};";
    }
    if ($headerTextColor) {
        $header_style .= "color: {$headerTextColor};";
    }
    
    // Get dates based on report type
    $current_date = new DateTime();
    $start_date = null;
    $end_date = null;
    
    switch ($reportType) {
        case 'weekly':
            // Get current week's start (Monday) and end (Sunday)
            $current_day_of_week = $current_date->format('N'); // 1 (Mon) through 7 (Sun)
            $days_to_monday = $current_day_of_week - 1;
            
            $start_date = clone $current_date;
            $start_date->modify("-{$days_to_monday} days"); // Go to Monday
            
            $end_date = clone $start_date;
            $end_date->modify('+6 days'); // Go to Sunday
            
            $header_text = 'Weekly Prayer Times (' . $start_date->format('M j') . ' - ' . $end_date->format('M j, Y') . ')';
            break;
            
        case 'next5days':
            // Get next 5 days including today
            $start_date = clone $current_date;
            $end_date = clone $current_date;
            $end_date->modify('+4 days'); // 5 days total including today
            
            $header_text = 'Next 5 Days Prayer Times (' . $start_date->format('M j') . ' - ' . $end_date->format('M j, Y') . ')';
            break;
            
        case 'monthly':
        default:
            // Get current month's start and end dates
            $start_date = new DateTime($current_date->format('Y-m-01'));
            $end_date = new DateTime($current_date->format('Y-m-t'));
            
            $header_text = $current_date->format('F Y');
            break;
    }
    
    // Get prayer times for the specified date range
    $prayer_times = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name 
         WHERE day BETWEEN %s AND %s 
         ORDER BY day ASC",
        $start_date->format('Y-m-d'),
        $end_date->format('Y-m-d')
    ), ARRAY_A);
    
    // If no times available, return a message
    if (empty($prayer_times)) {
        return '<div class="wp-block-prayer-times-monthly-prayer-times">
            <p>No prayer times available for the selected date range.</p>
        </div>';
    }
    
    // Start building the HTML output
    $output = '<div id="' . esc_attr($block_id) . '" class="wp-block-prayer-times-monthly-prayer-times ' . esc_attr($className) . '" 
                   style="' . esc_attr($container_style) . '"
                   data-show-sunrise="' . esc_attr($showSunrise ? '1' : '0') . '" 
                   data-show-iqama="' . esc_attr($showIqama ? '1' : '0') . '"
                   data-highlight-today="' . esc_attr($highlightToday ? '1' : '0') . '"
                   data-table-style="' . esc_attr($tableStyle) . '"
                   data-month="' . esc_attr($current_date->format('n')) . '"
                   data-year="' . esc_attr($current_date->format('Y')) . '"
                   data-report-type="' . esc_attr($reportType) . '">';
    
    // Header with navigation controls (only for monthly view)
    $output .= '<div class="prayer-times-month-header">';
    
    if ($reportType === 'monthly') {
        $output .= '<button class="prev-page">&laquo; Previous Month</button>';
        $output .= '<h3 class="month-name">' . esc_html($header_text) . '</h3>';
        $output .= '<button class="next-page">Next Month &raquo;</button>';
    } else {
        // For weekly/next5days, show header but disable navigation
        $output .= '<button class="prev-page" disabled style="visibility:hidden;">&laquo; Previous</button>';
        $output .= '<h3 class="month-name">' . esc_html($header_text) . '</h3>';
        $output .= '<button class="next-page" disabled style="visibility:hidden;">Next &raquo;</button>';
    }
    
    $output .= '</div>';
    
    // Table container
    $output .= '<div class="prayer-times-table-container">';
    $output .= prayertimes_generate_monthly_prayer_times_table($prayer_times, $showSunrise, $showIqama, $highlightToday, $tableStyle, $header_style, $table_style);
    $output .= '</div>';
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Generate the HTML table for monthly prayer times
 */
function prayertimes_generate_monthly_prayer_times_table($prayer_times, $showSunrise, $showIqama, $highlightToday, $tableStyle, $header_style, $table_style) {
    $today = date('Y-m-d');
    
    $output = '<table class="prayer-times-table ' . esc_attr('table-style-' . $tableStyle) . '" style="' . esc_attr($table_style) . '">';
    $output .= '<thead><tr style="' . esc_attr($header_style) . '">';
    $output .= '<th>Date</th>';
    $output .= '<th>Fajr</th>';
    if (!$showIqama) {
        $output .= '<th>Fajr Iqama</th>';
    }
    if ($showSunrise) {
        $output .= '<th>Sunrise</th>';
    }
    $output .= '<th>Dhuhr</th>';
    if (!$showIqama) {
        $output .= '<th>Dhuhr Iqama</th>';
    }
    $output .= '<th>Asr</th>';
    if (!$showIqama) {
        $output .= '<th>Asr Iqama</th>';
    }
    $output .= '<th>Maghrib</th>';
    if (!$showIqama) {
        $output .= '<th>Maghrib Iqama</th>';
    }
    $output .= '<th>Isha</th>';
    if (!$showIqama) {
        $output .= '<th>Isha Iqama</th>';
    }
    $output .= '</tr></thead>';
    
    $output .= '<tbody>';
    
    foreach ($prayer_times as $day) {
        $date_obj = new DateTime($day['day']);
        $is_friday = $date_obj->format('N') == 5; // 5 = Friday in ISO-8601
        $is_today = $day['day'] === $today;
        
        // Set appropriate classes for the row
        $row_classes = [];
        if ($highlightToday && $is_today) {
            $row_classes[] = 'today';
        }
        if ($is_friday) {
            $row_classes[] = 'friday';
        }
        
        $row_class = !empty($row_classes) ? implode(' ', $row_classes) : '';
        
        $output .= '<tr class="' . esc_attr($row_class) . '">';
        
        // Date column
        $output .= '<td class="date-column">';
        $output .= '<span class="day-name">' . esc_html($date_obj->format('D')) . '</span>';
        $output .= '<span class="day-number">' . esc_html($date_obj->format('j')) . '</span>';
        $output .= '</td>';
        
        // Fajr column
        $output .= '<td class="prayer-column">';
        if ($showIqama && !empty($day['fajr_iqama'])) {
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['fajr_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(prayertimes_format_prayer_time($day['fajr_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(prayertimes_format_prayer_time($day['fajr_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Fajr Iqama column (separate)
        if (!$showIqama && !empty($day['fajr_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['fajr_iqama'])) . '</span>';
            $output .= '</td>';
        }
        
        // Sunrise column (optional)
        if ($showSunrise) {
            $output .= '<td class="prayer-column sunrise-column">';
            if (!empty($day['sunrise'])) {
                $output .= '<span class="athan-time">' . esc_html(prayertimes_format_prayer_time($day['sunrise'])) . '</span>';
            } else {
                $output .= '-';
            }
            $output .= '</td>';
        }
        
        // Dhuhr column
        $output .= '<td class="prayer-column">';
        if ($showIqama && !empty($day['dhuhr_iqama'])) {
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['dhuhr_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(prayertimes_format_prayer_time($day['dhuhr_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(prayertimes_format_prayer_time($day['dhuhr_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Dhuhr Iqama column (separate)
        if (!$showIqama && !empty($day['dhuhr_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['dhuhr_iqama'])) . '</span>';
            $output .= '</td>';
        }
        
        // Asr column
        $output .= '<td class="prayer-column">';
        if ($showIqama && !empty($day['asr_iqama'])) {
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['asr_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(prayertimes_format_prayer_time($day['asr_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(prayertimes_format_prayer_time($day['asr_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Asr Iqama column (separate)
        if (!$showIqama && !empty($day['asr_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['asr_iqama'])) . '</span>';
            $output .= '</td>';
        }
        
        // Maghrib column
        $output .= '<td class="prayer-column">';
        if ($showIqama && !empty($day['maghrib_iqama'])) {
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['maghrib_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(prayertimes_format_prayer_time($day['maghrib_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(prayertimes_format_prayer_time($day['maghrib_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Maghrib Iqama column (separate)
        if (!$showIqama && !empty($day['maghrib_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['maghrib_iqama'])) . '</span>';
            $output .= '</td>';
        }
        
        // Isha column
        $output .= '<td class="prayer-column">';
        if ($showIqama && !empty($day['isha_iqama'])) {
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['isha_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(prayertimes_format_prayer_time($day['isha_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(prayertimes_format_prayer_time($day['isha_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Isha Iqama column (separate)
        if (!$showIqama && !empty($day['isha_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(prayertimes_format_prayer_time($day['isha_iqama'])) . '</span>';
            $output .= '</td>';
        }
        
        $output .= '</tr>';
    }
    
    $output .= '</tbody></table>';
    
    return $output;
}

/**
 * AJAX handler for pagination
 */
function prayertimes_monthly_prayer_times_pagination() {
    check_ajax_referer('prayertimes_monthly_prayer_times_nonce', 'nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . PRAYERTIMES_IQAMA_TABLE;
    
    // Get parameters from the request
    $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $show_sunrise = isset($_POST['show_sunrise']) && $_POST['show_sunrise'] === '1';
    $show_iqama = isset($_POST['show_iqama']) && $_POST['show_iqama'] === '1';
    $highlight_today = isset($_POST['highlight_today']) && $_POST['highlight_today'] === '1';
    $table_style = isset($_POST['table_style']) ? sanitize_text_field($_POST['table_style']) : 'default';
    
    // Validate month and year
    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        wp_send_json_error('Invalid month or year');
        return;
    }
    
    // Get start and end dates for the month
    $start_of_month = new DateTime("$year-$month-01");
    $end_of_month = new DateTime($start_of_month->format('Y-m-t'));
    
    // Get prayer times for the entire month
    $prayer_times = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name 
         WHERE day BETWEEN %s AND %s 
         ORDER BY day ASC",
        $start_of_month->format('Y-m-d'),
        $end_of_month->format('Y-m-d')
    ), ARRAY_A);
    
    // If no times available, return error
    if (empty($prayer_times)) {
        wp_send_json_error('No prayer times available for the selected month');
        return;
    }
    
    // Get month name
    $month_name = $start_of_month->format('F');
    
    // Generate table HTML
    $table_html = prayertimes_generate_monthly_prayer_times_table(
        $prayer_times, 
        $show_sunrise, 
        $show_iqama, 
        $highlight_today, 
        $table_style, 
        '', // No header style in AJAX response
        '' // No table style in AJAX response
    );
    
    wp_send_json_success([
        'table_html' => $table_html,
        'month_name' => $month_name
    ]);
}
add_action('wp_ajax_prayertimes_monthly_prayer_times_pagination', 'prayertimes_monthly_prayer_times_pagination');
add_action('wp_ajax_nopriv_prayertimes_monthly_prayer_times_pagination', 'prayertimes_monthly_prayer_times_pagination');

/**
 * AJAX handler to check if a month has prayer times
 */
function prayertimes_check_month_availability() {
    check_ajax_referer('prayertimes_monthly_prayer_times_nonce', 'nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . PRAYERTIMES_IQAMA_TABLE;
    
    // Get parameters from the request
    $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    
    // Validate month and year
    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        wp_send_json_error('Invalid month or year');
        return;
    }
    
    // Get start and end dates for the month
    $start_of_month = new DateTime("$year-$month-01");
    $end_of_month = new DateTime($start_of_month->format('Y-m-t'));
    
    // Check if there are any prayer times for the month
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE day BETWEEN %s AND %s",
        $start_of_month->format('Y-m-d'),
        $end_of_month->format('Y-m-d')
    ));
    
    wp_send_json_success([
        'has_records' => ($count > 0),
        'month' => $month,
        'year' => $year
    ]);
}
add_action('wp_ajax_prayertimes_check_month_availability', 'prayertimes_check_month_availability');
add_action('wp_ajax_nopriv_prayertimes_check_month_availability', 'prayertimes_check_month_availability');

/**
 * Helper function to format time based on global time format setting
 */
function prayertimes_format_prayer_time($time_string) {
    // Get time format from settings
    $opts = get_option('prayertimes_settings', []);
    $time_format = isset($opts['time_format']) ? $opts['time_format'] : '12hour';
    
    // Parse time string to DateTime object
    $time = strtotime($time_string);
    
    // Format according to setting
    if($time_format === '24hour') {
        return date('H:i', $time);
    } else {
        return date('g:i A', $time);
    }
}
