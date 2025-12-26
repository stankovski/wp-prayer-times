<?php

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/salah-api-mappings.php';

/**
 * Register REST API endpoints for Muslim Prayer Times
 */
function muslprti_register_rest_routes() {
    // Register the salah-api endpoint
    register_rest_route('muslim-prayer-times/v1', '/salah-api', array(
        'methods'  => 'GET',
        'callback' => 'muslprti_salah_api_endpoint',
        'permission_callback' => '__return_true', // Public endpoint
    ));
    
    // Register the CSV endpoint
    register_rest_route('muslim-prayer-times/v1', '/prayer-times-csv', array(
        'methods'  => 'GET',
        'callback' => 'muslprti_prayer_times_csv_endpoint',
        'permission_callback' => '__return_true', // Public endpoint
        'args' => array(
            'fromDate' => array(
                'required' => false,
                'type' => 'string',
                'format' => 'date',
                'description' => 'Start date in YYYY-MM-DD format',
            ),
            'toDate' => array(
                'required' => false,
                'type' => 'string',
                'format' => 'date',
                'description' => 'End date in YYYY-MM-DD format',
            ),
        ),
    ));
}
add_action('rest_api_init', 'muslprti_register_rest_routes');

/**
 * SalahAPI endpoint callback
 * Returns prayer times data in SalahAPI JSON format
 */
function muslprti_salah_api_endpoint($request) {
    $opts = get_option('muslprti_settings', []);
    
    // Get the base URL for the site
    $site_url = get_site_url();
    $csv_url = rest_url('muslim-prayer-times/v1/prayer-times-csv');
    
    // Build Info Object
    $info = array(
        'title' => sanitize_text_field(get_bloginfo('name')) . ' Prayer Times',
        'description' => 'Islamic prayer times provided by ' . sanitize_text_field(get_bloginfo('name')),
        'version' => '1.0.0',
    );
    
    // Get admin email if available
    $admin_email = get_option('admin_email');
    if ($admin_email && is_email($admin_email)) {
        $info['contact'] = array(
            'name' => sanitize_text_field(get_bloginfo('name')),
            'email' => sanitize_email($admin_email),
        );
    }
    
    // Build Location Object
    // Sanitize and validate timezone
    $default_timezone = 'America/Los_Angeles';
    $timezone = isset($opts['tz']) ? sanitize_text_field($opts['tz']) : $default_timezone;
    
    // Validate timezone against PHP's list of valid timezones
    $valid_timezones = timezone_identifiers_list();
    if (!in_array($timezone, $valid_timezones, true)) {
        $timezone = $default_timezone;
    }
    
    $location = array(
        'latitude' => isset($opts['lat']) ? floatval($opts['lat']) : 47.7623,
        'longitude' => isset($opts['lng']) ? floatval($opts['lng']) : -122.2054,
        'timezone' => $timezone,
        'dateFormat' => 'YYYY-MM-DD',
        'timeFormat' => 'HH:mm',
    );
    
    // Build CalculationMethod Object
    $calculation_method = muslprti_build_calculation_method_object($opts);
    
    // Build DailyPrayerTimes Object
    $daily_prayer_times = array(
        'csvUrl' => $csv_url,
        'csvUrlParameters' => array(
            'fromDate' => array(
                'in' => 'query',
                'type' => 'fromDate',
                'format' => 'YYYY-MM-DD',
            ),
            'toDate' => array(
                'in' => 'query',
                'type' => 'toDate',
                'format' => 'YYYY-MM-DD',
            ),
        ),
        'dateFormat' => 'YYYY-MM-DD',
        'timeFormat' => 'HH:mm',
    );
    
    // Build the complete SalahAPI response
    $response = array(
        'salahapi' => '1.0',
        'info' => $info,
        'location' => $location,
        'calculationMethod' => $calculation_method,
        'dailyPrayerTimes' => $daily_prayer_times,
    );
    
    return rest_ensure_response($response);
}

