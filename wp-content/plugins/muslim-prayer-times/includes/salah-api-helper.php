<?php

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/salah-api-mappings.php';

// Include the salah-api library
require_once __DIR__ . '/salah-api/Location.php';
require_once __DIR__ . '/salah-api/CalculationMethod.php';
require_once __DIR__ . '/salah-api/IqamaCalculationRules.php';
require_once __DIR__ . '/salah-api/PrayerCalculationRule.php';
require_once __DIR__ . '/salah-api/PrayerCalculationOverrideRule.php';
require_once __DIR__ . '/salah-api/Calculations/Builder.php';
require_once __DIR__ . '/salah-api/Calculations/PrayerTimes.php';
require_once __DIR__ . '/salah-api/Calculations/Method.php';
require_once __DIR__ . '/salah-api/Calculations/IqamaCalculator.php';
require_once __DIR__ . '/salah-api/Calculations/TimeHelpers.php';
require_once __DIR__ . '/salah-api/Calculations/HijriDateConverter.php';

use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;
use SalahAPI\PrayerCalculationOverrideRule;
use SalahAPI\Calculations\Builder;
use SalahAPI\Calculations\PrayerTimes;
use SalahAPI\Calculations\Method;

/**
 * Create a Location object from WordPress settings
 * 
 * @param array $opts WordPress plugin settings
 * @return Location Location object
 */
function muslprti_create_location($opts) {
    $latitude = isset($opts['lat']) ? floatval($opts['lat']) : 47.7623;
    $longitude = isset($opts['lng']) ? floatval($opts['lng']) : -122.2054;
    $timezone = isset($opts['tz']) ? $opts['tz'] : 'America/Los_Angeles';
    
    return new Location(
        $latitude,
        $longitude,
        $timezone,
        'Y-m-d',  // Date format
        'H:i'     // Time format (24-hour)
    );
}

/**
 * Create a CalculationMethod object from WordPress settings
 * 
 * @param array $opts WordPress plugin settings
 * @return CalculationMethod CalculationMethod object
 */
function muslprti_create_calculation_method($opts) {
    $method = isset($opts['method']) ? $opts['method'] : 'ISNA';
    $asr_calc = isset($opts['asr_calc']) ? $opts['asr_calc'] : 'STANDARD';
    $latitude_adjustment = isset($opts['latitude_adjustment']) ? $opts['latitude_adjustment'] : 'MOTN';
    
    $high_latitude_adjustment = muslprti_convert_high_lat_to_library($latitude_adjustment);
    
    // Create iqama calculation rules
    $iqama_rules = muslprti_create_iqama_rules($opts);
    
    // Get method parameters from the Method class
    $methods = Method::getMethods();
    $method_config = $methods[$method] ?? $methods[Method::METHOD_ISNA];
    
    // Get fajr and isha angles from the method configuration
    $fajr_angle = null;
    $isha_angle = null;
    
    if (isset($method_config['params'])) {
        $params = $method_config['params'];
        $fajr_angle = isset($params[PrayerTimes::FAJR]) ? floatval($params[PrayerTimes::FAJR]) : null;
        
        // Check if isha is an angle or minutes
        if (isset($params[PrayerTimes::ISHA])) {
            $isha_value = $params[PrayerTimes::ISHA];
            // If it's a string with 'min', we need to handle it differently
            if (is_string($isha_value) && strpos($isha_value, 'min') !== false) {
                // For now, we'll just use a default angle
                $isha_angle = 15.0;
            } else {
                $isha_angle = floatval($isha_value);
            }
        }
    }
    
    return new CalculationMethod(
        $method,
        $fajr_angle,
        $isha_angle,
        $asr_calc,
        $high_latitude_adjustment,
        $iqama_rules
    );
}

/**
 * Create IqamaCalculationRules from WordPress settings
 * 
 * @param array $opts WordPress plugin settings
 * @return IqamaCalculationRules|null IqamaCalculationRules object or null
 */
