<?php
/**
 * PrayerTimes2.php: Prayer Times Calculator (based on times.js v3.2)
 * Copyright (c) 2007-2025 Hamid Zarrabi-Zadeh
 * Ported to PHP from JavaScript implementation
 * License: MIT
 */

namespace IslamicNetwork\PrayerTimes;

use DateTime;
use DateTimezone;

/**
 * Class PrayerTimes2
 * @package IslamicNetwork\PrayerTimes
 */
class PrayerTimes2
{
    /**
     * Constants for all items the times are computed for
     */
    const IMSAK = 'Imsak';
    const FAJR = 'Fajr';
    const SUNRISE = 'Sunrise';
    const ZHUHR = 'Dhuhr';
    const ASR = 'Asr';
    const SUNSET = 'Sunset';
    const MAGHRIB = 'Maghrib';
    const ISHA = 'Isha';
    const MIDNIGHT = 'Midnight';
    const FIRST_THIRD = 'Firstthird';
    const LAST_THIRD = 'Lastthird';

    /**
     * Schools that determine the Asr shadow for the purpose of this class
     */
    const SCHOOL_STANDARD = 'STANDARD'; //0
    const SCHOOL_HANAFI = 'HANAFI'; // 1

    /**
     * Midnight Mode - how the midnight time is determined
     */
    const MIDNIGHT_MODE_STANDARD = 'STANDARD'; // Mid Sunset to Sunrise
    const MIDNIGHT_MODE_JAFARI = 'JAFARI'; // Mid Sunset to Fajr

    /**
     * Higher Latitude Adjustment Methods
     */
    const LATITUDE_ADJUSTMENT_METHOD_MOTN = 'MIDDLE_OF_THE_NIGHT'; // 1
    const LATITUDE_ADJUSTMENT_METHOD_ANGLE = 'ANGLE_BASED'; // 3, angle/60th of night
    const LATITUDE_ADJUSTMENT_METHOD_ONESEVENTH = 'ONE_SEVENTH'; // 2
    const LATITUDE_ADJUSTMENT_METHOD_NONE = 'NONE'; // 0

    /**
     * Formats in which data can be output
     */
    const TIME_FORMAT_24H = '24h'; // 24-hour format
    const TIME_FORMAT_12H = '12h'; // 12-hour format
    const TIME_FORMAT_12hNS = '12hNS'; // 12-hour format with no suffix
    const TIME_FORMAT_FLOAT = 'Float'; // floating point number
    const TIME_FORMAT_ISO8601 = 'iso8601';

    /**
     * High Latitude Adjustment Methods from times.js
     */
    const HIGH_LATS_NONE = 'None';
    const HIGH_LATS_NIGHT_MIDDLE = 'NightMiddle';
    const HIGH_LATS_ONE_SEVENTH = 'OneSeventh';
    const HIGH_LATS_ANGLE_BASED = 'AngleBased';

    /**
     * If we're unable to calculate a time, we'll return this
     */
    const INVALID_TIME = '-----';

    /**
     * Calculation methods configuration (from times.js)
     */
    private $methods = [
        'MWL' => ['fajr' => 18, 'isha' => 17],
        'ISNA' => ['fajr' => 15, 'isha' => 15],
        'Egypt' => ['fajr' => 19.5, 'isha' => 17.5],
        'Makkah' => ['fajr' => 18.5, 'isha' => '90 min'],
        'Karachi' => ['fajr' => 18, 'isha' => 18],
        'Tehran' => ['fajr' => 17.7, 'maghrib' => 4.5, 'midnight' => 'Jafari'],
        'Jafari' => ['fajr' => 16, 'maghrib' => 4, 'midnight' => 'Jafari'],
        'France' => ['fajr' => 12, 'isha' => 12],
        'Russia' => ['fajr' => 16, 'isha' => 15],
        'Singapore' => ['fajr' => 20, 'isha' => 18],
        'defaults' => ['isha' => 14, 'maghrib' => '1 min', 'midnight' => 'Standard']
    ];

    /**
     * Settings configuration (from times.js)
     */
    private $settings = [
        'dhuhr' => '0 min',
        'asr' => 'Standard',
        'highLats' => 'NightMiddle',
        'tune' => [],
        'format' => '24h',
        'rounding' => 'nearest',
        'utcOffset' => 'auto',
        'timezone' => null,
        'location' => [0, 0],
        'iterations' => 1
    ];

