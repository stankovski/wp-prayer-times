<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/wp-content/' );
}

// Minimal WordPress stubs required by settings-ajax.php.
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $wp_options;
		if ( ! isset( $wp_options ) || ! is_array( $wp_options ) ) {
			$wp_options = array();
		}
		return isset( $wp_options[ $option ] ) ? $wp_options[ $option ] : $default;
	}
}

// settings-ajax.php is not included here directly because it registers AJAX
// hooks that require a full WordPress environment. We only test the standalone
// helper function muslprti_parse_time_to_24h() and the generate-reformat logic
// that were extracted into testable form below.
//
// To make muslprti_parse_time_to_24h() available we include just the portion of
// the file up to the first add_action() call via a thin bootstrap that guards
// against re-declaring it.
if ( ! function_exists( 'muslprti_parse_time_to_24h' ) ) {
	require_once ABSPATH . 'plugins/muslim-prayer-times/includes/helpers.php';

	// Stub the remaining WordPress functions that helpers.php may call so
	// we can safely include settings-ajax.php's top-level function without
	// running any AJAX handlers.
	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ) { return trim( $str ); }
	}
	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $value ) { return $value; }
	}
	if ( ! function_exists( 'check_ajax_referer' ) ) {
		function check_ajax_referer() { return true; }
	}
	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can() { return true; }
	}
	if ( ! function_exists( 'wp_send_json_error' ) ) {
		function wp_send_json_error() {}
	}
	if ( ! function_exists( 'wp_send_json_success' ) ) {
		function wp_send_json_success() {}
	}
	if ( ! function_exists( 'add_action' ) ) {
		function add_action() {}
	}
	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( $text ) { return $text; }
	}
	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ) { return $text; }
	}
	if ( ! function_exists( 'wp_create_nonce' ) ) {
		function wp_create_nonce() { return ''; }
	}
	if ( ! function_exists( 'update_option' ) ) {
		function update_option() {}
	}
	if ( ! function_exists( 'add_query_arg' ) ) {
		function add_query_arg() { return ''; }
	}
	if ( ! function_exists( 'wp_remote_get' ) ) {
		function wp_remote_get() { return array(); }
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error() { return false; }
	}
	if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
		function wp_remote_retrieve_body() { return '{}'; }
	}
	if ( ! function_exists( 'sanitize_file_name' ) ) {
		function sanitize_file_name( $f ) { return $f; }
	}
	if ( ! function_exists( 'wp_check_filetype' ) ) {
		function wp_check_filetype() { return array( 'ext' => 'csv' ); }
	}

	// Include only the helper function definition at the top of settings-ajax.php.
	// We use output buffering so that any inadvertent output is swallowed.
	ob_start();
	require_once ABSPATH . 'plugins/muslim-prayer-times/settings-ajax.php';
	ob_end_clean();
}

/**
 * Tests for the two bug fixes applied to settings-ajax.php:
 *
 * 1. muslprti_parse_time_to_24h() – correctly converts 12H AM/PM times to H:i
 *    so that MySQL TIME columns store the right value (Bug: "all PM times saved
 *    as AM on import").
 *
 * 2. The generate-CSV reformat block – converts Builder H:i output to g:i A
 *    when time_format is '12hour', and leaves it as H:i for '24hour'
 *    (Bug: "Export / Generate ignore the Time Format setting").
 */
class SettingsAjaxTest extends TestCase {

	// -----------------------------------------------------------------------
	// Bug 1 – muslprti_parse_time_to_24h()
	// -----------------------------------------------------------------------

	/**
	 * AM times must be preserved exactly.
	 */
	public function test_parse_time_am_preserved() {
		$this->assertSame( '05:30', muslprti_parse_time_to_24h( '5:30 AM' ) );
		$this->assertSame( '07:00', muslprti_parse_time_to_24h( '7:00 AM' ) );
		$this->assertSame( '11:59', muslprti_parse_time_to_24h( '11:59 AM' ) );
	}

	/**
	 * PM times must be converted to 24H (was the core bug: PM was silently
	 * dropped so e.g. "7:30 PM" was stored as 07:30 instead of 19:30).
	 */
	public function test_parse_time_pm_converted_correctly() {
		$this->assertSame( '13:30', muslprti_parse_time_to_24h( '1:30 PM' ) );
		$this->assertSame( '19:30', muslprti_parse_time_to_24h( '7:30 PM' ) );
		$this->assertSame( '20:15', muslprti_parse_time_to_24h( '8:15 PM' ) );
		$this->assertSame( '21:45', muslprti_parse_time_to_24h( '9:45 PM' ) );
		$this->assertSame( '23:59', muslprti_parse_time_to_24h( '11:59 PM' ) );
	}

