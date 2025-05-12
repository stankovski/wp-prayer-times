<?php
/**
 * Hijri Date Converter
 * 
 * Provides functions to convert Gregorian dates to Hijri (Islamic) dates
 */

if (!defined('ABSPATH')) exit;

// Include the autoloader for Islamic Network libraries
require_once __DIR__ . '/islamic-network/autoload.php';

use IslamicNetwork\Calendar\Models\Astronomical\HighJudiciaryCouncilOfSaudiArabia;

$hjcosa = new HighJudiciaryCouncilOfSaudiArabia();

/**
 * Get Hijri month names based on language
 * 
 * @param string $language Language code ('en' or 'ar')
 * @return array Array of month names indexed by month number
 */
function prayertimes_get_hijri_months($language = 'en') {
    if ($language === 'ar') {
        return array(
            1 => 'محرم',
            2 => 'صفر',
            3 => 'ربيع الأول',
            4 => 'ربيع الثاني',
            5 => 'جمادى الأولى',
            6 => 'جمادى الآخرة',
            7 => 'رجب',
            8 => 'شعبان',
            9 => 'رمضان',
            10 => 'شوال',
            11 => 'ذو القعدة',
            12 => 'ذو الحجة'
        );
    } else {
        return array(
            1 => 'Muharram',
            2 => 'Safar',
            3 => 'Rabi al-Awwal',
            4 => 'Rabi al-Thani',
            5 => 'Jumada al-Awwal',
            6 => 'Jumada al-Thani',
            7 => 'Rajab',
            8 => 'Sha\'ban',
            9 => 'Ramadan',
            10 => 'Shawwal',
            11 => 'Dhu al-Qi\'dah',
            12 => 'Dhu al-Hijjah'
        );
    }
}

/**
 * Convert Gregorian date to Hijri date
 * 
 * @param string|DateTime $date Gregorian date (YYYY-MM-DD) or DateTime object
 * @param bool $formatted Whether to return a formatted string or array of date components
 * @param string $language Language for month names ('en' or 'ar')
 * @param int $offset Day offset to add/subtract from calculated Hijri date (-2 to +2)
 * @return string|array Formatted Hijri date string or array with year, month, day
 */
function prayertimes_convert_to_hijri($date, $formatted = true, $language = 'en', $offset = 0) {
    global $hjcosa;
    
    // If the global variable is still null, create it within this function
    if ($hjcosa === null) {
        $hjcosa = new HighJudiciaryCouncilOfSaudiArabia();
    }
    
    // If string date provided, convert to DateTime
    if (is_string($date)) {
        $date = new DateTime($date, new DateTimeZone(prayertimes_get_timezone()));
    }
    
    // Format the date in the required format for gToH (dd-mm-YYYY)
    $formatted_date = $date->format('d-m-Y');
    
    $h = $hjcosa->gToH($formatted_date);

    // Get Hijri month names using the new function
    $hijri_months = prayertimes_get_hijri_months($language);
    
    if ($formatted) {
        return sprintf('%d %s %dH',
            $h->day->number,
            $hijri_months[$h->month->number],
            $h->year
        );
    } else {
        return array(
            'day' => $h->day->number,
            'month' => $h->month->number,
            'month_name' => $hijri_months[$h->month->number],
            'year' => $h->year
        );
    }
}
