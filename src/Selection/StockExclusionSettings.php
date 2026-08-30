<?php
/**
 * Out-of-stock exclusion policy (no admin UI in M2).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

defined( 'ABSPATH' ) || exit;

/**
 * Option + filter; default OFF.
 */
final class StockExclusionSettings {

	public const OPTION_KEY = 'usp_exclude_out_of_stock';

	/**
	 * Whether OOS products are excluded from public selection.
	 */
	public static function is_enabled(): bool {
		$raw = get_option( self::OPTION_KEY, 'no' );
		$on  = ( true === $raw || 1 === $raw || '1' === $raw || 'yes' === $raw );
		/**
		 * Filter whether USP selection excludes out-of-stock products.
		 *
		 * @since 0.2.0
		 * @param bool $enabled Whether to exclude OOS products.
		 */
		return (bool) apply_filters( 'usp_exclude_out_of_stock', $on );
	}
}
