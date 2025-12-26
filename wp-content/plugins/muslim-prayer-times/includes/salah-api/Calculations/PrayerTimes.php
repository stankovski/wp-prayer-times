<?php

namespace SalahAPI\Calculations;

use DateTime;
use DateTimezone;

/**
 * Prayer Times Calculator
 * Based on times.js v3.2 by Hamid Zarrabi-Zadeh
 */
class PrayerTimes
{
    // Prayer time constants
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

    // Asr calculation schools
    const SCHOOL_STANDARD = 'STANDARD';
    const SCHOOL_HANAFI = 'HANAFI';

    // Midnight calculation modes
    const MIDNIGHT_MODE_STANDARD = 'STANDARD';
    const MIDNIGHT_MODE_JAFARI = 'JAFARI';

    // Higher latitude adjustment methods
    const LATITUDE_ADJUSTMENT_METHOD_MOTN = 'MIDDLE_OF_THE_NIGHT';
    const LATITUDE_ADJUSTMENT_METHOD_ANGLE = 'ANGLE_BASED';
    const LATITUDE_ADJUSTMENT_METHOD_ONESEVENTH = 'ONE_SEVENTH';
    const LATITUDE_ADJUSTMENT_METHOD_NONE = 'NONE';

    // Time formats
    const TIME_FORMAT_24H = '24h';
    const TIME_FORMAT_12H = '12h';
    const TIME_FORMAT_12hNS = '12hNS';
    const TIME_FORMAT_FLOAT = 'Float';
    const TIME_FORMAT_ISO8601 = 'iso8601';

    const HIGH_LATS_NONE = 'None';
    const HIGH_LATS_NIGHT_MIDDLE = 'NightMiddle';
    const HIGH_LATS_ONE_SEVENTH = 'OneSeventh';
    const HIGH_LATS_ANGLE_BASED = 'AngleBased';

    const INVALID_TIME = '-----';

    /**
     * Calculation methods configuration
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
     * Settings configuration
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

    private $labels = [
        'Fajr', 'Sunrise', 'Dhuhr', 'Asr',
        'Sunset', 'Maghrib', 'Isha', 'Midnight'
    ];

    private $utcTime;
    private $adjusted = false;
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
    private $shafaq = 'general';
    private $offset = [];

    /**
     * Constructor
     */
    public function __construct($method = Method::METHOD_MWL, $school = self::SCHOOL_STANDARD, $asrShadowFactor = null)
    {
        $this->setMethod($method);
        $this->setSchool($school);
        if ($asrShadowFactor !== null) {
            $this->asrShadowFactor = $asrShadowFactor;
        }
        $this->settings['timezone'] = date_default_timezone_get();
        $this->settings['location'][1] = -(new DateTime())->getOffset() / 240;
    }

    /**
     * Get prayer times for today
     */
    public function getTimesForToday($latitude, $longitude, $timezone, $elevation = null, $latitudeAdjustmentMethod = self::LATITUDE_ADJUSTMENT_METHOD_ANGLE, $midnightMode = null, $format = self::TIME_FORMAT_24H)
    {
        $date = new DateTime('', new DateTimezone($timezone));
        return $this->getTimes($date, $latitude, $longitude, $elevation, $latitudeAdjustmentMethod, $midnightMode, $format);
    }

    /**
     * Get prayer times for specific date
     */
    public function getTimes(DateTime $date, $latitude, $longitude, $elevation = null, $latitudeAdjustmentMethod = self::LATITUDE_ADJUSTMENT_METHOD_ANGLE, $midnightMode = null, $format = self::TIME_FORMAT_24H)
    {
        $this->date = $date;
        $this->latitude = 1 * $latitude;
        $this->longitude = 1 * $longitude;
        $this->elevation = $elevation === null ? 0 : 1 * $elevation;
        $this->settings['location'] = [$this->latitude, $this->longitude];
        $this->settings['timezone'] = $date->getTimezone()->getName();
        
        $this->setTimeFormat($format);
        $this->setLatitudeAdjustmentMethod($latitudeAdjustmentMethod);
        if ($midnightMode !== null) {
            $this->setMidnightMode($midnightMode);
        }

        $year = (int)$date->format('Y');
        $month = (int)$date->format('n');
        $day = (int)$date->format('j');
        
        $utcDate = new DateTime('', new DateTimezone('UTC'));
        $utcDate->setDate($year, $month, $day);
        $utcDate->setTime(0, 0, 0);
        $this->utcTime = $utcDate->getTimestamp() * 1000;

        $times = $this->computeTimes();
        $this->formatTimes($times);
        return $this->convertTimesToPrayerTimesFormat($times);
    }

