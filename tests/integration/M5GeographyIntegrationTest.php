<?php
/**
 * M5 geography + UGC integration tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Integration;

use UniversalSocialProof\Cleanup\RetentionSettings;
use UniversalSocialProof\Geo\NullGeoContextAdapter;
use UniversalSocialProof\Geo\UgcGeoContextAdapter;
use UniversalSocialProof\Plugin;
use UniversalSocialProof\Privacy\PersonalDataEraser;
use UniversalSocialProof\Privacy\PersonalDataExporter;
use UniversalSocialProof\Rest\NotificationsController;
use UniversalSocialProof\Selection\CandidateQuery;
use UniversalSocialProof\Selection\CandidateReader;
use UniversalSocialProof\Selection\ProductResolutionBudget;
use UniversalSocialProof\Selection\SelectionEngine;
use UniversalSocialProof\Selection\SelectionRequest;
use UniversalSocialProof\Storage\EventRepository;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Schema;
use UniversalSocialProof\Template\TemplateSettings;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

final class M5GeographyIntegrationTest extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private int $source_seq = 800000;

	public function set_up(): void {
		parent::set_up();
		Plugin::reset_for_tests();
		delete_option( Migrator::OPTION_VERSION );
		delete_option( RetentionSettings::OPTION_KEY );
		Migrator::upgrade_now();
		Plugin::init();
		$this->truncate_events();
		wp_set_current_user( 0 );
		remove_all_filters( 'usp_geo_context_adapter' );
		remove_all_filters( 'usp_geo_weighting_enabled' );
		remove_all_filters( TemplateSettings::FILTER );
	}

	public function tear_down(): void {
		remove_all_filters( 'usp_geo_context_adapter' );
		remove_all_filters( 'usp_geo_weighting_enabled' );
		remove_all_filters( TemplateSettings::FILTER );
		parent::tear_down();
	}

	public function test_schema_db_version_unchanged(): void {
		$this->assertSame( '20260829m1', Schema::DB_VERSION );
		$this->assertSame( '20260829m1', get_option( Migrator::OPTION_VERSION ) );
	}

	public function test_ugc_absent_uses_m2_path_no_country_sql(): void {
		$product = $this->create_simple_product( 'Global' );
		$this->insert_event( $product, 'SE', 0 );
		$this->insert_event( $product, 'DE', 1 );

		$reader = new CandidateReader();
		$engine = new SelectionEngine( $reader, new \UniversalSocialProof\Product\PublicProductResolver( new ProductResolutionBudget() ), array( $this, 'identity_shuffle' ) );
		$got    = $engine->select( new SelectionRequest( 5, null, 'unknown', array(), null ) );
		$this->assertCount( 2, $got );
		$this->assertSame( 1, $reader->query_count() ); // Global only for non-PDP without preferred.
		$this->assertLessThanOrEqual( 2, $reader->query_count() );
	}

	public function test_non_pdp_country_preference_and_sparse_fallback(): void {
		$se_a = $this->create_simple_product( 'SE-A' );
		$se_b = $this->create_simple_product( 'SE-B' );
		$de   = $this->create_simple_product( 'DE-X' );
		$se1  = $this->insert_event( $se_a, 'SE', 10 );
		$se2  = $this->insert_event( $se_b, 'SE', 9 );
		$de1  = $this->insert_event( $de, 'DE', 8 );

		$reader = new CandidateReader();
		$engine = $this->engine( $reader );
		$got    = $engine->select( new SelectionRequest( 2, null, 'unknown', array(), 'SE' ) );
		$ids    = array_map( static fn( $e ) => $e->public_id, $got );
		$this->assertCount( 2, $got );
		$this->assertContains( $se1['public_id'], $ids );
		$this->assertContains( $se2['public_id'], $ids );
		$this->assertNotContains( $de1['public_id'], $ids );
		$this->assertSame( 1, $reader->query_count() );

		$reader2 = new CandidateReader();
		$engine2 = $this->engine( $reader2 );
		$none    = $engine2->select( new SelectionRequest( 2, null, 'unknown', array(), 'JP' ) );
		$ids2    = array_map( static fn( $e ) => $e->public_id, $none );
		$this->assertCount( 2, $none );
		$this->assertContains( $de1['public_id'], $ids2 );
		$this->assertSame( 2, $reader2->query_count() );
	}

	public function test_null_purchase_country_excluded_from_country_pool_eligible_globally(): void {
		$product = $this->create_simple_product( 'NullC' );
		$null    = $this->insert_event( $product, null, 0 );
		$se      = $this->insert_event( $this->create_simple_product( 'SE' ), 'SE', 1 );

		$country     = ( new CandidateReader() )->find_recent_active(
			CandidateQuery::country( RetentionSettings::cutoff_utc(), array(), 'SE' )
		);
		$country_ids = array_map( static fn( $c ) => $c->public_id, $country );
		$this->assertNotContains( $null['public_id'], $country_ids );
		$this->assertContains( $se['public_id'], $country_ids );

		$got = $this->engine()->select( new SelectionRequest( 5, null, 'unknown', array(), 'JP' ) );
		$ids = array_map( static fn( $e ) => $e->public_id, $got );
		$this->assertContains( $null['public_id'], $ids );
	}

	public function test_duplicate_public_id_attempted_once_across_pools(): void {
		$product = $this->create_simple_product( 'Dup' );
		$row     = $this->insert_event( $product, 'SE', 0 );

		$loads  = array();
		$loader = static function ( int $id ) use ( &$loads ) {
			$loads[] = $id;
			return wc_get_product( $id );
		};
		$reader = new CandidateReader();
		$engine = NotificationsController::make_engine( array( $this, 'identity_shuffle' ), $loader );
		// Replace reader via reflection? make_engine creates its own reader.
		// Use select with geo: country pool then global — same public_id in both.
		$engine->select( new SelectionRequest( 1, null, 'unknown', array(), 'SE' ) );
		$this->assertSame( 1, $this->count_load_ids( $loads, (int) $product->get_id() ) );
		unset( $row );
	}

	public function test_pdp_tier1_accepts_skips_tier2(): void {
		$product = $this->create_simple_product( 'PDP' );
		$se      = $this->insert_event( $product, 'SE', 0 );
		$this->insert_event( $product, 'DE', 1 );
		$this->insert_event( $this->create_simple_product( 'Other' ), 'SE', 2 );

		$reader = new CandidateReader();
		$engine = $this->engine( $reader );
		$got    = $engine->select(
			new SelectionRequest( 1, (int) $product->get_id(), SelectionRequest::CONTEXT_PRODUCT, array(), 'SE' )
		);
		$this->assertCount( 1, $got );
		$this->assertSame( $se['public_id'], $got[0]->public_id );
		// Tier1 preferred×country + no Tier2 + no need Tier3/4 when K filled → 1 SQL.
		$this->assertSame( 1, $reader->query_count() );
	}

	public function test_pdp_tier1_miss_then_tier2_any_country(): void {
		$product = $this->create_simple_product( 'PDP2' );
		$de      = $this->insert_event( $product, 'DE', 0 );
		$this->insert_event( $this->create_simple_product( 'OtherSE' ), 'SE', 1 );

		$reader = new CandidateReader();
		$engine = $this->engine( $reader );
		$got    = $engine->select(
			new SelectionRequest( 1, (int) $product->get_id(), SelectionRequest::CONTEXT_PRODUCT, array(), 'SE' )
		);
		$this->assertCount( 1, $got );
		$this->assertSame( $de['public_id'], $got[0]->public_id );
		$this->assertSame( 2, $reader->query_count() ); // Tier1 miss + Tier2 accept.
	}

	public function test_pdp_shared_cap_tier1_consumes_all_five(): void {
		$parent = $this->create_variable_product();
		for ( $i = 0; $i < 5; $i++ ) {
			$variation = $this->create_variation( $parent );
			$variation->set_status( 'private' );
			$variation->save();
			$this->insert_event( $parent, 'SE', 10 + $i, (int) $variation->get_id() );
		}
		for ( $i = 0; $i < 3; $i++ ) {
			$variation = $this->create_variation( $parent );
			$variation->set_status( 'private' );
			$variation->save();
			$this->insert_event( $parent, 'DE', 20 + $i, (int) $variation->get_id() );
		}
		// Freshest global eligible event so Tier4 fills K before leftover preferred retries.
		$fallback = $this->create_simple_product( 'FB' );
		$this->insert_event( $fallback, 'DE', 0 );

		$loads  = array();
		$loader = static function ( int $id ) use ( &$loads ) {
			$loads[] = $id;
			return wc_get_product( $id );
		};
		$engine = NotificationsController::make_engine( array( $this, 'identity_shuffle' ), $loader );
		$got    = $engine->select(
			new SelectionRequest( 1, (int) $parent->get_id(), SelectionRequest::CONTEXT_PRODUCT, array(), 'SE' )
		);

		$parent_id = (int) $parent->get_id();
		$fb_id     = (int) $fallback->get_id();
		$preferred = 0;
		foreach ( $loads as $id ) {
			if ( $id !== $parent_id && $id !== $fb_id ) {
				++$preferred;
			}
		}
		$this->assertSame( ProductResolutionBudget::PDP_SEARCH_CAP, $preferred );
		$this->assertCount( 1, $got );
		$this->assertSame( $fb_id, $got[0]->product->id );
	}

	public function test_pdp_tier3_and_tier4_fallback(): void {
		$pdp = $this->create_simple_product( 'EmptyPDP' );
		$se  = $this->insert_event( $this->create_simple_product( 'SE-G' ), 'SE', 0 );
		$de  = $this->insert_event( $this->create_simple_product( 'DE-G' ), 'DE', 1 );

		$reader = new CandidateReader();
		$engine = $this->engine( $reader );
		$got    = $engine->select(
			new SelectionRequest( 1, (int) $pdp->get_id(), SelectionRequest::CONTEXT_PRODUCT, array(), 'SE' )
		);
		$this->assertCount( 1, $got );
		$this->assertSame( $se['public_id'], $got[0]->public_id );
		// Tier1 empty, Tier2 empty, Tier3 hits → 3 SQL.
		$this->assertSame( 3, $reader->query_count() );

		$reader2 = new CandidateReader();
		$engine2 = $this->engine( $reader2 );
		$got2    = $engine2->select(
			new SelectionRequest( 1, (int) $pdp->get_id(), SelectionRequest::CONTEXT_PRODUCT, array(), 'JP' )
		);
		$this->assertCount( 1, $got2 );
		$this->assertContains( $got2[0]->public_id, array( $se['public_id'], $de['public_id'] ) );
		$this->assertSame( 4, $reader2->query_count() );
	}

	public function test_exclude_and_budget_ceiling(): void {
		$a   = $this->create_simple_product( 'A' );
		$b   = $this->create_simple_product( 'B' );
		$ra  = $this->insert_event( $a, 'SE', 0 );
		$rb  = $this->insert_event( $b, 'SE', 1 );
		$got = $this->engine()->select(
			new SelectionRequest( 5, null, 'unknown', array( (string) $ra['public_id'] ), 'SE' )
		);
		$ids = array_map( static fn( $e ) => $e->public_id, $got );
		$this->assertNotContains( $ra['public_id'], $ids );
		$this->assertContains( $rb['public_id'], $ids );

		$loads = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$p = $this->create_simple_product( 'B' . $i );
			$this->insert_event( $p, 'SE', $i );
			wp_delete_post( (int) $p->get_id(), true );
		}
		$loader = static function ( int $id ) use ( &$loads ) {
			$loads[] = $id;
			return wc_get_product( $id );
		};
		$engine = NotificationsController::make_engine( array( $this, 'identity_shuffle' ), $loader );
		$engine->select( new SelectionRequest( 10, null, 'unknown', array(), 'SE' ) );
		$this->assertLessThanOrEqual( ProductResolutionBudget::MAX, count( $loads ) );
	}

	public function test_rest_ugc_adapter_filter_and_no_client_country(): void {
		$se = $this->create_simple_product( 'SE-R' );
		$de = $this->create_simple_product( 'DE-R' );
		$this->insert_event( $se, 'SE', 0 );
		$this->insert_event( $de, 'DE', 1 );

		add_filter(
			'usp_geo_context_adapter',
			static function () {
				return new UgcGeoContextAdapter( static fn() => 'SE' );
			}
		);
		$response = $this->dispatch(
			array(
				'limit'   => '1',
				'country' => 'DE',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'no-store', $this->cache_control( $response ) );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( NotificationsController::ALLOWLIST, array_keys( $data[0] ) );
		$this->assertArrayNotHasKey( 'visitor_country', $data[0] );
		$this->assertArrayNotHasKey( 'country_code', $data[0] );

		// Selected product should be SE (adapter), not client country=DE.
		$this->assertStringContainsString( 'SE-R', $data[0]['message'] );
	}

	public function test_rest_ugc_null_malformed_failure_no_5xx(): void {
		$product = $this->create_simple_product( 'Any' );
		$this->insert_event( $product, 'DE', 0 );

		add_filter(
			'usp_geo_context_adapter',
			static function () {
				return new UgcGeoContextAdapter( static fn() => null );
			}
		);
		$this->assertSame( 200, $this->dispatch( array( 'limit' => '1' ) )->get_status() );

		remove_all_filters( 'usp_geo_context_adapter' );
		add_filter(
			'usp_geo_context_adapter',
			static function () {
				return new UgcGeoContextAdapter( static fn() => 'not-a-country' );
			}
		);
		$this->assertSame( 200, $this->dispatch( array( 'limit' => '1' ) )->get_status() );

		remove_all_filters( 'usp_geo_context_adapter' );
		add_filter(
			'usp_geo_context_adapter',
			static function () {
				return new UgcGeoContextAdapter(
					static function () {
						throw new \RuntimeException( 'ugc fail' );
					}
				);
			}
		);
		$this->assertSame( 200, $this->dispatch( array( 'limit' => '1' ) )->get_status() );

		remove_all_filters( 'usp_geo_context_adapter' );
		add_filter(
			'usp_geo_context_adapter',
			static function () {
				return new NullGeoContextAdapter();
			}
		);
		$this->assertSame( 200, $this->dispatch( array( 'limit' => '1' ) )->get_status() );
	}

	public function test_template_purchase_country_not_visitor(): void {
		$product = $this->create_simple_product( 'Tpl' );
		$this->insert_event( $product, 'DE', 0 );

		add_filter(
			TemplateSettings::FILTER,
			static function () {
				return 'Bought in {{country}}';
			}
		);
		add_filter(
			'usp_geo_context_adapter',
			static function () {
				return new UgcGeoContextAdapter( static fn() => 'SE' );
			}
		);

		$data = $this->dispatch( array( 'limit' => '1' ) )->get_data();
		$this->assertNotEmpty( $data );
		$this->assertStringContainsString( 'Germany', $data[0]['message'] );
		$this->assertStringNotContainsString( 'Sweden', $data[0]['message'] );
	}

	public function test_privacy_exporter_eraser_regression_and_no_visitor_columns(): void {
		$product = $this->create_simple_product( 'Priv' );
		$order   = wc_create_order();
		$order->set_billing_country( 'SE' );
		$order->set_billing_email( 'buyer-m5@example.test' );
		$order->add_product( $product, 1 );
		$order->set_date_paid( time() );
		$order->calculate_totals( false );
		$order->save();
		$order->update_status( 'processing' );

		$export = PersonalDataExporter::export( 'buyer-m5@example.test', 1 );
		$this->assertIsArray( $export );
		$this->assertArrayHasKey( 'data', $export );

		$erase = PersonalDataEraser::erase( 'buyer-m5@example.test', 1 );
		$this->assertIsArray( $erase );

		global $wpdb;
		$table = Schema::events_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- DESCRIBE diagnostic; table from Schema.
		$cols = $wpdb->get_col( "DESCRIBE {$table}", 0 );
		$this->assertNotContains( 'visitor_country', $cols );
		$this->assertNotContains( 'visitor_ip', $cols );
		$this->assertContains( 'country_code', $cols );
	}

	public function test_worst_case_pdp_geo_sql_and_row_budgets(): void {
		$pdp = $this->create_simple_product( 'WC-PDP' );
		for ( $i = 0; $i < 25; $i++ ) {
			$this->insert_event( $pdp, 'SE', $i );
		}
		for ( $i = 0; $i < 25; $i++ ) {
			$this->insert_event( $pdp, 'DE', 100 + $i );
		}
		for ( $i = 0; $i < 90; $i++ ) {
			$this->insert_event( $this->create_simple_product( 'G' . $i ), 'NO', 200 + $i );
		}
		for ( $i = 0; $i < 90; $i++ ) {
			$this->insert_event( $this->create_simple_product( 'H' . $i ), 'FI', 300 + $i );
		}

		// Force Tier1 miss by requesting country with no preferred matches, then Tier2+3+4.
		$reader = new CandidateReader();
		$engine = $this->engine( $reader );
		$got    = $engine->select(
			new SelectionRequest( 10, (int) $pdp->get_id(), SelectionRequest::CONTEXT_PRODUCT, array(), 'JP' )
		);
		$this->assertLessThanOrEqual( 10, count( $got ) );
		$this->assertLessThanOrEqual( 4, $reader->query_count() );
		$this->assertLessThanOrEqual( 200, $reader->rows_fetched() );
	}

	/**
	 * @param CandidateReader|null $reader Reader.
	 */
	private function engine( ?CandidateReader $reader = null ): SelectionEngine {
		$reader = $reader ?? new CandidateReader();
		return new SelectionEngine(
			$reader,
			new \UniversalSocialProof\Product\PublicProductResolver( new ProductResolutionBudget() ),
			array( $this, 'identity_shuffle' )
		);
	}

	/**
	 * @param array $items Items.
	 * @return array
	 */
	public function identity_shuffle( array $items ): array {
		return array_values( $items );
	}

	/**
	 * @param array<string, string> $params Params.
	 */
	private function dispatch( array $params ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', '/' . NotificationsController::NAMESPACE . NotificationsController::ROUTE );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$response = rest_do_request( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		return $response;
	}

	private function cache_control( WP_REST_Response $response ): string {
		$headers = $response->get_headers();
		foreach ( $headers as $name => $value ) {
			if ( 0 === strcasecmp( (string) $name, 'Cache-Control' ) ) {
				return is_array( $value ) ? (string) $value[0] : (string) $value;
			}
		}
		return '';
	}

	/**
	 * @param \WC_Product $product Product.
	 * @param string|null $country Country.
	 * @param int         $age_sec Age.
	 * @param int|null    $variation_id Variation.
	 * @return array<string, mixed>
	 */
	private function insert_event( $product, ?string $country, int $age_sec, $variation_id = null ): array {
		++$this->source_seq;
		$now = gmdate( 'Y-m-d H:i:s' );
		$row = EventRepository::insert_event(
			array(
				'source_order_id' => $this->source_seq,
				'source_item_id'  => $this->source_seq,
				'public_id'       => wp_generate_uuid4(),
				'product_id'      => (int) $product->get_id(),
				'variation_id'    => $variation_id,
				'quantity'        => '1.000000',
				'country_code'    => $country,
				'occurred_at'     => gmdate( 'Y-m-d H:i:s', time() - $age_sec ),
				'captured_at'     => $now,
				'updated_at'      => $now,
			)
		);
		$this->assertIsArray( $row );
		return $row;
	}

	private function create_simple_product( string $name = 'USP M5' ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '10' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->save();
		return $product;
	}

	private function create_variable_product(): \WC_Product_Variable {
		$product = new \WC_Product_Variable();
		$product->set_name( 'USP M5 Variable' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product->set_attributes( array( $attribute ) );
		$product->save();
		return $product;
	}

	private function create_variation( \WC_Product_Variable $variable ): \WC_Product_Variation {
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( (int) $variable->get_id() );
		$variation->set_status( 'publish' );
		$variation->set_regular_price( '10' );
		$variation->set_attributes( array( 'Size' => 'a' ) );
		$variation->save();
		return $variation;
	}

	private function truncate_events(): void {
		global $wpdb;
		$table = Schema::events_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Schema::events_table().
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * @param array<int, int> $loads Loads.
	 * @param int             $id    ID.
	 */
	private function count_load_ids( array $loads, int $id ): int {
		$n = 0;
		foreach ( $loads as $load_id ) {
			if ( $load_id === $id ) {
				++$n;
			}
		}
		return $n;
	}
}
