<?php
/**
 * M2 integration tests — candidate SQL, selection, product eligibility, REST.
 *
 * Capture-integration tests create genuine WooCommerce orders and rely on M1
 * capture. Selection-specific fixture tests insert usp_events rows directly.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Integration;

use UniversalSocialProof\Cleanup\RetentionSettings;
use UniversalSocialProof\Plugin;
use UniversalSocialProof\Product\PublicProductResolver;
use UniversalSocialProof\Rest\NotificationsController;
use UniversalSocialProof\Selection\CandidateQuery;
use UniversalSocialProof\Selection\CandidateReader;
use UniversalSocialProof\Selection\ProductResolutionBudget;
use UniversalSocialProof\Selection\SelectionEngine;
use UniversalSocialProof\Selection\SelectionRequest;
use UniversalSocialProof\Selection\StockExclusionSettings;
use UniversalSocialProof\Storage\EventRepository;
use UniversalSocialProof\Storage\EventStatus;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Schema;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

final class M2SelectionRestIntegrationTest extends WP_UnitTestCase {

	/**
	 * Monotonic source ids for fixture inserts (not WooCommerce orders).
	 *
	 * @var int
	 */
	private int $source_seq = 700000;

	/**
	 * Variation attribute sequence.
	 *
	 * @var int
	 */
	private int $variation_seq = 0;

	public function set_up(): void {
		parent::set_up();
		Plugin::reset_for_tests();
		delete_option( Migrator::OPTION_VERSION );
		delete_option( StockExclusionSettings::OPTION_KEY );
		delete_option( RetentionSettings::OPTION_KEY );
		Migrator::upgrade_now();
		Plugin::init();
		$this->truncate_events();
		wp_set_current_user( 0 );
	}

	public function test_candidate_reader_active_only_and_freshness(): void {
		$product = $this->create_simple_product();
		$active  = $this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s' ) );
		$old     = $this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s', time() - ( 120 * DAY_IN_SECONDS ) ) );
		$sup     = $this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s' ) );
		EventRepository::suppress_item( (int) $sup['source_order_id'], (int) $sup['source_item_id'], 'cancelled' );

		$reader = new CandidateReader();
		$found  = $reader->find_recent_active( CandidateQuery::global( RetentionSettings::cutoff_utc(), array() ) );
		$ids    = array_map( static fn( $c ) => $c->public_id, $found );
		$this->assertContains( $active['public_id'], $ids );
		$this->assertNotContains( $old['public_id'], $ids );
		$this->assertNotContains( $sup['public_id'], $ids );
	}

	public function test_candidate_reader_global_and_preferred_caps(): void {
		$product = $this->create_simple_product();
		for ( $i = 0; $i < 85; $i++ ) {
			$this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s', time() - $i ) );
		}
		$reader = new CandidateReader();
		$global = $reader->find_recent_active( CandidateQuery::global( RetentionSettings::cutoff_utc(), array() ) );
		$this->assertCount( CandidateQuery::GLOBAL_LIMIT, $global );

		$preferred = $reader->find_recent_active(
			CandidateQuery::preferred( RetentionSettings::cutoff_utc(), array(), (int) $product->get_id() )
		);
		$this->assertCount( CandidateQuery::PREFERRED_LIMIT, $preferred );
	}

	public function test_candidate_reader_exclusion_and_no_rand(): void {
		$product = $this->create_simple_product();
		$keep    = $this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s' ) );
		$drop    = $this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s', time() - 10 ) );

		$reader = new CandidateReader();
		$found  = $reader->find_recent_active(
			CandidateQuery::global( RetentionSettings::cutoff_utc(), array( (string) $drop['public_id'] ) )
		);
		$ids    = array_map( static fn( $c ) => $c->public_id, $found );
		$this->assertContains( $keep['public_id'], $ids );
		$this->assertNotContains( $drop['public_id'], $ids );

		$plan = $reader->explain_recent_active( CandidateQuery::global( RetentionSettings::cutoff_utc(), array() ) );
		$this->assertNotEmpty( $plan );
		$this->assertStringNotContainsString( 'RAND', (string) wp_json_encode( $plan ) );
	}

	public function test_query_plan_uses_existing_indexes_when_optimizer_agrees(): void {
		global $wpdb;
		$product = $this->create_simple_product();
		for ( $i = 0; $i < 12; $i++ ) {
			$this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s', time() - $i ) );
		}
		$reader = new CandidateReader();
		$global = $reader->explain_recent_active( CandidateQuery::global( RetentionSettings::cutoff_utc(), array() ) );
		$pdp    = $reader->explain_recent_active(
			CandidateQuery::preferred( RetentionSettings::cutoff_utc(), array(), (int) $product->get_id() )
		);
		$this->assertNotEmpty( $global );
		$this->assertNotEmpty( $pdp );

		$indexes     = $wpdb->get_results( 'SHOW INDEX FROM ' . Schema::events_table(), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$index_names = array_unique( array_column( $indexes, 'Key_name' ) );
		$this->assertContains( 'status_occurred', $index_names );
		$this->assertContains( 'status_product_occurred', $index_names );

		$global_key = (string) ( $global[0]['key'] ?? '' );
		$pdp_key    = (string) ( $pdp[0]['key'] ?? '' );
		if ( '' !== $global_key ) {
			$this->assertContains( $global_key, array( 'status_occurred', 'status_product_occurred', 'PRIMARY', 'public_id' ) );
		}
		if ( '' !== $pdp_key ) {
			$this->assertContains( $pdp_key, array( 'status_product_occurred', 'status_occurred', 'PRIMARY', 'public_id' ) );
		}
	}

	public function test_selection_zero_one_many_and_k_bounds(): void {
		$engine = $this->engine();
		$none   = $engine->select( new SelectionRequest( 5, null, 'unknown', array() ) );
		$this->assertSame( array(), $none );

		$product = $this->create_simple_product();
		$one     = $this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s' ) );
		$got     = $engine->select( new SelectionRequest( 5, null, 'unknown', array() ) );
		$this->assertCount( 1, $got );
		$this->assertSame( $one['public_id'], $got[0]->public_id );

		for ( $i = 0; $i < 12; $i++ ) {
			$this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s', time() - $i - 1 ) );
		}
		$k1  = $this->engine()->select( new SelectionRequest( 1, null, 'unknown', array() ) );
		$k5  = $this->engine()->select( new SelectionRequest( 5, null, 'unknown', array() ) );
		$k10 = $this->engine()->select( new SelectionRequest( 10, null, 'unknown', array() ) );
		$this->assertCount( 1, $k1 );
		$this->assertCount( 5, $k5 );
		$this->assertCount( 10, $k10 );
		$this->assertLessThanOrEqual( 10, count( $k10 ) );
	}

	public function test_unique_events_duplicate_products_allowed_and_exclusions(): void {
		$product = $this->create_simple_product();
		$a       = $this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s' ) );
		$b       = $this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s', time() - 5 ) );
		$engine  = $this->engine();
		$got     = $engine->select( new SelectionRequest( 5, null, 'unknown', array() ) );
		$ids     = array_map( static fn( $e ) => $e->public_id, $got );
		$this->assertCount( count( array_unique( $ids ) ), $ids );
		$this->assertContains( $a['public_id'], $ids );
		$this->assertContains( $b['public_id'], $ids );
		$this->assertSame( (int) $product->get_id(), $got[0]->product->id );
		$this->assertSame( (int) $product->get_id(), $got[1]->product->id );

		$excluded = $engine->select( new SelectionRequest( 5, null, 'unknown', array( (string) $a['public_id'] ) ) );
		$ex_ids   = array_map( static fn( $e ) => $e->public_id, $excluded );
		$this->assertNotContains( $a['public_id'], $ex_ids );
		$this->assertContains( $b['public_id'], $ex_ids );
	}

	public function test_selection_skips_deleted_and_does_not_mutate_m1_rows(): void {
		$live    = $this->create_simple_product();
		$deleted = $this->create_simple_product();
		$keep    = $this->insert_fixture_event( $live, gmdate( 'Y-m-d H:i:s' ) );
		$gone    = $this->insert_fixture_event( $deleted, gmdate( 'Y-m-d H:i:s', time() - 2 ) );
		$before  = EventRepository::find_by_source( (int) $keep['source_order_id'], (int) $keep['source_item_id'] );
		wp_delete_post( (int) $deleted->get_id(), true );

		$got = $this->engine()->select( new SelectionRequest( 5, null, 'unknown', array() ) );
		$ids = array_map( static fn( $e ) => $e->public_id, $got );
		$this->assertContains( $keep['public_id'], $ids );
		$this->assertNotContains( $gone['public_id'], $ids );

		$after = EventRepository::find_by_source( (int) $keep['source_order_id'], (int) $keep['source_item_id'] );
		$this->assertSame( $before['updated_at'], $after['updated_at'] );
		$this->assertSame( EventStatus::ACTIVE, $after['status'] );
	}

	public function test_pdp_preferred_occupies_slot_and_global_cannot_consume_search_first(): void {
		$preferred_product = $this->create_simple_product( 'Preferred' );
		$globals           = array();
		for ( $i = 0; $i < 8; $i++ ) {
			$globals[] = $this->create_simple_product( 'G' . $i );
		}
		$pref_row = $this->insert_fixture_event( $preferred_product, gmdate( 'Y-m-d H:i:s', time() - 50 ) );
		foreach ( $globals as $i => $g ) {
			$this->insert_fixture_event( $g, gmdate( 'Y-m-d H:i:s', time() - $i ) );
		}

		$engine = $this->engine();
		$limit1 = $engine->select(
			new SelectionRequest( 1, (int) $preferred_product->get_id(), SelectionRequest::CONTEXT_PRODUCT, array() )
		);
		$this->assertCount( 1, $limit1 );
		$this->assertSame( $pref_row['public_id'], $limit1[0]->public_id );

		$limit5 = $this->engine()->select(
			new SelectionRequest( 5, (int) $preferred_product->get_id(), SelectionRequest::CONTEXT_PRODUCT, array() )
		);
		$this->assertSame( $pref_row['public_id'], $limit5[0]->public_id );
		$this->assertCount( 5, $limit5 );
	}

	public function test_pdp_no_preferred_and_invalid_request_product_falls_back_globally(): void {
		$global_product = $this->create_simple_product( 'Global' );
		$row            = $this->insert_fixture_event( $global_product, gmdate( 'Y-m-d H:i:s' ) );

		$missing = $this->engine()->select( new SelectionRequest( 5, 99999991, SelectionRequest::CONTEXT_PRODUCT, array() ) );
		$ids     = array_map( static fn( $e ) => $e->public_id, $missing );
		$this->assertContains( $row['public_id'], $ids );

		$other = $this->create_simple_product( 'Other' );
		$none  = $this->engine()->select(
			new SelectionRequest( 5, (int) $other->get_id(), SelectionRequest::CONTEXT_PRODUCT, array() )
		);
		$this->assertContains( $row['public_id'], array_map( static fn( $e ) => $e->public_id, $none ) );
	}

	public function test_pdp_excluded_preferred_and_all_preferred_invalid(): void {
		$preferred = $this->create_simple_product( 'Pref' );
		$global    = $this->create_simple_product( 'G' );
		$pref_row  = $this->insert_fixture_event( $preferred, gmdate( 'Y-m-d H:i:s' ) );
		$g_row     = $this->insert_fixture_event( $global, gmdate( 'Y-m-d H:i:s', time() - 1 ) );

		$excluded = $this->engine()->select(
			new SelectionRequest(
				5,
				(int) $preferred->get_id(),
				SelectionRequest::CONTEXT_PRODUCT,
				array( (string) $pref_row['public_id'] )
			)
		);
		$ids      = array_map( static fn( $e ) => $e->public_id, $excluded );
		$this->assertNotContains( $pref_row['public_id'], $ids );
		$this->assertContains( $g_row['public_id'], $ids );

		$draft = $this->create_simple_product( 'Draft' );
		$draft->set_status( 'draft' );
		$draft->save();
		$this->insert_fixture_event( $draft, gmdate( 'Y-m-d H:i:s' ) );
		$got = $this->engine()->select(
			new SelectionRequest( 5, (int) $draft->get_id(), SelectionRequest::CONTEXT_PRODUCT, array() )
		);
		$this->assertContains( $g_row['public_id'], array_map( static fn( $e ) => $e->public_id, $got ) );
	}

	public function test_variation_request_prioritizes_exact_variation_and_parent_request_uses_parent(): void {
		$parent    = $this->create_variable_product();
		$variation = $this->create_variation( $parent );
		$other_var = $this->create_variation( $parent );
		$exact     = $this->insert_fixture_event( $parent, gmdate( 'Y-m-d H:i:s', time() - 20 ), (int) $variation->get_id() );
		$this->insert_fixture_event( $parent, gmdate( 'Y-m-d H:i:s', time() - 1 ), (int) $other_var->get_id() );

		$from_var = $this->engine()->select(
			new SelectionRequest( 1, (int) $variation->get_id(), SelectionRequest::CONTEXT_PRODUCT, array() )
		);
		$this->assertCount( 1, $from_var );
		$this->assertSame( $exact['public_id'], $from_var[0]->public_id );
		$this->assertSame( (int) $variation->get_id(), $from_var[0]->product->id );

		$from_parent = $this->engine()->select(
			new SelectionRequest( 1, (int) $parent->get_id(), SelectionRequest::CONTEXT_PRODUCT, array() )
		);
		$this->assertCount( 1, $from_parent );
		$this->assertSame( (int) $parent->get_id(), $from_parent[0]->product_id );
	}

	public function test_resolvable_ineligible_variation_skips_without_parent_fallback(): void {
		$parent    = $this->create_variable_product();
		$variation = $this->create_variation( $parent );
		$row       = $this->insert_fixture_event( $parent, gmdate( 'Y-m-d H:i:s' ), (int) $variation->get_id() );
		$variation->set_status( 'private' );
		$variation->save();

		$got = $this->engine()->select( new SelectionRequest( 5, null, 'unknown', array() ) );
		$ids = array_map( static fn( $e ) => $e->public_id, $got );
		$this->assertNotContains( $row['public_id'], $ids );
	}

	public function test_unresolvable_variation_falls_back_to_eligible_parent(): void {
		$parent    = $this->create_variable_product();
		$variation = $this->create_variation( $parent );
		$row       = $this->insert_fixture_event( $parent, gmdate( 'Y-m-d H:i:s' ), (int) $variation->get_id() );
		wp_delete_post( (int) $variation->get_id(), true );

		$got = $this->engine()->select( new SelectionRequest( 5, null, 'unknown', array() ) );
		$this->assertNotEmpty( $got );
		$this->assertSame( $row['public_id'], $got[0]->public_id );
		$this->assertSame( (int) $parent->get_id(), $got[0]->product->id );
	}

	public function test_product_eligibility_matrix(): void {
		$resolver = new PublicProductResolver( new ProductResolutionBudget() );

		$simple = $this->create_simple_product();
		$this->assertNotNull( $resolver->resolve_for_event( (int) $simple->get_id(), null ) );

		$hidden = $this->create_simple_product();
		$hidden->set_catalog_visibility( 'hidden' );
		$hidden->save();
		$this->assertNull( $resolver->resolve_for_event( (int) $hidden->get_id(), null ) );

		foreach ( array( 'catalog', 'search' ) as $vis ) {
			$p = $this->create_simple_product();
			$p->set_catalog_visibility( $vis );
			$p->save();
			$this->assertNotNull( $resolver->resolve_for_event( (int) $p->get_id(), null ), $vis );
		}

		foreach ( array( 'draft', 'private', 'pending' ) as $status ) {
			$p = $this->create_simple_product();
			$p->set_status( $status );
			$p->save();
			$this->assertNull( $resolver->resolve_for_event( (int) $p->get_id(), null ), $status );
		}

		$future = $this->create_simple_product();
		$future->set_date_created( gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ) );
		$future->set_status( 'future' );
		$future->save();
		if ( 'publish' !== (string) $future->get_status() ) {
			$this->assertNull( $resolver->resolve_for_event( (int) $future->get_id(), null ), 'future' );
		}

		$trash = $this->create_simple_product();
		wp_trash_post( (int) $trash->get_id() );
		$this->assertNull( $resolver->resolve_for_event( (int) $trash->get_id(), null ) );

		$locked = $this->create_simple_product();
		$locked->set_post_password( 'secret' );
		$locked->save();
		$this->assertNull( $resolver->resolve_for_event( (int) $locked->get_id(), null ) );

		$oos = $this->create_simple_product();
		$oos->set_stock_status( 'outofstock' );
		$oos->save();
		$this->assertNotNull( $resolver->resolve_for_event( (int) $oos->get_id(), null ) );
		update_option( StockExclusionSettings::OPTION_KEY, 'yes' );
		$resolver2 = new PublicProductResolver( new ProductResolutionBudget() );
		$this->assertNull( $resolver2->resolve_for_event( (int) $oos->get_id(), null ) );
		delete_option( StockExclusionSettings::OPTION_KEY );

		$back = $this->create_simple_product();
		$back->set_stock_status( 'onbackorder' );
		$back->save();
		update_option( StockExclusionSettings::OPTION_KEY, 'yes' );
		$resolver3 = new PublicProductResolver( new ProductResolutionBudget() );
		$this->assertNotNull( $resolver3->resolve_for_event( (int) $back->get_id(), null ) );
		delete_option( StockExclusionSettings::OPTION_KEY );

		$empty_price = $this->create_simple_product();
		$empty_price->set_regular_price( '' );
		$empty_price->save();
		$this->assertNotNull( ( new PublicProductResolver( new ProductResolutionBudget() ) )->resolve_for_event( (int) $empty_price->get_id(), null ) );

		$virtual = $this->create_simple_product();
		$virtual->set_virtual( true );
		$virtual->set_downloadable( true );
		$virtual->save();
		$pub = ( new PublicProductResolver( new ProductResolutionBudget() ) )->resolve_for_event( (int) $virtual->get_id(), null );
		$this->assertNotNull( $pub );
		$this->assertNotSame( '', $pub->permalink );
		$this->assertNull( $pub->thumbnail_url );

		$parent    = $this->create_variable_product();
		$variation = $this->create_variation( $parent );
		$as_var    = ( new PublicProductResolver( new ProductResolutionBudget() ) )->resolve_for_event( (int) $parent->get_id(), (int) $variation->get_id() );
		$this->assertNotNull( $as_var );
		$this->assertSame( 'variation', $as_var->type );
		$this->assertNotSame( '', $as_var->permalink );

		$parent->set_status( 'draft' );
		$parent->save();
		$this->assertNull( ( new PublicProductResolver( new ProductResolutionBudget() ) )->resolve_for_event( (int) $parent->get_id(), (int) $variation->get_id() ) );
	}

	public function test_product_resolution_budget_never_exceeds_twenty_in_selection(): void {
		$loads = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$p = $this->create_simple_product( 'B' . $i );
			$this->insert_fixture_event( $p, gmdate( 'Y-m-d H:i:s', time() - $i ) );
			wp_delete_post( (int) $p->get_id(), true );
		}
		$loader = static function ( int $id ) use ( &$loads ) {
			$loads[] = $id;
			return wc_get_product( $id );
		};
		$engine = NotificationsController::make_engine( array( $this, 'identity_shuffle' ), $loader );
		$got    = $engine->select( new SelectionRequest( 10, null, 'unknown', array() ) );
		$this->assertSame( array(), $got );
		$this->assertCount( ProductResolutionBudget::MAX, $loads );
		$this->assertCount( ProductResolutionBudget::MAX, array_unique( $loads ) );
	}

	public function test_pdp_search_uncached_loads_capped_at_five(): void {
		$parent = $this->create_variable_product();
		for ( $i = 0; $i < 8; $i++ ) {
			$variation = $this->create_variation( $parent );
			$variation->set_status( 'private' );
			$variation->save();
			$this->insert_fixture_event( $parent, gmdate( 'Y-m-d H:i:s', time() - $i ), (int) $variation->get_id() );
		}
		$global = $this->create_simple_product( 'G' );
		$this->insert_fixture_event( $global, gmdate( 'Y-m-d H:i:s' ) );

		$loads  = array();
		$loader = static function ( int $id ) use ( &$loads ) {
			$loads[] = $id;
			return wc_get_product( $id );
		};
		$engine = NotificationsController::make_engine( array( $this, 'identity_shuffle' ), $loader );
		$engine->select(
			new SelectionRequest( 1, (int) $parent->get_id(), SelectionRequest::CONTEXT_PRODUCT, array() )
		);

		$variation_loads = 0;
		$parent_id       = (int) $parent->get_id();
		$global_id       = (int) $global->get_id();
		foreach ( $loads as $id ) {
			if ( $id !== $parent_id && $id !== $global_id ) {
				++$variation_loads;
			}
		}
		$this->assertLessThanOrEqual( ProductResolutionBudget::PDP_SEARCH_CAP, $variation_loads );
	}

	public function test_capture_integration_then_rest_returns_public_dto(): void {
		$product = $this->create_simple_product( 'Captured' );
		$order   = wc_create_order();
		$order->set_billing_country( 'SE' );
		$order->set_billing_email( 'buyer-m2@example.test' );
		$order->add_product( $product, 1 );
		$order->set_date_paid( time() );
		$order->calculate_totals( false );
		$order->save();
		$order->update_status( 'processing' );

		$item = array_values( $order->get_items( 'line_item' ) )[0];
		$row  = EventRepository::find_by_source( (int) $order->get_id(), (int) $item->get_id() );
		$this->assertNotNull( $row );

		$response = $this->dispatch( array( 'limit' => '5' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$ids = array_column( $data, 'public_id' );
		$this->assertContains( $row['public_id'], $ids );
		foreach ( $data as $item_dto ) {
			$this->assertSame( NotificationsController::ALLOWLIST, array_keys( $item_dto ) );
			$this->assertArrayNotHasKey( 'message', $item_dto );
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $item_dto['occurred_at'] );
			$this->assertArrayNotHasKey( 'source_order_id', $item_dto );
			$this->assertArrayNotHasKey( 'product_id', $item_dto );
			$this->assertArrayNotHasKey( 'quantity', $item_dto );
			$this->assertArrayNotHasKey( 'country_code', $item_dto );
		}
	}

	public function test_rest_anonymous_get_default_limit_and_string_five(): void {
		$product = $this->create_simple_product();
		for ( $i = 0; $i < 7; $i++ ) {
			$this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s', time() - $i ) );
		}
		$default = $this->dispatch( array() );
		$this->assertSame( 200, $default->get_status() );
		$this->assertCount( 5, $default->get_data() );
		$this->assertSame( 'no-store', $this->cache_control( $default ) );

		$as_string = $this->dispatch( array( 'limit' => '5' ) );
		$this->assertSame( 200, $as_string->get_status() );
		$this->assertCount( 5, $as_string->get_data() );
	}

	public function test_rest_limit_clamp_and_malformed(): void {
		$product = $this->create_simple_product();
		for ( $i = 0; $i < 12; $i++ ) {
			$this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s', time() - $i ) );
		}
		$this->assertCount( 1, $this->dispatch( array( 'limit' => '0' ) )->get_data() );
		$this->assertCount( 1, $this->dispatch( array( 'limit' => '-10' ) )->get_data() );
		$this->assertCount( 10, $this->dispatch( array( 'limit' => '11' ) )->get_data() );
		$this->assertCount( 10, $this->dispatch( array( 'limit' => '999' ) )->get_data() );

		$this->assertSame( 400, $this->dispatch( array( 'limit' => '5.9' ) )->get_status() );
		$this->assertSame( 'no-store', $this->cache_control( $this->dispatch( array( 'limit' => '5.9' ) ) ) );
		$this->assertSame( 400, $this->dispatch( array( 'limit' => 'nope' ) )->get_status() );
		$this->assertSame( 400, $this->dispatch( array( 'limit' => array( '1' ) ) )->get_status() );
	}

	public function test_rest_product_id_page_context_exclude_and_empty(): void {
		$this->assertSame( 400, $this->dispatch( array( 'product_id' => 'abc' ) )->get_status() );
		$this->assertSame( 400, $this->dispatch( array( 'product_id' => '5.9' ) )->get_status() );
		$this->assertSame( 200, $this->dispatch( array( 'product_id' => '99999992' ) )->get_status() );

		$product = $this->create_simple_product();
		$row     = $this->insert_fixture_event( $product, gmdate( 'Y-m-d H:i:s' ) );
		$unknown = $this->dispatch(
			array(
				'page_context' => 'checkout',
				'product_id'   => (string) $product->get_id(),
				'limit'        => '1',
			)
		);
		$this->assertSame( 200, $unknown->get_status() );

		$malformed = $this->dispatch(
			array(
				'exclude' => array( 'not-a-uuid', (string) $row['public_id'] ),
			)
		);
		$this->assertSame( 200, $malformed->get_status() );
		$this->assertNotContains( $row['public_id'], array_column( $malformed->get_data(), 'public_id' ) );

		$too_many = array();
		for ( $i = 0; $i < 21; $i++ ) {
			$too_many[] = wp_generate_uuid4();
		}
		$this->assertSame( 400, $this->dispatch( array( 'exclude' => $too_many ) )->get_status() );

		$this->truncate_events();
		$empty = $this->dispatch( array() );
		$this->assertSame( 200, $empty->get_status() );
		$this->assertSame( array(), $empty->get_data() );
	}

	public function test_rest_post_unavailable_and_unrelated_cache_header_untouched(): void {
		$request  = new WP_REST_Request( 'POST', '/universal-social-proof/v1/notifications' );
		$response = $this->dispatch_request( $request );
		$this->assertContains( $response->get_status(), array( 404, 405 ) );
		$this->assertSame( 'no-store', $this->cache_control( $response ) );

		$other = $this->dispatch_request( new WP_REST_Request( 'GET', '/wp/v2/types' ) );
		$cc    = $this->cache_control( $other );
		$this->assertNotSame( 'no-store', $cc );
	}

	public function test_missing_table_degrades_to_empty_array(): void {
		global $wpdb;
		$table = Schema::events_table();
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( Migrator::OPTION_VERSION );
		$response = $this->dispatch( array() );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
		Migrator::upgrade_now();
	}

	public function test_schema_version_unchanged(): void {
		$this->assertSame( '20260829m1', Schema::DB_VERSION );
		$this->assertSame( Schema::DB_VERSION, get_option( Migrator::OPTION_VERSION ) );
	}

	/**
	 * @param array<string, mixed> $query Query params.
	 */
	private function dispatch( array $query ): WP_REST_Response {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/universal-social-proof/v1/notifications' );
		$request->set_query_params( $query );
		return $this->dispatch_request( $request );
	}

	/**
	 * Apply rest_post_dispatch as WP_REST_Server::serve_request() does for real HTTP.
	 *
	 * Dispatch itself does not run that filter (validation 400/405 skip the callback).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function dispatch_request( WP_REST_Request $request ): WP_REST_Response {
		$server   = rest_get_server();
		$response = $server->dispatch( $request );
		/**
		 * Filters the REST API response (same hook WP applies in serve_request).
		 *
		 * @since 4.4.0
		 * @param WP_REST_Response $response Response.
		 * @param WP_REST_Server    $server   Server.
		 * @param WP_REST_Request   $request  Request.
		 */
		$response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), $server, $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		return $response;
	}

	/**
	 * @param WP_REST_Response|\WP_HTTP_Response $response Response.
	 */
	private function cache_control( $response ): string {
		$headers = $response->get_headers();
		foreach ( array( 'Cache-Control', 'cache-control' ) as $key ) {
			if ( isset( $headers[ $key ] ) ) {
				$val = $headers[ $key ];
				return is_array( $val ) ? implode( ',', $val ) : (string) $val;
			}
		}
		foreach ( $headers as $name => $val ) {
			if ( 0 === strcasecmp( (string) $name, 'Cache-Control' ) ) {
				return is_array( $val ) ? implode( ',', $val ) : (string) $val;
			}
		}
		return '';
	}

	private function engine(): SelectionEngine {
		return NotificationsController::make_engine( array( $this, 'identity_shuffle' ) );
	}

	/**
	 * @param array $items Candidates.
	 * @return array Candidates.
	 */
	public function identity_shuffle( array $items ): array {
		return array_values( $items );
	}

	/**
	 * Selection-specific fixture insert (not M1 capture).
	 *
	 * @param \WC_Product $product      Product (parent for variations).
	 * @param string      $occurred_at UTC MySQL datetime.
	 * @param int|null    $variation_id Variation ID.
	 * @param int|null    $product_id  Override stored product_id (ghost ids).
	 * @return array<string, mixed>
	 */
	private function insert_fixture_event( $product, string $occurred_at, $variation_id = null, $product_id = null ): array {
		++$this->source_seq;
		$now = gmdate( 'Y-m-d H:i:s' );
		$row = EventRepository::insert_event(
			array(
				'source_order_id' => $this->source_seq,
				'source_item_id'  => $this->source_seq,
				'public_id'       => wp_generate_uuid4(),
				'product_id'      => null !== $product_id ? $product_id : (int) $product->get_id(),
				'variation_id'    => $variation_id,
				'quantity'        => '1.000000',
				'country_code'    => 'SE',
				'occurred_at'     => $occurred_at,
				'captured_at'     => $now,
				'updated_at'      => $now,
			)
		);
		$this->assertIsArray( $row );
		return $row;
	}

	private function create_simple_product( string $name = 'USP M2 Product' ): \WC_Product_Simple {
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
		$product->set_name( 'USP Variable' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product->set_attributes( array( $attribute ) );
		$product->save();
		return $product;
	}

	private function create_variation( \WC_Product_Variable $variable ): \WC_Product_Variation {
		++$this->variation_seq;
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( (int) $variable->get_id() );
		$variation->set_regular_price( '10' );
		$variation->set_status( 'publish' );
		$variation->set_attributes( array( 'size' => 'opt' . $this->variation_seq ) );
		$variation->save();
		return $variation;
	}

	private function truncate_events(): void {
		global $wpdb;
		$table = Schema::events_table();
		$wpdb->query( 'TRUNCATE TABLE ' . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
