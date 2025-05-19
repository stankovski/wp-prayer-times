<?php

if (!defined('ABSPATH')) exit;

// Helper function to convert DateTime to minutes since midnight
function muslprti_time_to_minutes(DateTime $time) {
    // Get hours and minutes
    $hours = (int)$time->format('G');   // 24-hour format without leading zeros
    $minutes = (int)$time->format('i');
    
    // Calculate total minutes from midnight
    $total_minutes = ($hours * 60) + $minutes;
    
    // Check if DST is in effect using the timezone information in the DateTime object
    $is_dst = $time->format('I') == '1';
    
    // Adjust for DST if it's in effect
    if ($is_dst) {
        $total_minutes -= 60; // Subtract 60 minutes (1 hour) for DST
    }
    
    return $total_minutes;
}

// Helper function to normalize time by subtracting one hour if date is in DST
function muslprti_normalize_time_for_dst(DateTime $time) {
    $normalized_time = clone $time;
    
    // Check if DST is in effect
    $is_dst = $time->format('I') == '1';
    
    // If DST is in effect, subtract one hour
    if ($is_dst) {
        $normalized_time->modify('-1 hour');
    }
    
    return $normalized_time;
}

// Helper function to denormalize time by adding one hour if date is in DST
function muslprti_denormalize_time_for_dst(DateTime $time) {
    $denormalized_time = clone $time;
    
    // Check if DST is in effect
    $is_dst = $time->format('I') == '1';
    
    // If DST is in effect, add one hour
    if ($is_dst) {
        $denormalized_time->modify('+1 hour');
    }
    
    return $denormalized_time;
}

// Helper function to normalize all athan times in the days_data array
function muslprti_normalize_times_for_dst($days_data) {
    $normalized_days_data = [];
    foreach ($days_data as $day_index => $day_data) {
        $normalized_days_data[$day_index] = [
            'date' => clone $day_data['date'],
            'athan' => []
        ];
        
        // Normalize all available athan times
        foreach ($day_data['athan'] as $prayer => $time) {
            $normalized_days_data[$day_index]['athan'][$prayer] = muslprti_normalize_time_for_dst($time);
        }
    }
    
    return $normalized_days_data;
}

/**
 * Format a date or timestamp with the plugin's timezone
 * 
 * @param string $format The format string (as in PHP's date function)
 * @param int|null $timestamp Optional Unix timestamp (current time if null)
 * @return string Formatted date/time string with correct timezone
 */
function muslprti_date($format, $timestamp = null) {
    $timezone = muslprti_get_timezone();
    $tz_object = new DateTimeZone($timezone);
    
    if ($timestamp === null) {
        $date_time = new DateTime('now', $tz_object);
    } else {
        $date_time = new DateTime('@' . $timestamp, $tz_object);
    }
    
    return $date_time->format($format);
}

// Helper function to round a time down to the nearest X minutes
function muslprti_round_down(DateTime $time, $rounding_minutes = 1) {
    if ($rounding_minutes <= 1) {
        return $time; // No need to round if rounding is set to 1 minute
    }
    
    $timestamp = $time->getTimestamp();
    $minutes = muslprti_date('i', $timestamp);
    $seconds = muslprti_date('s', $timestamp);
    $total_seconds = ($minutes * 60) + $seconds;
    
    // Convert rounding_minutes to seconds
    $rounding_seconds = $rounding_minutes * 60;
    
    // Floor to the nearest interval (round down)
    $rounded_seconds = floor($total_seconds / $rounding_seconds) * $rounding_seconds;
    
    // Create a new DateTime with the rounded time
    $rounded_time = clone $time;
    
    // Get the hour part
    $hour = (int)$time->format('G');
    
    // Calculate new minutes and adjust hour if needed
    $new_minutes = floor($rounded_seconds / 60);
    if ($new_minutes >= 60) {
        $hour += floor($new_minutes / 60);
        $new_minutes = $new_minutes % 60;
    }
    
    // Set the new time
    $rounded_time->setTime($hour, $new_minutes, 0);
    
    return $rounded_time;
}

