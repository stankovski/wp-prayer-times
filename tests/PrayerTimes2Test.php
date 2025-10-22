<?php
/**
 * Simple test for PrayerTimes2.php implementation
 */

require_once __DIR__ . '/../wp-content/plugins/muslim-prayer-times/includes/islamic-network/PrayerTimes/PrayerTimes2.php';
require_once __DIR__ . '/../wp-content/plugins/muslim-prayer-times/includes/islamic-network/PrayerTimes/Method.php';

use IslamicNetwork\PrayerTimes\PrayerTimes2;
use IslamicNetwork\PrayerTimes\Method;

// Test basic functionality
try {
    // Create an instance
    $pt = new PrayerTimes2(Method::METHOD_ISNA);
    
    // Test location: New York City
    $latitude = 40.7128;
    $longitude = -74.0060;
    $timezone = 'America/New_York';
    
    // Get times for today
    $date = new DateTime('2025-01-15', new DateTimeZone($timezone));
    $times = $pt->getTimes($date, $latitude, $longitude, null, PrayerTimes2::LATITUDE_ADJUSTMENT_METHOD_ANGLE, null, PrayerTimes2::TIME_FORMAT_24H);
    
    echo "Prayer Times Test for New York City (2025-01-15):\n";
    echo "=================================================\n";
    
    if (is_array($times)) {
        foreach ($times as $prayer => $time) {
            echo sprintf("%-12s: %s\n", $prayer, $time);
        }
        echo "\nTest PASSED: Times generated successfully!\n";
    } else {
        echo "Test FAILED: Invalid times returned\n";
    }
    
    // Test method change
    $pt->setMethod(Method::METHOD_MWL);
    $times2 = $pt->getTimes($date, $latitude, $longitude);
    
    echo "\nMWL Method Test:\n";
    echo "================\n";
    if (is_array($times2)) {
        foreach ($times2 as $prayer => $time) {
            echo sprintf("%-12s: %s\n", $prayer, $time);
        }
        echo "\nMethod change test PASSED!\n";
    } else {
        echo "Method change test FAILED\n";
    }
    
    // Test tuning
    $pt->tune(0, 5, 0, 0, 0, 0, 0, -5, 0); // +5 min to Fajr, -5 min to Isha
    $times3 = $pt->getTimes($date, $latitude, $longitude);
    
    echo "\nTuning Test (+5 min Fajr, -5 min Isha):\n";
    echo "======================================\n";
    if (is_array($times3)) {
        foreach ($times3 as $prayer => $time) {
            echo sprintf("%-12s: %s\n", $prayer, $time);
        }
        echo "\nTuning test PASSED!\n";
    } else {
        echo "Tuning test FAILED\n";
    }
    
    echo "\n=== ALL TESTS COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "Test FAILED with exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}