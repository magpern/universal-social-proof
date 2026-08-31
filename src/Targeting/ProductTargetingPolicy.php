<?php
/**
 * Operator product exclusion for social-proof selection (filter only; no option).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Targeting;

use UniversalSocialProof\Product\PublicProduct;

defined( 'ABSPATH' ) || exit;

/**
 * Whether USP chooses to use an otherwise-valid public product for proof.
 */
final class ProductTargetingPolicy {

	public const FILTER = 'usp_excluded_product_ids';

	public const MAX_IDS = 200;

	/**
	 * Whether this public product is excluded by operator policy.
	 *
	 * @param PublicProduct $product Resolved merchandising product.
	 */
	public static function is_excluded( PublicProduct $product ): bool {
		$excluded = self::excluded_ids();
		if ( array() === $excluded ) {
			return false;
		}
		if ( isset( $excluded[ $product->id ] ) ) {
			return true;
		}
		return $product->parent_id > 0 && isset( $excluded[ $product->parent_id ] );
	}

	/**
	 * Validated exclusion ID map (id => true).
	 *
	 * @return array<int, true>
	 */
	public static function excluded_ids(): array {
		/**
		 * Filter product IDs excluded from USP social-proof selection.
		 *
		 * @since 0.4.0
		 * @param array<int> $ids Product or parent IDs.
		 */
		$raw = apply_filters( self::FILTER, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $id ) {
			if ( is_string( $id ) && 1 === preg_match( '/^[1-9][0-9]*$/', $id ) ) {
				$id = (int) $id;
			}
			if ( ! is_int( $id ) || $id <= 0 ) {
				continue;
			}
			$out[ $id ] = true;
			if ( count( $out ) >= self::MAX_IDS ) {
				break;
			}
		}
		return $out;
	}
}