/**
 * Build CalculationMethod object for SalahAPI
 */
function muslprti_build_calculation_method_object($opts) {
    $method = isset($opts['method']) ? $opts['method'] : 'ISNA';
    $asr_calc = isset($opts['asr_calc']) ? $opts['asr_calc'] : 'STANDARD';
    $latitude_adjustment = isset($opts['latitude_adjustment']) ? $opts['latitude_adjustment'] : 'MOTN';
    
    $calculation_method = array(
        'name' => muslprti_convert_method_to_salahapi($method),
        'asrCalculationMethod' => muslprti_convert_asr_to_salahapi($asr_calc),
        'highLatitudeAdjustment' => muslprti_convert_high_lat_to_salahapi($latitude_adjustment),
    );
    
    // Add iqama calculation rules if configured
    $iqama_rules = muslprti_build_iqama_rules_object($opts);
    if ($iqama_rules) {
        $calculation_method['iqamaCalculationRules'] = $iqama_rules;
    }
    
    // Add Jumuah rules if configured
    $jumuah_rules = muslprti_build_jumuah_rules_array($opts);
    if (!empty($jumuah_rules)) {
        $calculation_method['jumuahRules'] = $jumuah_rules;
    }
    
    return $calculation_method;
}

/**
 * Build IqamaCalculationRules object for SalahAPI
 */
function muslprti_build_iqama_rules_object($opts) {
    $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
    
    $iqama_rules = array();
    
    // Set changeOn day for weekly changes
    if ($iqama_frequency === 'weekly') {
        $iqama_rules['changeOn'] = 'friday';
    }
    
    // Fajr rules
    $iqama_rules['fajr'] = muslprti_build_prayer_rule($opts, 'fajr');
    
    // Dhuhr rules
    $iqama_rules['dhuhr'] = muslprti_build_prayer_rule($opts, 'dhuhr');
    
    // Asr rules
    $iqama_rules['asr'] = muslprti_build_prayer_rule($opts, 'asr');
    
    // Maghrib rules
    $iqama_rules['maghrib'] = muslprti_build_prayer_rule($opts, 'maghrib');
    
    // Isha rules
    $iqama_rules['isha'] = muslprti_build_prayer_rule($opts, 'isha');
    
    return $iqama_rules;
}

/**
 * Build PrayerCalculationRule for a specific prayer
 */
function muslprti_build_prayer_rule($opts, $prayer) {
    $rule = array();
    $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
    
    // Check if this prayer has daily change override
    $daily_change_key = $prayer . '_daily_change';
    $has_daily_change = isset($opts[$daily_change_key]) && $opts[$daily_change_key];
    
    // Determine change frequency
    $change = ($iqama_frequency === 'daily' || $has_daily_change) ? 'daily' : 'weekly';
    
    // Handle different rule types
    $rule_type_key = $prayer . '_rule';
    $rule_type = isset($opts[$rule_type_key]) ? $opts[$rule_type_key] : 'after_athan';
    
    // For fixed time rules (Dhuhr/Asr)
    if ($rule_type === 'fixed_time') {
        $standard_time = isset($opts[$prayer . '_fixed_standard']) ? $opts[$prayer . '_fixed_standard'] : '13:30';
        $dst_time = isset($opts[$prayer . '_fixed_dst']) ? $opts[$prayer . '_fixed_dst'] : '13:30';
        
        $rule['static'] = $standard_time;
        
        // Add DST override if different
        if ($standard_time !== $dst_time) {
            $rule['overrides'] = array(
                array(
                    'condition' => 'daylightSavingsTime',
                    'time' => array(
                        'static' => $dst_time,
                    ),
                ),
            );
        }
        
        return $rule;
    }
    
    // For time-based calculations
    $rule['change'] = $change;
    
    // Rounding
    $rounding_key = $prayer . '_rounding';
    if (isset($opts[$rounding_key])) {
        $rule['roundMinutes'] = strval($opts[$rounding_key]);
    }
    
    // Earliest/Latest constraints
    $earliest_key = $prayer . '_min_time';
    $latest_key = $prayer . '_max_time';
    
    if (isset($opts[$earliest_key]) && !empty($opts[$earliest_key])) {
        $rule['earliest'] = $opts[$earliest_key];
    }
    
    if (isset($opts[$latest_key]) && !empty($opts[$latest_key])) {
        $rule['latest'] = $opts[$latest_key];
    }
    
    // After athan minutes or before end minutes
    if ($prayer === 'fajr' && $rule_type === 'before_shuruq') {
        $before_minutes_key = 'fajr_minutes_before_shuruq';
        if (isset($opts[$before_minutes_key])) {
            $rule['beforeEndMinutes'] = intval($opts[$before_minutes_key]);
        }
    } else {
        $after_minutes_key = $prayer . '_minutes_after';
        if (isset($opts[$after_minutes_key])) {
            $rule['afterAthanMinutes'] = intval($opts[$after_minutes_key]);
        }
    }
    
    // Add Ramadan overrides if enabled
    if (isset($opts['ramadan_enabled']) && $opts['ramadan_enabled'] && in_array($prayer, ['fajr', 'maghrib', 'isha'])) {
        $ramadan_override = muslprti_build_ramadan_override($opts, $prayer);
        if ($ramadan_override) {
            $rule['overrides'] = array($ramadan_override);
        }
    }
    
    return $rule;
}

