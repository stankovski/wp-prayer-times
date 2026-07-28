<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/wp-content/' );
}

$muslprti_salah_api = ABSPATH . 'plugins/muslim-prayer-times/includes/salah-api/';
require_once $muslprti_salah_api . 'Location.php';
require_once $muslprti_salah_api . 'CalculationMethod.php';
require_once $muslprti_salah_api . 'IqamaCalculationRules.php';
require_once $muslprti_salah_api . 'PrayerCalculationRule.php';
require_once $muslprti_salah_api . 'Calculations/PrayerTimes.php';
require_once $muslprti_salah_api . 'Calculations/Method.php';
require_once $muslprti_salah_api . 'Calculations/TimeHelpers.php';
require_once $muslprti_salah_api . 'Calculations/IqamaCalculator.php';
require_once $muslprti_salah_api . 'Calculations/Builder.php';

use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;
use SalahAPI\Calculations\Builder;

/**
 * Tests for the optional SalahAPI 1.1 dual-Asr athan columns
 * (`asr_athan_standard` and `asr_athan_hanafi`).
 *
 * Covers:
 *  - the calculation Builder (generate path),
 *  - the export row-assembly logic, and
 *  - the import insert-data assembly logic.
 */
class AsrMethodsTest extends TestCase {

	/**
	 * Build a Builder for a fixed test location/method.
	 *
	 * @param bool        $include_asr_methods Whether to request the dual-Asr columns.
	 * @param string|null $asr_athan_method    Method used for the asr_athan output column.
	 * @return Builder
	 */
	private function make_builder( bool $include_asr_methods, ?string $asr_athan_method = null ): Builder {
		$location   = new Location( 47.7623, -122.2054, 'America/Los_Angeles', 'Y-m-d', 'H:i' );
		$asr_rule   = new PrayerCalculationRule( null, 'daily', 1, null, null, 15 );
		$iqama_rules = new IqamaCalculationRules( null, null, null, $asr_rule );
		$method     = new CalculationMethod( 'ISNA', null, null, 'standard', null, $iqama_rules );

		return new Builder( $location, $method, 0, $include_asr_methods, $asr_athan_method );
	}

	/**
	 * When the dual-Asr columns are NOT requested the header has the classic 12
	 * columns and no asr method columns.
	 */
	public function test_generate_without_asr_methods_has_no_extra_columns() {
		$builder = $this->make_builder( false );
		$csv     = $builder->build( '2026-06-01', '2026-06-02' );

		$header = $csv[0];
		$this->assertNotContains( 'asr_athan_standard', $header );
		$this->assertNotContains( 'asr_athan_hanafi', $header );
		$this->assertCount( 12, $header );
	}

	/**
	 * When requested, the header gains the two optional columns at the end and
	 * every data row carries valid H:i values for them.
	 */
	public function test_generate_with_asr_methods_appends_columns() {
		$builder = $this->make_builder( true );
		$csv     = $builder->build( '2026-06-01', '2026-06-02' );

		$header = $csv[0];
		$this->assertContains( 'asr_athan_standard', $header );
		$this->assertContains( 'asr_athan_hanafi', $header );

		// The two new columns are the final two columns.
		$this->assertSame( 'asr_athan_standard', $header[ count( $header ) - 2 ] );
		$this->assertSame( 'asr_athan_hanafi', $header[ count( $header ) - 1 ] );

		$std_idx    = array_search( 'asr_athan_standard', $header, true );
		$hanafi_idx = array_search( 'asr_athan_hanafi', $header, true );

		for ( $i = 1; $i < count( $csv ); $i++ ) {
			$row = $csv[ $i ];
			$this->assertCount( count( $header ), $row );
			$this->assertMatchesRegularExpression( '/^\d{2}:\d{2}$/', $row[ $std_idx ] );
			$this->assertMatchesRegularExpression( '/^\d{2}:\d{2}$/', $row[ $hanafi_idx ] );

			// Hanafi Asr is always at or after Standard Asr.
			$this->assertGreaterThanOrEqual(
				strtotime( $row[ $std_idx ] ),
				strtotime( $row[ $hanafi_idx ] ),
				'Hanafi Asr must not be earlier than Standard Asr'
			);
		}
	}

	/**
	 * Generated asr_athan follows the configured method without changing iqama.
	 */
	public function test_generate_asr_athan_follows_athan_method() {
		$standard = $this->make_builder( false, 'standard' )->build( '2026-06-01', '2026-06-01' );
		$hanafi   = $this->make_builder( false, 'hanafi' )->build( '2026-06-01', '2026-06-01' );
		$header   = $standard[0];

		$asr_idx   = array_search( 'asr_athan', $header, true );
		$iqama_idx = array_search( 'asr_iqama', $header, true );

		$this->assertNotSame( $standard[1][ $asr_idx ], $hanafi[1][ $asr_idx ] );
		$this->assertMatchesRegularExpression( '/^\d{2}:\d{2}$/', $standard[1][ $iqama_idx ] );
		$this->assertSame( $standard[1][ $iqama_idx ], $hanafi[1][ $iqama_idx ] );
	}

