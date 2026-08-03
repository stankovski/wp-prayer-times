<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/wp-content/' );
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $wp_options;

		return isset( $wp_options[ $option ] ) ? $wp_options[ $option ] : $default;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}

require_once ABSPATH . 'plugins/muslim-prayer-times/includes/rest-api.php';

class RestApiTest extends TestCase {
	protected function setUp(): void {
		global $wp_options;

		$wp_options = array();
	}

	protected function tearDown(): void {
		global $wp_options;

		$wp_options = null;
	}

	public function test_etag_uses_last_updated_value() {
		global $wp_options;

		$wp_options['muslprti_prayer_times_updated_at'] = '2026-08-01T12:34:56Z';

		$this->assertSame( '"2026-08-01T12:34:56Z"', muslprti_get_prayer_times_etag() );
	}

	public function test_etag_is_absent_without_last_updated_value() {
		$this->assertNull( muslprti_get_prayer_times_etag() );
	}

	public function test_etag_headers_include_shared_cache_policy() {
		$this->assertSame(
			array(
				'Cache-Control' => 'public, max-age=60, s-maxage=3600',
				'ETag'          => '"2026-08-01T12:34:56Z"',
			),
			muslprti_get_etag_headers( '"2026-08-01T12:34:56Z"' )
		);
	}

	public function test_if_none_match_accepts_exact_weak_list_and_wildcard_matches() {
		$etag = '"2026-08-01T12:34:56Z"';

		$this->assertTrue( muslprti_if_none_match_matches( $etag, $etag ) );
		$this->assertTrue( muslprti_if_none_match_matches( 'W/' . $etag, $etag ) );
		$this->assertTrue( muslprti_if_none_match_matches( '"older", ' . $etag, $etag ) );
		$this->assertTrue( muslprti_if_none_match_matches( '*', $etag ) );
	}

	public function test_if_none_match_rejects_a_different_etag() {
		$this->assertFalse(
			muslprti_if_none_match_matches( '"2026-07-31T12:34:56Z"', '"2026-08-01T12:34:56Z"' )
		);
		$this->assertFalse( muslprti_if_none_match_matches( null, '"2026-08-01T12:34:56Z"' ) );
	}
}