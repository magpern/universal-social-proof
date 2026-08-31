<?php
/**
 * M0/M1 unit tests — identity, autoload, gate behaviour.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UniversalSocialProof\Plugin;
use UniversalSocialProof\WooCommerce\WooCommerceGate;

final class FoundationUnitTest extends TestCase {

	protected function tearDown(): void {
		Plugin::reset_for_tests();
		parent::tearDown();
	}

	public function test_psr4_autoload_resolves_plugin_classes(): void {
		$this->assertTrue( class_exists( Plugin::class ) );
		$this->assertTrue( class_exists( WooCommerceGate::class ) );
	}

	public function test_version_constant_is_m3(): void {
		$this->assertSame( '0.3.0', USP_VERSION );
	}

	public function test_plugin_header_version_matches_constant(): void {
		$main     = dirname( __DIR__, 2 ) . '/universal-social-proof.php';
		$contents = file_get_contents( $main );
		$this->assertNotFalse( $contents );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*0\.3\.0\s*$/m', $contents );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Text Domain:\s*universal-social-proof\s*$/m', $contents );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Plugin Name:\s*Universal Social Proof\s*$/m', $contents );
		$this->assertStringContainsString( "define( 'USP_VERSION', '0.3.0' );", $contents );
	}

	public function test_composer_package_name(): void {
		$json = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/composer.json' ), true );
		$this->assertIsArray( $json );
		$this->assertSame( 'magpern/universal-social-proof', $json['name'] );
		$this->assertSame( 'UniversalSocialProof\\', array_key_first( $json['autoload']['psr-4'] ) );
	}

	public function test_woocommerce_gate_false_without_woocommerce_class(): void {
		$this->assertFalse( class_exists( 'WooCommerce', false ) );
		$this->assertFalse( WooCommerceGate::is_active() );
	}

	public function test_plugin_init_is_noop_without_woocommerce(): void {
		Plugin::init();
		$this->assertFalse( Plugin::is_initialized() );
	}

	public function test_m4_plus_packages_not_precreated(): void {
		$src       = dirname( __DIR__, 2 ) . '/src';
		$forbidden = array( 'Template', 'Geo', 'Admin' );
		foreach ( $forbidden as $dir ) {
			$this->assertDirectoryDoesNotExist( $src . '/' . $dir, "M3 must not pre-create {$dir}/" );
		}
		$this->assertDirectoryExists( $src . '/Frontend' );
		$this->assertDirectoryExists( $src . '/Selection' );
		$this->assertDirectoryExists( $src . '/Product' );
		$this->assertDirectoryExists( $src . '/Rest' );
	}

	public function test_main_file_declares_hpos_hook(): void {
		$main = (string) file_get_contents( dirname( __DIR__, 2 ) . '/universal-social-proof.php' );
		$this->assertStringContainsString( 'before_woocommerce_init', $main );
		$this->assertStringContainsString( 'custom_order_tables', $main );
		$this->assertStringContainsString( 'declare_compatibility', $main );
	}

	public function test_main_file_has_no_m2_rest(): void {
		$main = (string) file_get_contents( dirname( __DIR__, 2 ) . '/universal-social-proof.php' );
		$this->assertStringNotContainsString( 'register_rest_route', $main );
	}
}