    /**
     * Prayer time labels
     */
    private $labels = [
        'Fajr', 'Sunrise', 'Dhuhr', 'Asr',
        'Sunset', 'Maghrib', 'Isha', 'Midnight'
    ];

    // Internal calculation variables
    private $utcTime;
    private $adjusted = false;

    // Compatibility properties with original PrayerTimes.php
    private $date;
    private $method;
    private $school = self::SCHOOL_STANDARD;
    private $midnightMode;
    private $latitudeAdjustmentMethod;
    private $timeFormat;
    private $latitude;
    private $longitude;
    private $elevation;
    private $asrShadowFactor = null;
    private $shafaq = 'general'; // Only valid for METHOD_MOONSIGHTING
    private $offset = [];

    /**
     * Constructor - matches PrayerTimes.php API
     * @param string $method
     * @param string $school
     * @param null $asrShadowFactor
     */
    public function __construct($method = Method::METHOD_MWL, $school = self::SCHOOL_STANDARD, $asrShadowFactor = null)
    {
        $this->setMethod($method);
        $this->setSchool($school);
        if ($asrShadowFactor !== null) {
            $this->asrShadowFactor = $asrShadowFactor;
        }
        $this->settings['timezone'] = date_default_timezone_get();
        $this->settings['location'][1] = -(new DateTime())->getOffset() / 240; // Convert seconds to hours/4
    }

    // Public API methods (matching PrayerTimes.php interface)
    // These will be implemented in the next tasks

    /**
     * Get prayer times for today
     * @param $latitude
     * @param $longitude
     * @param $timezone
     * @param null $elevation
     * @param string $latitudeAdjustmentMethod
     * @param null $midnightMode
     * @param string $format
     * @return array
     * @throws \Exception
     */
    public function getTimesForToday($latitude, $longitude, $timezone, $elevation = null, $latitudeAdjustmentMethod = self::LATITUDE_ADJUSTMENT_METHOD_ANGLE, $midnightMode = null, $format = self::TIME_FORMAT_24H)
    {
        $date = new DateTime('', new DateTimezone($timezone));
        return $this->getTimes($date, $latitude, $longitude, $elevation, $latitudeAdjustmentMethod, $midnightMode, $format);
    }

    /**
     * Get prayer times for specific date
     * @param DateTime $date
     * @param $latitude
     * @param $longitude
     * @param $elevation
     * @param string $latitudeAdjustmentMethod
     * @param string $midnightMode
     * @param string $format
     * @return array
     */
    public function getTimes(DateTime $date, $latitude, $longitude, $elevation = null, $latitudeAdjustmentMethod = self::LATITUDE_ADJUSTMENT_METHOD_ANGLE, $midnightMode = null, $format = self::TIME_FORMAT_24H)
    {
        // Store parameters
        $this->date = $date;
        $this->latitude = 1 * $latitude;
        $this->longitude = 1 * $longitude;
        $this->elevation = $elevation === null ? 0 : 1 * $elevation;
        $this->settings['location'] = [$this->latitude, $this->longitude];
        
        $this->setTimeFormat($format);
        $this->setLatitudeAdjustmentMethod($latitudeAdjustmentMethod);
        if ($midnightMode !== null) {
            $this->setMidnightMode($midnightMode);
        }

        // Set UTC time for calculations
        $this->utcTime = $date->getTimestamp() * 1000; // Convert to milliseconds like JavaScript

        $times = $this->computeTimes();
        $this->formatTimes($times);
        return $this->convertTimesToPrayerTimesFormat($times);
    }

    // Setter methods (matching PrayerTimes.php API)
    
