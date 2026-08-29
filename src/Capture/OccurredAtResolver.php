<?php
/**
 * Authoritative commerce timestamp resolver.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Capture;

use DateTimeImmutable;
use DateTimeZone;
use WC_DateTime;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves occurred_at: date_paid → date_completed → date_created → null.
 */
final class OccurredAtResolver {

	/**
	 * Resolve authoritative purchase time in UTC.
	 *
	 * @param WC_Order $order Source order.
	 * @return DateTimeImmutable|null UTC instant, or null to fail closed.
	 */
	public static function resolve( WC_Order $order ): ?DateTimeImmutable {
		foreach ( array( 'get_date_paid', 'get_date_completed', 'get_date_created' ) as $method ) {
			$dt = $order->{$method}( 'edit' );
			if ( $dt instanceof WC_DateTime ) {
				return self::from_wc_datetime( $dt );
			}
		}
		return null;
	}

	/**
	 * Persistable UTC MySQL datetime string.
	 *
	 * @param DateTimeImmutable $dt UTC or convertible instant.
	 */
	public static function to_mysql_utc( DateTimeImmutable $dt ): string {
		return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Convert a WooCommerce datetime to UTC DateTimeImmutable.
	 *
	 * @param WC_DateTime $dt WooCommerce datetime.
	 */
	private static function from_wc_datetime( WC_DateTime $dt ): DateTimeImmutable {
		$utc = new DateTimeImmutable( '@' . $dt->getTimestamp() );
		return $utc->setTimezone( new DateTimeZone( 'UTC' ) );
	}
}