	/**
	 * Internal dual-Asr columns are omitted unless requested by the user.
	 */
	public function test_generate_selected_asr_without_extra_columns() {
		$with_dual    = $this->make_builder( true, 'hanafi' )->build( '2026-06-01', '2026-06-01' );
		$without_dual = $this->make_builder( false, 'hanafi' )->build( '2026-06-01', '2026-06-01' );
		$dual_header  = $with_dual[0];
		$header       = $without_dual[0];

		$asr_idx    = array_search( 'asr_athan', $header, true );
		$hanafi_idx = array_search( 'asr_athan_hanafi', $dual_header, true );

		$this->assertNotContains( 'asr_athan_standard', $header );
		$this->assertNotContains( 'asr_athan_hanafi', $header );
		$this->assertCount( 12, $header );
		$this->assertCount( 12, $without_dual[1] );
		$this->assertSame( $with_dual[1][ $hanafi_idx ], $without_dual[1][ $asr_idx ] );
	}

	// -----------------------------------------------------------------------
	// Export row assembly (mirrors muslprti_handle_export_db()).
	// -----------------------------------------------------------------------

	/**
	 * Replicate the export header + single-row assembly used by the handler.
	 *
	 * @param array $db_row              Associative DB row.
	 * @param bool  $include_asr_methods Whether the include box is checked.
	 * @return array{0:array,1:array} [header, row]
	 */
	private function assemble_export( array $db_row, bool $include_asr_methods ): array {
		$header = array( 'day', 'fajr_athan', 'fajr_iqama', 'sunrise', 'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama', 'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama' );
		if ( $include_asr_methods ) {
			$header[] = 'asr_athan_standard';
			$header[] = 'asr_athan_hanafi';
		}

		$row = array(
			$db_row['day'],
			$db_row['fajr_athan'],
			$db_row['fajr_iqama'],
			$db_row['sunrise'],
			$db_row['dhuhr_athan'],
			$db_row['dhuhr_iqama'],
			$db_row['asr_athan'],
			$db_row['asr_iqama'],
			$db_row['maghrib_athan'],
			$db_row['maghrib_iqama'],
			$db_row['isha_athan'],
			$db_row['isha_iqama'],
		);
		if ( $include_asr_methods ) {
			$row[] = ! empty( $db_row['asr_athan_standard'] ) ? $db_row['asr_athan_standard'] : '';
			$row[] = ! empty( $db_row['asr_athan_hanafi'] ) ? $db_row['asr_athan_hanafi'] : '';
		}

		return array( $header, $row );
	}

	/**
	 * Sample DB row with all fields populated.
	 *
	 * @return array
	 */
	private function sample_db_row(): array {
		return array(
			'day'                => '2026-06-01',
			'fajr_athan'         => '04:32',
			'fajr_iqama'         => '05:00',
			'sunrise'            => '06:15',
			'dhuhr_athan'        => '13:05',
			'dhuhr_iqama'        => '13:30',
			'asr_athan'          => '16:45',
			'asr_iqama'          => '17:00',
			'maghrib_athan'      => '20:10',
			'maghrib_iqama'      => '20:15',
			'isha_athan'         => '21:45',
			'isha_iqama'         => '22:00',
			'asr_athan_standard' => '16:45',
			'asr_athan_hanafi'   => '17:55',
		);
	}

	public function test_export_excludes_asr_methods_by_default() {
		list( $header, $row ) = $this->assemble_export( $this->sample_db_row(), false );

		$this->assertCount( 12, $header );
		$this->assertCount( 12, $row );
		$this->assertNotContains( 'asr_athan_standard', $header );
	}

	public function test_export_includes_asr_methods_when_enabled() {
		list( $header, $row ) = $this->assemble_export( $this->sample_db_row(), true );

		$this->assertCount( 14, $header );
		$this->assertCount( 14, $row );
		$this->assertSame( '16:45', $row[12] );
		$this->assertSame( '17:55', $row[13] );
	}

	public function test_export_empty_asr_methods_become_blank() {
		$db_row                       = $this->sample_db_row();
		$db_row['asr_athan_standard'] = null;
		$db_row['asr_athan_hanafi']   = '';

		list( , $row ) = $this->assemble_export( $db_row, true );

		$this->assertSame( '', $row[12] );
		$this->assertSame( '', $row[13] );
	}