    /**
     * Set calculation method
     * @param string $method
     */
    public function setMethod($method = Method::METHOD_MWL)
    {
        // Map Method constants to times.js method names
        $methodMapping = [
            Method::METHOD_MWL => 'MWL',
            Method::METHOD_ISNA => 'ISNA',
            Method::METHOD_EGYPT => 'Egypt',
            Method::METHOD_MAKKAH => 'Makkah',
            Method::METHOD_KARACHI => 'Karachi',
            Method::METHOD_TEHRAN => 'Tehran',
            Method::METHOD_JAFARI => 'Jafari',
            Method::METHOD_FRANCE => 'France',
            Method::METHOD_RUSSIA => 'Russia',
            Method::METHOD_SINGAPORE => 'Singapore'
        ];
        
        $jsMethod = $methodMapping[$method] ?? 'MWL';
        $this->method = $method;
        
        // Apply method settings
        if (isset($this->methods[$jsMethod])) {
            $this->settings = array_merge($this->settings, $this->methods[$jsMethod]);
        }
        
        // Apply defaults
        $this->settings = array_merge($this->settings, $this->methods['defaults']);
    }
    
    /**
     * Set school for Asr calculation
     * @param string $school
     */
    public function setSchool($school = self::SCHOOL_STANDARD)
    {
        $this->school = $school;
        $this->settings['asr'] = ($school == self::SCHOOL_HANAFI) ? 'Hanafi' : 'Standard';
    }
    
    /**
     * Set midnight calculation mode
     * @param string $mode
     */
    public function setMidnightMode($mode = self::MIDNIGHT_MODE_STANDARD)
    {
        $this->midnightMode = $mode;
        $this->settings['midnight'] = ($mode == self::MIDNIGHT_MODE_JAFARI) ? 'Jafari' : 'Standard';
    }
    
    /**
     * Set latitude adjustment method for high latitudes
     * @param string $method
     */
    public function setLatitudeAdjustmentMethod($method = self::LATITUDE_ADJUSTMENT_METHOD_ANGLE)
    {
        $this->latitudeAdjustmentMethod = $method;
        
        // Map to times.js naming
        $mapping = [
            self::LATITUDE_ADJUSTMENT_METHOD_NONE => 'None',
            self::LATITUDE_ADJUSTMENT_METHOD_MOTN => 'NightMiddle',
            self::LATITUDE_ADJUSTMENT_METHOD_ONESEVENTH => 'OneSeventh',
            self::LATITUDE_ADJUSTMENT_METHOD_ANGLE => 'AngleBased'
        ];
        
        $this->settings['highLats'] = $mapping[$method] ?? 'AngleBased';
    }
    
    /**
     * Set time format
     * @param string $format
     */
    public function setTimeFormat($format = self::TIME_FORMAT_24H)
    {
        $this->timeFormat = $format;
        
        // Map to times.js format
        $formatMapping = [
            self::TIME_FORMAT_24H => '24h',
            self::TIME_FORMAT_12H => '12h',
            self::TIME_FORMAT_12hNS => '12H',
            self::TIME_FORMAT_FLOAT => 'Float',
            self::TIME_FORMAT_ISO8601 => 'iso8601'
        ];
        
        $this->settings['format'] = $formatMapping[$format] ?? '24h';
    }
    
    /**
     * Set shafaq for moonsighting method
     * @param string $shafaq
     */
    public function setShafaq(string $shafaq)
    {
        $this->shafaq = $shafaq;
    }
    
    /**
     * Set custom calculation method
     * @param Method $method
     */
    public function setCustomMethod(Method $method)
    {
        $this->method = Method::METHOD_CUSTOM;
        $methodVars = get_object_vars($method);
        
        // Convert custom method to times.js format
        if (isset($methodVars['params'])) {
            $params = $methodVars['params'];
            $customMethod = [];
            
            if (isset($params[self::FAJR])) $customMethod['fajr'] = $params[self::FAJR];
            if (isset($params[self::ISHA])) $customMethod['isha'] = $params[self::ISHA];
            if (isset($params[self::MAGHRIB])) $customMethod['maghrib'] = $params[self::MAGHRIB];
            if (isset($params[self::MIDNIGHT])) $customMethod['midnight'] = $params[self::MIDNIGHT];
            
            $this->methods['CUSTOM'] = $customMethod;
            $this->settings = array_merge($this->settings, $customMethod);
        }
    }
    
