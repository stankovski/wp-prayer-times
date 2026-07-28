<?php

namespace SalahAPI\Calculations;

use DateTime;
use DateTimeZone;
use DateInterval;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;

/**
 * Prayer Times Builder
 * 
 * Builds prayer times based on SalahAPI data and date range.
 * Uses calculation methods from PrayerTimes and Iqama calculation logic.
 */
class Builder
{
    private const DAY_OF_WEEK_MAP = [
        'Sunday' => 0,
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6,
    ];

    private Location $location;
    private CalculationMethod $calculationMethod;
    private int $elevation;
    private bool $includeAsrMethods;

    /** Asr school driving every prayer time, including the Asr used for iqama. */
    private string $iqamaAsrSchool;

    /** Asr school used for the `asr_athan` output column only. */
    private string $athanAsrSchool;

    /** @var array<string, PrayerTimes> Lazily created calculators keyed by Asr school. */
    private array $calculators = [];

    /**
     * Constructor
     * 
     * @param Location $location Location configuration
     * @param CalculationMethod $calculationMethod Calculation method configuration
     * @param int $elevation Elevation in meters (default: 0)
     * @param bool $includeAsrMethods When true, the generated CSV includes the optional
     *                                `asr_athan_standard` and `asr_athan_hanafi` columns
     *                                (SalahAPI 1.1 feature).
     * @param string|null $asrAthanMethod Asr method used for the `asr_athan` output column.
     *                                    Defaults to the calculation method used for iqama.
     */
    public function __construct(
        Location $location,
        CalculationMethod $calculationMethod,
        int $elevation = 0,
        bool $includeAsrMethods = false,
        ?string $asrAthanMethod = null
    ) {
        $this->location = $location;
        $this->calculationMethod = $calculationMethod;
        $this->elevation = $elevation;
        $this->includeAsrMethods = $includeAsrMethods;
        $this->iqamaAsrSchool = $this->normalizeAsrSchool($calculationMethod->asrCalculationMethod);
        $this->athanAsrSchool = $this->normalizeAsrSchool(
            $asrAthanMethod ?? $calculationMethod->asrCalculationMethod
        );
    }

    /**
     * Get the prayer times calculator for an Asr school, creating it on first use
     *
     * @param string $asrSchool PrayerTimes school constant
     * @return PrayerTimes
     */
    private function calculatorFor(string $asrSchool): PrayerTimes
    {
        return $this->calculators[$asrSchool] ??= new PrayerTimes(
            $this->calculationMethod->name,
            $asrSchool
        );
    }

    /**
     * Calculate a day's prayer times using the given Asr school
     *
     * @param DateTime $date Day to calculate
     * @param string $asrSchool PrayerTimes school constant
     * @return array Prayer times keyed by PrayerTimes constants, in 24h format
     */
    private function calculateTimes(DateTime $date, string $asrSchool): array
    {
        return $this->calculatorFor($asrSchool)->getTimes(
            $date,
            $this->location->latitude,
            $this->location->longitude,
            $this->elevation,
            $this->normalizeHighLatitudeAdjustment($this->calculationMethod->highLatitudeAdjustment),
            null,
            PrayerTimes::TIME_FORMAT_24H
        );
    }

    /**
     * Normalize high latitude adjustment method from config format to PrayerTimes constant
     * 
     * @param string|null $method Method from config
     * @return string Normalized method constant
     */
    private function normalizeHighLatitudeAdjustment(?string $method): string
    {
        if ($method === null) {
            return PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN;
        }
        
        // Map common config values to PrayerTimes constants
        $mapping = [
            'MiddleOfTheNight' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
            'MIDDLE_OF_THE_NIGHT' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
            'NightMiddle' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
            'AngleBased' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ANGLE,
            'ANGLE_BASED' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ANGLE,
            'OneSeventh' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ONESEVENTH,
            'ONE_SEVENTH' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ONESEVENTH,
            'None' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_NONE,
            'NONE' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_NONE,
        ];
        
        return $mapping[$method] ?? PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN;
    }

    /**
     * Normalize Asr calculation method from config format to PrayerTimes school constant
     *
     * @param string|null $method Asr calculation method from config (e.g. "standard", "hanafi")
     * @return string Normalized school constant
     */
    private function normalizeAsrSchool(?string $method): string
    {
        if ($method === null) {
            return PrayerTimes::SCHOOL_STANDARD;
        }

        return strtolower($method) === 'hanafi'
            ? PrayerTimes::SCHOOL_HANAFI
            : PrayerTimes::SCHOOL_STANDARD;
    }

