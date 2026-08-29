<?php
/**
 * Purchase country from WooCommerce order addresses.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Capture;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Billing country, else shipping, else null. ISO [A-Z]{2} only.
 */
final class CountryExtractor {

	/**
	 * Extract purchase country from order addresses.
	 *
	 * @param WC_Order $order Source order.
	 * @return string|null Two-letter uppercase ISO country code.
	 */
	public static function extract( WC_Order $order ): ?string {
		$billing = self::normalize( (string) $order->get_billing_country( 'edit' ) );
		if ( null !== $billing ) {
			return $billing;
		}
		return self::normalize( (string) $order->get_shipping_country( 'edit' ) );
	}

	/**
	 * Normalize a raw country value to ISO [A-Z]{2} or null.
	 *
	 * @param string $raw Raw country value.
	 * @return string|null Normalized code or null.
	 */
	public static function normalize( string $raw ): ?string {
		$code = strtoupper( trim( $raw ) );
		if ( 1 === preg_match( '/^[A-Z]{2}$/', $code ) ) {
			return $code;
		}
		return null;
	}
}
