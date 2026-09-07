<?php
/**
 * M6 admin, display switch, diagnostics, uninstall integration tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Integration;

use UniversalSocialProof\Admin\AdminController;
use UniversalSocialProof\Admin\DiagnosticsService;
use UniversalSocialProof\Frontend\AssetLoader;
use UniversalSocialProof\Plugin;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Storage\EventStatus;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Schema;
use WP_REST_Request;
use WP_UnitTestCase;

final class M6AdminIntegrationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Plugin::reset_for_tests();
		delete_option( SettingsRepository::OPTION_KEY );
		delete_option( SettingsRepository::VERSION_KEY );
		delete_option( SettingsRepository::LEGACY_RETENTION );
		delete_option( SettingsRepository::LEGACY_OOS );
		SettingsRepository::reset_for_tests();
		Migrator::upgrade_now();
		Plugin::init();
		AssetLoader::reset_for_tests();
	}

	public function test_settings_migration_from_legacy_options(): void {
		delete_option( SettingsRepository::OPTION_KEY );
		delete_option( SettingsRepository::VERSION_KEY );
		SettingsRepository::reset_for_tests();
		update_option( SettingsRepository::LEGACY_RETENTION, 21 );
		update_option( SettingsRepository::LEGACY_OOS, 'yes' );
		SettingsRepository::maybe_migrate();
		$this->assertSame( 1, (int) get_option( SettingsRepository::VERSION_KEY ) );
		$s = SettingsRepository::get_persisted();
		$this->assertSame( 21, $s['retention_days'] );
		$this->assertTrue( $s['exclude_out_of_stock'] );
		$this->assertSame( 21, (int) get_option( SettingsRepository::LEGACY_RETENTION ) );
	}

	public function test_display_disabled_rest_empty_and_no_enqueue(): void {
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array( 'display_enabled' => false )
			)
		);

		$this->go_to( home_url( '/' ) );
		$this->assertFalse( AssetLoader::should_load() );

		$request  = new WP_REST_Request( 'GET', '/' . \UniversalSocialProof\Rest\NotificationsController::NAMESPACE . '/notifications' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
		$headers = $response->get_headers();
		$cc      = $headers['Cache-Control'] ?? $headers['cache-control'] ?? '';
		$this->assertSame( 'no-store', $cc );
	}

	public function test_display_disabled_capture_still_works(): void {
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array( 'display_enabled' => false )
			)
		);

		$product = new \WC_Product_Simple();
		$product->set_name( 'USP M6 Capture' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '12' );
		$product->set_catalog_visibility( 'visible' );
		$product->save();

		$order = wc_create_order();
		$order->set_billing_country( 'SE' );
		$order->add_product( $product, 1 );
		$order->set_status( 'pending' );
		$order->save();
		$order->update_status( 'processing' );

		global $wpdb;
		$table = Schema::events_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Schema::events_table().
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND status = %s",
				$product->get_id(),
				EventStatus::ACTIVE
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$this->assertGreaterThan( 0, $count );
	}

	public function test_admin_menu_requires_manage_woocommerce(): void {
		/**
		 * Fires before the administration menu loads in wp-admin.
		 *
		 * @since 1.5.0
		 */
		do_action( 'admin_menu' );
		global $submenu;
		$found = false;
		if ( isset( $submenu['woocommerce'] ) ) {
			foreach ( $submenu['woocommerce'] as $item ) {
				if ( isset( $item[2] ) && AdminController::MENU_SLUG === $item[2] ) {
					$found = true;
					$this->assertSame( 'manage_woocommerce', $item[1] );
				}
			}
		}
		$this->assertTrue( $found, 'Social Proof submenu missing' );
	}

	public function test_diagnostics_aggregates_and_no_provenance_keys(): void {
		$diag = DiagnosticsService::collect();
		$this->assertSame( '0.6.0', $diag['runtime_version'] );
		$this->assertSame( Schema::DB_VERSION, $diag['db_version'] );
		$this->assertArrayHasKey( 'events', $diag );
		$json = wp_json_encode( $diag );
		$this->assertIsString( $json );
		$this->assertStringNotContainsString( 'source_order_id', $json );
		$this->assertStringNotContainsString( 'source_item_id', $json );
		$this->assertArrayHasKey( 'active_count', $diag['events'] );
		$this->assertArrayHasKey( 'suppressed_count', $diag['events'] );
	}

	public function test_schema_unchanged(): void {
		$this->assertSame( '20260829m1', Schema::DB_VERSION );
	}

	public function test_uninstall_script_exists_and_is_guarded(): void {
		$path = dirname( __DIR__, 2 ) . '/uninstall.php';
		$this->assertFileExists( $path );
		$src = (string) file_get_contents( $path );
		$this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' ) || exit", $src );
		$this->assertStringContainsString( 'usp_settings', $src );
		$this->assertStringContainsString( 'DROP TABLE', $src );
	}

	public function test_cart_toggle_allows_load(): void {
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array( 'load_on_cart' => true )
			)
		);
		add_filter( 'woocommerce_is_cart', '__return_true' );
		$this->assertTrue( \UniversalSocialProof\Targeting\TargetingPolicy::should_load() );
		remove_all_filters( 'woocommerce_is_cart' );

		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array( 'load_on_cart' => false )
			)
		);
		add_filter( 'woocommerce_is_cart', '__return_true' );
		$this->assertFalse( \UniversalSocialProof\Targeting\TargetingPolicy::should_load() );
		remove_all_filters( 'woocommerce_is_cart' );
	}

	public function test_checkout_hard_denied_even_if_settings_would_allow(): void {
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		$this->assertFalse( \UniversalSocialProof\Targeting\TargetingPolicy::should_load() );
		remove_all_filters( 'woocommerce_is_checkout' );
	}
}
