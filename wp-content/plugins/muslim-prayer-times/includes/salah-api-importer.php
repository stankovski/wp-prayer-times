<?php

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/salah-api-mappings.php';

/**
 * Import SalahAPI JSON and convert to WordPress settings
 * 
 * @param string $json_data JSON string in SalahAPI format
 * @return array|WP_Error Array of settings to merge, or WP_Error on failure
 */
function muslprti_import_salahapi_json($json_data) {
    // Decode JSON
    $data = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_json', 'Invalid JSON format: ' . json_last_error_msg());
    }
    
    // Validate SalahAPI version
    if (!isset($data['salahapi'])) {
        return new WP_Error('invalid_salahapi', 'Not a valid SalahAPI document: missing salahapi version field');
    }
    
    $settings = array();
    
    // Import Location data
    if (isset($data['location'])) {
        $location_settings = muslprti_import_location($data['location']);
        if (is_wp_error($location_settings)) {
            return $location_settings;
        }
        $settings = array_merge($settings, $location_settings);
    }
    
    // Import CalculationMethod data
    if (isset($data['calculationMethod'])) {
        $method_settings = muslprti_import_calculation_method($data['calculationMethod']);
        if (is_wp_error($method_settings)) {
            return $method_settings;
        }
        $settings = array_merge($settings, $method_settings);
    }
    
    return $settings;
}

/**
 * Import Location object from SalahAPI
 * 
 * @param array $location Location object from SalahAPI
 * @return array Settings array
 */
function muslprti_import_location($location) {
    $settings = array();
    
    if (isset($location['latitude'])) {
        $settings['lat'] = floatval($location['latitude']);
    }
    
    if (isset($location['longitude'])) {
        $settings['lng'] = floatval($location['longitude']);
    }
    
    if (isset($location['timezone'])) {
        $settings['tz'] = sanitize_text_field($location['timezone']);
    }
    
    return $settings;
}

/**
 * Import CalculationMethod object from SalahAPI
 * 
 * @param array $calc_method CalculationMethod object from SalahAPI
 * @return array Settings array
 */
function muslprti_import_calculation_method($calc_method) {
    $settings = array();
    
    // Import calculation method name
    if (isset($calc_method['name'])) {
        $settings['method'] = muslprti_convert_method_from_salahapi($calc_method['name']);
    }
    
    // Import ASR calculation method
    if (isset($calc_method['asrCalculationMethod'])) {
        $settings['asr_calc'] = muslprti_convert_asr_from_salahapi($calc_method['asrCalculationMethod']);
    }
    
    // Import high latitude adjustment
    if (isset($calc_method['highLatitudeAdjustment'])) {
        $settings['latitude_adjustment'] = muslprti_convert_high_lat_from_salahapi($calc_method['highLatitudeAdjustment']);
    }
    
    // Import iqama calculation rules
    if (isset($calc_method['iqamaCalculationRules'])) {
        $iqama_settings = muslprti_import_iqama_rules($calc_method['iqamaCalculationRules']);
        $settings = array_merge($settings, $iqama_settings);
    }
    
    // Import Jumuah rules
    if (isset($calc_method['jumuahRules']) && is_array($calc_method['jumuahRules'])) {
        $jumuah_settings = muslprti_import_jumuah_rules($calc_method['jumuahRules']);
        $settings = array_merge($settings, $jumuah_settings);
    }
    
    return $settings;
}

/**
 * Import IqamaCalculationRules from SalahAPI
 * 
 * @param array $iqama_rules IqamaCalculationRules object from SalahAPI
 * @return array Settings array
 */
function muslprti_import_iqama_rules($iqama_rules) {
    $settings = array();
    
    // Import changeOn (weekly vs daily)
    if (isset($iqama_rules['changeOn'])) {
        $settings['iqama_frequency'] = 'weekly';
    } else {
        $settings['iqama_frequency'] = 'daily';
    }
    
    // Import each prayer's rules
    $prayers = array('fajr', 'dhuhr', 'asr', 'maghrib', 'isha');
    foreach ($prayers as $prayer) {
        if (isset($iqama_rules[$prayer])) {
            $prayer_settings = muslprti_import_prayer_rule($iqama_rules[$prayer], $prayer);
            $settings = array_merge($settings, $prayer_settings);
        }
    }
    
    return $settings;
}

