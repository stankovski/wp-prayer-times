<?php
/**
 * Hijri Date Converter
 * 
 * Provides functions to convert Gregorian dates to Hijri (Islamic) dates
 */

if (!defined('ABSPATH')) exit;

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
    // If string date provided, convert to DateTime
    if (is_string($date)) {
        $date = new DateTime($date, new DateTimeZone(prayertimes_get_timezone()));
    }
    
    // Apply offset to the Gregorian date before conversion
    if ($offset != 0) {
        $offset_date = clone $date;
        $offset_date->modify($offset . ' days');
        $date = $offset_date;
    }
    
    // Julian Date calculation
    $gregorian_day = $date->format('j');
    $gregorian_month = $date->format('n');
    $gregorian_year = $date->format('Y');
    
    if ($gregorian_month < 3) {
        $gregorian_year -= 1;
        $gregorian_month += 12;
    }
    
    $a = floor($gregorian_year / 100);
    $b = 2 - $a + floor($a / 4);
    
    if ($gregorian_year < 1583) {
        $b = 0;
    }
    if ($gregorian_year === 1582) {
        if ($gregorian_month > 10) {
            $b = -10;
        }
        if ($gregorian_month === 10) {
            if ($gregorian_day > 4) {
                $b = -10;
            } else {
                $b = 0;
            }
        }
    }
    
    $jd = floor(365.25 * ($gregorian_year + 4716)) + floor(30.6001 * ($gregorian_month + 1)) + $gregorian_day + $b - 1524.5;
    
    $b = 0;
    if ($jd > 2299160) {
        $a = floor(($jd - 1867216.25) / 36524.25);
        $b = 1 + $a - floor($a / 4);
    }
    
    $bb = $jd + $b + 1524;
    $cc = floor(($bb - 122.1) / 365.25);
    $dd = floor(365.25 * $cc);
    $ee = floor(($bb - $dd) / 30.6001);
    
    // Islamic calendar calculation
    $days = $jd - 1948084;
    $hijri_year = 10631.0 / 30.0;
    $shift1 = 8.01 / 60.0;
    
    $z = $days + $shift1;
    $cyc = floor($z / 10631.0);
    $z = $z - 10631 * $cyc;
    $j = floor(($z - $shift1) / $hijri_year);
    $z = $z - floor($j * $hijri_year + $shift1);
    
    $hijri_year = 30 * $cyc + $j;
    $hijri_month = floor(($z + 28.5001) / 29.5);
    if ($hijri_month === 13) {
        $hijri_month = 12;
    }
    $hijri_day = (int)(ceil($z - floor(29.5001 * $hijri_month - 29)));
    
    // Get Hijri month names using the new function
    $hijri_months = prayertimes_get_hijri_months($language);
    
    if ($formatted) {
        return sprintf('%d %s %dH', 
            $hijri_day, 
            $hijri_months[$hijri_month], 
            $hijri_year
        );
    } else {
        return array(
            'day' => $hijri_day,
            'month' => $hijri_month,
            'month_name' => $hijri_months[$hijri_month],
            'year' => $hijri_year
        );
    }
}
