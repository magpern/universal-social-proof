<?php
/**
 * Storefront asset enqueue and bootstrap localization.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Frontend;

use UniversalSocialProof\Targeting\TargetingPolicy;

defined( 'ABSPATH' ) || exit;

/**
 * Loads USP toaster CSS/JS when the presentation gate allows.
 */
final class AssetLoader {

	public const SCRIPT_HANDLE = 'usp-toaster';
	public const STYLE_HANDLE  = 'usp-toaster';

	/**
	 * Whether assets were enqueued this request.
	 *
	 * @var bool
	 */
	private static bool $enqueued = false;

	/**
	 * Enqueue CSS/JS and localize bootstrap config.
	 */
	public static function enqueue(): void {
		if ( self::$enqueued || ! self::should_load() ) {
			return;
		}

		$base = trailingslashit( plugins_url( '', USP_PLUGIN_FILE ) );
		$ver  = defined( 'USP_VERSION' ) ? USP_VERSION : '0.4.1';

		wp_enqueue_style(
			self::STYLE_HANDLE,
			$base . 'assets/css/usp-toaster.css',
			array(),
			$ver
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$base . 'assets/js/usp-toaster.js',
			array(),
			$ver,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'uspToaster',
			BootstrapConfig::build()
		);

		self::$enqueued = true;
	}

	/**
	 * Whether the toaster should load on this request.
	 */
	public static function should_load(): bool {
		return TargetingPolicy::should_load();
	}

	/**
	 * Whether assets were enqueued this request.
	 */
	public static function was_enqueued(): bool {
		return self::$enqueued;
	}

	/**
	 * Test seam.
	 */
	public static function reset_for_tests(): void {
		self::$enqueued = false;
	}
}
