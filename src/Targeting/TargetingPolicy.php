<?php
/**
 * Storefront page load policy for toaster assets.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Targeting;

use UniversalSocialProof\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the USP toaster may load on the current request.
 */
final class TargetingPolicy {

	/**
	 * Default page gate (M4/M3 presentation defaults + architecture checkout exclude + M6 toggles).
	 */
	public static function should_load(): bool {
		if ( is_admin() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		if ( is_feed() ) {
			return false;
		}
		if ( ! function_exists( 'is_checkout' ) ) {
			return false;
		}
		// Checkout exclusion is architecture-aligned (FROZEN) — never configurable.
		if ( is_checkout() ) {
			return false;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return SettingsRepository::load_on_cart();
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return SettingsRepository::load_on_account();
		}
		return true;
	}
}
