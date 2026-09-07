<?php
/**
 * Out-of-stock exclusion policy.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

use UniversalSocialProof\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Settings + filter; default OFF. Precedence: default → usp_settings → filter.
 */
final class StockExclusionSettings {

	public const OPTION_KEY = 'usp_exclude_out_of_stock';

	/**
	 * Whether OOS products are excluded from public selection.
	 */
	public static function is_enabled(): bool {
		$on = SettingsRepository::exclude_out_of_stock();
		/**
		 * Filter whether USP selection excludes out-of-stock products.
		 *
		 * @since 0.2.0
		 * @param bool $enabled Whether to exclude OOS products.
		 */
		return (bool) apply_filters( 'usp_exclude_out_of_stock', $on );
	}
}