    /**
     * Tune prayer times with minute offsets
     * @param int $imsak
     * @param int $fajr
     * @param int $sunrise
     * @param int $dhuhr
     * @param int $asr
     * @param int $maghrib
     * @param int $sunset
     * @param int $isha
     * @param int $midnight
     */
    public function tune($imsak = 0, $fajr = 0, $sunrise = 0, $dhuhr = 0, $asr = 0, $maghrib = 0, $sunset = 0, $isha = 0, $midnight = 0)
    {
        $this->offset = [
            self::IMSAK => $imsak,
            self::FAJR => $fajr,
            self::SUNRISE => $sunrise,
            self::ZHUHR => $dhuhr,
            self::ASR => $asr,
            self::MAGHRIB => $maghrib,
            self::SUNSET => $sunset,
            self::ISHA => $isha,
            self::MIDNIGHT => $midnight
        ];
        
        // Convert to times.js format
        $this->settings['tune'] = [
            'fajr' => $fajr,
            'sunrise' => $sunrise,
            'dhuhr' => $dhuhr,
            'asr' => $asr,
            'sunset' => $sunset,
            'maghrib' => $maghrib,
            'isha' => $isha,
            'midnight' => $midnight
        ];
    }

    // Getter methods (matching PrayerTimes.php API)
    
    /**
     * Get all available calculation methods
     * @return array
     */
    public function getMethods()
    {
        return Method::getMethods();
    }
    
    /**
     * Get current calculation method
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }
    
    /**
     * Get metadata about current configuration
     * @return array
     */
    public function getMeta(): array
    {
        $result = [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->settings['timezone'] ?? date_default_timezone_get(),
            'method' => $this->getMethods()[$this->method] ?? [],
            'latitudeAdjustmentMethod' => $this->latitudeAdjustmentMethod,
            'midnightMode' => $this->midnightMode,
            'school' => $this->school,
            'offset' => $this->offset,
        ];
        
        if (isset($result['method']['offset'])) {
            unset($result['method']['offset']);
        }
        
        if ($this->method == Method::METHOD_MOONSIGHTING) {
            $result['latitudeAdjustmentMethod'] = self::LATITUDE_ADJUSTMENT_METHOD_NONE;
            $result['method']['params']['shafaq'] = $this->shafaq;
        }
        
        return $result;
    }
    
    /**
     * Handle moonsighting method recalculation (compatibility method)
     * @param array $times
     * @return array
     */
    public function moonsightingRecalculation(array $times): array
    {
        if ($this->method == Method::METHOD_MOONSIGHTING) {
            // For moonsighting method, we would need the MoonSighting library
            // This is a placeholder implementation for compatibility
            // In a real implementation, you would use:
            // $fajrMS = new Fajr($this->date, $this->latitude);
            // $ishaMS = new Isha($this->date, $this->latitude, $this->shafaq);
            
            // For now, just return the times unchanged
            return $times;
        }
        
        return $times;
    }
    
    /**
     * Load calculation methods (compatibility method)
     */
    public function loadMethods()
    {
        // This is handled in the constructor for times.js compatibility
        // Method data is already loaded in $this->methods
    }

    // Core calculation methods (ported from times.js)
    
    /**
     * Main computation method - computes prayer times
     * @return array
     */
    private function computeTimes()
    {
        // Default times (like times.js)
        $times = [
            'fajr' => 5,
            'sunrise' => 6,
            'dhuhr' => 12,
            'asr' => 13,
            'sunset' => 18,
            'maghrib' => 18,
            'isha' => 18,
            'midnight' => 24
        ];

        // Process times for the specified number of iterations
        for ($i = 0; $i < $this->settings['iterations']; $i++) {
            $times = $this->processTimes($times);
        }

        $this->adjustHighLats($times);
        $this->updateTimes($times);
        $this->tuneTimes($times);
        $this->convertTimes($times);
        
        return $times;
    }

    /**
     * Process prayer times using angle calculations
     * @param array $times
     * @return array
     */
    private function processTimes($times)
    {
        $params = $this->settings;
        $horizon = 0.833;

        return [
            'fajr' => $this->angleTime($params['fajr'] ?? 18, $times['fajr'], -1),
            'sunrise' => $this->angleTime($horizon, $times['sunrise'], -1),
            'dhuhr' => $this->midDay($times['dhuhr']),
            'asr' => $this->angleTime($this->asrAngle($params['asr'] ?? 'Standard', $times['asr']), $times['asr']),
            'sunset' => $this->angleTime($horizon, $times['sunset']),
            'maghrib' => $this->angleTime($params['maghrib'] ?? 0, $times['maghrib']),
            'isha' => $this->angleTime($params['isha'] ?? 18, $times['isha']),
            'midnight' => $this->midDay($times['midnight']) + 12
        ];
    }

