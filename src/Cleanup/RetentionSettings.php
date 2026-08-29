<?php
/**
 * Retention days configuration.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Cleanup;

defined( 'ABSPATH' ) || exit;

/**
 * Retention window: default 60, clamp 7–90.
 */
final class RetentionSettings {

	public const OPTION_KEY = 'usp_retention_days';
	public const DEFAULT    = 60;
	public const MIN        = 7;
	public const MAX        = 90;

	/**
	 * Effective retention days.
	 */
	public static function days(): int {
		$raw = (int) get_option( self::OPTION_KEY, self::DEFAULT );
		/**
		 * Filter USP retention days (clamped to 7–90 after filter).
		 *
		 * @since 0.1.0
		 * @param int $days Retention days.
		 */
		$days = (int) apply_filters( 'usp_retention_days', $raw );
		return max( self::MIN, min( self::MAX, $days ) );
	}

	/**
	 * UTC MySQL datetime cutoff: events with occurred_at before this are purgeable.
	 */
	public static function cutoff_utc(): string {
		$days = self::days();
		return gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
	}
}