// Helper function to round a time up to the nearest X minutes
function muslprti_round_up(DateTime $time, $rounding_minutes = 1) {
    if ($rounding_minutes <= 1) {
        return $time; // No need to round if rounding is set to 1 minute
    }
    
    $timestamp = $time->getTimestamp();
    $minutes = muslprti_date('i', $timestamp);
    $seconds = muslprti_date('s', $timestamp);
    $total_seconds = ($minutes * 60) + $seconds;
    
    // Convert rounding_minutes to seconds
    $rounding_seconds = $rounding_minutes * 60;
    
    // Ceiling to the nearest interval (round up)
    // If already on an interval boundary and seconds are 0, don't round up
    if ($total_seconds % $rounding_seconds === 0 && $seconds === 0) {
        $rounded_seconds = $total_seconds;
    } else {
        $rounded_seconds = ceil($total_seconds / $rounding_seconds) * $rounding_seconds;
    }
    
    // Create a new DateTime with the rounded time
    $rounded_time = clone $time;
    
    // Get the hour part
    $hour = (int)$time->format('G');
    
    // Calculate new minutes and adjust hour if needed
    $new_minutes = floor($rounded_seconds / 60);
    if ($new_minutes >= 60) {
        $hour += floor($new_minutes / 60);
        $new_minutes = $new_minutes % 60;
    }
    
    // Set the new time
    $rounded_time->setTime($hour, $new_minutes, 0);
    
    return $rounded_time;
}

// Helper function to calculate Fajr Iqama times for a collection of days
function muslprti_calculate_fajr_iqama($days_data, $fajr_rule, $fajr_minutes_after, $fajr_minutes_before_shuruq, $is_weekly, $fajr_rounding) {
    $results = [];
    
    // Use the new helper function to normalize all times
    $normalized_days_data = muslprti_normalize_times_for_dst($days_data);
    
    // Find latest Athan for weekly calculation
    $latest_fajr = null;
    $latest_sunrise = null;
    
    if ($is_weekly) {
        foreach ($normalized_days_data as $day_data) {
            if ($latest_fajr === null || 
                muslprti_time_to_minutes($day_data['athan']['fajr']) > muslprti_time_to_minutes($latest_fajr)) {
                $latest_fajr = clone $day_data['athan']['fajr'];
            }
            if ($latest_sunrise === null || 
                muslprti_time_to_minutes($day_data['athan']['sunrise']) > muslprti_time_to_minutes($latest_sunrise)) {
                $latest_sunrise = clone $day_data['athan']['sunrise'];
            }
        }
        
        // Apply rounding to the latest times
        $latest_fajr = muslprti_round_up($latest_fajr, $fajr_rounding);
        $latest_sunrise = muslprti_round_down($latest_sunrise, $fajr_rounding);
    }
    
    // Process each day
    foreach ($normalized_days_data as $day_index => $day_data) {
        $day_date = $day_data['date'];
        $day_fajr_athan = $day_data['athan']['fajr'];
        
        // Determine iqama time based on rule
        if ($is_weekly) {
            // Weekly calculation - use consistent time derived from latest athan/sunrise
            if ($fajr_rule === 'after_athan') {
                // Use latest fajr time + minutes for all days
                $time_components = explode(':', $latest_fajr->format('H:i'));
                $day_fajr_iqama = clone $day_date;
                $day_fajr_iqama->setTime((int)$time_components[0], (int)$time_components[1]);
                $day_fajr_iqama->modify("+{$fajr_minutes_after} minutes");
            } else if ($fajr_rule === 'before_shuruq') {
                $day_sunrise = $day_data['athan']['sunrise'];
                
                // Get time component from latest sunrise and apply to this day
                $time_components = explode(':', $latest_sunrise->format('H:i'));
                $day_fajr_iqama = clone $day_date;
                $day_fajr_iqama->setTime((int)$time_components[0], (int)$time_components[1]);
                $day_fajr_iqama->modify("-{$fajr_minutes_before_shuruq} minutes");
            }
        } else {
            // Daily calculation
            $day_fajr_iqama = clone $day_fajr_athan;
            if ($fajr_rule === 'after_athan') {
                $day_fajr_iqama->modify("+{$fajr_minutes_after} minutes");
            } else if ($fajr_rule === 'before_shuruq') {
                $day_sunrise = $day_data['athan']['sunrise'];
                $day_fajr_iqama = clone $day_sunrise;
                $day_fajr_iqama->modify("-{$fajr_minutes_before_shuruq} minutes");
            }
        }
        
        // Denormalize the result to account for DST before storing
        // Use the original date for denormalization to maintain correct DST information
        $day_fajr_iqama = muslprti_denormalize_time_for_dst($day_fajr_iqama);
        
        $results[$day_index] = $day_fajr_iqama;
    }
    
    return $results;
}

