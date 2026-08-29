<?php
/**
 * Plugin composition root (M0 foundation).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof;

use UniversalSocialProof\WooCommerce\WooCommerceGate;

defined( 'ABSPATH' ) || exit;

/**
 * Idempotent bootstrap. M0 registers no social-proof feature hooks.
 */
final class Plugin {

	/**
	 * Whether init() has completed for this request.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Boot the plugin when WooCommerce is available.
	 *
	 * M0 registers no social-proof feature hooks.
	 */
	public static function init(): void {
		if ( self::$initialized || ! WooCommerceGate::is_active() ) {
			return;
		}

		self::$initialized = true;
	}

	/**
	 * Whether the composition root has completed init.
	 */
	public static function is_initialized(): bool {
		return self::$initialized;
	}

	/**
	 * Test seam: reset bootstrap state between integration tests.
	 */
	public static function reset_for_tests(): void {
		self::$initialized = false;
	}
}