	// -----------------------------------------------------------------------
	// Import insert-data assembly (mirrors muslprti_handle_import()).
	// -----------------------------------------------------------------------

	/**
	 * Replicate the conditional insert-data/format assembly used by the import
	 * handler: the optional columns are only included when present in the CSV
	 * header (i.e. the parsed row).
	 *
	 * @param array $row_data Parsed CSV row keyed by header column.
	 * @return array{0:array,1:array} [insert_data, insert_format]
	 */
	private function assemble_import( array $row_data ): array {
		$insert_data   = array(
			'day'           => $row_data['day'],
			'fajr_athan'    => $this->parse_time( isset( $row_data['fajr_athan'] ) ? $row_data['fajr_athan'] : '' ),
			'asr_athan'     => $this->parse_time( isset( $row_data['asr_athan'] ) ? $row_data['asr_athan'] : '' ),
			'asr_iqama'     => $this->parse_time( isset( $row_data['asr_iqama'] ) ? $row_data['asr_iqama'] : '' ),
		);
		$insert_format = array( '%s', '%s', '%s', '%s' );

		if ( array_key_exists( 'asr_athan_standard', $row_data ) ) {
			$insert_data['asr_athan_standard'] = $this->parse_time( $row_data['asr_athan_standard'] );
			$insert_format[]                   = '%s';
		}
		if ( array_key_exists( 'asr_athan_hanafi', $row_data ) ) {
			$insert_data['asr_athan_hanafi'] = $this->parse_time( $row_data['asr_athan_hanafi'] );
			$insert_format[]                 = '%s';
		}

		return array( $insert_data, $insert_format );
	}

	/**
	 * Mirror of muslprti_parse_time_to_24h() for time normalisation in tests.
	 *
	 * @param string $time_str Raw time string.
	 * @return string Normalised H:i time, or empty string.
	 */
	private function parse_time( string $time_str ): string {
		if ( '' === trim( $time_str ) ) {
			return '';
		}
		$dt = DateTime::createFromFormat( 'g:i A', strtoupper( trim( $time_str ) ) );
		if ( false === $dt ) {
			$dt = DateTime::createFromFormat( 'H:i', trim( $time_str ) );
		}
		if ( false === $dt ) {
			$dt = DateTime::createFromFormat( 'G:i', trim( $time_str ) );
		}
		return false !== $dt ? $dt->format( 'H:i' ) : $time_str;
	}

	public function test_import_without_asr_columns_keeps_classic_fields() {
		$row_data = array(
			'day'        => '2026-06-01',
			'fajr_athan' => '4:32 AM',
			'asr_athan'  => '4:45 PM',
			'asr_iqama'  => '5:00 PM',
		);

		list( $insert_data, $insert_format ) = $this->assemble_import( $row_data );

		$this->assertArrayNotHasKey( 'asr_athan_standard', $insert_data );
		$this->assertArrayNotHasKey( 'asr_athan_hanafi', $insert_data );
		$this->assertCount( 4, $insert_format );
		// 12H PM input is normalised to 24H.
		$this->assertSame( '16:45', $insert_data['asr_athan'] );
	}

	public function test_import_with_asr_columns_stores_and_normalises_them() {
		$row_data = array(
			'day'                => '2026-06-01',
			'fajr_athan'         => '04:32',
			'asr_athan'          => '16:45',
			'asr_iqama'          => '17:00',
			'asr_athan_standard' => '4:45 PM',
			'asr_athan_hanafi'   => '5:55 PM',
		);

		list( $insert_data, $insert_format ) = $this->assemble_import( $row_data );

		$this->assertArrayHasKey( 'asr_athan_standard', $insert_data );
		$this->assertArrayHasKey( 'asr_athan_hanafi', $insert_data );
		$this->assertSame( '16:45', $insert_data['asr_athan_standard'] );
		$this->assertSame( '17:55', $insert_data['asr_athan_hanafi'] );
		$this->assertCount( 6, $insert_format );
	}

	// -----------------------------------------------------------------------
	// CSV REST endpoint asr method override (mirrors
	// muslprti_prayer_times_csv_endpoint()).
	// -----------------------------------------------------------------------

	/**
	 * Replicate the asr_athan override applied by the CSV endpoint: when an Asr
	 * method is requested, asr_athan is sourced from the matching dual-Asr
	 * column, falling back to the stored asr_athan when that column is empty.
	 *
	 * @param array  $results    Rows keyed by column name.
	 * @param string $asr_method '', 'standard', or 'hanafi'.
	 * @return array Rows with asr_athan possibly overridden.
	 */
	private function apply_asr_method( array $results, string $asr_method ): array {
		if ( '' !== $asr_method ) {
			$asr_column = 'standard' === $asr_method ? 'asr_athan_standard' : 'asr_athan_hanafi';
			foreach ( $results as &$result_row ) {
				if ( ! empty( $result_row[ $asr_column ] ) ) {
					$result_row['asr_athan'] = $result_row[ $asr_column ];
				}
			}
			unset( $result_row );
		}

		return $results;
	}