function muslprti_create_iqama_rules($opts) {
    $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
    
    // Determine changeOn day (for weekly changes)
    $change_on = ($iqama_frequency === 'weekly') ? 'Friday' : null;
    
    // Create Fajr rule
    $fajr_rule = muslprti_create_fajr_rule($opts);
    
    // Create Dhuhr rule
    $dhuhr_rule = muslprti_create_dhuhr_rule($opts);
    
    // Create Asr rule
    $asr_rule = muslprti_create_asr_rule($opts);
    
    // Create Maghrib rule
    $maghrib_rule = muslprti_create_maghrib_rule($opts);
    
    // Create Isha rule
    $isha_rule = muslprti_create_isha_rule($opts);
    
    return new IqamaCalculationRules(
        $change_on,
        $fajr_rule,
        $dhuhr_rule,
        $asr_rule,
        $maghrib_rule,
        $isha_rule
    );
}

/**
 * Create Fajr PrayerCalculationRule from WordPress settings
 * 
 * @param array $opts WordPress plugin settings
 * @return PrayerCalculationRule Fajr calculation rule
 */
function muslprti_create_fajr_rule($opts) {
    $fajr_rule_type = isset($opts['fajr_rule']) ? $opts['fajr_rule'] : 'after_athan';
    $fajr_minutes_after = isset($opts['fajr_minutes_after']) ? intval($opts['fajr_minutes_after']) : 20;
    $fajr_minutes_before_shuruq = isset($opts['fajr_minutes_before_shuruq']) ? intval($opts['fajr_minutes_before_shuruq']) : 45;
    $fajr_daily_change = isset($opts['fajr_daily_change']) ? boolval($opts['fajr_daily_change']) : false;
    $fajr_rounding = isset($opts['fajr_rounding']) ? intval($opts['fajr_rounding']) : 1;
    $fajr_min_time = isset($opts['fajr_min_time']) ? $opts['fajr_min_time'] : '05:00';
    $fajr_max_time = isset($opts['fajr_max_time']) ? $opts['fajr_max_time'] : '07:00';
    $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
    
    // Determine change frequency (daily or weekly)
    $change = ($iqama_frequency === 'daily' || $fajr_daily_change) ? 'daily' : 'weekly';
    
    // Determine which parameters to use
    $after_athan_minutes = null;
    $before_end_minutes = null;
    
    if ($fajr_rule_type === 'before_shuruq') {
        $before_end_minutes = $fajr_minutes_before_shuruq;
    } else {
        $after_athan_minutes = $fajr_minutes_after;
    }
    
    // Check if Ramadan rules are enabled
    $overrides = [];
    if (isset($opts['ramadan_enabled']) && $opts['ramadan_enabled']) {
        $ramadan_override = muslprti_create_ramadan_override(
            $opts,
            'fajr',
            isset($opts['ramadan_fajr_minutes_after']) ? intval($opts['ramadan_fajr_minutes_after']) : 20,
            isset($opts['ramadan_fajr_rounding']) ? intval($opts['ramadan_fajr_rounding']) : 1
        );
        if ($ramadan_override) {
            $overrides[] = $ramadan_override;
        }
    }
    
    return new PrayerCalculationRule(
        null,  // static
        $change,
        $fajr_rounding,
        $fajr_min_time,
        $fajr_max_time,
        $after_athan_minutes,
        $before_end_minutes,
        !empty($overrides) ? $overrides : null
    );
}

/**
 * Create Dhuhr PrayerCalculationRule from WordPress settings
 * 
 * @param array $opts WordPress plugin settings
 * @return PrayerCalculationRule Dhuhr calculation rule
 */
