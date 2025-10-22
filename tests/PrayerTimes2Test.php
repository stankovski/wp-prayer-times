<?php
/**
 * Simple test for PrayerTimes2.php implementation
 */

require_once __DIR__ . '/../wp-content/plugins/muslim-prayer-times/includes/islamic-network/PrayerTimes/PrayerTimes2.php';
require_once __DIR__ . '/../wp-content/plugins/muslim-prayer-times/includes/islamic-network/PrayerTimes/Method.php';

use PHPUnit\Framework\TestCase;
use IslamicNetwork\PrayerTimes\PrayerTimes2;
use IslamicNetwork\PrayerTimes\Method;

/**
 * PrayerTimes2 Tests
 *
 * @package PrayerTimes
 */
class PrayerTimes2Test extends TestCase {
    
    /**
     * Test prayer times calculation for Bothell, WA on 2026-01-01
     * Using ISNA method
     */
    public function testPrayerTimes2CalculationForBothellWA20260101() {
        // Bothell, WA coordinates
        $latitude = 47.7580361;
        $longitude = -122.1985255;
        $timezone = 'America/Los_Angeles';
        
        // Expected times for 2026-01-01 (from times.js v3.2 with ISNA method)
        $expectedTimes = [
            'Fajr' => '06:24',
            'Sunrise' => '07:58',
            'Dhuhr' => '12:13',
            'Asr' => '14:10',
            'Maghrib' => '16:29',
            'Isha' => '18:02'
        ];
        
        // Create PrayerTimes2 instance with ISNA method
        $pt = new PrayerTimes2(Method::METHOD_ISNA);
        
        // Get times for the date
        $date = new DateTime('2026-01-01', new DateTimeZone($timezone));
        $times = $pt->getTimes(
            $date, 
            $latitude, 
            $longitude, 
            null, 
            PrayerTimes2::LATITUDE_ADJUSTMENT_METHOD_ANGLE, 
            null, 
            PrayerTimes2::TIME_FORMAT_24H
        );
        
        // Assert that times were returned
        $this->assertIsArray($times, 'Prayer times should be returned as an array');
        
        // Validate each prayer time (allowing for minor calculation differences)
        // The times.js algorithm may have slight differences compared to other calculators
        $this->assertEqualsWithTolerance($expectedTimes['Fajr'], $times['Fajr'], 1, 'Fajr time');
        $this->assertEqualsWithTolerance($expectedTimes['Sunrise'], $times['Sunrise'], 1, 'Sunrise time');
        $this->assertEqualsWithTolerance($expectedTimes['Dhuhr'], $times['Dhuhr'], 1, 'Dhuhr time');
        $this->assertEqualsWithTolerance($expectedTimes['Asr'], $times['Asr'], 1, 'Asr time');
        $this->assertEqualsWithTolerance($expectedTimes['Maghrib'], $times['Maghrib'], 1, 'Maghrib time');
        $this->assertEqualsWithTolerance($expectedTimes['Isha'], $times['Isha'], 1, 'Isha time');
    }
    
    /**
     * Test prayer times calculation for Bothell, WA on 2026-06-25 (summer date)
     * Using ISNA method
     * This tests a summer date with longer days and different sun angles
     */
    public function testPrayerTimes2CalculationForBothellWA20260625Summer() {
        // Bothell, WA coordinates
        $latitude = 47.7580361;
        $longitude = -122.1985255;
        $timezone = 'America/Los_Angeles';
        
        // Expected times for 2026-06-25 (from times.js v3.2 with ISNA method)
        // This is a summer date to test different astronomical conditions
        $expectedTimes = [
            'Fajr' => '02:57',
            'Sunrise' => '05:12',
            'Dhuhr' => '13:12',
            'Asr' => '17:26',
            'Maghrib' => '21:12',
            'Isha' => '23:26'
        ];
        
        // Create PrayerTimes2 instance with ISNA method
        $pt = new PrayerTimes2(Method::METHOD_ISNA);
        
        // Get times for the date
        $date = new DateTime('2026-06-25', new DateTimeZone($timezone));
        $times = $pt->getTimes(
            $date, 
            $latitude, 
            $longitude, 
            null, 
            PrayerTimes2::LATITUDE_ADJUSTMENT_METHOD_MOTN, // NightMiddle to match times.js default
            null, 
            PrayerTimes2::TIME_FORMAT_24H
        );
        
        // Assert that times were returned
        $this->assertIsArray($times, 'Prayer times should be returned as an array');
        
        // Validate each prayer time (allowing for minor calculation differences)
        $this->assertEqualsWithTolerance($expectedTimes['Fajr'], $times['Fajr'], 1, 'Fajr time');
        $this->assertEqualsWithTolerance($expectedTimes['Sunrise'], $times['Sunrise'], 1, 'Sunrise time');
        $this->assertEqualsWithTolerance($expectedTimes['Dhuhr'], $times['Dhuhr'], 1, 'Dhuhr time');
        $this->assertEqualsWithTolerance($expectedTimes['Asr'], $times['Asr'], 1, 'Asr time');
        $this->assertEqualsWithTolerance($expectedTimes['Maghrib'], $times['Maghrib'], 1, 'Maghrib time');
        $this->assertEqualsWithTolerance($expectedTimes['Isha'], $times['Isha'], 1, 'Isha time');
    }
    
    /**
     * Helper method to assert time equality with tolerance
     * @param string $expected Expected time in H:i format
     * @param string $actual Actual time in H:i format
     * @param int $toleranceMinutes Tolerance in minutes
     * @param string $message Assertion message
     */
    private function assertEqualsWithTolerance($expected, $actual, $toleranceMinutes, $message)
    {
        $expectedTime = strtotime($expected);
        $actualTime = strtotime($actual);
        $diff = abs($expectedTime - $actualTime) / 60; // Convert to minutes
        
        $this->assertLessThanOrEqual(
            $toleranceMinutes,
            $diff,
            sprintf('%s should match within %d minutes. Expected: %s, Got: %s (diff: %.1f min)',
                $message, $toleranceMinutes, $expected, $actual, $diff)
        );
    }
}