    /**
     * Build prayer times for a date range
     * 
     * @param DateTime|string $startDate Start date
     * @param DateTime|string $endDate End date
     * @return array Array of prayer time data
     */
    public function build($startDate, $endDate): array
    {
        // Convert string dates to DateTime if needed
        $dtz = new DateTimeZone($this->location->timezone);
        
        if (is_string($startDate)) {
            $startDate = new DateTime($startDate, $dtz);
        } else {
            $startDate = clone $startDate;
            $startDate->setTimezone($dtz);
        }
        
        if (is_string($endDate)) {
            $endDate = new DateTime($endDate, $dtz);
        } else {
            $endDate = clone $endDate;
            $endDate->setTimezone($dtz);
        }
        
        // Calculate number of days
        $interval = $startDate->diff($endDate);
        $daysToGenerate = (int)$interval->days + 1; // Include end date
        
        // Process in weekly batches if using weekly frequency
        $iqamaRules = $this->calculationMethod->iqamaCalculationRules;
        $isWeekly = ($iqamaRules?->changeOn ?? null) !== null;
        
        // Collect all prayer times first
        $allDaysData = [];
        $currentDate = clone $startDate;
        
        for ($i = 0; $i < $daysToGenerate; $i++) {
            // Every prayer except the Asr athan comes from the iqama Asr school.
            $times = $this->calculateTimes($currentDate, $this->iqamaAsrSchool);

            $datePrefix = $currentDate->format('Y-m-d') . ' ';
            $toDateTime = function (string $time) use ($datePrefix, $dtz): DateTime {
                return new DateTime($datePrefix . $time, $dtz);
            };

            // Memoized so each school is only calculated once per day.
            $asrBySchool = [$this->iqamaAsrSchool => $times[PrayerTimes::ASR]];
            $asrFor = function (string $asrSchool) use (&$asrBySchool, $currentDate): string {
                return $asrBySchool[$asrSchool] ??=
                    $this->calculateTimes($currentDate, $asrSchool)[PrayerTimes::ASR];
            };

            $athan = [
                'fajr' => $toDateTime($times[PrayerTimes::FAJR]),
                'sunrise' => $toDateTime($times[PrayerTimes::SUNRISE]),
                'dhuhr' => $toDateTime($times[PrayerTimes::ZHUHR]),
                'asr' => $toDateTime($times[PrayerTimes::ASR]),
                'asr_athan' => $toDateTime($asrFor($this->athanAsrSchool)),
                'maghrib' => $toDateTime($times[PrayerTimes::MAGHRIB]),
                'isha' => $toDateTime($times[PrayerTimes::ISHA]),
            ];

            if ($this->includeAsrMethods) {
                $athan['asr_standard'] = $toDateTime($asrFor(PrayerTimes::SCHOOL_STANDARD));
                $athan['asr_hanafi'] = $toDateTime($asrFor(PrayerTimes::SCHOOL_HANAFI));
            }

            $allDaysData[$i] = [
                'date' => clone $currentDate,
                'athan' => $athan,
            ];
            
            $currentDate->modify('+1 day');
        }
        
        // Now process in weekly batches or all at once
        $csvData = [];
        $header = [
            'day', 'fajr_athan', 'fajr_iqama', 'sunrise',
            'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama',
            'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama'
        ];
        if ($this->includeAsrMethods) {
            $header[] = 'asr_athan_standard';
            $header[] = 'asr_athan_hanafi';
        }
        $csvData[] = $header;
        
        if ($isWeekly) {
            $csvData = array_merge($csvData, $this->processWeekly($allDaysData, $dtz));
        } else {
            // For daily frequency, just calculate all days at once
            $csvData = array_merge($csvData, $this->calculateWeekIqama($allDaysData, $dtz));
        }
        
        return $csvData;
    }

    /**
     * Process days in weekly batches
     * 
     * @param array $allDaysData All day data
     * @param DateTimeZone $dtz Timezone
     * @return array CSV rows
     */
    private function processWeekly(array $allDaysData, DateTimeZone $dtz): array
    {
        $csvRows = [];
        $currentWeekStart = null;
        $weekDaysData = [];
        $processedDays = 0;
        $totalDays = count($allDaysData);
        
        // Determine the day of week when iqama times change
        $changeOnDay = $this->calculationMethod->iqamaCalculationRules?->changeOn ?? 'Friday';
        $changeOnDayNumber = self::DAY_OF_WEEK_MAP[$changeOnDay] ?? 5;
        
        foreach ($allDaysData as $dayIndex => $dayData) {
            $currentDate = $dayData['date'];
            $currentDayNumber = (int)$currentDate->format('w');
            
            // Start a new week on the change day
            if ($currentDayNumber == $changeOnDayNumber || $currentWeekStart === null) {
                if ($currentWeekStart !== null && !empty($weekDaysData)) {
                    // Process the previous week
                    $csvRows = array_merge($csvRows, $this->calculateWeekIqama($weekDaysData, $dtz));
                }
                $currentWeekStart = clone $currentDate;
                $weekDaysData = [];
            }
            
            // Add this day to the week
            $weekDaysData[$dayIndex] = $dayData;
            
            // Check if this is the last day or end of week
            $isEndOfWeek = self::isEndOfWeek($currentDayNumber, $changeOnDayNumber);
            $isLastDay = ($processedDays + 1) >= $totalDays;
            
            if ($isEndOfWeek || $isLastDay) {
                // Process this week
                $csvRows = array_merge($csvRows, $this->calculateWeekIqama($weekDaysData, $dtz));
                $weekDaysData = [];
                $currentWeekStart = null;
            }
            
            $processedDays++;
        }
        
        // Process any remaining days
        if (!empty($weekDaysData)) {
            $csvRows = array_merge($csvRows, $this->calculateWeekIqama($weekDaysData, $dtz));
        }
        
        return $csvRows;
    }