function muslprti_create_dhuhr_rule($opts) {
    $dhuhr_rule_type = isset($opts['dhuhr_rule']) ? $opts['dhuhr_rule'] : 'after_athan';
    $dhuhr_minutes_after = isset($opts['dhuhr_minutes_after']) ? intval($opts['dhuhr_minutes_after']) : 15;
    $dhuhr_fixed_standard = isset($opts['dhuhr_fixed_standard']) ? $opts['dhuhr_fixed_standard'] : '13:30';
    $dhuhr_fixed_dst = isset($opts['dhuhr_fixed_dst']) ? $opts['dhuhr_fixed_dst'] : '13:30';
    $dhuhr_daily_change = isset($opts['dhuhr_daily_change']) ? boolval($opts['dhuhr_daily_change']) : false;
    $dhuhr_rounding = isset($opts['dhuhr_rounding']) ? intval($opts['dhuhr_rounding']) : 1;
    $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
    
    // For fixed time, we need to handle DST overrides
    if ($dhuhr_rule_type === 'fixed_time') {
        // Create base rule with standard time
        $base_rule = new PrayerCalculationRule(
            $dhuhr_fixed_standard,  // static time
            null,
            null,
            null,
            null,
            null,
            null,
            null
        );
        
        // If DST time is different, create an override
        if ($dhuhr_fixed_dst !== $dhuhr_fixed_standard) {
            $dst_override_rule = new PrayerCalculationRule(
                $dhuhr_fixed_dst,  // static time for DST
                null,
                null,
                null,
                null,
                null,
                null,
                null
            );
            
            $override = new PrayerCalculationOverrideRule(
                'daylightSavingsTime',
                $dst_override_rule
            );
            
            $base_rule->overrides = [$override];
        }
        
        return $base_rule;
    }
    
    // Otherwise, minutes after athan
    $change = ($iqama_frequency === 'daily' || $dhuhr_daily_change) ? 'daily' : 'weekly';
    
    return new PrayerCalculationRule(
        null,  // static
        $change,
        $dhuhr_rounding,
        null,  // earliest
        null,  // latest
        $dhuhr_minutes_after,
        null,  // beforeEndMinutes
        null   // overrides
    );
}

/**
 * Create Asr PrayerCalculationRule from WordPress settings
 * 
 * @param array $opts WordPress plugin settings
 * @return PrayerCalculationRule Asr calculation rule
 */
function muslprti_create_asr_rule($opts) {
    $asr_rule_type = isset($opts['asr_rule']) ? $opts['asr_rule'] : 'after_athan';
    $asr_minutes_after = isset($opts['asr_minutes_after']) ? intval($opts['asr_minutes_after']) : 15;
    $asr_fixed_standard = isset($opts['asr_fixed_standard']) ? $opts['asr_fixed_standard'] : '16:30';
    $asr_fixed_dst = isset($opts['asr_fixed_dst']) ? $opts['asr_fixed_dst'] : '16:30';
    $asr_daily_change = isset($opts['asr_daily_change']) ? boolval($opts['asr_daily_change']) : false;
    $asr_rounding = isset($opts['asr_rounding']) ? intval($opts['asr_rounding']) : 1;
    $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
    
    // For fixed time, we need to handle DST overrides
    if ($asr_rule_type === 'fixed_time') {
        // Create base rule with standard time
        $base_rule = new PrayerCalculationRule(
            $asr_fixed_standard,  // static time
            null,
            null,
            null,
            null,
            null,
            null,
            null
        );
        
        // If DST time is different, create an override
        if ($asr_fixed_dst !== $asr_fixed_standard) {
            $dst_override_rule = new PrayerCalculationRule(
                $asr_fixed_dst,  // static time for DST
                null,
                null,
                null,
                null,
                null,
                null,
                null
            );
            
            $override = new PrayerCalculationOverrideRule(
                'daylightSavingsTime',
                $dst_override_rule
            );
            
            $base_rule->overrides = [$override];
        }
        
        return $base_rule;
    }
    
    // Otherwise, minutes after athan
    $change = ($iqama_frequency === 'daily' || $asr_daily_change) ? 'daily' : 'weekly';
    
    return new PrayerCalculationRule(
        null,  // static
        $change,
        $asr_rounding,
        null,  // earliest
        null,  // latest
        $asr_minutes_after,
        null,  // beforeEndMinutes
        null   // overrides
    );
}

/**
 * Create Maghrib PrayerCalculationRule from WordPress settings
 * 
 * @param array $opts WordPress plugin settings
 * @return PrayerCalculationRule Maghrib calculation rule
 */
