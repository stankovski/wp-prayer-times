<?php
/**
 * Monthly Prayer Times Gutenberg Block
 */

if (!defined('ABSPATH')) exit;

/**
 * Register the block
 */
function muslprti_register_monthly_prayer_times_block() {
    // Register block script
    wp_register_script(
        'muslprti-monthly-prayer-times-block',
        plugins_url('block.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'block.js'),
        false
    );

    // Register block styles
    wp_register_style(
        'muslprti-monthly-prayer-times-style',
        plugins_url('style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'style.css')
    );
    
    // Register dynamic styles handle that will receive inline CSS
    wp_register_style(
        'muslprti-monthly-prayer-times-dynamic-style',
        false, // No actual CSS file
        array(),
        '1.0.0' // Version parameter to avoid caching issues
    );
    wp_enqueue_style('muslprti-monthly-prayer-times-dynamic-style');

    // Register the block
    register_block_type('prayer-times/monthly-prayer-times', array(
        'editor_script' => 'muslprti-monthly-prayer-times-block',
        'editor_style' => 'muslprti-monthly-prayer-times-style',
        'style' => 'muslprti-monthly-prayer-times-style',
        'render_callback' => 'muslprti_render_monthly_prayer_times_block',
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
            'showPagination' => array(
                'type' => 'boolean',
                'default' => true,
            ),
        ),
    ));
}
add_action('init', 'muslprti_register_monthly_prayer_times_block');

/**
 * Render the Monthly Prayer Times block on the frontend
 */
function muslprti_render_monthly_prayer_times_block($attributes) {
    global $wpdb;
    $table_name = $wpdb->prefix . MUSLPRTI_IQAMA_TABLE;
    
    // Enqueue the frontend script
    wp_enqueue_script('muslprti-monthly-prayer-times-frontend');
    
    // Get block attributes with sanitization
    $className = isset($attributes['className']) ? sanitize_html_class($attributes['className']) : '';
    $align = isset($attributes['align']) ? sanitize_text_field($attributes['align']) : 'center';
    $headerTextColor = isset($attributes['headerTextColor']) ? sanitize_hex_color($attributes['headerTextColor']) : '';
    $headerColor = isset($attributes['headerColor']) ? sanitize_hex_color($attributes['headerColor']) : '';
    $tableStyle = isset($attributes['tableStyle']) ? sanitize_text_field($attributes['tableStyle']) : 'default';
    $fontSize = isset($attributes['fontSize']) ? absint($attributes['fontSize']) : 16;
    $showSunrise = isset($attributes['showSunrise']) ? (bool)$attributes['showSunrise'] : true;
    $showIqama = isset($attributes['showIqama']) ? (bool)$attributes['showIqama'] : true;
    $highlightToday = isset($attributes['highlightToday']) ? (bool)$attributes['highlightToday'] : true;
    $reportType = isset($attributes['reportType']) ? sanitize_text_field($attributes['reportType']) : 'monthly';
    $showPagination = isset($attributes['showPagination']) ? (bool)$attributes['showPagination'] : true;
    
    // Get timezone from settings
    $timezone = muslprti_get_timezone();
    
    // Create DateTime object with timezone
    $datetime_zone = new DateTimeZone($timezone);
    $current_date = new DateTime('now', $datetime_zone);
    
    // Generate a unique ID for this instance
    $block_id = 'muslprti-monthly-' . uniqid();
    
    // Create inline styles
    $container_style = "text-align: " . esc_attr($align) . ";";
    if ($fontSize) {
        $container_style .= "font-size: " . esc_attr($fontSize) . "px;";
    }
    
    $table_style = '';
    
    $header_style = '';
    if ($headerColor) {
        $header_style .= "background-color: " . esc_attr($headerColor) . ";";
    }
    if ($headerTextColor) {
        $header_style .= "color: " . esc_attr($headerTextColor) . ";";
    }
    
    // Get dates based on report type
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
            $start_date = new DateTime($current_date->format('Y-m-01'), $datetime_zone);
            $end_date = new DateTime($current_date->format('Y-m-t'), $datetime_zone);
            
            $header_text = $current_date->format('F Y');
            break;
    }
    
    // Get prayer times for the specified date range using prepared query with caching
    $cache_key = 'muslprti_monthly_prayer_times_' . $start_date->format('Y-m-d') . '_' . $end_date->format('Y-m-d');
    $prayer_times = wp_cache_get($cache_key, 'muslim_prayer_times');
    
    if (false === $prayer_times) {
        $prayer_times = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE day BETWEEN %s AND %s 
             ORDER BY day ASC",
            $start_date->format('Y-m-d'),
            $end_date->format('Y-m-d')
        ), ARRAY_A);
        
        // Cache the result for 1 hour (3600 seconds)
        wp_cache_set($cache_key, $prayer_times, 'muslim_prayer_times', 3600);
    }
    
    // If no times available, return a message
    if (empty($prayer_times)) {
        return '<div class="wp-block-prayer-times-monthly-prayer-times">
            <p>' . esc_html__('No prayer times available for the selected date range.', 'muslim-prayer-times') . '</p>
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
                   data-report-type="' . esc_attr($reportType) . '"
                   data-show-pagination="' . esc_attr($showPagination ? '1' : '0') . '">';
    
    // Header with navigation controls (only for monthly view with pagination enabled)
    $output .= '<div class="prayer-times-month-header">';
    
    if ($reportType === 'monthly' && $showPagination) {
        $output .= '<button class="prev-page">' . esc_html__('« Previous Month', 'muslim-prayer-times') . '</button>';
        $output .= '<h3 class="month-name">' . esc_html($header_text) . '</h3>';
        $output .= '<button class="next-page">' . esc_html__('Next Month »', 'muslim-prayer-times') . '</button>';
    } else {
        // For weekly/next5days or monthly without pagination, show header but disable navigation
        $output .= '<button class="prev-page" disabled style="visibility:hidden;">' . esc_html__('« Previous', 'muslim-prayer-times') . '</button>';
        $output .= '<h3 class="month-name">' . esc_html($header_text) . '</h3>';
        $output .= '<button class="next-page" disabled style="visibility:hidden;">' . esc_html__('Next »', 'muslim-prayer-times') . '</button>';
    }
    
    $output .= '</div>';
    
    // Table container
    $output .= '<div class="prayer-times-table-container">';
    $output .= muslprti_generate_monthly_prayer_times_table($prayer_times, $showSunrise, $showIqama, $highlightToday, $tableStyle, $header_style, $table_style);
    $output .= '</div>';
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Generate the HTML table for monthly prayer times
 */
function muslprti_generate_monthly_prayer_times_table($prayer_times, $showSunrise, $showIqama, $highlightToday, $tableStyle, $header_style, $table_style) {
    // Get timezone from settings
    $timezone = muslprti_get_timezone();
    
    // Create DateTime object with timezone
    $datetime_zone = new DateTimeZone($timezone);
    $today = (new DateTime('now', $datetime_zone))->format('Y-m-d');
    
    $output = '<table class="prayer-times-table ' . esc_attr('table-style-' . $tableStyle) . '" style="' . esc_attr($table_style) . '">';
    $output .= '<thead><tr style="' . esc_attr($header_style) . '">';
    $output .= '<th>' . esc_html__('Date', 'muslim-prayer-times') . '</th>';
    $output .= '<th>' . esc_html__('Fajr', 'muslim-prayer-times') . '</th>';
    if (!$showIqama) {
        $output .= '<th>' . esc_html__('Fajr Iqama', 'muslim-prayer-times') . '</th>';
    }
    if ($showSunrise) {
        $output .= '<th>' . esc_html__('Sunrise', 'muslim-prayer-times') . '</th>';
    }
    $output .= '<th>' . esc_html__('Dhuhr', 'muslim-prayer-times') . '</th>';
    if (!$showIqama) {
        $output .= '<th>' . esc_html__('Dhuhr Iqama', 'muslim-prayer-times') . '</th>';
    }
    $output .= '<th>' . esc_html__('Asr', 'muslim-prayer-times') . '</th>';
    if (!$showIqama) {
        $output .= '<th>' . esc_html__('Asr Iqama', 'muslim-prayer-times') . '</th>';
    }
    $output .= '<th>' . esc_html__('Maghrib', 'muslim-prayer-times') . '</th>';
    if (!$showIqama) {
        $output .= '<th>' . esc_html__('Maghrib Iqama', 'muslim-prayer-times') . '</th>';
    }
    $output .= '<th>' . esc_html__('Isha', 'muslim-prayer-times') . '</th>';
    if (!$showIqama) {
        $output .= '<th>' . esc_html__('Isha Iqama', 'muslim-prayer-times') . '</th>';
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
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['fajr_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(muslprti_format_prayer_time($day['fajr_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(muslprti_format_prayer_time($day['fajr_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Fajr Iqama column (separate)
        if (!$showIqama && !empty($day['fajr_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['fajr_iqama'])) . '</span>';
            $output .= '</td>';
        }
        
        // Sunrise column (optional)
        if ($showSunrise) {
            $output .= '<td class="prayer-column sunrise-column">';
            if (!empty($day['sunrise'])) {
                $output .= '<span class="athan-time">' . esc_html(muslprti_format_prayer_time($day['sunrise'])) . '</span>';
            } else {
                $output .= '-';
            }
            $output .= '</td>';
        }
        
        // Dhuhr column
        $output .= '<td class="prayer-column">';
        if ($showIqama && !empty($day['dhuhr_iqama'])) {
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['dhuhr_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(muslprti_format_prayer_time($day['dhuhr_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(muslprti_format_prayer_time($day['dhuhr_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Dhuhr Iqama column (separate)
        if (!$showIqama && !empty($day['dhuhr_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['dhuhr_iqama'])) . '</span>';
            $output .= '</td>';
        }
        
        // Asr column
        $output .= '<td class="prayer-column">';
        if ($showIqama && !empty($day['asr_iqama'])) {
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['asr_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(muslprti_format_prayer_time($day['asr_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(muslprti_format_prayer_time($day['asr_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Asr Iqama column (separate)
        if (!$showIqama && !empty($day['asr_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['asr_iqama'])) . '</span>';
            $output .= '</td>';
        }
        
        // Maghrib column
        $output .= '<td class="prayer-column">';
        if ($showIqama && !empty($day['maghrib_iqama'])) {
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['maghrib_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(muslprti_format_prayer_time($day['maghrib_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(muslprti_format_prayer_time($day['maghrib_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Maghrib Iqama column (separate)
        if (!$showIqama && !empty($day['maghrib_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['maghrib_iqama'])) . '</span>';
            $output .= '</td>';
        }
        
        // Isha column
        $output .= '<td class="prayer-column">';
        if ($showIqama && !empty($day['isha_iqama'])) {
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['isha_iqama'])) . '</span>';
            $output .= '<span class="athan-time">Athan: ' . esc_html(muslprti_format_prayer_time($day['isha_athan'])) . '</span>';
        } else {
            $output .= '<span class="athan-time">' . esc_html(muslprti_format_prayer_time($day['isha_athan'])) . '</span>';
        }
        $output .= '</td>';
        
        // Isha Iqama column (separate)
        if (!$showIqama && !empty($day['isha_iqama'])) {
            $output .= '<td class="prayer-column iqama-column">';
            $output .= '<span class="iqama-time">' . esc_html(muslprti_format_prayer_time($day['isha_iqama'])) . '</span>';
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
function muslprti_monthly_prayer_times_pagination() {
    check_ajax_referer('muslprti_monthly_prayer_times_nonce', 'nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . MUSLPRTI_IQAMA_TABLE;
    
    // Get parameters from the request with sanitization
    $month = isset($_POST['month']) ? intval(wp_unslash($_POST['month'])) : intval(muslprti_date('n'));
    $year = isset($_POST['year']) ? intval(wp_unslash($_POST['year'])) : intval(muslprti_date('Y'));
    $show_sunrise = isset($_POST['show_sunrise']) && sanitize_text_field(wp_unslash($_POST['show_sunrise'])) === '1';
    $show_iqama = isset($_POST['show_iqama']) && sanitize_text_field(wp_unslash($_POST['show_iqama'])) === '1';
    $highlight_today = isset($_POST['highlight_today']) && sanitize_text_field(wp_unslash($_POST['highlight_today'])) === '1';
    $table_style = isset($_POST['table_style']) ? sanitize_text_field(wp_unslash($_POST['table_style'])) : 'default';
    
    // Validate month and year
    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        wp_send_json_error(esc_html__('Invalid month or year', 'muslim-prayer-times'));
        return;
    }
    
    // Get start and end dates for the month
    $start_of_month = new DateTime("$year-$month-01");
    $end_of_month = new DateTime($start_of_month->format('Y-m-t'));
    
    // Get prayer times for the entire month using prepared query with caching
    $cache_key = 'muslprti_monthly_prayer_times_' . $start_of_month->format('Y-m-d') . '_' . $end_of_month->format('Y-m-d');
    $prayer_times = wp_cache_get($cache_key, 'muslim_prayer_times');
    
    if (false === $prayer_times) {
        $prayer_times = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE day BETWEEN %s AND %s 
             ORDER BY day ASC",
            $start_of_month->format('Y-m-d'),
            $end_of_month->format('Y-m-d')
        ), ARRAY_A);
        
        // Cache the result for 1 hour (3600 seconds)
        wp_cache_set($cache_key, $prayer_times, 'muslim_prayer_times', 3600);
    }
    
    // If no times available, return error
    if (empty($prayer_times)) {
        wp_send_json_error(esc_html__('No prayer times available for the selected month', 'muslim-prayer-times'));
        return;
    }
    
    // Get month name
    $month_name = $start_of_month->format('F');
    
    // Generate table HTML
    $table_html = muslprti_generate_monthly_prayer_times_table(
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
        'month_name' => esc_html($month_name)
    ]);
}
add_action('wp_ajax_muslprti_monthly_prayer_times_pagination', 'muslprti_monthly_prayer_times_pagination');
add_action('wp_ajax_nopriv_muslprti_monthly_prayer_times_pagination', 'muslprti_monthly_prayer_times_pagination');

/**
 * AJAX handler to check if a month has prayer times
 */
function muslprti_check_month_availability() {
    check_ajax_referer('muslprti_monthly_prayer_times_nonce', 'nonce');
    
    global $wpdb;
    $table_name = $wpdb->prefix . MUSLPRTI_IQAMA_TABLE;
    
    // Get parameters from the request with sanitization
    $month = isset($_POST['month']) ? intval(wp_unslash($_POST['month'])) : intval(muslprti_date('n'));
    $year = isset($_POST['year']) ? intval(wp_unslash($_POST['year'])) : intval(muslprti_date('Y'));
    
    // Validate month and year
    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        wp_send_json_error(esc_html__('Invalid month or year', 'muslim-prayer-times'));
        return;
    }
    
    // Get start and end dates for the month
    $start_of_month = new DateTime("$year-$month-01");
    $end_of_month = new DateTime($start_of_month->format('Y-m-t'));
    
    // Check if there are any prayer times for the month using prepared query with caching
    $cache_key = 'muslprti_month_count_' . $start_of_month->format('Y-m-d') . '_' . $end_of_month->format('Y-m-d');
    $count = wp_cache_get($cache_key, 'muslim_prayer_times');
    
    if (false === $count) {
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE day BETWEEN %s AND %s",
            $start_of_month->format('Y-m-d'),
            $end_of_month->format('Y-m-d')
        ));
        
        // Cache the result for 1 hour (3600 seconds)
        wp_cache_set($cache_key, $count, 'muslim_prayer_times', 3600);
    }
    
    wp_send_json_success([
        'has_records' => ($count > 0),
        'month' => $month,
        'year' => $year
    ]);
}
add_action('wp_ajax_muslprti_check_month_availability', 'muslprti_check_month_availability');
add_action('wp_ajax_nopriv_muslprti_check_month_availability', 'muslprti_check_month_availability');

/**
 * Helper function to format time based on global time format setting
 */
function muslprti_format_prayer_time($time_string) {
    // Get time format from settings
    $opts = get_option('muslprti_settings', []);
    $time_format = isset($opts['time_format']) ? $opts['time_format'] : '12hour';
    
    // Parse time string to DateTime object
    $time = strtotime($time_string);
    
    // Format according to setting
    if($time_format === '24hour') {
        return muslprti_date('H:i', $time);
    } else {
        return muslprti_date('g:i A', $time);
    }
}