// Helper function to calculate Dhuhr Iqama times for a collection of days
function muslprti_calculate_dhuhr_iqama($days_data, $dhuhr_rule, $dhuhr_minutes_after, $dhuhr_fixed_standard, $dhuhr_fixed_dst, $is_weekly, $dhuhr_rounding) {
    $results = [];
    
    // Use the new helper function to normalize all times
    $normalized_days_data = muslprti_normalize_times_for_dst($days_data);
    
    // Find latest Athan for weekly calculation
    $latest_dhuhr = null;
    
    if ($is_weekly) {
        foreach ($normalized_days_data as $day_data) {
            if ($latest_dhuhr === null || 
                muslprti_time_to_minutes($day_data['athan']['dhuhr']) > muslprti_time_to_minutes($latest_dhuhr)) {
                $latest_dhuhr = clone $day_data['athan']['dhuhr'];
            }
        }
        
        // Apply rounding to the latest time
        $latest_dhuhr = muslprti_round_up($latest_dhuhr, $dhuhr_rounding);
    }
    
    // Process each day
    foreach ($normalized_days_data as $day_index => $day_data) {
        $day_date = $day_data['date'];
        $day_dhuhr_athan = $day_data['athan']['dhuhr'];
        $day_is_dst = $days_data[$day_index]['date']->format('I') == '1'; // Use original date for DST check
        
        // Determine iqama time based on rule
        if ($is_weekly) {
            // Weekly calculation
            if ($dhuhr_rule === 'after_athan') {
                // Use latest dhuhr time + minutes for all days
                $time_components = explode(':', $latest_dhuhr->format('H:i'));
                $day_dhuhr_iqama = clone $day_date;
                $day_dhuhr_iqama->setTime((int)$time_components[0], (int)$time_components[1]);
                $day_dhuhr_iqama->modify("+{$dhuhr_minutes_after} minutes");
            } else if ($dhuhr_rule === 'fixed_time') {
                $fixed_time = $day_is_dst ? $dhuhr_fixed_dst : $dhuhr_fixed_standard;
                list($hours, $minutes) = explode(':', $fixed_time);
                $day_dhuhr_iqama = clone $day_date;
                $day_dhuhr_iqama->setTime((int)$hours, (int)$minutes);
                $day_dhuhr_iqama = muslprti_normalize_time_for_dst($day_dhuhr_iqama);
            }
        } else {
            // Daily calculation
            $day_dhuhr_iqama = clone $day_dhuhr_athan;
            if ($dhuhr_rule === 'after_athan') {
                $day_dhuhr_iqama->modify("+{$dhuhr_minutes_after} minutes");
            } else if ($dhuhr_rule === 'fixed_time') {
                $fixed_time = $day_is_dst ? $dhuhr_fixed_dst : $dhuhr_fixed_standard;
                list($hours, $minutes) = explode(':', $fixed_time);
                $day_dhuhr_iqama = clone $day_date;
                $day_dhuhr_iqama->setTime((int)$hours, (int)$minutes);
                $day_dhuhr_iqama = muslprti_normalize_time_for_dst($day_dhuhr_iqama);
            }
        }
        
        // Denormalize the result to account for DST before storing
        // Use the original date for denormalization to maintain correct DST information
        $day_dhuhr_iqama = muslprti_denormalize_time_for_dst($day_dhuhr_iqama);
        
        $results[$day_index] = $day_dhuhr_iqama;
    }
    
    return $results;
}

