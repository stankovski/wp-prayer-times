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
    
    $h = $hjcosa->gToH($formatted_date, $offset);

    // Get Hijri month names using the new function
    $month_name = $h->month->en;
    if ($language === 'ar') {
        $month_name = $h->month->ar;
    }
    
    if ($formatted) {
        return sprintf('%d %s %dH',
            $h->day->number,
            $month_name,
            $h->year
        );
    } else {
        return array(
            'day' => $h->day->number,
            'month' => $h->month->number,
            'month_name' => $month_name,
            'year' => $h->year
        );
    }
}
