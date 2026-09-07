<?php
/**
 * M5 unit tests — geo adapter, policy, query shapes, DTO allowlist.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use UniversalSocialProof\Geo\GeographyPolicy;
use UniversalSocialProof\Geo\NullGeoContextAdapter;
use UniversalSocialProof\Geo\UgcGeoContextAdapter;
use UniversalSocialProof\Rest\NotificationsController;
use UniversalSocialProof\Selection\CandidateQuery;
use UniversalSocialProof\Selection\SelectionRequest;
use UniversalSocialProof\Template\TemplateContext;

final class M5GeoUnitTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_all_filters( GeographyPolicy::FILTER );
	}

	protected function tearDown(): void {
		remove_all_filters( GeographyPolicy::FILTER );
		parent::tearDown();
	}

	public function test_null_adapter_unavailable(): void {
		$adapter = new NullGeoContextAdapter();
		$this->assertFalse( $adapter->is_available() );
		$this->assertNull( $adapter->country_code() );
	}

	public function test_ugc_absent_unavailable(): void {
		$adapter = new UgcGeoContextAdapter();
		$this->assertFalse( $adapter->is_available() );
		$this->assertNull( $adapter->country_code() );
	}

	public function test_normalize_valid_and_lowercase(): void {
		$this->assertSame( 'SE', UgcGeoContextAdapter::normalize( 'SE' ) );
		$this->assertSame( 'SE', UgcGeoContextAdapter::normalize( 'se' ) );
		$this->assertSame( 'SE', UgcGeoContextAdapter::normalize( ' se ' ) );
	}

	public function test_normalize_null_and_malformed(): void {
		$this->assertNull( UgcGeoContextAdapter::normalize( null ) );
		$this->assertNull( UgcGeoContextAdapter::normalize( '' ) );
		$this->assertNull( UgcGeoContextAdapter::normalize( 'S' ) );
		$this->assertNull( UgcGeoContextAdapter::normalize( 'SWE' ) );
		$this->assertNull( UgcGeoContextAdapter::normalize( '12' ) );
		$this->assertNull( UgcGeoContextAdapter::normalize( 'SË' ) );
	}

	public function test_fetcher_valid_se_memoized_once(): void {
		$calls   = 0;
		$adapter = new UgcGeoContextAdapter(
			static function () use ( &$calls ) {
				++$calls;
				return 'se';
			}
		);
		$this->assertTrue( $adapter->is_available() );
		$this->assertSame( 'SE', $adapter->country_code() );
		$this->assertSame( 'SE', $adapter->country_code() );
		$this->assertSame( 1, $calls );
	}

	public function test_fetcher_null_and_malformed(): void {
		$null = new UgcGeoContextAdapter( static fn() => null );
		$this->assertNull( $null->country_code() );

		$bad = new UgcGeoContextAdapter( static fn() => 'sweden' );
		$this->assertNull( $bad->country_code() );
	}

	public function test_fetcher_throwable_fails_closed(): void {
		$adapter = new UgcGeoContextAdapter(
			static function () {
				throw new RuntimeException( 'ugc boom' );
			}
		);
		$this->assertNull( $adapter->country_code() );
		$this->assertNull( $adapter->country_code() );
	}

	public function test_policy_available_with_country(): void {
		$adapter = new UgcGeoContextAdapter( static fn() => 'DE' );
		$this->assertSame( 'DE', GeographyPolicy::visitor_country_for_selection( $adapter ) );
	}

	public function test_policy_unavailable_and_null(): void {
		$this->assertNull( GeographyPolicy::visitor_country_for_selection( new NullGeoContextAdapter() ) );
		$null = new UgcGeoContextAdapter( static fn() => null );
		$this->assertNull( GeographyPolicy::visitor_country_for_selection( $null ) );
	}

	public function test_policy_filter_disabled(): void {
		add_filter(
			GeographyPolicy::FILTER,
			static function ( $enabled ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- filter signature.
				return false;
			}
		);
		$adapter = new UgcGeoContextAdapter( static fn() => 'SE' );
		$this->assertNull( GeographyPolicy::visitor_country_for_selection( $adapter ) );
	}

	public function test_policy_filter_enabled_passthrough(): void {
		add_filter(
			GeographyPolicy::FILTER,
			static function ( $enabled ) {
				return (bool) $enabled;
			}
		);
		$adapter = new UgcGeoContextAdapter( static fn() => 'NO' );
		$this->assertSame( 'NO', GeographyPolicy::visitor_country_for_selection( $adapter ) );
	}

	public function test_candidate_query_country_shapes(): void {
		$country = CandidateQuery::country( '2026-01-01 00:00:00', array(), 'se' );
		$this->assertSame( 80, $country->limit() );
		$this->assertTrue( $country->has_country() );
		$this->assertSame( 'SE', $country->country_code() );
		$this->assertFalse( $country->is_preferred() );

		$pref = CandidateQuery::preferred_country( '2026-01-01 00:00:00', array(), 9, 'de' );
		$this->assertSame( 20, $pref->limit() );
		$this->assertTrue( $pref->is_preferred() );
		$this->assertSame( 9, $pref->product_id() );
		$this->assertSame( 'DE', $pref->country_code() );

		$global = CandidateQuery::global( '2026-01-01 00:00:00', array() );
		$this->assertFalse( $global->has_country() );
		$this->assertNull( $global->country_code() );
	}

	public function test_selection_request_visitor_country(): void {
		$none = new SelectionRequest( 5, null, SelectionRequest::CONTEXT_UNKNOWN, array() );
		$this->assertFalse( $none->has_geo() );
		$this->assertNull( $none->visitor_country );

		$geo = new SelectionRequest( 5, 12, SelectionRequest::CONTEXT_PRODUCT, array(), 'SE' );
		$this->assertTrue( $geo->has_geo() );
		$this->assertSame( 'SE', $geo->visitor_country );
		$this->assertTrue( $geo->is_pdp() );
	}

	public function test_dto_allowlist_exact_keys_no_visitor_country(): void {
		$dto = NotificationsController::allowlist(
			array(
				'public_id'          => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
				'product_url'        => 'https://example.test/p',
				'thumbnail_url'      => null,
				'occurred_at'        => '2026-01-01T00:00:00+00:00',
				'message'            => 'Someone purchased X',
				'show_relative_time' => true,
				'visitor_country'    => 'SE',
				'country_code'       => 'DE',
				'ip'                 => '1.2.3.4',
			)
		);
		$this->assertSame( NotificationsController::ALLOWLIST, array_keys( $dto ) );
		$this->assertArrayNotHasKey( 'visitor_country', $dto );
		$this->assertArrayNotHasKey( 'country_code', $dto );
		$this->assertArrayNotHasKey( 'ip', $dto );
	}

	public function test_template_country_label_without_wc_is_empty(): void {
		// Unit bootstrap has no WooCommerce; label resolution is integration-covered.
		$this->assertSame( '', TemplateContext::country_label( 'DE' ) );
		$this->assertSame( '', TemplateContext::country_label( null ) );
	}
}