/**
 * Build Ramadan override rule for a prayer
 */
function muslprti_build_ramadan_override($opts, $prayer) {
    $ramadan_minutes_key = 'ramadan_' . $prayer . '_minutes_after';
    $ramadan_rounding_key = 'ramadan_' . $prayer . '_rounding';
    
    if (!isset($opts[$ramadan_minutes_key])) {
        return null;
    }
    
    $override_rule = array(
        'change' => 'daily',
        'afterAthanMinutes' => intval($opts[$ramadan_minutes_key]),
    );
    
    if (isset($opts[$ramadan_rounding_key])) {
        $override_rule['roundMinutes'] = strval($opts[$ramadan_rounding_key]);
    }
    
    return array(
        'condition' => 'ramadan',
        'time' => $override_rule,
    );
}

/**
 * Build JumuahRules array for SalahAPI
 */
function muslprti_build_jumuah_rules_array($opts) {
    $jumuah_rules = array();
    
    // Check for up to 3 Jumuah prayers
    for ($i = 1; $i <= 3; $i++) {
        $time_key = 'jumuah' . $i;
        $name_key = 'jumuah' . $i . '_name';
        
        $time = isset($opts[$time_key]) ? $opts[$time_key] : '';
        $name = isset($opts[$name_key]) ? $opts[$name_key] : 'Jumuah ' . $i;
        
        // Only add if time is set and not empty
        if (!empty($time)) {
            $jumuah_rules[] = array(
                'name' => $name,
                'time' => array(
                    'static' => $time,
                ),
            );
        }
    }
    
    return $jumuah_rules;
}

/**
 * CSV endpoint callback
 * Returns prayer times data in CSV format
 */