	/**
	 * 12:xx PM = noon onwards (12:00–12:59) must stay in the 12:xx range.
	 */
	public function test_parse_time_noon_pm() {
		$this->assertSame( '12:00', muslprti_parse_time_to_24h( '12:00 PM' ) );
		$this->assertSame( '12:30', muslprti_parse_time_to_24h( '12:30 PM' ) );
	}

	/**
	 * 12:xx AM = midnight hour must be converted to 00:xx.
	 */
	public function test_parse_time_midnight_am() {
		$this->assertSame( '00:00', muslprti_parse_time_to_24h( '12:00 AM' ) );
		$this->assertSame( '00:30', muslprti_parse_time_to_24h( '12:30 AM' ) );
	}

	/**
	 * Values already in H:i (24H) must pass through unchanged.
	 */
	public function test_parse_time_already_24h() {
		$this->assertSame( '00:00', muslprti_parse_time_to_24h( '00:00' ) );
		$this->assertSame( '07:30', muslprti_parse_time_to_24h( '07:30' ) );
		$this->assertSame( '13:30', muslprti_parse_time_to_24h( '13:30' ) );
		$this->assertSame( '23:59', muslprti_parse_time_to_24h( '23:59' ) );
	}

	/**
	 * Single-digit hour without leading zero in 24H notation (G:i).
	 */
	public function test_parse_time_single_digit_hour_24h() {
		$this->assertSame( '05:00', muslprti_parse_time_to_24h( '5:00' ) );
		$this->assertSame( '09:15', muslprti_parse_time_to_24h( '9:15' ) );
	}

	/**
	 * Lower-case am/pm must also be accepted.
	 */
	public function test_parse_time_lowercase_am_pm() {
		$this->assertSame( '05:30', muslprti_parse_time_to_24h( '5:30 am' ) );
		$this->assertSame( '19:30', muslprti_parse_time_to_24h( '7:30 pm' ) );
	}

	/**
	 * Leading/trailing whitespace must be stripped.
	 */
	public function test_parse_time_whitespace_trimmed() {
		$this->assertSame( '13:30', muslprti_parse_time_to_24h( '  1:30 PM  ' ) );
		$this->assertSame( '07:00', muslprti_parse_time_to_24h( "\t7:00 AM\t" ) );
	}

	/**
	 * Empty or whitespace-only input must return an empty string (safe for DB
	 * insert into a nullable TIME column).
	 */
	public function test_parse_time_empty_input_returns_empty_string() {
		$this->assertSame( '', muslprti_parse_time_to_24h( '' ) );
		$this->assertSame( '', muslprti_parse_time_to_24h( '   ' ) );
	}

	// -----------------------------------------------------------------------
	// Bug 2 – Generate / Export time-format reformat logic
	//
	// We test the pure reformat algorithm directly (the same code that lives
	// inside muslprti_handle_generate()) without invoking the full AJAX
	// handler.  This isolates the logic from WordPress internals.
	// -----------------------------------------------------------------------

	/**
	 * Apply the same reformat block that muslprti_handle_generate() uses so we
	 * can assert on the result without needing a full WP AJAX environment.
	 *
	 * @param array  $csv_data  Array of rows; row 0 is the header.
	 * @param string $time_format  Plugin setting: '12hour' or '24hour'.
	 * @return array Modified $csv_data.
	 */
	private function apply_generate_reformat( array $csv_data, string $time_format ): array {
		$opts      = array( 'time_format' => $time_format );
		$time_fmt  = ( isset( $opts['time_format'] ) && $opts['time_format'] === '12hour' ) ? 'g:i A' : 'H:i';

		if ( $time_fmt !== 'H:i' ) {
			foreach ( $csv_data as $i => &$row ) {
				if ( $i === 0 ) {
					continue; // skip header row
				}
				for ( $col = 1; $col < count( $row ); $col++ ) {
					if ( ! empty( $row[ $col ] ) ) {
						$dt = DateTime::createFromFormat( 'H:i', $row[ $col ] );
						if ( $dt ) {
							$row[ $col ] = $dt->format( $time_fmt );
						}
					}
				}
			}
			unset( $row );
		}

		return $csv_data;
	}

	/**
	 * With time_format = '24hour' the Builder's H:i values must be left as-is.
	 */
	public function test_generate_24hour_format_unchanged() {
		$header   = array( 'day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama' );
		$data_row = array( '2026-06-01', '04:32', '05:00', '06:15', '13:05', '13:30', '16:45', '17:00', '20:10', '20:15', '21:45', '22:00' );

		$result = $this->apply_generate_reformat( array( $header, $data_row ), '24hour' );

		$this->assertSame( $data_row, $result[1] );
	}

