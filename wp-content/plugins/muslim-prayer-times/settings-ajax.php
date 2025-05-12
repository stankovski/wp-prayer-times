<?php

if (!defined('ABSPATH')) exit;

use IslamicNetwork\PrayerTimes\PrayerTimes;

// Include helper functions
require_once __DIR__ . '/includes/helpers.php';

// AJAX handler for geocoding
function prayertimes_handle_geocode() {
    check_ajax_referer('prayertimes_geocode_nonce', 'nonce');
    
    $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
    
    if (empty($address)) {
        wp_send_json_error('Address is required');
    }
    
    // Use Nominatim OpenStreetMap API for geocoding (free but rate limited)
    $url = add_query_arg(
        array(
            'q' => urlencode($address),
            'format' => 'json',
        ),
        'https://nominatim.openstreetmap.org/search'
    );
    
    $response = wp_remote_get($url, array(
        'timeout' => 15,
        'headers' => array(
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
        )
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);
    
    if (empty($data)) {
        wp_send_json_error('No results found for this address.');
    }
    
    // Get the first result
    $result = array(
        'lat' => $data[0]->lat,
        'lon' => $data[0]->lon,
        'display_name' => $data[0]->display_name
    );
    
    wp_send_json_success($result);
}
add_action('wp_ajax_prayertimes_geocode', 'prayertimes_handle_geocode');

// AJAX handler for prayer times generation - refactored version
function prayertimes_handle_generate() {
    check_ajax_referer('prayertimes_generate_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to generate prayer times');
        return;
    }
    
    try {
        // Include the autoloader for Islamic Network libraries
        require_once __DIR__ . '/includes/islamic-network/autoload.php';
        
        if (!class_exists('IslamicNetwork\PrayerTimes\PrayerTimes')) {
            wp_send_json_error('Muslim Prayer Times library not available');
            return;
        }
        
        // Get saved settings
        $opts = get_option('prayertimes_settings', []);
        $latitude = isset($opts['lat']) ? $opts['lat'] : 47.7623;
        $longitude = isset($opts['lng']) ? $opts['lng'] : -122.2054;
        $timezone = isset($opts['tz']) ? $opts['tz'] : 'America/Los_Angeles';
        $method = isset($opts['method']) ? $opts['method'] : 'ISNA';
        $asr_calc = isset($opts['asr_calc']) ? $opts['asr_calc'] : 'STANDARD';
        
        // Create DateTime objects for the current date and timezone
        $dtz = new DateTimeZone($timezone);
        $now = new DateTime('now', $dtz);
        
        // Handle period or custom date range
        if (isset($_POST['period']) && $_POST['period'] === 'custom') {
            // Custom date range specified
            if (!isset($_POST['start_date']) || !isset($_POST['end_date'])) {
                wp_send_json_error('Custom date range requires both start and end dates');
                return;
            }
            
            try {
                // Parse start date
                $start_date = new DateTime($_POST['start_date'], $dtz);
                
                // Parse end date
                $end_date = new DateTime($_POST['end_date'], $dtz);
                
                // Ensure start date is not after end date
                if ($start_date > $end_date) {
                    wp_send_json_error('Start date cannot be after end date');
                    return;
                }
                
                // Calculate days difference
                $days_diff = $start_date->diff($end_date)->days + 1; // +1 to include both start and end dates
                
                // Limit to 730 days (2 years) to prevent server overload
                if ($days_diff > 730) {
                    wp_send_json_error('Custom date range cannot exceed 2 years (730 days)');
                    return;
                }
                
                $days_to_generate = $days_diff;
                
                // Debug log
                error_log('Muslim Prayer Times Generate: Custom date range from ' . $start_date->format('Y-m-d') . ' to ' . $end_date->format('Y-m-d') . ' (' . $days_to_generate . ' days)');
                
            } catch (Exception $e) {
                wp_send_json_error('Invalid date format: ' . $e->getMessage());
                return;
            }
        } else {
            // Standard period option
            $days_to_generate = isset($_POST['period']) ? intval($_POST['period']) : 30;
            
            // Apply a reasonable limit to prevent server overload
            $days_to_generate = min(max($days_to_generate, 7), 365);
            
            // Use current date as start date
            $start_date = clone $now;
            
            // Debug log
            error_log('Muslim Prayer Times Generate: Days to generate = ' . $days_to_generate);
        }
        
        // Debug log - add to error_log
        error_log('Muslim Prayer Times Generate: POST data = ' . print_r($_POST, true));
        
        // Initialize the PrayerTimes object
        $pt = new PrayerTimes($method, $asr_calc);
        
        // Prepare CSV data
        $csv_data = [];
        $csv_data[] = ['day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama'];
        
        // Get Iqama configuration
        $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
        $is_weekly = ($iqama_frequency === 'weekly');
        
        // Rules for each prayer
        $fajr_rule = isset($opts['fajr_rule']) ? $opts['fajr_rule'] : 'after_athan';
        $fajr_minutes_after = isset($opts['fajr_minutes_after']) ? $opts['fajr_minutes_after'] : 20;
        $fajr_minutes_before_shuruq = isset($opts['fajr_minutes_before_shuruq']) ? $opts['fajr_minutes_before_shuruq'] : 45;
        $fajr_daily_change = isset($opts['fajr_daily_change']) ? $opts['fajr_daily_change'] : 0;
        $fajr_rounding = isset($opts['fajr_rounding']) ? $opts['fajr_rounding'] : 1;
        
        $dhuhr_rule = isset($opts['dhuhr_rule']) ? $opts['dhuhr_rule'] : 'after_athan';
        $dhuhr_minutes_after = isset($opts['dhuhr_minutes_after']) ? $opts['dhuhr_minutes_after'] : 15;
        $dhuhr_fixed_standard = isset($opts['dhuhr_fixed_standard']) ? $opts['dhuhr_fixed_standard'] : '13:30';
        $dhuhr_fixed_dst = isset($opts['dhuhr_fixed_dst']) ? $opts['dhuhr_fixed_dst'] : '13:30';
        $dhuhr_daily_change = isset($opts['dhuhr_daily_change']) ? $opts['dhuhr_daily_change'] : 0;
        $dhuhr_rounding = isset($opts['dhuhr_rounding']) ? $opts['dhuhr_rounding'] : 1;
        
        $asr_rule = isset($opts['asr_rule']) ? $opts['asr_rule'] : 'after_athan';
        $asr_minutes_after = isset($opts['asr_minutes_after']) ? $opts['asr_minutes_after'] : 15;
        $asr_fixed_standard = isset($opts['asr_fixed_standard']) ? $opts['asr_fixed_standard'] : '16:30';
        $asr_fixed_dst = isset($opts['asr_fixed_dst']) ? $opts['asr_fixed_dst'] : '16:30';
        $asr_daily_change = isset($opts['asr_daily_change']) ? $opts['asr_daily_change'] : 0;
        $asr_rounding = isset($opts['asr_rounding']) ? $opts['asr_rounding'] : 1;
        
        $maghrib_minutes_after = isset($opts['maghrib_minutes_after']) ? $opts['maghrib_minutes_after'] : 5;
        $maghrib_daily_change = isset($opts['maghrib_daily_change']) ? $opts['maghrib_daily_change'] : 0;
        $maghrib_rounding = isset($opts['maghrib_rounding']) ? $opts['maghrib_rounding'] : 1;
        
        $isha_rule = isset($opts['isha_rule']) ? $opts['isha_rule'] : 'after_athan';
        $isha_minutes_after = isset($opts['isha_minutes_after']) ? $opts['isha_minutes_after'] : 15;
        $isha_min_time = isset($opts['isha_min_time']) ? $opts['isha_min_time'] : '19:30';
        $isha_max_time = isset($opts['isha_max_time']) ? $opts['isha_max_time'] : '22:00';
        $isha_daily_change = isset($opts['isha_daily_change']) ? $opts['isha_daily_change'] : 0;
        $isha_rounding = isset($opts['isha_rounding']) ? $opts['isha_rounding'] : 1;
        
        // Find the next Friday or use current date if it's Friday (or use start_date for custom range)
        if (isset($_POST['period']) && $_POST['period'] === 'custom') {
            // For custom date range, use the specified start date
            $current_date = clone $start_date;
        } else {
            // For standard periods, find the next Friday from now
            $current_date = clone $now;
            $day_of_week = $current_date->format('w');  // 0 (Sun) - 6 (Sat)
            
            if ($day_of_week != 5) { // 5 is Friday
                // Calculate days since the last Friday
                $days_since_friday = ($day_of_week + 2) % 7;
                $current_date->modify("-{$days_since_friday} days");
            }
        }
        
        // Process days in weekly batches
        $processed_days = 0;
        $current_week_start = null;
        $week_number = 0;
        
        while ($processed_days < $days_to_generate) {
            // Check if it's a new week (Friday)
            $is_friday = $current_date->format('w') == 5;
            
            if ($is_friday || $current_week_start === null) {
                // Start a new week
                $week_number++;
                $current_week_start = clone $current_date;
                
                // Initialize data structure for this week's days
                $week_days_data = [];
            }
            
            // Get prayer times for the current day
            $times = $pt->getTimes(
                $current_date,
                floatval($latitude),
                floatval($longitude),
                null,
                PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ANGLE,
                PrayerTimes::MIDNIGHT_MODE_STANDARD,
                PrayerTimes::TIME_FORMAT_24H
            );
            
            // Store this day's data
            $day_index = $processed_days;
            $week_days_data[$day_index] = [
                'date' => clone $current_date,
                'formatted_date' => $current_date->format('Y-m-d'),
                'athan' => [
                    'fajr' => new DateTime($times['Fajr'], $dtz),
                    'sunrise' => new DateTime($times['Sunrise'], $dtz),
                    'dhuhr' => new DateTime($times['Dhuhr'], $dtz),
                    'asr' => new DateTime($times['Asr'], $dtz),
                    'maghrib' => new DateTime($times['Maghrib'], $dtz),
                    'isha' => new DateTime($times['Isha'], $dtz)
                ]
            ];
            
            // End of a week or last day of generation?
            $is_thursday = $current_date->format('w') == 4; // 4 is Thursday
            $is_last_day = ($processed_days + 1) >= $days_to_generate;
            
            if ($is_thursday || $is_last_day || $iqama_frequency === 'daily') {
                // Process the current batch of days
                
                // Calculate iqama times using our helper functions
                $fajr_iqamas = prayertimes_calculate_fajr_iqama(
                    $week_days_data, 
                    $fajr_rule, 
                    $fajr_minutes_after, 
                    $fajr_minutes_before_shuruq, 
                    $is_weekly && !$fajr_daily_change,
                    $fajr_rounding
                );
                
                $dhuhr_iqamas = prayertimes_calculate_dhuhr_iqama(
                    $week_days_data, 
                    $dhuhr_rule, 
                    $dhuhr_minutes_after, 
                    $dhuhr_fixed_standard, 
                    $dhuhr_fixed_dst, 
                    $is_weekly && !$dhuhr_daily_change,
                    $dhuhr_rounding
                );
                
                $asr_iqamas = prayertimes_calculate_asr_iqama(
                    $week_days_data, 
                    $asr_rule, 
                    $asr_minutes_after, 
                    $asr_fixed_standard, 
                    $asr_fixed_dst, 
                    $is_weekly && !$asr_daily_change,
                    $asr_rounding
                );
                
                $maghrib_iqamas = prayertimes_calculate_maghrib_iqama(
                    $week_days_data, 
                    $maghrib_minutes_after, 
                    $is_weekly && !$maghrib_daily_change,
                    $maghrib_rounding
                );
                
                $isha_iqamas = prayertimes_calculate_isha_iqama(
                    $week_days_data, 
                    $isha_rule, 
                    $isha_minutes_after, 
                    $isha_min_time, 
                    $isha_max_time, 
                    $is_weekly && !$isha_daily_change,
                    $isha_rounding
                );
                
                // Add rows to CSV data
                foreach ($week_days_data as $day_index => $day_data) {
                    $day_formatted = $day_data['formatted_date'];
                    
                    // Get athan times for the day
                    $fajr_athan = $day_data['athan']['fajr'];
                    $sunrise = $day_data['athan']['sunrise'];
                    $dhuhr_athan = $day_data['athan']['dhuhr'];
                    $asr_athan = $day_data['athan']['asr'];
                    $maghrib_athan = $day_data['athan']['maghrib'];
                    $isha_athan = $day_data['athan']['isha'];
                    
                    // Get iqama times for the day
                    $fajr_iqama = $fajr_iqamas[$day_index];
                    $dhuhr_iqama = $dhuhr_iqamas[$day_index];
                    $asr_iqama = $asr_iqamas[$day_index];
                    $maghrib_iqama = $maghrib_iqamas[$day_index];
                    $isha_iqama = $isha_iqamas[$day_index];
                    
                    // Add row to CSV data
                    $csv_data[] = [
                        $day_formatted,
                        $fajr_athan->format('g:i A'),
                        $fajr_iqama->format('g:i A'),
                        $sunrise->format('g:i A'),
                        $dhuhr_athan->format('g:i A'),
                        $dhuhr_iqama->format('g:i A'),
                        $asr_athan->format('g:i A'),
                        $asr_iqama->format('g:i A'),
                        $maghrib_athan->format('g:i A'),
                        $maghrib_iqama->format('g:i A'),
                        $isha_athan->format('g:i A'),
                        $isha_iqama->format('g:i A')
                    ];
                }
                
                // Reset for next week if weekly frequency
                if ($is_weekly) {
                    $week_days_data = [];
                }
            }
            
            // Move to next day
            $current_date->modify('+1 day');
            $processed_days++;
        }
        
        // Sort the CSV data by date (skip header row)
        $header = array_shift($csv_data);
        usort($csv_data, function($a, $b) {
            return strcmp($a[0], $b[0]);
        });
        array_unshift($csv_data, $header);
        
        // Convert data to CSV format
        $csv_content = '';
        foreach ($csv_data as $row) {
            $csv_content .= implode(',', $row) . "\n";
        }
        
        // Debug info - how many rows were generated
        error_log('Muslim Prayer Times Generate: Generated ' . count($csv_data) . ' rows');
        
        wp_send_json_success([
            'filename' => 'prayer_times_' . prayertimes_date('Y-m-d') . '.csv',
            'content' => $csv_content
        ]);
        
    } catch (\Throwable $e) {
        error_log('Muslim Prayer Times Generate Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        wp_send_json_error('Error generating prayer times: ' . $e->getMessage());
    }
}
add_action('wp_ajax_prayertimes_generate_times', 'prayertimes_handle_generate');

// Add new AJAX handler for exporting prayer times from database
function prayertimes_handle_export_db() {
    check_ajax_referer('prayertimes_export_db_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to export prayer times');
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . PRAYERTIMES_IQAMA_TABLE;
    
    try {
        // Get the current date
        $now = new DateTime('now', new DateTimeZone(prayertimes_get_timezone()));
        $start_date = $now->format('Y-m-d');
        
        // Get the date 365 days from now
        $end_date = clone $now;
        $end_date->modify('+365 days');
        $end_date = $end_date->format('Y-m-d');
        
        // Get all dates in the given range from the database       
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE day BETWEEN %s AND %s ORDER BY day ASC",
            $start_date,
            $end_date
        ), ARRAY_A);
        
        // Create a lookup array of existing days
        $existing_days = [];
        foreach ($results as $row) {
            $existing_days[$row['day']] = $row;
        }
        
        // Prepare CSV data with header
        $csv_data = [];
        $csv_data[] = ['day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama'];
        
        // Generate all days for the next 365 days
        $current_date = clone $now;
        for ($i = 0; $i < 365; $i++) {
            $date_str = $current_date->format('Y-m-d');
            
            if (isset($existing_days[$date_str])) {
                // Day exists in database, use stored values
                $row = $existing_days[$date_str];
                $csv_data[] = [
                    $row['day'],
                    $row['fajr_athan'] ? prayertimes_date('g:i A', strtotime($row['fajr_athan'])) : '',
                    $row['fajr_iqama'] ? prayertimes_date('g:i A', strtotime($row['fajr_iqama'])) : '',
                    $row['sunrise'] ? prayertimes_date('g:i A', strtotime($row['sunrise'])) : '',
                    $row['dhuhr_athan'] ? prayertimes_date('g:i A', strtotime($row['dhuhr_athan'])) : '',
                    $row['dhuhr_iqama'] ? prayertimes_date('g:i A', strtotime($row['dhuhr_iqama'])) : '',
                    $row['asr_athan'] ? prayertimes_date('g:i A', strtotime($row['asr_athan'])) : '',
                    $row['asr_iqama'] ? prayertimes_date('g:i A', strtotime($row['asr_iqama'])) : '',
                    $row['maghrib_athan'] ? prayertimes_date('g:i A', strtotime($row['maghrib_athan'])) : '',
                    $row['maghrib_iqama'] ? prayertimes_date('g:i A', strtotime($row['maghrib_iqama'])) : '',
                    $row['isha_athan'] ? prayertimes_date('g:i A', strtotime($row['isha_athan'])) : '',
                    $row['isha_iqama'] ? prayertimes_date('g:i A', strtotime($row['isha_iqama'])) : ''
                ];
            } else {
                // Day doesn't exist, add empty row with just the date
                $csv_data[] = [
                    $date_str,
                    '', '', '', '', '', '', '', '', '', '', ''
                ];
            }
            
            $current_date->modify('+1 day');
        }
        
        // Convert data to CSV format
        $csv_content = '';
        foreach ($csv_data as $row) {
            $csv_content .= implode(',', $row) . "\n";
        }
        
        wp_send_json_success([
            'filename' => 'prayer_times_db_' . prayertimes_date('Y-m-d') . '.csv',
            'content' => $csv_content
        ]);
        
    } catch (\Throwable $e) {
        wp_send_json_error('Error exporting prayer times: ' . $e->getMessage());
    }
}
add_action('wp_ajax_prayertimes_export_db', 'prayertimes_handle_export_db');