function muslprti_prayer_times_csv_endpoint($request) {
    global $wpdb;
    
    // Check cache first
    $from_date = $request->get_param('fromDate');
    $to_date = $request->get_param('toDate');
    
    // Default to current month if no dates provided
    if (empty($from_date) && empty($to_date)) {
        $from_date = muslprti_date('Y-m-01'); // First day of current month
        $to_date = muslprti_date('Y-m-t');   // Last day of current month
    } elseif (empty($from_date)) {
        $from_date = $to_date;
    } elseif (empty($to_date)) {
        $to_date = $from_date;
    }
    
    // Validate date format and actual date validity
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
        return new WP_Error('invalid_date', 'Date must be in YYYY-MM-DD format', array('status' => 400));
    }
    
    // Validate that dates are actually valid (e.g., not 2025-02-30)
    $from_date_obj = DateTime::createFromFormat('Y-m-d', $from_date);
    $to_date_obj = DateTime::createFromFormat('Y-m-d', $to_date);
    
    if (!$from_date_obj || $from_date_obj->format('Y-m-d') !== $from_date ||
        !$to_date_obj || $to_date_obj->format('Y-m-d') !== $to_date) {
        return new WP_Error('invalid_date', 'Invalid date provided', array('status' => 400));
    }
    
    // Ensure from_date is not after to_date
    if ($from_date_obj > $to_date_obj) {
        return new WP_Error('invalid_date_range', 'Start date must be before or equal to end date', array('status' => 400));
    }
    
    // Limit date range to prevent abuse (max 1 year)
    $date_diff = $from_date_obj->diff($to_date_obj);
    $days_diff = $date_diff->days;
    if ($days_diff > 366) {
        return new WP_Error('date_range_too_large', 'Date range cannot exceed 1 year', array('status' => 400));
    }
    
    // Check cache for this date range
    $cache_key = 'muslprti_csv_' . md5($from_date . '_' . $to_date);
    $cached_data = wp_cache_get($cache_key, 'muslim_prayer_times');
    
    if (false !== $cached_data) {
        // Return cached CSV content directly to avoid JSON encoding
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: inline; filename="prayer-times.csv"');
        header('Content-Length: ' . strlen($cached_data));
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $cached_data;
        exit;
    }
    
    // Table name is safe - MUSLPRTI_IQAMA_TABLE is a constant, not user input
    $table_name = $wpdb->prefix . MUSLPRTI_IQAMA_TABLE;
    
    // Query prayer times from database
    // Note: Table name must be escaped outside of prepare() as prepare() only handles placeholders
    $query = $wpdb->prepare(
        "SELECT day, fajr_athan, fajr_iqama, sunrise, dhuhr_athan, dhuhr_iqama, 
                asr_athan, asr_iqama, maghrib_athan, maghrib_iqama, isha_athan, isha_iqama
         FROM " . esc_sql($table_name) . "
         WHERE day >= %s AND day <= %s
         ORDER BY day ASC",
        $from_date,
        $to_date
    );
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    if (empty($results)) {
        return new WP_Error('no_data', 'No prayer times found for the specified date range', array('status' => 404));
    }
    
    // Build CSV content in memory without using fopen()
    // Define headers
    $headers = array(
        'day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama',
        'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama'
    );
    
    // Build CSV content as string
    $csv_lines = array();
    
    // Add header row
    $csv_lines[] = implode(',', array_map(function($header) {
        return '"' . str_replace('"', '""', $header) . '"';
    }, $headers));
    
    // Add data rows
    foreach ($results as $row) {
        $csv_row = array();
        foreach ($headers as $header) {
            $value = isset($row[$header]) ? $row[$header] : '';
            $csv_row[] = '"' . str_replace('"', '""', $value) . '"';
        }
        $csv_lines[] = implode(',', $csv_row);
    }
    
    // Combine all lines
    $csv_content = implode("\n", $csv_lines);
    
    // Cache the CSV content for 5 minutes
    wp_cache_set($cache_key, $csv_content, 'muslim_prayer_times', 300);
    
    // For CSV, we need to output directly to avoid JSON encoding
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: inline; filename="prayer-times.csv"');
    header('Content-Length: ' . strlen($csv_content));
    
    // Output CSV content and exit
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $csv_content;
    exit;
}

/**
 * Helper function to get the SalahAPI endpoint URL
 */
function muslprti_get_salah_api_url() {
    return rest_url('muslim-prayer-times/v1/salah-api');
}

/**
 * Helper function to get the CSV endpoint URL
 */
function muslprti_get_csv_api_url() {
    return rest_url('muslim-prayer-times/v1/prayer-times-csv');
}