// Helper function to calculate Asr Iqama times for a collection of days
function muslprti_calculate_asr_iqama($days_data, $asr_rule, $asr_minutes_after, $asr_fixed_standard, $asr_fixed_dst, $is_weekly, $asr_rounding) {
    $results = [];
    
    // Use the new helper function to normalize all times
    $normalized_days_data = muslprti_normalize_times_for_dst($days_data);
    
    // Find latest Athan for weekly calculation
    $latest_asr = null;
    
    if ($is_weekly) {
        foreach ($normalized_days_data as $day_data) {
            if ($latest_asr === null || 
                muslprti_time_to_minutes($day_data['athan']['asr']) > muslprti_time_to_minutes($latest_asr)) {
                $latest_asr = clone $day_data['athan']['asr'];
            }
        }
        
        // Apply rounding to the latest time
        $latest_asr = muslprti_round_up($latest_asr, $asr_rounding);
    }
    
    // Process each day
    foreach ($normalized_days_data as $day_index => $day_data) {
        $day_date = $day_data['date'];
        $day_asr_athan = $day_data['athan']['asr'];
        $day_is_dst = $days_data[$day_index]['date']->format('I') == '1'; // Use original date for DST check
        
        // Determine iqama time based on rule
        if ($is_weekly) {
            // Weekly calculation
            if ($asr_rule === 'after_athan') {
                // Use latest asr time + minutes for all days
                $time_components = explode(':', $latest_asr->format('H:i'));
                $day_asr_iqama = clone $day_date;
                $day_asr_iqama->setTime((int)$time_components[0], (int)$time_components[1]);
                $day_asr_iqama->modify("+{$asr_minutes_after} minutes");
            } else if ($asr_rule === 'fixed_time') {
                $fixed_time = $day_is_dst ? $asr_fixed_dst : $asr_fixed_standard;
                list($hours, $minutes) = explode(':', $fixed_time);
                $day_asr_iqama = clone $day_date;
                $day_asr_iqama->setTime((int)$hours, (int)$minutes);
                $day_asr_iqama = muslprti_normalize_time_for_dst($day_asr_iqama);    
            }
        } else {
            // Daily calculation
            $day_asr_iqama = clone $day_asr_athan;
            if ($asr_rule === 'after_athan') {
                $day_asr_iqama->modify("+{$asr_minutes_after} minutes");
            } else if ($asr_rule === 'fixed_time') {
                $fixed_time = $day_is_dst ? $asr_fixed_dst : $asr_fixed_standard;
                list($hours, $minutes) = explode(':', $fixed_time);
                $day_asr_iqama = clone $day_date;
                $day_asr_iqama->setTime((int)$hours, (int)$minutes);
                $day_asr_iqama = muslprti_normalize_time_for_dst($day_asr_iqama);
            }
        }
        
        // Denormalize the result to account for DST before storing
        // Use the original date for denormalization to maintain correct DST information
        $day_asr_iqama = muslprti_denormalize_time_for_dst($day_asr_iqama);
        
        $results[$day_index] = $day_asr_iqama;
    }
    
    return $results;
}

// Helper function to calculate Maghrib Iqama times for a collection of days
function muslprti_calculate_maghrib_iqama($days_data, $maghrib_minutes_after, $is_weekly, $maghrib_rounding) {
    $results = [];
    
    // Use the new helper function to normalize all times
    $normalized_days_data = muslprti_normalize_times_for_dst($days_data);
    
    // Find latest Athan for weekly calculation
    $latest_maghrib = null;
    
    if ($is_weekly) {
        foreach ($normalized_days_data as $day_data) {
            if ($latest_maghrib === null || 
                muslprti_time_to_minutes($day_data['athan']['maghrib']) > muslprti_time_to_minutes($latest_maghrib)) {
                $latest_maghrib = clone $day_data['athan']['maghrib'];
            }
        }
        
        // Apply rounding to the latest time
        $latest_maghrib = muslprti_round_up($latest_maghrib, $maghrib_rounding);
    }
    
    // Process each day
    foreach ($normalized_days_data as $day_index => $day_data) {
        $day_date = $day_data['date'];
        $day_maghrib_athan = $day_data['athan']['maghrib'];
        
        // Maghrib is always calculated as minutes after Athan
        if ($is_weekly) {
            // Use latest maghrib time + minutes for all days
            $time_components = explode(':', $latest_maghrib->format('H:i'));
            $day_maghrib_iqama = clone $day_date;
            $day_maghrib_iqama->setTime((int)$time_components[0], (int)$time_components[1]);
            $day_maghrib_iqama->modify("+{$maghrib_minutes_after} minutes");
        } else {
            // Daily calculation
            $day_maghrib_iqama = clone $day_maghrib_athan;
            $day_maghrib_iqama->modify("+{$maghrib_minutes_after} minutes");
        }
        
        // Denormalize the result to account for DST before storing
        // Use the original date for denormalization to maintain correct DST information
        $day_maghrib_iqama = muslprti_denormalize_time_for_dst($day_maghrib_iqama);
        
        $results[$day_index] = $day_maghrib_iqama;
    }
    
    return $results;
}