    /**
     * Update times with minute-based adjustments and midnight mode
     * @param array $times
     */
    private function updateTimes(&$times)
    {
        $params = $this->settings;

        // Apply minute-based adjustments for Maghrib and Isha
        if ($this->isMin($params['maghrib'] ?? '0 min')) {
            $times['maghrib'] = $times['sunset'] + $this->value($params['maghrib'] ?? '0 min') / 60;
        }
        if ($this->isMin($params['isha'] ?? '0 min')) {
            $times['isha'] = $times['maghrib'] + $this->value($params['isha'] ?? '0 min') / 60;
        }
        
        // Handle Jafari midnight mode
        if (($params['midnight'] ?? 'Standard') == 'Jafari') {
            $nextFajr = $this->angleTime($params['fajr'] ?? 18, 29, -1) + 24;
            $times['midnight'] = ($times['sunset'] + ($this->adjusted ? $times['fajr'] + 24 : $nextFajr)) / 2;
        }
        
        // Apply Dhuhr adjustment
        $times['dhuhr'] += $this->value($params['dhuhr'] ?? '0 min') / 60;
    }

    /**
     * Apply user-defined time adjustments
     * @param array $times
     */
    private function tuneTimes(&$times)
    {
        $mins = $this->settings['tune'];
        foreach ($times as $i => $time) {
            if (isset($mins[$i])) {
                $times[$i] += $mins[$i] / 60;
            }
        }
    }

    /**
     * Convert times to proper timezone and longitude adjustment
     * @param array $times
     */
    private function convertTimes(&$times)
    {
        $lng = $this->settings['location'][1];
        foreach ($times as $i => $time) {
            $adjustedTime = $time - $lng / 15;
            $timestamp = $this->utcTime + ($adjustedTime * 3600000); // Convert hours to milliseconds
            $times[$i] = $this->roundTime($timestamp);
        }
    }
    
    /**
     * Adjust times for higher latitudes
     * @param array $times
     */
    private function adjustHighLats(&$times)
    {
        $params = $this->settings;
        if ($params['highLats'] == 'None') {
            return;
        }

        $this->adjusted = false;
        $night = 24 + $times['sunrise'] - $times['sunset'];

        $times['fajr'] = $this->adjustTime($times['fajr'], $times['sunrise'], $params['fajr'], $night, -1);
        $times['isha'] = $this->adjustTime($times['isha'], $times['sunset'], $params['isha'], $night);
        $times['maghrib'] = $this->adjustTime($times['maghrib'], $times['sunset'], $params['maghrib'], $night);
    }

    /**
     * Adjust individual time for higher latitudes
     * @param float $time
     * @param float $base
     * @param mixed $angle
     * @param float $night
     * @param int $direction
     * @return float
     */
    private function adjustTime($time, $base, $angle, $night, $direction = 1)
    {
        $factors = [
            'NightMiddle' => 1 / 2,
            'OneSeventh' => 1 / 7,
            'AngleBased' => 1 / 60 * $this->value($angle)
        ];
        
        $portion = ($factors[$this->settings['highLats']] ?? 0.5) * $night;
        $timeDiff = ($time - $base) * $direction;
        
        if (is_nan($time) || $timeDiff > $portion) {
            $time = $base + $portion * $direction;
            $this->adjusted = true;
        }
        
        return $time;
    }
    
    // Astronomical calculation methods (ported from times.js)
    
    /**
     * Compute sun position (declination and equation of time)
     * @param float $time
     * @return object
     */
    private function sunPosition($time)
    {
        $lng = $this->settings['location'][1];
        $D = $this->utcTime / 86400000 - 10957.5 + $this->value($time) / 24 - $lng / 360;

        $g = $this->mod(357.529 + 0.98560028 * $D, 360);
        $q = $this->mod(280.459 + 0.98564736 * $D, 360);
        $L = $this->mod($q + 1.915 * $this->sin($g) + 0.020 * $this->sin(2 * $g), 360);
        $e = 23.439 - 0.00000036 * $D;
        $RA = $this->mod($this->arctan2($this->cos($e) * $this->sin($L), $this->cos($L)) / 15, 24);

        $result = new \stdClass();
        $result->declination = $this->arcsin($this->sin($e) * $this->sin($L));
        $result->equation = $q / 15 - $RA;
        
        return $result;
    }