    /**
     * Set calculation method
     */
    public function setMethod($method = Method::METHOD_MWL)
    {
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
        
        $this->settings = array_merge($this->settings, $this->methods['defaults']);
        
        if (isset($this->methods[$jsMethod])) {
            $this->settings = array_merge($this->settings, $this->methods[$jsMethod]);
        }
    }
    
    /**
     * Set school for Asr calculation
     */
    public function setSchool($school = self::SCHOOL_STANDARD)
    {
        $this->school = $school;
        $this->settings['asr'] = ($school == self::SCHOOL_HANAFI) ? 'Hanafi' : 'Standard';
    }
    
    /**
     * Set midnight calculation mode
     */
    public function setMidnightMode($mode = self::MIDNIGHT_MODE_STANDARD)
    {
        $this->midnightMode = $mode;
        $this->settings['midnight'] = ($mode == self::MIDNIGHT_MODE_JAFARI) ? 'Jafari' : 'Standard';
    }
    
    /**
     * Set latitude adjustment method for high latitudes
     */
    public function setLatitudeAdjustmentMethod($method = self::LATITUDE_ADJUSTMENT_METHOD_ANGLE)
    {
        $this->latitudeAdjustmentMethod = $method;
        
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
     */
    public function setTimeFormat($format = self::TIME_FORMAT_24H)
    {
        $this->timeFormat = $format;
        
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
     */
    public function setShafaq(string $shafaq)
    {
        $this->shafaq = $shafaq;
    }
    
    /**
     * Set custom calculation method
     */
    public function setCustomMethod(Method $method)
    {
        $this->method = Method::METHOD_CUSTOM;
        $methodVars = get_object_vars($method);
        
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

    /**
     * Get all available calculation methods
     */
    public function getMethods()
    {
        return Method::getMethods();
    }
    
    /**
     * Get current calculation method
     */
    public function getMethod()
    {
        return $this->method;
    }
    
    /**
     * Get metadata about current configuration
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
     * Handle moonsighting method recalculation
     */
    public function moonsightingRecalculation(array $times): array
    {
        if ($this->method == Method::METHOD_MOONSIGHTING) {
            return $times;
        }
        
        return $times;
    }
    
    /**
     * Load calculation methods
     */
    public function loadMethods()
    {
        // Method data is already loaded in $this->methods
    }

    /**
     * Main computation method
     */
    private function computeTimes()
    {
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
     * Update times with minute-based adjustments
     */
    private function updateTimes(&$times)
    {
        $params = $this->settings;

        if ($this->isMin($params['maghrib'] ?? '0 min')) {
            $times['maghrib'] = $times['sunset'] + $this->value($params['maghrib'] ?? '0 min') / 60;
        }
        if ($this->isMin($params['isha'] ?? '0 min')) {
            $times['isha'] = $times['maghrib'] + $this->value($params['isha'] ?? '0 min') / 60;
        }
        
        if (($params['midnight'] ?? 'Standard') == 'Jafari') {
            $nextFajr = $this->angleTime($params['fajr'] ?? 18, 29, -1) + 24;
            $times['midnight'] = ($times['sunset'] + ($this->adjusted ? $times['fajr'] + 24 : $nextFajr)) / 2;
        }
        
        $times['dhuhr'] += $this->value($params['dhuhr'] ?? '0 min') / 60;
    }

    /**
     * Apply user-defined time adjustments
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
     * Convert times to proper timezone
     */
    private function convertTimes(&$times)
    {
        $lng = $this->settings['location'][1];
        
        foreach ($times as $i => $time) {
            $adjustedTime = $time - $lng / 15;
            $timestamp = $this->utcTime + floor($adjustedTime * 3600000);
            $times[$i] = $this->roundTime($timestamp);
        }
    }
    
    /**
     * Adjust times for higher latitudes
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
    
    /**
     * Compute sun position
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
     */
    private function midDay($time)
    {
        $eqt = $this->sunPosition($time)->equation;
        $noon = $this->mod(12 - $eqt, 24);
        return $noon;
    }

    /**
     * Compute time at specific angle
     */
    private function angleTime($angle, $time, $direction = 1)
    {
        $lat = $this->settings['location'][0];
        $decl = $this->sunPosition($time)->declination;
        $numerator = -$this->sin($angle) - $this->sin($lat) * $this->sin($decl);
        $denominator = $this->cos($lat) * $this->cos($decl);
        
        if (abs($numerator / $denominator) > 1) {
            return NAN;
        }
        
        $diff = $this->arccos($numerator / $denominator) / 15;
        return $this->midDay($time) + $diff * $direction;
    }

    /**
     * Compute asr angle
     */
    private function asrAngle($asrParam, $time)
    {
        $shadowFactors = ['Standard' => 1, 'Hanafi' => 2];
        $shadowFactor = isset($shadowFactors[$asrParam]) ? $shadowFactors[$asrParam] : $this->value($asrParam);
        
        $lat = $this->settings['location'][0];
        $decl = $this->sunPosition($time)->declination;
        return -$this->arccot($shadowFactor + $this->tan(abs($lat - $decl)));
    }
    
    /**
     * Convert string to number
     */
    private function value($str)
    {
        preg_match('/[0-9.+-]*/', (string)$str, $matches);
        return (float)($matches[0] ?? 0);
    }

    /**
     * Detect if input contains 'min'
     */
    private function isMin($str)
    {
        return strpos((string)$str, 'min') !== false;
    }

    /**
     * Positive modulo
     */
    private function mod($a, $b)
    {
        $result = fmod($a, $b);
        if ($result < 0) {
            $result += $b;
        }
        return $result;
    }

    /**
     * Round time timestamp
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
        
        $oneMinute = 60000;
        return (float)($rounding($timestamp / $oneMinute) * $oneMinute);
    }
    
    /**
     * Format all times
     */
    private function formatTimes(&$times)
    {
        foreach ($times as $i => $time) {
            $times[$i] = $this->formatTime($time);
        }
    }

    /**
     * Format individual time
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
     */
    private function timeToString($timestamp, $format)
    {
        $utcOffset = $this->settings['utcOffset'];
        
        $timestampSeconds = $timestamp / 1000;
        
        if ($utcOffset !== 'auto') {
            $timestampSeconds += $utcOffset * 60;
        }
        
        $date = new DateTime('@' . $timestampSeconds, new DateTimezone('UTC'));
        
        if ($utcOffset === 'auto') {
            $date->setTimezone(new DateTimezone($this->settings['timezone']));
        }
        
        if ($format == '24h') {
            return $date->format('H:i');
        } elseif ($format == '12h') {
            return $date->format('g:i A');
        } elseif ($format == '12H') {
            return $date->format('g:i');
        } else {
            return $date->format('H:i');
        }
    }

    /**
     * Convert times to expected format
     */
    private function convertTimesToPrayerTimesFormat($times)
    {
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
        
        // Note: Imsak, First Third, and Last Third are typically calculated separately
        // For now, we'll just provide the main prayer times
        // These additional times would need to be calculated before formatting
        
        return $result;
    }
    
    /**
     * Get timezone offset in hours
     */
    private function getTimezoneOffsetHours()
    {
        if ($this->date !== null) {
            return $this->date->getOffset() / 3600;
        }
        return 0;
    }
    
    /**
     * Convert formatted time back to timestamp
     */
    private function convertTimestamp($time)
    {
        if (is_numeric($time)) {
            return $time;
        }
        
        if (is_string($time) && preg_match('/(\d{1,2}):(\d{2})/', $time, $matches)) {
            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
            return ($hours * 3600 + $minutes * 60) * 1000;
        }
        
        return 0;
    }
    
    // Trigonometry methods (degree-based)
    private function dtr($d) { return (float)$d * M_PI / 180; }
    private function rtd($r) { return (float)$r * 180 / M_PI; }
    private function sin($d) { return sin($this->dtr($d)); }
    private function cos($d) { return cos($this->dtr($d)); }
    private function tan($d) { return tan($this->dtr($d)); }
    private function arcsin($d) { return $this->rtd(asin($d)); }
    private function arccos($d) { return $this->rtd(acos($d)); }
    private function arctan($d) { return $this->rtd(atan($d)); }
    private function arccot($x) { return $this->rtd(atan(1 / (float)$x)); }
    private function arctan2($y, $x) { return $this->rtd(atan2($y, $x)); }
}
