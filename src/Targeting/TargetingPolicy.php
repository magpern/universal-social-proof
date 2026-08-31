<?php
/**
 * Storefront page load policy for toaster assets (no persisted settings).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the USP toaster may load on the current request.
 */
final class TargetingPolicy {

	/**
	 * Default page gate (M4/M3 presentation defaults + architecture checkout exclude).
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
		// Checkout exclusion is architecture-aligned (FROZEN).
		if ( is_checkout() ) {
			return false;
		}
		// Cart/account are presentation defaults, not immutable architecture.
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return false;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return false;
		}
		return true;
	}
}