    /**
     * Compute mid-day time
     * @param float $time
     * @return float
     */
    private function midDay($time)
    {
        $eqt = $this->sunPosition($time)->equation;
        $noon = $this->mod(12 - $eqt, 24);
        return $noon;
    }

    /**
     * Compute the time when sun reaches a specific angle below horizon
     * @param float $angle
     * @param float $time
     * @param int $direction 1 for sunset/isha, -1 for fajr/sunrise
     * @return float
     */
    private function angleTime($angle, $time, $direction = 1)
    {
        $lat = $this->settings['location'][0];
        $decl = $this->sunPosition($time)->declination;
        $numerator = -$this->sin($angle) - $this->sin($lat) * $this->sin($decl);
        $denominator = $this->cos($lat) * $this->cos($decl);
        
        if (abs($numerator / $denominator) > 1) {
            return NAN; // Sun never reaches this angle at this location/date
        }
        
        $diff = $this->arccos($numerator / $denominator) / 15;
        return $this->midDay($time) + $diff * $direction;
    }

    /**
     * Compute asr angle
     * @param string|float $asrParam
     * @param float $time
     * @return float
     */
    private function asrAngle($asrParam, $time)
    {
        $shadowFactors = ['Standard' => 1, 'Hanafi' => 2];
        $shadowFactor = isset($shadowFactors[$asrParam]) ? $shadowFactors[$asrParam] : $this->value($asrParam);
        
        $lat = $this->settings['location'][0];
        $decl = $this->sunPosition($time)->declination;
        return -$this->arccot($shadowFactor + $this->tan(abs($lat - $decl)));
    }
    
    // Utility methods (ported from times.js)
    
    /**
     * Convert string to number
     * @param string $str
     * @return float
     */
    private function value($str)
    {
        preg_match('/[0-9.+-]*/', (string)$str, $matches);
        return (float)($matches[0] ?? 0);
    }

    /**
     * Detect if input contains 'min'
     * @param string $str
     * @return bool
     */
    private function isMin($str)
    {
        return strpos((string)$str, 'min') !== false;
    }

    /**
     * Positive modulo
     * @param float $a
     * @param float $b
     * @return float
     */
    private function mod($a, $b)
    {
        return (($a % $b) + $b) % $b;
    }

    /**
     * Round time timestamp
     * @param float $timestamp
     * @return float
     */
    private function roundTime($timestamp)
    {
        $roundingMethods = [
            'up' => 'ceil',
            'down' => 'floor',
            'nearest' => 'round'
        ];
        
        $rounding = $roundingMethods[$this->settings['rounding']] ?? null;
        if (!$rounding) {
            return $timestamp;
        }
        
        $oneMinute = 60000; // milliseconds
        return (int)($rounding($timestamp / $oneMinute) * $oneMinute);
    }
    
    // Utility methods
    
    /**
     * Format all times
     * @param array $times
     */
    private function formatTimes(&$times)
    {
        foreach ($times as $i => $time) {
            $times[$i] = $this->formatTime($time);
        }
    }

    /**
     * Format individual time
     * @param float $timestamp
     * @return string|float
     */
    private function formatTime($timestamp)
    {
        $format = $this->settings['format'];
        $invalidTime = self::INVALID_TIME;
        
        if (is_nan($timestamp)) {
            return $invalidTime;
        }
        
        if (is_callable($format)) {
            return $format($timestamp);
        }
        
        if (strtolower($format) == 'x') {
            return floor($timestamp / (($format == 'X') ? 1000 : 1));
        }
        
        return $this->timeToString($timestamp, $format);
    }

