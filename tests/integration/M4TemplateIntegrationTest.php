<?php
/**
 * M4 template + targeting integration tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Integration;

use UniversalSocialProof\Frontend\AssetLoader;
use UniversalSocialProof\Plugin;
use UniversalSocialProof\Product\PublicProductResolver;
use UniversalSocialProof\Rest\NotificationsController;
use UniversalSocialProof\Selection\CandidateReader;
use UniversalSocialProof\Selection\ProductResolutionBudget;
use UniversalSocialProof\Selection\SelectionEngine;
use UniversalSocialProof\Selection\SelectionRequest;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Schema;
use UniversalSocialProof\Targeting\ProductTargetingPolicy;
use UniversalSocialProof\Template\TemplateSettings;
use WP_REST_Request;
use WP_UnitTestCase;

final class M4TemplateIntegrationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Plugin::reset_for_tests();
		Migrator::upgrade_now();
		Plugin::init();
		remove_all_filters( TemplateSettings::FILTER );
		remove_all_filters( ProductTargetingPolicy::FILTER );
		AssetLoader::reset_for_tests();
	}

	public function tear_down(): void {
		remove_all_filters( TemplateSettings::FILTER );
		remove_all_filters( ProductTargetingPolicy::FILTER );
		parent::tear_down();
	}

	public function test_schema_unchanged(): void {
		$this->assertSame( '20260829m1', Schema::DB_VERSION );
	}

	public function test_rest_default_message_and_show_relative_time_true(): void {
		$product = $this->create_simple_product( 'M4 Demo Product' );
		$this->capture_order( $product );

		$response = $this->dispatch( array( 'limit' => '5' ) );
		$this->assertSame( 200, $response->get_status() );
		$headers = $response->get_headers();
		$cc      = '';
		foreach ( $headers as $name => $value ) {
			if ( 0 === strcasecmp( (string) $name, 'Cache-Control' ) ) {
				$cc = is_array( $value ) ? (string) $value[0] : (string) $value;
				break;
			}
		}
		$this->assertSame( 'no-store', $cc );
		$data = $response->get_data();
		$this->assertNotEmpty( $data );
		$item = $data[0];
		$this->assertSame( NotificationsController::ALLOWLIST, array_keys( $item ) );
		$this->assertSame( 'Someone purchased M4 Demo Product', $item['message'] );
		$this->assertTrue( $item['show_relative_time'] );
		$this->assertArrayNotHasKey( 'quantity', $item );
		$this->assertArrayNotHasKey( 'country_code', $item );
	}

	public function test_time_ago_template_sets_show_relative_time_false(): void {
		$product = $this->create_simple_product( 'Timed Product' );
		$this->capture_order( $product );

		add_filter(
			TemplateSettings::FILTER,
			static function () {
				return 'Someone purchased {{product}} {{time_ago}}';
			}
		);

		$response = $this->dispatch( array( 'limit' => '1' ) );
		$data     = $response->get_data();
		$this->assertNotEmpty( $data );
		$this->assertFalse( $data[0]['show_relative_time'] );
		$this->assertStringContainsString( 'Someone purchased Timed Product', $data[0]['message'] );
		$this->assertDoesNotMatchRegularExpression( '/\{\{/', $data[0]['message'] );
	}

	public function test_malformed_filter_template_falls_back_without_brace_leak(): void {
		$product = $this->create_simple_product( 'Brace Safe Product' );
		$this->capture_order( $product );

		add_filter(
			TemplateSettings::FILTER,
			static function () {
				return 'Someone purchased {{product}} }}';
			}
		);

		$response = $this->dispatch( array( 'limit' => '1' ) );
		$data     = $response->get_data();
		$this->assertNotEmpty( $data );
		$this->assertSame( 'Someone purchased Brace Safe Product', $data[0]['message'] );
		$this->assertTrue( $data[0]['show_relative_time'] );
		$this->assertStringNotContainsString( '}', $data[0]['message'] );
		$this->assertStringNotContainsString( '{', $data[0]['message'] );
	}

	public function test_excluded_product_does_not_consume_k_slot(): void {
		$excluded = $this->create_simple_product( 'Excluded' );
		$allowed  = $this->create_simple_product( 'Allowed' );
		$this->capture_order( $excluded );
		$this->capture_order( $allowed );

		add_filter(
			ProductTargetingPolicy::FILTER,
			static function () use ( $excluded ) {
				return array( (int) $excluded->get_id() );
			}
		);

		$budget   = new ProductResolutionBudget();
		$resolver = new PublicProductResolver( $budget );
		$engine   = new SelectionEngine( new CandidateReader(), $resolver, static fn( $x ) => $x );
		$selected = $engine->select( new SelectionRequest( 5, null, SelectionRequest::CONTEXT_UNKNOWN, array() ) );
		$names    = array_map( static fn( $e ) => $e->product->name, $selected );
		$this->assertContains( 'Allowed', $names );
		$this->assertNotContains( 'Excluded', $names );

		// Resolver still considers excluded product merchandising-eligible.
		$resolved = $resolver->resolve_for_event( (int) $excluded->get_id(), null );
		$this->assertNotNull( $resolved );
		$this->assertTrue( ProductTargetingPolicy::is_excluded( $resolved ) );
	}

	public function test_page_gates(): void {
		$this->go_to( home_url( '/' ) );
		$this->assertTrue( AssetLoader::should_load() );

		add_filter( 'woocommerce_is_checkout', '__return_true' );
		$this->assertFalse( AssetLoader::should_load() );
		remove_all_filters( 'woocommerce_is_checkout' );

		add_filter( 'woocommerce_is_cart', '__return_true' );
		$this->assertFalse( AssetLoader::should_load() );
		remove_all_filters( 'woocommerce_is_cart' );
	}

	public function test_no_message_in_page_html(): void {
		$this->go_to( home_url( '/' ) );
		AssetLoader::enqueue();
		ob_start();
		\UniversalSocialProof\Frontend\ShellRenderer::render();
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'Someone purchased', $html );
		$this->assertStringNotContainsString( 'show_relative_time', $html );
	}

	/**
	 * Create a published simple product.
	 *
	 * @param string $name Product name.
	 * @return \WC_Product
	 */
	private function create_simple_product( string $name = 'USP M4' ): \WC_Product {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_regular_price( '19' );
		$product->save();
		return $product;
	}

	/**
	 * Capture a paid processing order for the product.
	 *
	 * @param \WC_Product $product Product.
	 */
	private function capture_order( \WC_Product $product ): void {
		$order = wc_create_order();
		$order->set_billing_country( 'SE' );
		$order->set_billing_email( 'm4-' . wp_generate_password( 8, false ) . '@example.test' );
		$order->add_product( $product, 1 );
		$order->set_date_paid( time() );
		$order->calculate_totals( false );
		$order->save();
		$order->update_status( 'processing' );
	}

	/**
	 * @param array<string, mixed> $params Query params.
	 */
	private function dispatch( array $params ) {
		$request = new WP_REST_Request( 'GET', '/' . NotificationsController::NAMESPACE . NotificationsController::ROUTE );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return rest_do_request( $request );
	}
}
