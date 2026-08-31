<?php
/**
 * Server-side relative-time phrases aligned with M3 buckets.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Template;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Formats occurred_at into a relative-time string for {{time_ago}}.
 */
final class RelativeTimeFormatter {

	/**
	 * Format relative time or null if uncomputable.
	 *
	 * Buckets match assets/js/usp-toaster.js formatRelativeTime:
	 * &lt;45s just now; &lt;60m minutes; &lt;24h hours; &lt;30d days.
	 *
	 * @param DateTimeImmutable      $occurred_at Event time UTC.
	 * @param DateTimeImmutable|null $now    Clock (UTC); default now.
	 */
	public static function format( DateTimeImmutable $occurred_at, ?DateTimeImmutable $now = null ): ?string {
		$now   = $now ?? new DateTimeImmutable( 'now', $occurred_at->getTimezone() );
		$delta = $now->getTimestamp() - $occurred_at->getTimestamp();
		if ( $delta < -120 ) {
			return null;
		}
		if ( $delta < 45 ) {
			return __( 'just now', 'universal-social-proof' );
		}
		$minutes = (int) floor( $delta / 60 );
		if ( $minutes < 60 ) {
			$m = max( 1, $minutes );
			if ( 1 === $m ) {
				/* translators: %d: one minute */
				return sprintf( __( '%d minute ago', 'universal-social-proof' ), $m );
			}
			/* translators: %d: number of minutes */
			return sprintf( __( '%d minutes ago', 'universal-social-proof' ), $m );
		}
		$hours = (int) floor( $delta / 3600 );
		if ( $hours < 24 ) {
			$h = max( 1, $hours );
			if ( 1 === $h ) {
				/* translators: %d: one hour */
				return sprintf( __( '%d hour ago', 'universal-social-proof' ), $h );
			}
			/* translators: %d: number of hours */
			return sprintf( __( '%d hours ago', 'universal-social-proof' ), $h );
		}
		$days = (int) floor( $delta / 86400 );
		if ( $days < 30 ) {
			$d = max( 1, $days );
			if ( 1 === $d ) {
				/* translators: %d: one day */
				return sprintf( __( '%d day ago', 'universal-social-proof' ), $d );
			}
			/* translators: %d: number of days */
			return sprintf( __( '%d days ago', 'universal-social-proof' ), $d );
		}
		return null;
	}
}
