<?php
/**
 * Quantity helpers — DECIMAL(18,6) without silent integer truncation.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Normalize and compare WooCommerce-compatible quantities.
 */
final class Quantity {

	public const SCALE = 6;

	/**
	 * Format a quantity for MySQL DECIMAL(18,6) storage.
	 *
	 * @param float|int|string $quantity Raw quantity.
	 */
	public static function format( $quantity ): string {
		return number_format( (float) $quantity, self::SCALE, '.', '' );
	}

	/**
	 * Display-normalize a stored DECIMAL(18,6) quantity (trim trailing zeros).
	 *
	 * Does not cast to int. Examples: 1.000000→1, 1.500000→1.5, 0.250000→0.25.
	 *
	 * @param float|int|string $quantity Stored or raw quantity.
	 */
	public static function format_display( $quantity ): string {
		$scaled = self::format( $quantity );
		if ( str_contains( $scaled, '.' ) ) {
			$scaled = rtrim( rtrim( $scaled, '0' ), '.' );
		}
		return '' === $scaled ? '0' : $scaled;
	}

	/**
	 * True when quantity is strictly positive.
	 *
	 * @param float|int|string $quantity Raw quantity.
	 */
	public static function is_positive( $quantity ): bool {
		return self::to_scaled_int( $quantity ) > 0;
	}

	/**
	 * True when refunded qty is at least ordered qty (full line refund).
	 *
	 * @param float|int|string $ordered  Original purchased quantity.
	 * @param float|int|string $refunded Absolute cumulative refunded quantity.
	 */
	public static function is_fully_refunded( $ordered, $refunded ): bool {
		$o = self::to_scaled_int( $ordered );
		$r = self::to_scaled_int( $refunded );
		return $o > 0 && $r >= $o;
	}

	/**
	 * Scale to integer micros to avoid binary-float comparison artifacts.
	 *
	 * @param float|int|string $quantity Raw quantity.
	 */
	public static function to_scaled_int( $quantity ): int {
		return (int) round( (float) $quantity * ( 10 ** self::SCALE ) );
	}
}
