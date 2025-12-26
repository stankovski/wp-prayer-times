<?php

namespace SalahAPI\Calculations;

use DateTime;

/**
 * Helper functions for time manipulation, rounding, and DST handling
 */
class TimeHelpers
{
    /**
     * Convert DateTime to minutes since midnight
     * 
     * @param DateTime $time
     * @return int Total minutes from midnight
     */
    public static function timeToMinutes(DateTime $time): int
    {
        $hours = (int)$time->format('G');
        $minutes = (int)$time->format('i');
        $totalMinutes = ($hours * 60) + $minutes;
        
        // Check if DST is in effect
        $isDst = $time->format('I') == '1';
        
        // Adjust for DST if it's in effect
        if ($isDst) {
            $totalMinutes -= 60;
        }
        
        return $totalMinutes;
    }

    /**
     * Normalize time by subtracting one hour if date is in DST
     * 
     * @param DateTime $time
     * @return DateTime Normalized time
     */
    public static function normalizeTimeForDst(DateTime $time): DateTime
    {
        $normalizedTime = clone $time;
        
        // Check if DST is in effect
        $isDst = $time->format('I') == '1';
        
        // If DST is in effect, subtract one hour
        if ($isDst) {
            $normalizedTime->modify('-1 hour');
        }
        
        return $normalizedTime;
    }

    /**
     * Denormalize time by adding one hour if date is in DST
     * 
     * @param DateTime $time
     * @return DateTime Denormalized time
     */
    public static function denormalizeTimeForDst(DateTime $time): DateTime
    {
        $denormalizedTime = clone $time;
        
        // Check if DST is in effect
        $isDst = $time->format('I') == '1';
        
        // If DST is in effect, add one hour
        if ($isDst) {
            $denormalizedTime->modify('+1 hour');
        }
        
        return $denormalizedTime;
    }

    /**
     * Normalize all athan times in the days_data array
     * 
     * @param array $daysData Array of day data with athan times
     * @return array Normalized days data
     */
    public static function normalizeTimesForDst(array $daysData): array
    {
        $normalizedDaysData = [];
        
        foreach ($daysData as $dayIndex => $dayData) {
            $normalizedDaysData[$dayIndex] = [
                'date' => $dayData['date'],
                'athan' => []
            ];
            
            // Normalize all available athan times
            foreach ($dayData['athan'] as $prayer => $time) {
                $normalizedDaysData[$dayIndex]['athan'][$prayer] = self::normalizeTimeForDst($time);
            }
        }
        
        return $normalizedDaysData;
    }

    /**
     * Round a time down to the nearest X minutes
     * 
     * @param DateTime $time
     * @param int $roundingMinutes
     * @return DateTime Rounded time
     */
    public static function roundDown(DateTime $time, int $roundingMinutes = 1): DateTime
    {
        if ($roundingMinutes <= 1) {
            return $time;
        }
        
        $timestamp = $time->getTimestamp();
        $minutes = (int)$time->format('i');
        $seconds = (int)$time->format('s');
        $totalSeconds = ($minutes * 60) + $seconds;
        
        // Convert rounding_minutes to seconds
        $roundingSeconds = $roundingMinutes * 60;
        
        // Floor to the nearest interval (round down)
        $roundedSeconds = floor($totalSeconds / $roundingSeconds) * $roundingSeconds;
        
        // Create a new DateTime with the rounded time
        $roundedTime = clone $time;
        
        // Get the hour part
        $hour = (int)$time->format('G');
        
        // Calculate new minutes and adjust hour if needed
        $newMinutes = floor($roundedSeconds / 60);
        if ($newMinutes >= 60) {
            $hour += floor($newMinutes / 60);
            $newMinutes = $newMinutes % 60;
        }
        
        // Set the new time
        $roundedTime->setTime($hour, (int)$newMinutes, 0);
        
        return $roundedTime;
    }

    /**
     * Round a time up to the nearest X minutes
     * 
     * @param DateTime $time
     * @param int $roundingMinutes
     * @return DateTime Rounded time
     */
    public static function roundUp(DateTime $time, int $roundingMinutes = 1): DateTime
    {
        if ($roundingMinutes <= 1) {
            return $time;
        }
        
        $timestamp = $time->getTimestamp();
        $minutes = (int)$time->format('i');
        $seconds = (int)$time->format('s');
        $totalSeconds = ($minutes * 60) + $seconds;
        
        // Convert rounding_minutes to seconds
        $roundingSeconds = $roundingMinutes * 60;
        
        // Ceiling to the nearest interval (round up)
        // If already on an interval boundary and seconds are 0, don't round up
        if ($totalSeconds % $roundingSeconds === 0 && $seconds === 0) {
            $roundedSeconds = $totalSeconds;
        } else {
            $roundedSeconds = ceil($totalSeconds / $roundingSeconds) * $roundingSeconds;
        }
        
        // Create a new DateTime with the rounded time
        $roundedTime = clone $time;
        
        // Get the hour part
        $hour = (int)$time->format('G');
        
        // Calculate new minutes and adjust hour if needed
        $newMinutes = floor($roundedSeconds / 60);
        if ($newMinutes >= 60) {
            $hour += floor($newMinutes / 60);
            $newMinutes = $newMinutes % 60;
        }
        
        // Set the new time
        $roundedTime->setTime($hour, (int)$newMinutes, 0);
        
        return $roundedTime;
    }

    /**
     * Parse time string (HH:MM) and set it on a DateTime object
     * 
     * @param DateTime $date Base date to use
     * @param string $timeString Time in HH:MM format
     * @return DateTime DateTime with the specified time
     */
    public static function parseTimeString(DateTime $date, string $timeString): DateTime
    {
        $result = clone $date;
        $parts = explode(':', $timeString);
        
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Invalid time format: {$timeString}");
        }
        
        $hours = (int)$parts[0];
        $minutes = (int)$parts[1];
        
        $result->setTime($hours, $minutes, 0);
        
        return $result;
    }

    /**
     * Convert Western numerals to Arabic numerals
     * 
     * @param string|int|float $number
     * @return string The input with Western numerals converted to Arabic numerals
     */
    public static function convertToArabicNumerals($number): string
    {
        $westernNumerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $arabicNumerals = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        
        $numberStr = (string)$number;
        
        return str_replace($westernNumerals, $arabicNumerals, $numberStr);
    }
}