    /**
     * Calculate Iqama times for a week (or batch) of days
     * 
     * @param array $weekDaysData Days data for the week
     * @param DateTimeZone $dtz Timezone
     * @return array CSV rows
     */
    private function calculateWeekIqama(array $weekDaysData, DateTimeZone $dtz): array
    {
        if (empty($weekDaysData)) {
            return [];
        }
        
        $iqamaRules = $this->calculationMethod->iqamaCalculationRules;
        
        // Calculate Iqama times for each prayer, passing base rules with overrides
        // IqamaCalculator will resolve overrides per-day for static times
        $fajrIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'fajr',
            $iqamaRules?->fajr,
            'sunrise'  // End prayer name for beforeEndMinutes calculation
        );
        
        $dhuhrIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'dhuhr',
            $iqamaRules?->dhuhr
        );
        
        $asrIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'asr',
            $iqamaRules?->asr
        );
        
        $maghribIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'maghrib',
            $iqamaRules?->maghrib
        );
        
        $ishaIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'isha',
            $iqamaRules?->isha
        );
        
        // Build CSV rows
        $csvRows = [];
        
        foreach ($weekDaysData as $dayIndex => $dayData) {
            $date = $dayData['date'];
            $athan = $dayData['athan'];
            
            $row = [
                $date->format('Y-m-d'),
                $athan['fajr']->format('H:i'),
                isset($fajrIqamaTimes[$dayIndex]) ? $fajrIqamaTimes[$dayIndex]->format('H:i') : '',
                $athan['sunrise']->format('H:i'),
                $athan['dhuhr']->format('H:i'),
                isset($dhuhrIqamaTimes[$dayIndex]) ? $dhuhrIqamaTimes[$dayIndex]->format('H:i') : '',
                $athan['asr_athan']->format('H:i'),
                isset($asrIqamaTimes[$dayIndex]) ? $asrIqamaTimes[$dayIndex]->format('H:i') : '',
                $athan['maghrib']->format('H:i'),
                isset($maghribIqamaTimes[$dayIndex]) ? $maghribIqamaTimes[$dayIndex]->format('H:i') : '',
                $athan['isha']->format('H:i'),
                isset($ishaIqamaTimes[$dayIndex]) ? $ishaIqamaTimes[$dayIndex]->format('H:i') : '',
            ];

            if ($this->includeAsrMethods) {
                $row[] = isset($athan['asr_standard']) ? $athan['asr_standard']->format('H:i') : '';
                $row[] = isset($athan['asr_hanafi']) ? $athan['asr_hanafi']->format('H:i') : '';
            }

            $csvRows[] = $row;
        }
        
        return $csvRows;
    }

    /**
     * Build and return as CSV string
     * 
     * @param DateTime|string $startDate Start date
     * @param DateTime|string $endDate End date
     * @return string CSV content
     */
    public function buildCsv($startDate, $endDate): string
    {
        $data = $this->build($startDate, $endDate);
        
        $csvContent = '';
        foreach ($data as $row) {
            $csvContent .= implode(',', $row) . "\n";
        }
        
        return $csvContent;
    }

    /**
     * Build and return as array of associative arrays
     * 
     * @param DateTime|string $startDate Start date
     * @param DateTime|string $endDate End date
     * @return array Array of associative arrays
     */
    public function buildAssociative($startDate, $endDate): array
    {
        $data = $this->build($startDate, $endDate);
        
        // Remove header row
        $header = array_shift($data);
        
        // Convert to associative arrays
        $result = [];
        foreach ($data as $row) {
            $assoc = [];
            foreach ($header as $index => $key) {
                $assoc[$key] = $row[$index] ?? null;
            }
            $result[] = $assoc;
        }
        
        return $result;
    }

    /**
     * Check if the current day is the end of the week (day before change day)
     * 
     * @param int $currentDayNumber Current day number (0-6)
     * @param int $changeOnDayNumber Day number when iqama changes (0-6)
     * @return bool True if it's the end of the week
     */
    private static function isEndOfWeek(int $currentDayNumber, int $changeOnDayNumber): bool
    {
        $dayBeforeChange = ($changeOnDayNumber - 1 + 7) % 7;
        return $currentDayNumber == $dayBeforeChange;
    }
}