// AJAX handler for import preview
function prayertimes_handle_import_preview() {
    check_ajax_referer('prayertimes_import_preview_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    if (empty($_FILES['import_file'])) {
        wp_send_json_error('No file uploaded');
        return;
    }
    
    $file = $_FILES['import_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = array(
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        );
        $error_message = isset($upload_errors[$file['error']]) ? $upload_errors[$file['error']] : 'Unknown upload error';
        wp_send_json_error($error_message);
        return;
    }
    
    // Check file type
    $file_type = wp_check_filetype(basename($file['name']), array('csv' => 'text/csv'));
    if (!$file_type['ext']) {
        wp_send_json_error('Invalid file type. Please upload a CSV file.');
        return;
    }
    
    // Initialize WP_Filesystem
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    
    // Process the CSV file
    $rows = array();
    
    // Get file content using WP_Filesystem
    $content = $wp_filesystem->get_contents($file['tmp_name']);
    if ($content === false) {
        wp_send_json_error('Failed to read file');
        return;
    }
    
    // Process CSV content
    $lines = explode("\n", $content);
    if (empty($lines)) {
        wp_send_json_error('No data found in the CSV file');
        return;
    }
    
    // Parse header row
    $header = str_getcsv(array_shift($lines));
    $header = array_map('trim', array_map('strtolower', $header));
    
    // Validate header structure
    $expected_headers = array('day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama');
    
    if (count(array_intersect($expected_headers, $header)) !== count($expected_headers)) {
        wp_send_json_error('CSV header format is invalid. Expected columns: ' . implode(', ', $expected_headers));
        return;
    }
    
    // Process data rows
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line);
        if (count($data) === count($header)) {
            $row = array_combine($header, $data);
            
            // Convert any date format to Y-m-d
            if (isset($row['day'])) {
                // Try different date formats (including Excel's M/d/YY)
                $date_obj = false;
                $formats = array('Y-m-d', 'n/j/y', 'n/j/Y', 'm/d/y', 'm/d/Y', 'd-m-Y', 'd/m/Y');
                
                foreach ($formats as $format) {
                    $date_obj = DateTime::createFromFormat($format, $row['day']);
                    if ($date_obj !== false) {
                        break;
                    }
                }
                
                if ($date_obj) {
                    $row['day'] = $date_obj->format('Y-m-d');
                } else {
                    $row['day_error'] = 'Invalid date format';
                }
            }
            
            $rows[] = $row;
        }
    }
    
    if (empty($rows)) {
        wp_send_json_error('No valid data found in the CSV file');
        return;
    }
    
    // Return preview data
    wp_send_json_success(array(
        'preview' => $rows,
        'total_rows' => count($rows) 
    ));
}
add_action('wp_ajax_prayertimes_import_preview', 'prayertimes_handle_import_preview');