/**
 * Import a single prayer calculation rule from SalahAPI
 * 
 * @param array $rule PrayerCalculationRule object from SalahAPI
 * @param string $prayer Prayer name (fajr, dhuhr, asr, maghrib, isha)
 * @return array Settings array
 */
function muslprti_import_prayer_rule($rule, $prayer) {
    $settings = array();
    
    // Check if it's a static time
    if (isset($rule['static'])) {
        // Fixed time rule (for Dhuhr/Asr)
        if (in_array($prayer, array('dhuhr', 'asr'))) {
            $settings[$prayer . '_rule'] = 'fixed_time';
            $settings[$prayer . '_fixed_standard'] = $rule['static'];
            
            // Check for DST override
            if (isset($rule['overrides']) && is_array($rule['overrides'])) {
                foreach ($rule['overrides'] as $override) {
                    if (isset($override['condition']) && $override['condition'] === 'daylightSavingsTime') {
                        if (isset($override['time']['static'])) {
                            $settings[$prayer . '_fixed_dst'] = $override['time']['static'];
                        }
                    }
                }
            }
            
            // If no DST override found, use the same time
            if (!isset($settings[$prayer . '_fixed_dst'])) {
                $settings[$prayer . '_fixed_dst'] = $rule['static'];
            }
        }
        return $settings;
    }
    
    // Dynamic calculation rule
    if (isset($rule['change'])) {
        $settings[$prayer . '_daily_change'] = ($rule['change'] === 'daily') ? 1 : 0;
    }
    
    if (isset($rule['roundMinutes'])) {
        $settings[$prayer . '_rounding'] = intval($rule['roundMinutes']);
    }
    
    if (isset($rule['earliest'])) {
        $settings[$prayer . '_min_time'] = $rule['earliest'];
    }
    
    if (isset($rule['latest'])) {
        $settings[$prayer . '_max_time'] = $rule['latest'];
    }
    
    if (isset($rule['afterAthanMinutes'])) {
        $settings[$prayer . '_minutes_after'] = intval($rule['afterAthanMinutes']);
        if (in_array($prayer, array('dhuhr', 'asr'))) {
            $settings[$prayer . '_rule'] = 'after_athan';
        }
    }
    
    if (isset($rule['beforeEndMinutes'])) {
        if ($prayer === 'fajr') {
            $settings['fajr_rule'] = 'before_shuruq';
            $settings['fajr_minutes_before_shuruq'] = intval($rule['beforeEndMinutes']);
        }
    }
    
    // Check for Ramadan overrides
    if (isset($rule['overrides']) && is_array($rule['overrides'])) {
        foreach ($rule['overrides'] as $override) {
            if (isset($override['condition']) && $override['condition'] === 'ramadan') {
                $settings['ramadan_enabled'] = 1;
                if (isset($override['time']['afterAthanMinutes'])) {
                    $settings['ramadan_' . $prayer . '_minutes_after'] = intval($override['time']['afterAthanMinutes']);
                }
                if (isset($override['time']['roundMinutes'])) {
                    $settings['ramadan_' . $prayer . '_rounding'] = intval($override['time']['roundMinutes']);
                }
            }
        }
    }
    
    return $settings;
}

/**
 * Import Jumuah rules from SalahAPI
 * 
 * @param array $jumuah_rules Array of JumuahRule objects from SalahAPI
 * @return array Settings array
 */
function muslprti_import_jumuah_rules($jumuah_rules) {
    $settings = array();
    
    $index = 1;
    foreach ($jumuah_rules as $jumuah) {
        if ($index > 3) break; // Only support up to 3 Jumuah prayers
        
        if (isset($jumuah['name'])) {
            $settings['jumuah' . $index . '_name'] = sanitize_text_field($jumuah['name']);
        }
        
        if (isset($jumuah['time']['static'])) {
            $settings['jumuah' . $index] = $jumuah['time']['static'];
        }
        
        $index++;
    }
    
    return $settings;
}

/**
 * Fetch SalahAPI JSON from a URL
 * 
 * @param string $url URL to fetch from
 * @return string|WP_Error JSON string or WP_Error on failure
 */
function muslprti_fetch_salahapi_from_url($url) {
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return new WP_Error('invalid_url', 'Invalid URL provided');
    }
    
    // Fetch the URL
    $response = wp_remote_get($url, array(
        'timeout' => 15,
        'sslverify' => true,
    ));
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return new WP_Error('http_error', 'HTTP error ' . $status_code);
    }
    
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return new WP_Error('empty_response', 'Empty response from URL');
    }
    
    return $body;
}
