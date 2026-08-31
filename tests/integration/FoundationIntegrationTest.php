<?php
/**
 * M0 integration tests — load, HPOS, inert surface.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Integration;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use UniversalSocialProof\Plugin;
use WP_UnitTestCase;

final class FoundationIntegrationTest extends WP_UnitTestCase {

	public function test_plugin_initializes_when_woocommerce_present(): void {
		$this->assertTrue( class_exists( 'WooCommerce' ) );
		$this->assertTrue( Plugin::is_initialized() );
		$this->assertTrue( defined( 'USP_VERSION' ) );
		$this->assertSame( '0.3.0', USP_VERSION );
	}

	public function test_hpos_compatibility_declared(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			$this->markTestSkipped( 'FeaturesUtil unavailable.' );
		}

		$plugin_id = 'universal-social-proof/universal-social-proof.php';
		$features  = FeaturesUtil::get_compatible_features_for_plugin( $plugin_id );

		$compatible = is_array( $features ) && isset( $features['compatible'] ) && is_array( $features['compatible'] )
			? $features['compatible']
			: (array) $features;

		$this->assertContains( 'custom_order_tables', $compatible );
	}

	public function test_usp_events_table_exists_after_m1(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'usp_events';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$this->assertSame( $table, $found );
	}

	public function test_usp_notifications_route_is_get_only(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/universal-social-proof/v1/notifications', $routes );
		foreach ( array_keys( $routes ) as $route ) {
			$this->assertDoesNotMatchRegularExpression(
				'#^/usp(/|$)#',
				(string) $route,
				'M2 must not register abbreviated /usp REST routes'
			);
		}
	}

	public function test_no_frontend_usp_assets_enqueued(): void {
		$wp_scripts = wp_scripts();
		$wp_styles  = wp_styles();
		foreach ( array_keys( $wp_scripts->registered ) as $handle ) {
			$handle = (string) $handle;
			$this->assertDoesNotMatchRegularExpression( '/^(usp-|universal-social-proof)/', $handle );
		}
		foreach ( array_keys( $wp_styles->registered ) as $handle ) {
			$handle = (string) $handle;
			$this->assertDoesNotMatchRegularExpression( '/^(usp-|universal-social-proof)/', $handle );
		}
	}

	public function test_init_is_idempotent(): void {
		Plugin::init();
		Plugin::init();
		$this->assertTrue( Plugin::is_initialized() );
	}
}