// AJAX handler for the actual import
function prayertimes_handle_import() {
    check_ajax_referer('prayertimes_import_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    if (empty($_FILES['import_file'])) {
        wp_send_json_error('No file uploaded');
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . PRAYERTIMES_IQAMA_TABLE;
    
    $file = $_FILES['import_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('File upload error: ' . $file['error']);
        return;
    }
    
    // Initialize WP_Filesystem
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    
    // Get file content using WP_Filesystem
    $content = $wp_filesystem->get_contents($file['tmp_name']);
    if ($content === false) {
        wp_send_json_error('Failed to read file');
        return;
    }
    
    // Process CSV content
    $lines = explode("\n", $content);
    if (empty($lines)) {
        wp_send_json_error('No data found in the CSV file');
        return;
    }
    
    // Parse header row
    $header = str_getcsv(array_shift($lines));
    $header = array_map('trim', array_map('strtolower', $header));
    
    // Process data rows
    $success_count = 0;
    $error_count = 0;
    $errors = array();
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line);
        if (count($data) === count($header)) {
            $row = array_combine($header, $data);
            
            // Parse date
            $date_obj = false;
            $formats = array('Y-m-d', 'n/j/y', 'n/j/Y', 'm/d/y', 'm/d/Y', 'd-m-Y', 'd/m/Y');
            
            foreach ($formats as $format) {
                $date_obj = DateTime::createFromFormat($format, $row['day']);
                if ($date_obj !== false) {
                    break;
                }
            }
            
            if (!$date_obj) {
                $error_count++;
                $errors[] = "Row skipped: Invalid date format '{$row['day']}'";
                continue;
            }
            
            // Format date for database
            $row['day'] = $date_obj->format('Y-m-d');
            
            // Format prayer times (convert from AM/PM to 24h format for database)
            $prayer_columns = array('fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 
                                  'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 
                                  'isha_athan', 'isha_iqama');
            
            foreach ($prayer_columns as $column) {
                if (!empty($row[$column])) {
                    // Try to parse the time
                    $time_obj = DateTime::createFromFormat('g:i A', $row[$column]);
                    if (!$time_obj) {
                        $time_obj = DateTime::createFromFormat('H:i', $row[$column]);
                    }
                    
                    if ($time_obj) {
                        $row[$column] = $time_obj->format('H:i:s');
                    } else {
                        $row[$column] = null; // If time can't be parsed, set to null
                    }
                } else {
                    $row[$column] = null;
                }
            }
            
            // Insert or update the database record
            $result = $wpdb->replace(
                $table_name,
                array(
                    'day' => $row['day'],
                    'fajr_athan' => $row['fajr_athan'],
                    'fajr_iqama' => $row['fajr_iqama'],
                    'sunrise' => $row['sunrise'],
                    'dhuhr_athan' => $row['dhuhr_athan'],
                    'dhuhr_iqama' => $row['dhuhr_iqama'],
                    'asr_athan' => $row['asr_athan'],
                    'asr_iqama' => $row['asr_iqama'],
                    'maghrib_athan' => $row['maghrib_athan'],
                    'maghrib_iqama' => $row['maghrib_iqama'],
                    'isha_athan' => $row['isha_athan'],
                    'isha_iqama' => $row['isha_iqama']
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Database error on row with date {$row['day']}: " . $wpdb->last_error;
            }
        }
    }
    
    wp_send_json_success(array(
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors
    ));
}
add_action('wp_ajax_prayertimes_import', 'prayertimes_handle_import');

// Add AJAX handler for Hijri date preview
function prayertimes_handle_hijri_preview() {
    check_ajax_referer('prayertimes_hijri_preview_nonce', 'nonce');
    
    // Get the offset from the request
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    
    // Ensure the offset is within allowed range
    $offset = max(-2, min(2, $offset));
    
    // Load the Hijri date converter
    require_once __DIR__ . '/includes/hijri-date-converter.php';
    
    // Get today's date and convert to Hijri with the offset
    $today = prayertimes_date('Y-m-d');
    $hijri_date = prayertimes_convert_to_hijri($today, true, 'en', $offset);
    
    wp_send_json_success([
        'hijri_date' => $hijri_date
    ]);
}
add_action('wp_ajax_prayertimes_preview_hijri', 'prayertimes_handle_hijri_preview');
