<?php
/**
 * M3 frontend unit tests — bootstrap config and load gates.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UniversalSocialProof\Frontend\BootstrapConfig;
use UniversalSocialProof\Rest\NotificationsController;

final class M3FrontendUnitTest extends TestCase {

	public function test_bootstrap_timing_and_limits(): void {
		$this->assertSame( 5, BootstrapConfig::LIMIT );
		$this->assertSame( 3, BootstrapConfig::MAX_BATCHES );
		$this->assertSame( 3000, BootstrapConfig::INITIAL_DELAY_MS );
		$this->assertSame( 6000, BootstrapConfig::VISIBLE_MS );
		$this->assertSame( 2000, BootstrapConfig::GAP_MS );
		$this->assertSame( 280, BootstrapConfig::MOTION_MS );
		$this->assertSame( 'usp.v1', BootstrapConfig::STORAGE_KEY );
	}

	public function test_bootstrap_build_shape_without_wordpress_product(): void {
		if ( ! function_exists( 'rest_url' ) ) {
			$this->markTestSkipped( 'rest_url requires WordPress.' );
		}
		$config = BootstrapConfig::build();
		$this->assertSame( BootstrapConfig::LIMIT, $config['limit'] );
		$this->assertSame( BootstrapConfig::MAX_BATCHES, $config['maxBatches'] );
		$this->assertSame( BootstrapConfig::STORAGE_KEY, $config['storageKey'] );
		$this->assertArrayHasKey( 'restUrl', $config );
		$this->assertArrayHasKey( 'timing', $config );
		$this->assertArrayHasKey( 'i18n', $config );
		$this->assertArrayHasKey( 'dismiss', $config['i18n'] );
		$this->assertStringContainsString(
			NotificationsController::NAMESPACE . NotificationsController::ROUTE,
			(string) $config['restUrl']
		);
	}

	public function test_frontend_package_exists_and_m4_absent(): void {
		$src = dirname( __DIR__, 2 ) . '/src';
		$this->assertDirectoryExists( $src . '/Frontend' );
		$this->assertFileExists( $src . '/Frontend/FrontendController.php' );
		$this->assertFileExists( $src . '/Frontend/AssetLoader.php' );
		$this->assertFileExists( $src . '/Frontend/BootstrapConfig.php' );
		$this->assertFileExists( $src . '/Frontend/ShellRenderer.php' );
		foreach ( array( 'Template', 'Geo', 'Admin' ) as $dir ) {
			$this->assertDirectoryDoesNotExist( $src . '/' . $dir );
		}
	}

	public function test_assets_exist_within_size_budgets(): void {
		$root = dirname( __DIR__, 2 );
		$js   = $root . '/assets/js/usp-toaster.js';
		$css  = $root . '/assets/css/usp-toaster.css';
		$this->assertFileExists( $js );
		$this->assertFileExists( $css );
		$this->assertLessThanOrEqual( 16 * 1024, filesize( $js ) );
		$this->assertLessThanOrEqual( 6 * 1024, filesize( $css ) );
	}

	public function test_no_php_fixture_injection_in_frontend(): void {
		$src   = dirname( __DIR__, 2 ) . '/src/Frontend';
		$scan  = '';
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $src ) );
		foreach ( $files as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$scan .= (string) file_get_contents( $file->getPathname() );
			}
		}
		$this->assertStringNotContainsString( 'fixture', strtolower( $scan ) );
		$this->assertStringNotContainsString( 'fake', strtolower( $scan ) );
		$this->assertStringNotContainsString( 'WP_DEBUG', $scan );
		$this->assertStringNotContainsString( '{{product}}', $scan );
	}

	public function test_m3_plus_tokens_absent_from_src(): void {
		$src   = dirname( __DIR__, 2 ) . '/src';
		$scan  = '';
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $src ) );
		foreach ( $files as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$scan .= (string) file_get_contents( $file->getPathname() );
			}
		}
		$this->assertStringNotContainsString( '{{product}}', $scan );
		$this->assertStringNotContainsString( '{{country}}', $scan );
		$this->assertStringNotContainsString( '{{time_ago}}', $scan );
		$this->assertStringNotContainsString( '{{quantity}}', $scan );
		$this->assertStringNotContainsString( 'GeoContextAdapter', $scan );
	}
}
