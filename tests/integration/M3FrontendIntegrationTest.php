<?php
/**
 * M3 frontend integration — enqueue, shell, bootstrap, gates.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Integration;

use UniversalSocialProof\Frontend\AssetLoader;
use UniversalSocialProof\Frontend\BootstrapConfig;
use UniversalSocialProof\Plugin;
use UniversalSocialProof\Rest\NotificationsController;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Schema;
use WP_UnitTestCase;

final class M3FrontendIntegrationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Plugin::reset_for_tests();
		Migrator::upgrade_now();
		Plugin::init();
		wp_dequeue_script( AssetLoader::SCRIPT_HANDLE );
		wp_dequeue_style( AssetLoader::STYLE_HANDLE );
		wp_deregister_script( AssetLoader::SCRIPT_HANDLE );
		wp_deregister_style( AssetLoader::STYLE_HANDLE );
		AssetLoader::reset_for_tests();
	}

	public function test_frontend_registers_and_enqueues_on_shop(): void {
		$this->go_to( home_url( '/' ) );
		/**
		 * Fires when scripts and styles are enqueued on the front end.
		 *
		 * @since 2.8.0
		 */
		do_action( 'wp_enqueue_scripts' );
		$this->assertTrue( AssetLoader::should_load() );
		AssetLoader::enqueue();
		$this->assertTrue( AssetLoader::was_enqueued() );
		$this->assertTrue( wp_script_is( AssetLoader::SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( AssetLoader::STYLE_HANDLE, 'enqueued' ) );
	}

	public function test_shell_contains_no_event_payload(): void {
		$this->go_to( home_url( '/' ) );
		AssetLoader::enqueue();
		ob_start();
		\UniversalSocialProof\Frontend\ShellRenderer::render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'data-usp-toaster', $html );
		$this->assertStringContainsString( 'usp-toaster__message', $html );
		$this->assertStringNotContainsString( 'occurred_at', $html );
		$this->assertStringNotContainsString( 'public_id', $html );
		$this->assertStringNotContainsString( 'product_url', $html );
	}

	public function test_bootstrap_pdp_and_unknown(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'USP M3 PDP' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->go_to( get_permalink( $product->get_id() ) );
		$config = BootstrapConfig::build();
		$this->assertSame( 'product', $config['pageContext'] );
		$this->assertSame( (int) $product->get_id(), $config['productId'] );
		$this->assertStringContainsString(
			NotificationsController::NAMESPACE . '/notifications',
			(string) $config['restUrl']
		);
		$this->assertArrayNotHasKey( 'events', $config );
		$this->assertArrayNotHasKey( 'message', $config );

		$this->go_to( home_url( '/' ) );
		$unknown = BootstrapConfig::build();
		$this->assertSame( 'unknown', $unknown['pageContext'] );
		$this->assertNull( $unknown['productId'] );
	}

	public function test_checkout_cart_account_excluded(): void {
		$this->go_to( home_url( '/' ) );
		$this->assertTrue( AssetLoader::should_load(), 'baseline storefront' );

		add_filter( 'woocommerce_is_checkout', '__return_true' );
		$this->assertFalse( AssetLoader::should_load(), 'checkout' );
		remove_filter( 'woocommerce_is_checkout', '__return_true' );

		add_filter( 'woocommerce_is_cart', '__return_true' );
		$this->assertFalse( AssetLoader::should_load(), 'cart' );
		remove_filter( 'woocommerce_is_cart', '__return_true' );

		// Account pages are detected via is_account_page(); force page context.
		$account_id = (int) get_option( 'woocommerce_myaccount_page_id' );
		$this->assertGreaterThan( 0, $account_id );
		$this->go_to( get_permalink( $account_id ) );
		if ( ! is_account_page() ) {
			add_filter( 'woocommerce_is_account_page', '__return_true' );
		}
		$this->assertFalse( AssetLoader::should_load(), 'account' );
		remove_filter( 'woocommerce_is_account_page', '__return_true' );
	}

	public function test_rest_still_get_only_and_schema_unchanged(): void {
		$request  = new \WP_REST_Request( 'POST', '/universal-social-proof/v1/notifications' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 404, 405 ) );
		$this->assertSame( '20260829m1', Schema::DB_VERSION );
	}

	public function test_m4_packages_present_geo_admin_absent(): void {
		$src = dirname( __DIR__, 2 ) . '/src';
		$this->assertDirectoryExists( $src . '/Template' );
		$this->assertDirectoryExists( $src . '/Targeting' );
		foreach ( array( 'Geo', 'Admin' ) as $dir ) {
			$this->assertDirectoryDoesNotExist( $src . '/' . $dir );
		}
	}

	public function test_localize_has_no_fixture_events(): void {
		$this->go_to( home_url( '/' ) );
		AssetLoader::enqueue();
		$data = wp_scripts()->get_data( AssetLoader::SCRIPT_HANDLE, 'data' );
		$this->assertIsString( $data );
		$this->assertStringNotContainsString( 'Someone purchased', $data );
		$this->assertStringNotContainsString( '"message"', $data );
		$this->assertStringContainsString( 'restUrl', $data );
	}
}
