<?php
/**
 * Cart event retention (minutes).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Cleanup;

use UniversalSocialProof\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Cart freshness window: default 60 minutes, clamp 5–1440 (ADR-0018).
 */
final class CartRetentionSettings {

	public const DEFAULT = 60;
	public const MIN     = 5;
	public const MAX     = 1440;

	/**
	 * Effective cart retention minutes.
	 */
	public static function minutes(): int {
		$raw = SettingsRepository::cart_retention_minutes();
		/**
		 * Filter USP cart retention minutes (clamped to 5–1440 after filter).
		 *
		 * @since 1.1.0
		 * @param int $minutes Retention minutes.
		 */
		$minutes = (int) apply_filters( 'usp_cart_retention_minutes', $raw );
		return max( self::MIN, min( self::MAX, $minutes ) );
	}

	/**
	 * UTC MySQL datetime cutoff for cart events.
	 */
	public static function cutoff_utc(): string {
		$minutes = self::minutes();
		$seconds = defined( 'MINUTE_IN_SECONDS' ) ? MINUTE_IN_SECONDS : 60;
		return gmdate( 'Y-m-d H:i:s', time() - ( $minutes * $seconds ) );
	}
}
