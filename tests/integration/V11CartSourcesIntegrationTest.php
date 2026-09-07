<?php
/**
 * V1.1 cart capture, schema migration, source mixing integration tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Integration;

use UniversalSocialProof\Capture\CartCaptureService;
use UniversalSocialProof\Cleanup\CartRetentionSettings;
use UniversalSocialProof\Plugin;
use UniversalSocialProof\Rest\NotificationsController;
use UniversalSocialProof\Selection\SelectionRequest;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Storage\EventRepository;
use UniversalSocialProof\Storage\EventStatus;
use UniversalSocialProof\Storage\EventType;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Schema;
use WP_UnitTestCase;

final class V11CartSourcesIntegrationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Plugin::reset_for_tests();
		delete_option( SettingsRepository::OPTION_KEY );
		delete_option( SettingsRepository::VERSION_KEY );
		SettingsRepository::reset_for_tests();
		CartCaptureService::reset_for_tests();
		Migrator::upgrade_now();
		SettingsRepository::maybe_migrate();
		Plugin::init();
	}

	public function test_schema_has_event_type_and_nullable_provenance(): void {
		global $wpdb;
		$table = Schema::events_table();
		$cols  = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by    = array();
		foreach ( $cols as $col ) {
			$by[ $col['Field'] ] = $col;
		}
		$this->assertArrayHasKey( 'event_type', $by );
		$this->assertSame( 'YES', strtoupper( (string) $by['source_order_id']['Null'] ) );
		$this->assertSame( 'YES', strtoupper( (string) $by['source_item_id']['Null'] ) );
		$this->assertSame( Schema::DB_VERSION, (string) get_option( Migrator::OPTION_VERSION ) );
	}

	public function test_migrate_from_legacy_m1_schema_backfills_purchase(): void {
		global $wpdb;
		$table = Schema::events_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		$charset = $wpdb->get_charset_collate();
		$wpdb->query(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_order_id bigint(20) unsigned NOT NULL,
				source_item_id bigint(20) unsigned NOT NULL,
				status varchar(16) NOT NULL,
				suppress_reason varchar(32) DEFAULT NULL,
				public_id char(36) NOT NULL,
				product_id bigint(20) unsigned NOT NULL,
				variation_id bigint(20) unsigned DEFAULT NULL,
				quantity decimal(18,6) NOT NULL,
				country_code char(2) DEFAULT NULL,
				occurred_at datetime NOT NULL,
				captured_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY source_order_item (source_order_id, source_item_id),
				UNIQUE KEY public_id (public_id),
				KEY status_occurred (status, occurred_at)
			) {$charset}"
		);
		$now = gmdate( 'Y-m-d H:i:s' );
		$pid = 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff';
		$wpdb->insert(
			$table,
			array(
				'source_order_id' => 7001,
				'source_item_id'  => 7002,
				'status'          => EventStatus::ACTIVE,
				'public_id'       => $pid,
				'product_id'      => 42,
				'quantity'        => '2.000000',
				'country_code'    => 'SE',
				'occurred_at'     => $now,
				'captured_at'     => $now,
				'updated_at'      => $now,
			)
		);
		update_option( Migrator::OPTION_VERSION, '20260829m1' );
		$this->assertTrue( Migrator::upgrade_now() );
		$this->assertTrue( Migrator::upgrade_now() ); // Idempotent.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id = %s", $pid ), ARRAY_A );
		// phpcs:enable
		$this->assertIsArray( $row );
		$this->assertSame( EventType::PURCHASE, $row['event_type'] );
		$this->assertSame( '7001', (string) $row['source_order_id'] );
		$this->assertSame( '2.000000', $row['quantity'] );
		$this->assertSame( Schema::DB_VERSION, (string) get_option( Migrator::OPTION_VERSION ) );
		$this->assertTrue( Migrator::schema_supports_cart_events() );
	}

	public function test_cart_capture_one_event_per_logical_add(): void {
		SettingsRepository::save( array_merge( SettingsRepository::defaults(), array( 'cart_enabled' => true ) ) );
		$product_id = $this->create_simple_product();

		CartCaptureService::on_add_to_cart( 'key1', $product_id, 2, 0, array(), array() );
		CartCaptureService::on_add_to_cart( 'key1', $product_id, 2, 0, array(), array() ); // Same request dedupe.

		global $wpdb;
		$table = Schema::events_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND product_id = %d",
				EventType::ADD_TO_CART,
				$product_id
			)
		);
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_type = %s AND product_id = %d LIMIT 1",
				EventType::ADD_TO_CART,
				$product_id
			),
			ARRAY_A
		);
		// phpcs:enable
		$this->assertSame( 1, $count );
		$this->assertIsArray( $row );
		$this->assertNull( $row['source_order_id'] );
		$this->assertNull( $row['source_item_id'] );
		$this->assertSame( '2.000000', $row['quantity'] );
		$this->assertArrayNotHasKey( 'ip', $row );
		$this->assertArrayNotHasKey( 'customer_id', $row );
		$this->assertArrayNotHasKey( 'session_id', $row );
	}

	public function test_cart_disabled_does_not_capture(): void {
		SettingsRepository::save( array_merge( SettingsRepository::defaults(), array( 'cart_enabled' => false ) ) );
		$product_id = $this->create_simple_product();
		CartCaptureService::reset_for_tests();
		CartCaptureService::on_add_to_cart( 'k', $product_id, 1, 0, array(), array() );

		global $wpdb;
		$table = Schema::events_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is Schema::events_table().
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND product_id = %d",
				EventType::ADD_TO_CART,
				$product_id
			)
		);
		// phpcs:enable
		$this->assertSame( 0, $count );
	}

	public function test_purchase_priority_then_cart_fill(): void {
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array(
					'purchase_enabled'      => true,
					'cart_enabled'          => true,
					'geo_weighting_enabled' => false,
				)
			)
		);

		$p1  = $this->create_simple_product( 'P1' );
		$p2  = $this->create_simple_product( 'P2' );
		$c1  = $this->create_simple_product( 'C1' );
		$now = gmdate( 'Y-m-d H:i:s' );

		EventRepository::insert_event(
			array(
				'source_order_id' => 8001,
				'source_item_id'  => 8002,
				'public_id'       => wp_generate_uuid4(),
				'product_id'      => $p1,
				'variation_id'    => null,
				'quantity'        => '1.000000',
				'country_code'    => 'SE',
				'occurred_at'     => $now,
				'captured_at'     => $now,
				'updated_at'      => $now,
			)
		);
		EventRepository::insert_event(
			array(
				'source_order_id' => 8003,
				'source_item_id'  => 8004,
				'public_id'       => wp_generate_uuid4(),
				'product_id'      => $p2,
				'variation_id'    => null,
				'quantity'        => '1.000000',
				'country_code'    => 'SE',
				'occurred_at'     => $now,
				'captured_at'     => $now,
				'updated_at'      => $now,
			)
		);
		EventRepository::insert_cart_event(
			array(
				'public_id'    => wp_generate_uuid4(),
				'product_id'   => $c1,
				'variation_id' => null,
				'quantity'     => '1.000000',
				'country_code' => 'SE',
				'occurred_at'  => $now,
				'captured_at'  => $now,
				'updated_at'   => $now,
			)
		);

		$engine = NotificationsController::make_engine( static fn( array $items ) => $items );
		$got    = $engine->select( new SelectionRequest( 3, null, SelectionRequest::CONTEXT_UNKNOWN, array() ) );
		$this->assertCount( 3, $got );
		$types = array_map( static fn( $e ) => $e->event_type, $got );
		$this->assertSame(
			array( EventType::PURCHASE, EventType::PURCHASE, EventType::ADD_TO_CART ),
			$types
		);
	}

	public function test_cart_only_when_purchases_disabled(): void {
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array(
					'purchase_enabled'      => false,
					'cart_enabled'          => true,
					'geo_weighting_enabled' => false,
				)
			)
		);
		$product = $this->create_simple_product( 'CartOnly' );
		$now     = gmdate( 'Y-m-d H:i:s' );
		EventRepository::insert_event(
			array(
				'source_order_id' => 8101,
				'source_item_id'  => 8102,
				'public_id'       => wp_generate_uuid4(),
				'product_id'      => $this->create_simple_product( 'IgnoredPurchase' ),
				'variation_id'    => null,
				'quantity'        => '1.000000',
				'country_code'    => 'SE',
				'occurred_at'     => $now,
				'captured_at'     => $now,
				'updated_at'      => $now,
			)
		);
		EventRepository::insert_cart_event(
			array(
				'public_id'    => wp_generate_uuid4(),
				'product_id'   => $product,
				'variation_id' => null,
				'quantity'     => '1.000000',
				'country_code' => null,
				'occurred_at'  => $now,
				'captured_at'  => $now,
				'updated_at'   => $now,
			)
		);

		$engine = NotificationsController::make_engine( static fn( array $items ) => $items );
		$got    = $engine->select( new SelectionRequest( 5, null, SelectionRequest::CONTEXT_UNKNOWN, array() ) );
		$this->assertCount( 1, $got );
		$this->assertSame( EventType::ADD_TO_CART, $got[0]->event_type );
		$dto = $got[0]->to_public_array();
		$this->assertIsArray( $dto );
		$this->assertStringContainsString( 'just added', $dto['message'] );
		$this->assertStringNotContainsString( 'Someone in  ', $dto['message'] );
	}

	public function test_stale_cart_excluded_by_cutoff(): void {
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array(
					'purchase_enabled'       => false,
					'cart_enabled'           => true,
					'cart_retention_minutes' => 60,
					'geo_weighting_enabled'  => false,
				)
			)
		);
		$product = $this->create_simple_product( 'Stale' );
		$old     = gmdate( 'Y-m-d H:i:s', time() - ( 120 * MINUTE_IN_SECONDS ) );
		EventRepository::insert_cart_event(
			array(
				'public_id'    => wp_generate_uuid4(),
				'product_id'   => $product,
				'variation_id' => null,
				'quantity'     => '1.000000',
				'country_code' => 'SE',
				'occurred_at'  => $old,
				'captured_at'  => $old,
				'updated_at'   => $old,
			)
		);
		$engine = NotificationsController::make_engine( static fn( array $items ) => $items );
		$got    = $engine->select( new SelectionRequest( 5, null, SelectionRequest::CONTEXT_UNKNOWN, array() ) );
		$this->assertSame( array(), $got );
	}

	public function test_cleanup_purges_old_cart_events(): void {
		$product = $this->create_simple_product( 'Purge' );
		$old     = gmdate( 'Y-m-d H:i:s', time() - ( 200 * MINUTE_IN_SECONDS ) );
		EventRepository::insert_cart_event(
			array(
				'public_id'    => wp_generate_uuid4(),
				'product_id'   => $product,
				'variation_id' => null,
				'quantity'     => '1.000000',
				'country_code' => null,
				'occurred_at'  => $old,
				'captured_at'  => $old,
				'updated_at'   => $old,
			)
		);
		$deleted = EventRepository::delete_older_than_for_type(
			EventType::ADD_TO_CART,
			CartRetentionSettings::cutoff_utc(),
			50
		);
		$this->assertGreaterThanOrEqual( 1, $deleted );

		global $wpdb;
		$table = Schema::events_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is Schema::events_table().
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND product_id = %d",
				EventType::ADD_TO_CART,
				$product
			)
		);
		// phpcs:enable
		$this->assertSame( 0, $count );
		$this->assertSame( 60, CartRetentionSettings::minutes() );
	}

	/**
	 * @param string $name Product name.
	 */
	private function create_simple_product( string $name = 'USP Test' ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '10' );
		$product->set_status( 'publish' );
		$product->save();
		return (int) $product->get_id();
	}
}