function muslprti_create_maghrib_rule($opts) {
    $maghrib_minutes_after = isset($opts['maghrib_minutes_after']) ? intval($opts['maghrib_minutes_after']) : 5;
    $maghrib_daily_change = isset($opts['maghrib_daily_change']) ? boolval($opts['maghrib_daily_change']) : false;
    $maghrib_rounding = isset($opts['maghrib_rounding']) ? intval($opts['maghrib_rounding']) : 1;
    $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
    
    $change = ($iqama_frequency === 'daily' || $maghrib_daily_change) ? 'daily' : 'weekly';
    
    // Check if Ramadan rules are enabled
    $overrides = [];
    if (isset($opts['ramadan_enabled']) && $opts['ramadan_enabled']) {
        $ramadan_override = muslprti_create_ramadan_override(
            $opts,
            'maghrib',
            isset($opts['ramadan_maghrib_minutes_after']) ? intval($opts['ramadan_maghrib_minutes_after']) : 10,
            isset($opts['ramadan_maghrib_rounding']) ? intval($opts['ramadan_maghrib_rounding']) : 1
        );
        if ($ramadan_override) {
            $overrides[] = $ramadan_override;
        }
    }
    
    return new PrayerCalculationRule(
        null,  // static
        $change,
        $maghrib_rounding,
        null,  // earliest
        null,  // latest
        $maghrib_minutes_after,
        null,  // beforeEndMinutes
        !empty($overrides) ? $overrides : null
    );
}

/**
 * Create Isha PrayerCalculationRule from WordPress settings
 * 
 * @param array $opts WordPress plugin settings
 * @return PrayerCalculationRule Isha calculation rule
 */
function muslprti_create_isha_rule($opts) {
    $isha_minutes_after = isset($opts['isha_minutes_after']) ? intval($opts['isha_minutes_after']) : 15;
    $isha_min_time = isset($opts['isha_min_time']) ? $opts['isha_min_time'] : '19:30';
    $isha_max_time = isset($opts['isha_max_time']) ? $opts['isha_max_time'] : '22:00';
    $isha_daily_change = isset($opts['isha_daily_change']) ? boolval($opts['isha_daily_change']) : false;
    $isha_rounding = isset($opts['isha_rounding']) ? intval($opts['isha_rounding']) : 1;
    $iqama_frequency = isset($opts['iqama_frequency']) ? $opts['iqama_frequency'] : 'weekly';
    
    $change = ($iqama_frequency === 'daily' || $isha_daily_change) ? 'daily' : 'weekly';
    
    // Check if Ramadan rules are enabled
    $overrides = [];
    if (isset($opts['ramadan_enabled']) && $opts['ramadan_enabled']) {
        $ramadan_override = muslprti_create_ramadan_override(
            $opts,
            'isha',
            isset($opts['ramadan_isha_minutes_after']) ? intval($opts['ramadan_isha_minutes_after']) : 20,
            isset($opts['ramadan_isha_rounding']) ? intval($opts['ramadan_isha_rounding']) : 1
        );
        if ($ramadan_override) {
            $overrides[] = $ramadan_override;
        }
    }
    
    return new PrayerCalculationRule(
        null,  // static
        $change,
        $isha_rounding,
        $isha_min_time,
        $isha_max_time,
        $isha_minutes_after,
        null,  // beforeEndMinutes
        !empty($overrides) ? $overrides : null
    );
}

/**
 * Create a Ramadan override rule for a prayer
 * 
 * @param array $opts WordPress plugin settings
 * @param string $prayer Prayer name (fajr, maghrib, isha)
 * @param int $minutes_after Minutes after athan
 * @param int $rounding Rounding interval
 * @return PrayerCalculationOverrideRule|null Override rule or null
 */
function muslprti_create_ramadan_override($opts, $prayer, $minutes_after, $rounding) {
    // Ramadan overrides always use daily change
    $override_rule = new PrayerCalculationRule(
        null,  // static
        'daily',  // Always daily during Ramadan
        $rounding,
        null,  // earliest (inherit from base rule)
        null,  // latest (inherit from base rule)
        $minutes_after,
        null,  // beforeEndMinutes
        null   // no nested overrides
    );
    
    return new PrayerCalculationOverrideRule(
        'ramadan',
        $override_rule
    );
}

/**
 * Create a Builder instance from WordPress settings
 * 
 * @param array $opts WordPress plugin settings
 * @param int $elevation Elevation in meters (default: 0)
 * @return Builder Builder instance
 */
function muslprti_create_builder($opts, $elevation = 0) {
    $location = muslprti_create_location($opts);
    $calculation_method = muslprti_create_calculation_method($opts);
    
    return new Builder($location, $calculation_method, $elevation);
}