	public function test_csv_default_keeps_stored_asr_athan() {
		$results = array(
			array(
				'day'                => '2026-06-01',
				'asr_athan'          => '16:45',
				'asr_athan_standard' => '16:45',
				'asr_athan_hanafi'   => '17:55',
			),
		);

		$out = $this->apply_asr_method( $results, '' );

		$this->assertSame( '16:45', $out[0]['asr_athan'] );
	}

	public function test_csv_standard_method_uses_standard_column() {
		$results = array(
			array(
				'day'                => '2026-06-01',
				'asr_athan'          => '17:00',
				'asr_athan_standard' => '16:45',
				'asr_athan_hanafi'   => '17:55',
			),
		);

		$out = $this->apply_asr_method( $results, 'standard' );

		$this->assertSame( '16:45', $out[0]['asr_athan'] );
	}

	public function test_csv_hanafi_method_uses_hanafi_column() {
		$results = array(
			array(
				'day'                => '2026-06-01',
				'asr_athan'          => '17:00',
				'asr_athan_standard' => '16:45',
				'asr_athan_hanafi'   => '17:55',
			),
		);

		$out = $this->apply_asr_method( $results, 'hanafi' );

		$this->assertSame( '17:55', $out[0]['asr_athan'] );
	}

	public function test_csv_method_falls_back_when_column_empty() {
		$results = array(
			array(
				'day'                => '2026-06-01',
				'asr_athan'          => '17:00',
				'asr_athan_standard' => '',
				'asr_athan_hanafi'   => null,
			),
		);

		$standard = $this->apply_asr_method( $results, 'standard' );
		$this->assertSame( '17:00', $standard[0]['asr_athan'] );

		$hanafi = $this->apply_asr_method( $results, 'hanafi' );
		$this->assertSame( '17:00', $hanafi[0]['asr_athan'] );
	}

	// -----------------------------------------------------------------------
	// CSV REST endpoint time format (mirrors the formatting loop in
	// muslprti_prayer_times_csv_endpoint()).
	// -----------------------------------------------------------------------

	/**
	 * Resolve the effective time format the endpoint would use: an explicit
	 * 'timeFormat' param wins, otherwise the stored Time Format setting is used.
	 *
	 * @param string $param   The timeFormat request param ('' when absent).
	 * @param string $setting The plugin Time Format setting ('12hour'/'24hour').
	 * @return string PHP date() format string.
	 */
	private function resolve_time_fmt( string $param, string $setting ): string {
		if ( '' !== $param ) {
			$time_format = $param;
		} else {
			$time_format = '12hour' === $setting ? '12hour' : '24hour';
		}

		return '12hour' === $time_format ? 'g:i A' : 'H:i';
	}

	/**
	 * Format a stored DB time value the same way the endpoint does.
	 *
	 * @param string $value    Stored time value.
	 * @param string $time_fmt PHP date() format string.
	 * @return string
	 */
	private function format_time( string $value, string $time_fmt ): string {
		if ( '' === $value ) {
			return '';
		}
		$timestamp = strtotime( $value );
		return false !== $timestamp ? gmdate( $time_fmt, $timestamp ) : $value;
	}

	public function test_csv_time_format_defaults_to_24hour_setting() {
		$time_fmt = $this->resolve_time_fmt( '', '24hour' );
		$this->assertSame( '16:45', $this->format_time( '16:45:00', $time_fmt ) );
	}

	public function test_csv_time_format_defaults_to_12hour_setting() {
		$time_fmt = $this->resolve_time_fmt( '', '12hour' );
		$this->assertSame( '4:45 PM', $this->format_time( '16:45:00', $time_fmt ) );
	}

	public function test_csv_time_format_param_overrides_setting() {
		// Setting is 24hour but the param requests 12hour.
		$time_fmt = $this->resolve_time_fmt( '12hour', '24hour' );
		$this->assertSame( '4:45 PM', $this->format_time( '16:45:00', $time_fmt ) );

		// Setting is 12hour but the param requests 24hour.
		$time_fmt = $this->resolve_time_fmt( '24hour', '12hour' );
		$this->assertSame( '16:45', $this->format_time( '16:45:00', $time_fmt ) );
	}

	public function test_csv_time_format_leaves_empty_values_empty() {
		$time_fmt = $this->resolve_time_fmt( '', '24hour' );
		$this->assertSame( '', $this->format_time( '', $time_fmt ) );
	}
}
