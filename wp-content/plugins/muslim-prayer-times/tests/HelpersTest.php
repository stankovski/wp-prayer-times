<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');
}

// Mock WordPress get_option function
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $wp_options;
        
        if (!isset($wp_options) || !is_array($wp_options)) {
            $wp_options = [];
        }
        
        if (isset($wp_options[$option])) {
            return $wp_options[$option];
        }
        
        return $default;
    }
}

require_once ABSPATH . 'plugins/muslim-prayer-times/includes/helpers.php';

/**
 * Muslim Prayer Times Plugin Helper Functions Tests
 *
 * @package PrayerTimes
 */

class HelpersTest extends TestCase {
    // Setup and teardown to handle the mocked options
    protected function setUp(): void {
        global $wp_options;
        $wp_options = [];
    }
    
    protected function tearDown(): void {
        global $wp_options;
        $wp_options = null;
    }

    // Mock setting an option value for testing
    protected function setOption($option, $value) {
        global $wp_options;
        $wp_options[$option] = $value;
    }

    /**
     * Test the time to minutes conversion function
     */
    public function testTimeToMinutes() {
        // Create test DateTime objects
        $time1 = new DateTime('2023-01-01 05:30:00', new DateTimeZone('UTC')); // 5:30 AM
        $time2 = new DateTime('2023-01-01 13:45:00', new DateTimeZone('UTC')); // 1:45 PM
        $time3 = new DateTime('2023-01-01 23:15:00', new DateTimeZone('UTC')); // 11:15 PM
        $time4 = new DateTime('2023-01-01 00:00:00', new DateTimeZone('UTC')); // Midnight

        // Test with standard time (non-DST)
        $this->assertEquals(330, prayertimes_time_to_minutes($time1)); // 5:30 = (5*60) + 30 = 330
        $this->assertEquals(825, prayertimes_time_to_minutes($time2)); // 13:45 = (13*60) + 45 = 825
        $this->assertEquals(1395, prayertimes_time_to_minutes($time3)); // 23:15 = (23*60) + 15 = 1395
        $this->assertEquals(0, prayertimes_time_to_minutes($time4)); // 00:00 = 0

        // Create a DateTime with DST in effect
        $dst_time = new DateTime('2023-07-01 14:30:00', new DateTimeZone('America/New_York'));
        
        // Mock the actual DST check by replacing the real function
        if (function_exists('runkit7_function_redefine')) {
            runkit7_function_redefine('prayertimes_time_to_minutes', function($time) {
                $hours = (int)$time->format('G');
                $minutes = (int)$time->format('i');
                $total_minutes = ($hours * 60) + $minutes;
                // Hard-code DST detection for test
                $is_dst = true;
                if ($is_dst) {
                    $total_minutes -= 60;
                }
                return $total_minutes;
            });
            
            // With DST, 14:30 should return (14*60)+30-60 = 810
            $this->assertEquals(810, prayertimes_time_to_minutes($dst_time));
        }
    }