    /**
     * Convert timestamp to string
     * @param float $timestamp
     * @param string $format
     * @return string
     */
    private function timeToString($timestamp, $format)
    {
        $utcOffset = $this->settings['utcOffset'];
        
        // Convert timestamp from milliseconds to seconds for PHP
        $timestampSeconds = $timestamp / 1000;
        
        // Apply UTC offset if not auto
        if ($utcOffset !== 'auto') {
            $timestampSeconds += $utcOffset * 60; // utcOffset is in minutes
        }
        
        // Create DateTime object
        $date = new DateTime();
        $date->setTimestamp($timestampSeconds);
        
        // Set timezone
        if (isset($this->settings['timezone'])) {
            try {
                $date->setTimezone(new DateTimezone($this->settings['timezone']));
            } catch (\Exception $e) {
                // Fallback to UTC if timezone is invalid
                $date->setTimezone(new DateTimezone('UTC'));
            }
        }
        
        // Format based on requested format
        if ($format == '24h') {
            return $date->format('H:i');
        } elseif ($format == '12h') {
            return $date->format('g:i A');
        } elseif ($format == '12H') {
            return $date->format('g:i'); // 12-hour without AM/PM
        } else {
            return $date->format('H:i'); // Default to 24h
        }
    }

    /**
     * Convert times to the format expected by PrayerTimes.php API
     * @param array $times
     * @return array
     */
    private function convertTimesToPrayerTimesFormat($times)
    {
        // Map JavaScript naming to PrayerTimes.php constants
        $mapping = [
            'fajr' => self::FAJR,
            'sunrise' => self::SUNRISE,
            'dhuhr' => self::ZHUHR,
            'asr' => self::ASR,
            'sunset' => self::SUNSET,
            'maghrib' => self::MAGHRIB,
            'isha' => self::ISHA,
            'midnight' => self::MIDNIGHT
        ];
        
        $result = [];
        foreach ($mapping as $jsKey => $phpKey) {
            if (isset($times[$jsKey])) {
                $result[$phpKey] = $times[$jsKey];
            }
        }
        
        // Add additional times that PrayerTimes.php provides
        if (isset($result[self::SUNSET]) && isset($result[self::FAJR])) {
            // Calculate first third and last third of night
            $nightLength = 24 + $result[self::SUNRISE] - $result[self::SUNSET];
            $result[self::FIRST_THIRD] = $this->formatTime($this->convertTimestamp($result[self::SUNSET]) + ($nightLength / 3) * 3600000);
            $result[self::LAST_THIRD] = $this->formatTime($this->convertTimestamp($result[self::SUNSET]) + (2 * $nightLength / 3) * 3600000);
        }
        
        // Add Imsak if not present (10 minutes before Fajr by default)
        if (!isset($result[self::IMSAK]) && isset($result[self::FAJR])) {
            $fajrTimestamp = $this->convertTimestamp($result[self::FAJR]);
            $result[self::IMSAK] = $this->formatTime($fajrTimestamp - 10 * 60000); // 10 minutes before
        }
        
        return $result;
    }
    
    /**
     * Helper method to convert formatted time back to timestamp for calculations
     * @param mixed $time
     * @return float
     */
    private function convertTimestamp($time)
    {
        if (is_numeric($time)) {
            return $time;
        }
        
        // If it's a formatted string, we need to parse it back
        // This is a simplified approach - in a real implementation you might want more robust parsing
        if (is_string($time) && preg_match('/(\d{1,2}):(\d{2})/', $time, $matches)) {
            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
            
            // Convert to milliseconds from start of day
            return ($hours * 3600 + $minutes * 60) * 1000;
        }
        
        return 0;
    }
    
    // Trigonometry methods (degree-based)
    private function dtr($d) { return $d * M_PI / 180; }
    private function rtd($r) { return $r * 180 / M_PI; }
    private function sin($d) { return sin($this->dtr($d)); }
    private function cos($d) { return cos($this->dtr($d)); }
    private function tan($d) { return tan($this->dtr($d)); }
    private function arcsin($d) { return $this->rtd(asin($d)); }
    private function arccos($d) { return $this->rtd(acos($d)); }
    private function arctan($d) { return $this->rtd(atan($d)); }
    private function arccot($x) { return $this->rtd(atan(1 / $x)); }
    private function arctan2($y, $x) { return $this->rtd(atan2($y, $x)); }
}