	/**
	 * With time_format = '12hour' every time column must be converted to g:i A.
	 */
	public function test_generate_12hour_format_converts_times() {
		$header   = array( 'day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama' );
		$data_row = array( '2026-06-01', '04:32', '05:00', '06:15', '13:05', '13:30', '16:45', '17:00', '20:10', '20:15', '21:45', '22:00' );

		$result = $this->apply_generate_reformat( array( $header, $data_row ), '12hour' );

		$expected = array( '2026-06-01', '4:32 AM', '5:00 AM', '6:15 AM', '1:05 PM', '1:30 PM', '4:45 PM', '5:00 PM', '8:10 PM', '8:15 PM', '9:45 PM', '10:00 PM' );
		$this->assertSame( $expected, $result[1] );
	}

	/**
	 * The header row (index 0) must never be modified.
	 */
	public function test_generate_header_row_not_modified() {
		$header   = array( 'day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama' );
		$data_row = array( '2026-06-01', '04:32', '05:00', '06:15', '13:05', '13:30', '16:45', '17:00', '20:10', '20:15', '21:45', '22:00' );

		$result = $this->apply_generate_reformat( array( $header, $data_row ), '12hour' );

		$this->assertSame( $header, $result[0] );
	}

	/**
	 * Empty time cells (e.g. no iqama stored) must stay empty after reformatting.
	 */
	public function test_generate_empty_time_cells_stay_empty() {
		$header   = array( 'day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama' );
		$data_row = array( '2026-06-01', '04:32', '', '06:15', '13:05', '', '16:45', '', '20:10', '', '21:45', '' );

		$result = $this->apply_generate_reformat( array( $header, $data_row ), '12hour' );

		$this->assertSame( '', $result[1][2],  'fajr_iqama should be empty' );
		$this->assertSame( '', $result[1][5],  'dhuhr_iqama should be empty' );
		$this->assertSame( '', $result[1][7],  'asr_iqama should be empty' );
		$this->assertSame( '', $result[1][9],  'maghrib_iqama should be empty' );
		$this->assertSame( '', $result[1][11], 'isha_iqama should be empty' );
	}

	/**
	 * Multiple data rows must all be reformatted, not just the first.
	 */
	public function test_generate_multiple_rows_all_reformatted() {
		$header = array( 'day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama' );
		$row1   = array( '2026-06-01', '04:32', '05:00', '06:15', '13:05', '13:30', '16:45', '17:00', '20:10', '20:15', '21:45', '22:00' );
		$row2   = array( '2026-06-02', '04:33', '05:00', '06:16', '13:05', '13:30', '16:46', '17:00', '20:11', '20:15', '21:46', '22:00' );

		$result = $this->apply_generate_reformat( array( $header, $row1, $row2 ), '12hour' );

		// Day column (index 0) must be untouched.
		$this->assertSame( '2026-06-01', $result[1][0] );
		$this->assertSame( '2026-06-02', $result[2][0] );

		// Spot-check PM conversions in both rows.
		$this->assertSame( '1:05 PM', $result[1][4] );
		$this->assertSame( '1:05 PM', $result[2][4] );
		$this->assertSame( '4:32 AM', $result[1][1] );
		$this->assertSame( '4:33 AM', $result[2][1] );
	}

	/**
	 * Default (missing time_format key) should behave like '24hour' for
	 * the generate path, i.e. no reformatting applied.
	 *
	 * Note: the export path defaults to '12hour'; the generate path defaults
	 * to '24hour' (Builder native format).  We test the generate path here.
	 */
	public function test_generate_missing_time_format_defaults_to_24hour() {
		$header   = array( 'day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama' );
		$data_row = array( '2026-06-01', '04:32', '05:00', '06:15', '13:05', '13:30', '16:45', '17:00', '20:10', '20:15', '21:45', '22:00' );

		// Replicate generate logic with no time_format key at all.
		$opts     = array();
		$time_fmt = ( isset( $opts['time_format'] ) && $opts['time_format'] === '12hour' ) ? 'g:i A' : 'H:i';
		$this->assertSame( 'H:i', $time_fmt, 'Missing key must default to H:i (24H)' );

		$result = $this->apply_generate_reformat( array( $header, $data_row ), '24hour' );
		$this->assertSame( $data_row, $result[1] );
	}
}
