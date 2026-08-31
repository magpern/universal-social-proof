<?php
/**
 * M3 frontend composition: enqueue + empty shell.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Registers storefront toaster hooks.
 */
final class FrontendController {

	/**
	 * Whether hooks were registered this process.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register frontend hooks once.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		add_action( 'wp_enqueue_scripts', array( AssetLoader::class, 'enqueue' ) );
		add_action( 'wp_footer', array( ShellRenderer::class, 'render' ), 20 );
	}

	/**
	 * Test seam: reset registration flag.
	 */
	public static function reset_for_tests(): void {
		self::$registered = false;
		AssetLoader::reset_for_tests();
	}
}