// Helper function to calculate Isha Iqama times for a collection of days
function muslprti_calculate_isha_iqama($days_data, $isha_rule, $isha_minutes_after, $isha_min_time, $isha_max_time, $is_weekly, $isha_rounding) {
    $results = [];
    
    // Use the new helper function to normalize all times
    $normalized_days_data = muslprti_normalize_times_for_dst($days_data);
    
    // Find latest Athan for weekly calculation
    $latest_isha = null;
    
    if ($is_weekly) {
        foreach ($normalized_days_data as $day_data) {
            if ($latest_isha === null || 
                muslprti_time_to_minutes($day_data['athan']['isha']) > muslprti_time_to_minutes($latest_isha)) {
                $latest_isha = clone $day_data['athan']['isha'];
            }
        }
        
        // Apply rounding to the latest time
        $latest_isha = muslprti_round_up($latest_isha, $isha_rounding);
    }
    
    // Process each day
    foreach ($normalized_days_data as $day_index => $day_data) {
        $day_date = $day_data['date'];
        $day_isha_athan = $day_data['athan']['isha'];
        
        // Determine iqama time based on rule
        if ($is_weekly) {
            // Weekly calculation
            if ($isha_rule === 'after_athan') {
                // Use latest isha time + minutes for all days
                $time_components = explode(':', $latest_isha->format('H:i'));
                $day_isha_iqama = clone $day_date;
                $day_isha_iqama->setTime((int)$time_components[0], (int)$time_components[1]);
                $day_isha_iqama->modify("+{$isha_minutes_after} minutes");
            }
        } else {
            // Daily calculation
            $day_isha_iqama = clone $day_isha_athan;
            if ($isha_rule === 'after_athan') {
                $day_isha_iqama->modify("+{$isha_minutes_after} minutes");
            }
        }
        
        // Create min_isha_time and max_isha_time as DateTime objects
        $min_isha_time = clone $day_date;
        list($hours, $minutes) = explode(':', $isha_min_time);
        $min_isha_time->setTime((int)$hours, (int)$minutes);
        $min_isha_time = muslprti_normalize_time_for_dst($min_isha_time);
        
        $max_isha_time = clone $day_date;
        list($hours, $minutes) = explode(':', $isha_max_time);
        $max_isha_time->setTime((int)$hours, (int)$minutes);
        $max_isha_time = muslprti_normalize_time_for_dst($max_isha_time);
        
        // Use the greater of either athan+minutes or min_isha_time
        if (muslprti_time_to_minutes($day_isha_iqama) < muslprti_time_to_minutes($min_isha_time)) {
            $day_isha_iqama = $min_isha_time;
        }
        
        // Apply max time constraint
        if (muslprti_time_to_minutes($day_isha_iqama) > muslprti_time_to_minutes($max_isha_time)) {
            $day_isha_iqama = $max_isha_time;
        }
        
        // Denormalize the result to account for DST before storing
        // Use the original date for denormalization to maintain correct DST information
        $day_isha_iqama = muslprti_denormalize_time_for_dst($day_isha_iqama);
        
        $results[$day_index] = $day_isha_iqama;
    }
    
    return $results;
}

/**
 * Convert Western numerals to Arabic numerals
 * 
 * @param string|int|float $number The number or string containing numbers to convert
 * @return string The input with Western numerals converted to Arabic numerals
 */
function muslprti_convert_to_arabic_numerals($number) {
    // Define mapping of Western to Arabic numerals
    $western_numerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $arabic_numerals  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    
    // Convert the number to string if it's not already
    $number_str = (string) $number;
    
    // Replace Western numerals with Arabic equivalents
    return str_replace($western_numerals, $arabic_numerals, $number_str);
}

/**
 * Get the configured timezone or default to WordPress timezone
 * 
 * @return string The timezone identifier
 */
function muslprti_get_timezone() {
    // Get timezone from options
    $opts = get_option('muslprti_settings', []);
    $tz = isset($opts['tz']) ? $opts['tz'] : null;
    
    // If timezone is not set in options, use WordPress timezone
    if (empty($tz)) {
        $tz = get_option('timezone_string');
        
        // If WordPress timezone is set as an offset instead of a timezone string
        if (empty($tz)) {
            $gmt_offset = get_option('gmt_offset');
            if (!empty($gmt_offset)) {
                // Convert offset to timezone format like "UTC+3" or "UTC-5"
                $prefix = $gmt_offset >= 0 ? '+' : '';
                $tz = 'UTC' . $prefix . (string)$gmt_offset;
            } else {
                // Fallback to UTC if no timezone is set
                $tz = 'UTC';
            }
        }
    }
    
    return $tz;
}