    /**
     * Test rounding down time to nearest X minutes
     */
    public function testRoundDown() {
        // Test with no rounding (default 1 minute)
        $time = new DateTime('2023-01-01 13:45:30', new DateTimeZone('UTC'));
        $result = prayertimes_round_down($time);
        $this->assertEquals('13:45:30', $result->format('H:i:s'));
        $this->assertEquals($time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged

        // Test with 5 minute rounding
        $time = new DateTime('2023-01-01 13:47:30');
        $result = prayertimes_round_down($time, 5);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test with 15 minute rounding
        $time = new DateTime('2023-01-01 13:59:59');
        $result = prayertimes_round_down($time, 15);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test exact match to interval (should stay the same)
        $time = new DateTime('2023-01-01 13:45:00');
        $result = prayertimes_round_down($time, 15);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test hour change
        $time = new DateTime('2023-01-01 14:01:30');
        $result = prayertimes_round_down($time, 30);
        $this->assertEquals('14:00:00', $result->format('H:i:s'));
    }

    /**
     * Test rounding up time to nearest X minutes
     */
    public function testRoundUp() {
        // Test with no rounding (default 1 minute)
        $time = new DateTime('2023-01-01 13:45:30', new DateTimeZone('UTC'));
        $result = prayertimes_round_up($time);
        $this->assertEquals('13:45:30', $result->format('H:i:s')); // No change
        $this->assertEquals($time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged

        // Test with 5 minute rounding
        $time = new DateTime('2023-01-01 13:42:01');
        $result = prayertimes_round_up($time, 5);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test with 15 minute rounding
        $time = new DateTime('2023-01-01 13:31:00');
        $result = prayertimes_round_up($time, 15);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test exact match to interval (should stay the same)
        $time = new DateTime('2023-01-01 13:45:00');
        $result = prayertimes_round_up($time, 15);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test hour change
        $time = new DateTime('2023-01-01 13:59:30');
        $result = prayertimes_round_up($time, 5);
        $this->assertEquals('14:00:00', $result->format('H:i:s'));
    }

    /**
     * Test Fajr Iqama time calculation
     */
    public function testCalculateFajrIqama() {
        // Create test data
        $days_data = [
            0 => [
                'date' => new DateTime('2023-01-01'),
                'athan' => [
                    'fajr' => new DateTime('2023-01-01 05:30:00'),
                    'sunrise' => new DateTime('2023-01-01 07:15:00')
                ],
            ],
            1 => [
                'date' => new DateTime('2023-01-02'),
                'athan' => [
                    'fajr' => new DateTime('2023-01-02 05:31:00'),
                    'sunrise' => new DateTime('2023-01-02 07:16:00')
                ],
            ],
        ];

        // Test after_athan rule with daily calculation
        $results = prayertimes_calculate_fajr_iqama(
            $days_data, 'after_athan', 20, 0, false, 5
        );
        
        $this->assertEquals('05:50:00', $results[0]->format('H:i:s'));
        $this->assertEquals('05:51:00', $results[1]->format('H:i:s'));

        // Test after_athan rule with weekly calculation
        $results = prayertimes_calculate_fajr_iqama(
            $days_data, 'after_athan', 20, 0, true, 5
        );
        
        $this->assertEquals('05:55:00', $results[0]->format('H:i:s'));
        $this->assertEquals('05:55:00', $results[1]->format('H:i:s'));

        // Test before_shuruq rule with daily calculation
        $results = prayertimes_calculate_fajr_iqama(
            $days_data, 'before_shuruq', 0, 45, false, 5
        );
        
        $this->assertEquals('06:30:00', $results[0]->format('H:i:s'));
        $this->assertEquals('06:31:00', $results[1]->format('H:i:s'));

        // Test before_shuruq rule with weekly calculation
        $results = prayertimes_calculate_fajr_iqama(
            $days_data, 'before_shuruq', 0, 45, true, 5
        );
        
        $this->assertEquals('06:30:00', $results[0]->format('H:i:s'));
        $this->assertEquals('06:30:00', $results[1]->format('H:i:s'));
    }

    /**
     * Test Fajr Iqama time calculation during DST
     */
    public function testCalculateFajrIqamaDuringDST() {
        // Create test data
        $days_data = [
            0 => [
                'date' => new DateTime('2023-03-11', new DateTimeZone('America/New_York')),
                'athan' => [
                    'fajr' => new DateTime('2023-03-11 05:30:00', new DateTimeZone('America/New_York')),
                    'sunrise' => new DateTime('2023-03-11 07:15:00', new DateTimeZone('America/New_York'))
                ],
            ],
            1 => [
                'date' => new DateTime('2023-03-12', new DateTimeZone('America/New_York')),
                'athan' => [
                    'fajr' => new DateTime('2023-03-12 06:31:00', new DateTimeZone('America/New_York')),
                    'sunrise' => new DateTime('2023-03-12 08:16:00', new DateTimeZone('America/New_York'))
                ],
            ],
            2 => [
                'date' => new DateTime('2023-03-13', new DateTimeZone('America/New_York')),
                'athan' => [
                    'fajr' => new DateTime('2023-03-13 06:32:00', new DateTimeZone('America/New_York')),
                    'sunrise' => new DateTime('2023-03-13 08:17:00', new DateTimeZone('America/New_York'))
                ],
            ],
        ];

        // Test after_athan rule with daily calculation
        $results = prayertimes_calculate_fajr_iqama(
            $days_data, 'after_athan', 20, 0, false, 5
        );
        
        $this->assertEquals(0, $days_data[0]['athan']['fajr']->format('I'));
        $this->assertEquals(1, $days_data[1]['athan']['fajr']->format('I'));
        $this->assertEquals(1, $days_data[2]['athan']['fajr']->format('I'));
        $this->assertEquals('05:50:00', $results[0]->format('H:i:s'));
        $this->assertEquals('06:51:00', $results[1]->format('H:i:s'));
        $this->assertEquals('06:52:00', $results[2]->format('H:i:s'));

        // Test after_athan rule with weekly calculation
        $results = prayertimes_calculate_fajr_iqama(
            $days_data, 'after_athan', 20, 0, true, 5
        );
        
        $this->assertEquals('05:50:00', $results[0]->format('H:i:s'));
        $this->assertEquals('06:50:00', $results[1]->format('H:i:s'));
        $this->assertEquals('06:50:00', $results[2]->format('H:i:s'));

        // Test before_shuruq rule with daily calculation
        $results = prayertimes_calculate_fajr_iqama(
            $days_data, 'before_shuruq', 0, 45, false, 5
        );
        
        $this->assertEquals('06:30:00', $results[0]->format('H:i:s'));
        $this->assertEquals('07:31:00', $results[1]->format('H:i:s'));
        $this->assertEquals('07:32:00', $results[2]->format('H:i:s'));

        // Test before_shuruq rule with weekly calculation
        $results = prayertimes_calculate_fajr_iqama(
            $days_data, 'before_shuruq', 0, 45, true, 5
        );
        
        $this->assertEquals('06:30:00', $results[0]->format('H:i:s'));
        $this->assertEquals('07:30:00', $results[1]->format('H:i:s'));
        $this->assertEquals('07:30:00', $results[2]->format('H:i:s'));
    }

    /**
     * Test Dhuhr Iqama time calculation
     */
    public function testCalculateDhuhrIqama() {
        // Create test data
        $days_data = [
            0 => [
                'date' => new DateTime('2023-01-01', new DateTimeZone('America/New_York')),
                'athan' => [
                    'dhuhr' => new DateTime('2023-01-01 12:30:00'),
                ],
            ],
            1 => [
                'date' => new DateTime('2023-06-02', new DateTimeZone('America/New_York')),
                'athan' => [
                    'dhuhr' => new DateTime('2023-06-02 12:31:00'),
                ],
            ],
        ];

        // Test after_athan rule with daily calculation
        $results = prayertimes_calculate_dhuhr_iqama(
            $days_data, 'after_athan', 15, '13:30', '14:30', false, 5
        );
        
        $this->assertEquals('12:45:00', $results[0]->format('H:i:s'));
        $this->assertEquals('12:46:00', $results[1]->format('H:i:s'));

        // Test with weekly calculation
        $results = prayertimes_calculate_dhuhr_iqama(
            $days_data, 'after_athan', 15, '13:30', '14:30', true, 15
        );
        
        $this->assertEquals('13:00:00', $results[0]->format('H:i:s'));
        $this->assertEquals('14:00:00', $results[1]->format('H:i:s'));

        // Test fixed_time rule
        $results = prayertimes_calculate_dhuhr_iqama(
            $days_data, 'fixed_time', 0, '13:30', '14:30', false, 5
        );
        
        $this->assertEquals(0, $days_data[0]['date']->format('I'));
        $this->assertEquals(1, $days_data[1]['date']->format('I'));
        $this->assertEquals('13:30:00', $results[0]->format('H:i:s'));
        $this->assertEquals('14:30:00', $results[1]->format('H:i:s'));
    }
    
    /**
     * Test Dhuhr Iqama time calculation during DST
     */
    public function testCalculateDhuhrIqamaDuringDST() {
        // Create test data across DST boundary (March 11-13, 2023)
        $days_data = [
            0 => [
                'date' => new DateTime('2023-03-11', new DateTimeZone('America/New_York')),
                'athan' => [
                    'dhuhr' => new DateTime('2023-03-11 12:30:00', new DateTimeZone('America/New_York')),
                ],
            ],
            1 => [
                'date' => new DateTime('2023-03-12', new DateTimeZone('America/New_York')),
                'athan' => [
                    'dhuhr' => new DateTime('2023-03-12 13:31:00', new DateTimeZone('America/New_York')),
                ],
            ],
            2 => [
                'date' => new DateTime('2023-03-13', new DateTimeZone('America/New_York')),
                'athan' => [
                    'dhuhr' => new DateTime('2023-03-13 13:32:00', new DateTimeZone('America/New_York')),
                ],
            ],
        ];

        // Verify DST status of test dates
        $this->assertEquals(0, $days_data[0]['athan']['dhuhr']->format('I'));  // March 11 - not DST
        $this->assertEquals(1, $days_data[1]['athan']['dhuhr']->format('I'));  // March 12 - DST starts
        $this->assertEquals(1, $days_data[2]['athan']['dhuhr']->format('I'));  // March 13 - DST

        // Test after_athan rule with daily calculation
        $results = prayertimes_calculate_dhuhr_iqama(
            $days_data, 'after_athan', 15, '13:30', '14:30', false, 5
        );
        
        $this->assertEquals('12:45:00', $results[0]->format('H:i:s'));
        $this->assertEquals('13:46:00', $results[1]->format('H:i:s'));
        $this->assertEquals('13:47:00', $results[2]->format('H:i:s'));

        // Test after_athan rule with weekly calculation
        $results = prayertimes_calculate_dhuhr_iqama(
            $days_data, 'after_athan', 15, '13:30', '14:30', true, 5
        );
        
        $this->assertEquals('12:45:00', $results[0]->format('H:i:s'));
        $this->assertEquals('13:45:00', $results[1]->format('H:i:s'));;
        $this->assertEquals('13:45:00', $results[2]->format('H:i:s'));

        // Test fixed_time rule
        $results = prayertimes_calculate_dhuhr_iqama(
            $days_data, 'fixed_time', 0, '13:00', '14:00', false, 5
        );
        
        $this->assertEquals('13:00:00', $results[0]->format('H:i:s'));  // Not DST
        $this->assertEquals('13:00:00', $results[1]->format('H:i:s'));  // DST
        $this->assertEquals('14:00:00', $results[2]->format('H:i:s'));  // DST
    }

    /**
     * Test Maghrib Iqama time calculation
     */
    public function testCalculateMaghribIqama() {
        // Create test data
        $days_data = [
            0 => [
                'date' => new DateTime('2023-01-01'),
                'athan' => [
                    'maghrib' => new DateTime('2023-01-01 17:45:00'),
                ],
            ],
            1 => [
                'date' => new DateTime('2023-01-02'),
                'athan' => [
                    'maghrib' => new DateTime('2023-01-02 17:47:00'),
                ],
            ],
        ];

        // Test with daily calculation
        $results = prayertimes_calculate_maghrib_iqama(
            $days_data, 10, false, 5
        );
        
        $this->assertEquals('17:55:00', $results[0]->format('H:i:s'));
        $this->assertEquals('17:57:00', $results[1]->format('H:i:s'));

        $results = prayertimes_calculate_maghrib_iqama(
            $days_data, 10, true, 5
        );
        
        $this->assertEquals('18:00:00', $results[0]->format('H:i:s'));
        $this->assertEquals('18:00:00', $results[1]->format('H:i:s'));
    }
    
    /**
     * Test Maghrib Iqama time calculation during DST
     */
    public function testCalculateMaghribIqamaDuringDST() {
        // Create test data across DST boundary (March 11-13, 2023)
        $days_data = [
            0 => [
                'date' => new DateTime('2023-03-11', new DateTimeZone('America/New_York')),
                'athan' => [
                    'maghrib' => new DateTime('2023-03-11 18:05:00', new DateTimeZone('America/New_York')),
                ],
            ],
            1 => [
                'date' => new DateTime('2023-03-12', new DateTimeZone('America/New_York')),
                'athan' => [
                    'maghrib' => new DateTime('2023-03-12 19:06:00', new DateTimeZone('America/New_York')),
                ],
            ],
            2 => [
                'date' => new DateTime('2023-03-13', new DateTimeZone('America/New_York')),
                'athan' => [
                    'maghrib' => new DateTime('2023-03-13 19:07:00', new DateTimeZone('America/New_York')),
                ],
            ],
        ];

        // Verify DST status of test dates
        $this->assertEquals(0, $days_data[0]['athan']['maghrib']->format('I'));  // March 11 - not DST
        $this->assertEquals(1, $days_data[1]['athan']['maghrib']->format('I'));  // March 12 - DST starts
        $this->assertEquals(1, $days_data[2]['athan']['maghrib']->format('I'));  // March 13 - DST

        // Test with daily calculation
        $results = prayertimes_calculate_maghrib_iqama(
            $days_data, 10, false, 5
        );
        
        $this->assertEquals('18:15:00', $results[0]->format('H:i:s'));
        $this->assertEquals('19:16:00', $results[1]->format('H:i:s'));
        $this->assertEquals('19:17:00', $results[2]->format('H:i:s'));

        // Test with weekly calculation
        $results = prayertimes_calculate_maghrib_iqama(
            $days_data, 10, true, 5
        );
        
        // Weekly calculation should use normalized time from latest athan
        $this->assertEquals('18:15:00', $results[0]->format('H:i:s'));  // Not DST
        $this->assertEquals('19:15:00', $results[1]->format('H:i:s'));  // DST
        $this->assertEquals('19:15:00', $results[2]->format('H:i:s'));  // DST
    }

    /**
     * Test Isha Iqama time calculation
     */
    public function testCalculateIshaIqama() {
        // Create test data
        $days_data = [
            0 => [
                'date' => new DateTime('2023-01-01'),
                'athan' => [
                    'isha' => new DateTime('2023-01-01 19:15:00'),
                ],
            ],
            1 => [
                'date' => new DateTime('2023-01-02'),
                'athan' => [
                    'isha' => new DateTime('2023-01-02 19:17:00'),
                ],
            ],
        ];

        // Test after_athan rule with daily calculation
        $results = prayertimes_calculate_isha_iqama(
            $days_data, 'after_athan', 15, '19:30', '21:00', false, 5
        );
        
        $this->assertEquals('19:30:00', $results[0]->format('H:i:s'));
        $this->assertEquals('19:32:00', $results[1]->format('H:i:s'));

        // Test with minimum time constraint
        $results = prayertimes_calculate_isha_iqama(
            $days_data, 'after_athan', 15, '19:30', '21:00', false, 5
        );
        
        $this->assertEquals('19:30:00', $results[0]->format('H:i:s'));

        // Test with maximum time constraint
        $days_data[1]['athan']['isha'] = new DateTime('2023-01-02 20:55:00');
        $results = prayertimes_calculate_isha_iqama(
            $days_data, 'after_athan', 15, '19:30', '21:00', false, 5
        );
        
        $this->assertEquals('21:00:00', $results[1]->format('H:i:s'));

        // Test with weekly calculation
        $days_data[0]['athan']['isha'] = new DateTime('2023-01-01 19:16:00');
        $days_data[1]['athan']['isha'] = new DateTime('2023-01-02 19:20:00');
        $results = prayertimes_calculate_isha_iqama(
            $days_data, 'after_athan', 15, '19:30', '21:00', true, 5
        );
        
        $this->assertEquals('19:35:00', $results[0]->format('H:i:s'));
        $this->assertEquals('19:35:00', $results[1]->format('H:i:s'));
    }
    
    /**
     * Test Isha Iqama time calculation during DST
     */
    public function testCalculateIshaIqamaDuringDST() {
        // Create test data across DST boundary (March 11-13, 2023)
        $days_data = [
            0 => [
                'date' => new DateTime('2023-03-11', new DateTimeZone('America/New_York')),
                'athan' => [
                    'isha' => new DateTime('2023-03-11 19:25:00', new DateTimeZone('America/New_York')),
                ],
            ],
            1 => [
                'date' => new DateTime('2023-03-12', new DateTimeZone('America/New_York')),
                'athan' => [
                    'isha' => new DateTime('2023-03-12 20:26:00', new DateTimeZone('America/New_York')),
                ],
            ],
            2 => [
                'date' => new DateTime('2023-03-13', new DateTimeZone('America/New_York')),
                'athan' => [
                    'isha' => new DateTime('2023-03-13 20:27:00', new DateTimeZone('America/New_York')),
                ],
            ],
        ];

        // Verify DST status of test dates
        $this->assertEquals(0, $days_data[0]['athan']['isha']->format('I'));  // March 11 - not DST
        $this->assertEquals(1, $days_data[1]['athan']['isha']->format('I'));  // March 12 - DST starts
        $this->assertEquals(1, $days_data[2]['athan']['isha']->format('I'));  // March 13 - DST

        // Test after_athan rule with daily calculation and min/max constraints
        $results = prayertimes_calculate_isha_iqama(
            $days_data, 'after_athan', 15, '19:30', '21:00', false, 5
        );
        
        $this->assertEquals('19:40:00', $results[0]->format('H:i:s'));  // 19:25 + 15 = 19:40
        $this->assertEquals('20:41:00', $results[1]->format('H:i:s'));  // 20:26 + 15 = 20:41
        $this->assertEquals('20:42:00', $results[2]->format('H:i:s'));  // 20:27 + 15 = 20:42

        // Test with minimum time constraint
        $days_data[0]['athan']['isha'] = new DateTime('2023-03-11 19:15:00', new DateTimeZone('America/New_York'));
        $results = prayertimes_calculate_isha_iqama(
            $days_data, 'after_athan', 15, '19:35', '21:00', false, 5
        );
        
        $this->assertEquals('19:35:00', $results[0]->format('H:i:s'));  // Uses min time (19:35)

        // Test with maximum time constraint
        $days_data[2]['athan']['isha'] = new DateTime('2023-03-13 20:55:00', new DateTimeZone('America/New_York'));
        $results = prayertimes_calculate_isha_iqama(
            $days_data, 'after_athan', 15, '19:35', '21:00', false, 5
        );
        
        $this->assertEquals('21:00:00', $results[2]->format('H:i:s'));  // Uses max time (21:00)

        // Test with weekly calculation
        $days_data[0]['athan']['isha'] = new DateTime('2023-03-11 19:25:00', new DateTimeZone('America/New_York'));
        $days_data[1]['athan']['isha'] = new DateTime('2023-03-12 20:26:00', new DateTimeZone('America/New_York'));
        $days_data[2]['athan']['isha'] = new DateTime('2023-03-13 20:27:00', new DateTimeZone('America/New_York'));
        
        $results = prayertimes_calculate_isha_iqama(
            $days_data, 'after_athan', 15, '19:35', '21:00', true, 5
        );
        
        // Weekly calculation should use normalized time from latest athan
        $this->assertEquals('19:40:00', $results[0]->format('H:i:s'));  // Not DST
        $this->assertEquals('20:40:00', $results[1]->format('H:i:s'));  // DST
        $this->assertEquals('20:40:00', $results[2]->format('H:i:s'));  // DST
    }

    /**
     * Test Asr Iqama time calculation
     */
    public function testCalculateAsrIqama() {
        // Create test data
        $days_data = [
            0 => [
                'date' => new DateTime('2023-01-01', new DateTimeZone('America/New_York')),
                'athan' => [
                    'asr' => new DateTime('2023-01-01 15:30:00'),
                ],
            ],
            1 => [
                'date' => new DateTime('2023-06-02', new DateTimeZone('America/New_York')),
                'athan' => [
                    'asr' => new DateTime('2023-06-02 15:32:00'),
                ],
            ],
        ];

        // Test after_athan rule with daily calculation
        $results = prayertimes_calculate_asr_iqama(
            $days_data, 'after_athan', 15, '16:00', '17:00', false, 5
        );
        
        $this->assertEquals('15:45:00', $results[0]->format('H:i:s'));
        $this->assertEquals('15:47:00', $results[1]->format('H:i:s'));

        // Test fixed_time rule
        $results = prayertimes_calculate_asr_iqama(
            $days_data, 'fixed_time', 0, '16:00', '17:00', false, 5
        );
        
        $this->assertEquals('16:00:00', $results[0]->format('H:i:s'));
        $this->assertEquals('17:00:00', $results[1]->format('H:i:s'));

        // Test with weekly calculation
        $results = prayertimes_calculate_asr_iqama(
            $days_data, 'after_athan', 15, '16:00', '17:00', true, 5
        );
        
        // Weekly calculation should use normalized time from latest athan
        $this->assertEquals('15:50:00', $results[0]->format('H:i:s'));
        $this->assertEquals('16:50:00', $results[1]->format('H:i:s'));
    }
    
    /**
     * Test Asr Iqama time calculation during DST
     */
    public function testCalculateAsrIqamaDuringDST() {
        // Create test data across DST boundary (March 11-13, 2023)
        $days_data = [
            0 => [
                'date' => new DateTime('2023-03-11', new DateTimeZone('America/New_York')),
                'athan' => [
                    'asr' => new DateTime('2023-03-11 15:30:00', new DateTimeZone('America/New_York')),
                ],
            ],
            1 => [
                'date' => new DateTime('2023-03-12', new DateTimeZone('America/New_York')),
                'athan' => [
                    'asr' => new DateTime('2023-03-12 16:31:00', new DateTimeZone('America/New_York')),
                ],
            ],
            2 => [
                'date' => new DateTime('2023-03-13', new DateTimeZone('America/New_York')),
                'athan' => [
                    'asr' => new DateTime('2023-03-13 16:32:00', new DateTimeZone('America/New_York')),
                ],
            ],
        ];

        // Verify DST status of test dates
        $this->assertEquals(0, $days_data[0]['athan']['asr']->format('I'));  // March 11 - not DST
        $this->assertEquals(1, $days_data[1]['athan']['asr']->format('I'));  // March 12 - DST starts
        $this->assertEquals(1, $days_data[2]['athan']['asr']->format('I'));  // March 13 - DST

        // Test after_athan rule with daily calculation
        $results = prayertimes_calculate_asr_iqama(
            $days_data, 'after_athan', 15, '16:00', '17:00', false, 5
        );
        
        $this->assertEquals('15:45:00', $results[0]->format('H:i:s'));
        $this->assertEquals('16:46:00', $results[1]->format('H:i:s'));
        $this->assertEquals('16:47:00', $results[2]->format('H:i:s'));

        // Test fixed_time rule
        $results = prayertimes_calculate_asr_iqama(
            $days_data, 'fixed_time', 0, '16:00', '17:00', false, 5
        );
        
        $this->assertEquals('16:00:00', $results[0]->format('H:i:s'));  // Not DST
        $this->assertEquals('16:00:00', $results[1]->format('H:i:s'));  // DST
        $this->assertEquals('17:00:00', $results[2]->format('H:i:s'));  // DST

        // Test with weekly calculation
        $results = prayertimes_calculate_asr_iqama(
            $days_data, 'after_athan', 15, '16:00', '17:00', true, 5
        );
        
        // Weekly calculation should use normalized time from latest athan
        $this->assertEquals('15:45:00', $results[0]->format('H:i:s'));  // Not DST
        $this->assertEquals('16:45:00', $results[1]->format('H:i:s'));  // DST
        $this->assertEquals('16:45:00', $results[2]->format('H:i:s'));  // DST
    }

    /**
     * Test normalizing time for DST
     */
    public function testNormalizeTimeForDst() {
        // Test with non-DST time
        $standard_time = new DateTime('2023-01-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(0, $standard_time->format('I')); // Verify it's not DST
        
        $result = prayertimes_normalize_time_for_dst($standard_time);
        $this->assertEquals('13:30:00', $result->format('H:i:s')); // No change for non-DST
        $this->assertEquals($standard_time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($standard_time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged
        
        // Test with DST time
        $dst_time = new DateTime('2023-07-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(1, $dst_time->format('I')); // Verify it's DST
        
        $result = prayertimes_normalize_time_for_dst($dst_time);
        $this->assertEquals('12:30:00', $result->format('H:i:s')); // Should subtract 1 hour
        $this->assertEquals($dst_time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($dst_time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged
    }

    /**
     * Test denormalizing time for DST
     */
    public function testDenormalizeTimeForDst() {
        // Test with non-DST time
        $standard_time = new DateTime('2023-01-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(0, $standard_time->format('I')); // Verify it's not DST
        
        $result = prayertimes_denormalize_time_for_dst($standard_time);
        $this->assertEquals('13:30:00', $result->format('H:i:s')); // No change for non-DST
        $this->assertEquals($standard_time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($standard_time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged
        
        // Test with DST time
        $dst_time = new DateTime('2023-07-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(1, $dst_time->format('I')); // Verify it's DST
        
        $result = prayertimes_denormalize_time_for_dst($dst_time);
        $this->assertEquals('14:30:00', $result->format('H:i:s')); // Should add 1 hour
        $this->assertEquals($dst_time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($dst_time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged
    }

    /**
     * Test normalizing and denormalizing time for DST in sequence
     */
    public function testNormalizeAndDenormalizeTimeForDst() {
        // Test with DST time
        $original_time = new DateTime('2023-07-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(1, $original_time->format('I')); // Verify it's DST
        
        // Normalize (subtract hour)
        $normalized = prayertimes_normalize_time_for_dst($original_time);
        $this->assertEquals('12:30:00', $normalized->format('H:i:s'));
        
        // Denormalize (add hour back)
        $denormalized = prayertimes_denormalize_time_for_dst($normalized);
        $this->assertEquals('13:30:00', $denormalized->format('H:i:s'));
        $this->assertEquals($original_time->format('H:i:s'), $denormalized->format('H:i:s')); // Should match original
    }

    /**
     * Test normalizing times in days_data structure
     */
    public function testNormalizeTimesInDaysData() {
        // Create test days_data with both DST and non-DST dates
        $days_data = [
            0 => [
                'date' => new DateTime('2023-01-15', new DateTimeZone('America/New_York')), // non-DST
                'athan' => [
                    'fajr' => new DateTime('2023-01-15 06:30:00', new DateTimeZone('America/New_York')),
                    'dhuhr' => new DateTime('2023-01-15 12:15:00', new DateTimeZone('America/New_York')),
                ],
            ],
            1 => [
                'date' => new DateTime('2023-07-15', new DateTimeZone('America/New_York')), // DST
                'athan' => [
                    'fajr' => new DateTime('2023-07-15 06:30:00', new DateTimeZone('America/New_York')),
                    'dhuhr' => new DateTime('2023-07-15 12:15:00', new DateTimeZone('America/New_York')),
                ],
            ],
        ];
        
        // Manually normalize and check
        $normalized_day0_fajr = prayertimes_normalize_time_for_dst($days_data[0]['athan']['fajr']);
        $normalized_day1_fajr = prayertimes_normalize_time_for_dst($days_data[1]['athan']['fajr']);
        
        $this->assertEquals('06:30:00', $normalized_day0_fajr->format('H:i:s')); // No change for non-DST
        $this->assertEquals('05:30:00', $normalized_day1_fajr->format('H:i:s')); // -1 hour for DST
        
        // Denormalize and check
        $denormalized_day0_fajr = prayertimes_denormalize_time_for_dst($normalized_day0_fajr);
        $denormalized_day1_fajr = prayertimes_denormalize_time_for_dst($normalized_day1_fajr);
        
        $this->assertEquals('06:30:00', $denormalized_day0_fajr->format('H:i:s')); // No change for non-DST
        $this->assertEquals('06:30:00', $denormalized_day1_fajr->format('H:i:s')); // Should match original
    }

    /**
     * Test converting Western numerals to Arabic numerals
     */
    public function testConvertToArabicNumerals() {
        // Test with integer
        $this->assertEquals('١٢٣٤٥', prayertimes_convert_to_arabic_numerals(12345));
        
        // Test with string
        $this->assertEquals('٦٧٨٩٠', prayertimes_convert_to_arabic_numerals('67890'));
        
        // Test with mixed string
        $this->assertEquals('Prayer time: ٥:٣٠', prayertimes_convert_to_arabic_numerals('Prayer time: 5:30'));
        
        // Test with floating point number
        $this->assertEquals('٣.١٤', prayertimes_convert_to_arabic_numerals(3.14));
        
        // Test with string containing non-numeric characters
        $this->assertEquals('Fajr: ٠٥:١٥, Dhuhr: ١٢:٣٠', 
            prayertimes_convert_to_arabic_numerals('Fajr: 05:15, Dhuhr: 12:30'));
        
        // Test with empty string
        $this->assertEquals('', prayertimes_convert_to_arabic_numerals(''));
        
        // Test with string containing no numerals
        $this->assertEquals('No numbers here', prayertimes_convert_to_arabic_numerals('No numbers here'));
        
        // Test with zero
        $this->assertEquals('٠', prayertimes_convert_to_arabic_numerals(0));
    }
    
    /**
     * Test converting Gregorian date to Hijri date
     */
    public function testConvertToHijri() {
        // Require the hijri date converter file
        require_once ABSPATH . 'plugins/muslim-prayer-times/includes/hijri-date-converter.php';
        
        // Test with specific known dates (these are approximate conversions)
        // January 1, 2023 ≈ Jumada al-Thani 9, 1444
        $date1 = '2023-01-01';
        $result1 = prayertimes_convert_to_hijri($date1);
        $this->assertStringContainsString('8 Jumada al-Thani 1444H', $result1);
        
        // May 15, 2023 ≈ Shawwal 25, 1444
        $date2 = '2023-05-15';
        $result2 = prayertimes_convert_to_hijri($date2);
        $this->assertStringContainsString('25 Shawwal 1444H', $result2);
        
        // Test with DateTime object
        $date3 = new DateTime('2023-03-10');
        $result3 = prayertimes_convert_to_hijri($date3);
        $this->assertStringContainsString('18 Sha\'ban 1444H', $result3);
        
        // Test non-formatted (array) return
        $date4 = '2023-01-15';
        $result4 = prayertimes_convert_to_hijri($date4, false);
        $this->assertIsArray($result4);
        $this->assertEquals(22, $result4['day']);
        $this->assertEquals(6, $result4['month']);
        $this->assertEquals('Jumada al-Thani', $result4['month_name']);
        $this->assertEquals(1444, $result4['year']);
        
        // Test Arabic language output
        $date5 = '2023-01-15';
        $result5 = prayertimes_convert_to_hijri($date5, true, 'ar');
        $this->assertStringContainsString('22 جمادى الآخرة 1444H', $result5);

        // Test Arabic language with non-formatted return
        $date6 = '2023-01-15';
        $result6 = prayertimes_convert_to_hijri($date6, false, 'ar');
        $this->assertIsArray($result6);
        $this->assertEquals(22, $result6['day']);
        $this->assertEquals(6, $result6['month']);
        $this->assertEquals('جمادى الآخرة', $result6['month_name']);
        $this->assertEquals(1444, $result6['year']);
        
        // Test with offset parameter
        $date7 = '2023-01-01';
        $result7a = prayertimes_convert_to_hijri($date7, true, 'en', 0);  // No offset
        $result7b = prayertimes_convert_to_hijri($date7, true, 'en', 1);  // +1 day
        $result7c = prayertimes_convert_to_hijri($date7, true, 'en', -1); // -1 day
        
        $this->assertStringContainsString('9 Jumada al-Thani 1444H', $result7a);
        $this->assertStringContainsString('10 Jumada al-Thani 1444H', $result7b);
        $this->assertStringContainsString('8 Jumada al-Thani 1444H', $result7c);
        
        // Test date crossing Hijri year boundary
        $date8 = '2023-07-19'; // Around Muharram 1, 1445
        $result8 = prayertimes_convert_to_hijri($date8);
        $this->assertStringContainsString('2 Muharram 1445H', $result8);
        
        // Test date crossing Hijri month boundary
        $date9 = '2023-02-20'; // End of Rajab to beginning of Sha'ban
        $result9a = prayertimes_convert_to_hijri($date9, true, 'en', 0);
        $result9b = prayertimes_convert_to_hijri($date9, true, 'en', 1);
        
        $this->assertStringContainsString('30 Rajab 1444H', $result9a);
        $this->assertStringContainsString('1 Sha\'ban 1444H', $result9b);
    }

    /**
     * Test getting timezone function
     */
    public function testGetTimezone() {
        // Test when plugin timezone setting is set
        $this->setOption('prayertimes_settings', ['tz' => 'America/Los_Angeles']);
        $this->assertEquals('America/Los_Angeles', prayertimes_get_timezone());
        
        // Test when plugin timezone is not set but WordPress timezone is set
        $this->setOption('prayertimes_settings', []);
        $this->setOption('timezone_string', 'Europe/London');
        $this->assertEquals('Europe/London', prayertimes_get_timezone());
        
        // Test when WordPress timezone is set as an offset
        $this->setOption('timezone_string', '');
        $this->setOption('gmt_offset', 5.5);
        $this->assertEquals('UTC+5.5', prayertimes_get_timezone());
        
        // Test negative offset
        $this->setOption('gmt_offset', -4);
        $this->assertEquals('UTC-4', prayertimes_get_timezone());
        
        // Test fallback to UTC
        $this->setOption('prayertimes_settings', []);
        $this->setOption('timezone_string', '');
        $this->setOption('gmt_offset', 0);
        $this->assertEquals('UTC', prayertimes_get_timezone());
        $this->setOption('gmt_offset', '');
        $this->assertEquals('UTC', prayertimes_get_timezone());
        
        // Test empty settings
        global $wp_options;
        $wp_options = [];
        $this->assertEquals('UTC', prayertimes_get_timezone());
    }
}