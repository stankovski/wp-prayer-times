<?php
/**
 * Hijri Date Converter - Compatibility wrapper for salah-api HijriDateConverter
 * 
 * This file provides backward compatibility by wrapping the salah-api
 * HijriDateConverter class with the expected function interface.
 */

if (!defined('ABSPATH')) exit;

// Load the salah-api HijriDateConverter class
require_once __DIR__ . '/salah-api/Calculations/HijriDateConverter.php';

use SalahAPI\Calculations\HijriDateConverter;

/**
 * Convert Gregorian date to Hijri date
 * 
 * @param string|DateTime $date Gregorian date (Y-m-d format or DateTime object)
 * @param bool $long_format Whether to return long format (default: true)
 * @param string $language Language for month names ('en' or 'ar', default: 'en')
 * @param int $offset Day offset to apply to Hijri date (default: 0)
 * @return array|string Array with 'day', 'month', 'year', 'month_name' or formatted string if long_format is true
 */
function muslprti_convert_to_hijri($date, $long_format = true, $language = 'en', $offset = 0) {
    // Convert string to DateTime if needed
    if (is_string($date)) {
        $date_obj = new DateTime($date);
    } else {
        $date_obj = $date;
    }
    
    // Get Hijri date using the salah-api library
    $hijri = HijriDateConverter::convertToHijri($date_obj, $offset);
    
    // Get month names
    $month_names_en = [
        1 => 'Muḥarram',
        2 => 'Ṣafar',
        3 => 'Rabīʿ al-awwal',
        4 => 'Rabīʿ al-thānī',
        5 => 'Jumādá al-ūlá',
        6 => 'Jumādá al-ākhirah',
        7 => 'Rajab',
        8 => 'Shaʿbān',
        9 => 'Ramaḍān',
        10 => 'Shawwāl',
        11 => 'Dhū al-Qaʿdah',
        12 => 'Dhū al-Ḥijjah'
    ];
    
    $month_names_ar = [
        1 => 'مُحَرَّم',
        2 => 'صَفَر',
        3 => 'رَبيع الأوّل',
        4 => 'رَبيع الثاني',
        5 => 'جُمادى الأولى',
        6 => 'جُمادى الآخرة',
        7 => 'رَجَب',
        8 => 'شَعْبان',
        9 => 'رَمَضان',
        10 => 'شَوّال',
        11 => 'ذوالقعدة',
        12 => 'ذوالحجة'
    ];
    
    $month_names = ($language === 'ar') ? $month_names_ar : $month_names_en;
    $month_name = $month_names[$hijri['month']] ?? '';
    
    // Create result array
    $result = [
        'day' => $hijri['day'],
        'month' => $hijri['month'],
        'year' => $hijri['year'],
        'month_name' => $month_name
    ];
    
    // Return formatted string if long_format is true
    if ($long_format) {
        return sprintf('%d %s %dH', $hijri['day'], $month_name, $hijri['year']);
    }
    
    return $result;
}
