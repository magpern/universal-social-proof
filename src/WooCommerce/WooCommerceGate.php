<?php
/**
 * WooCommerce availability gate.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Detects whether WooCommerce is loaded. Used before any WC-facing bootstrap.
 */
final class WooCommerceGate {

	/**
	 * Whether the WooCommerce plugin class is available.
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}
}
