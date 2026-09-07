<?php
/**
 * Bounded retention purge by occurred_at.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Cleanup;

use Throwable;
use UniversalSocialProof\Logger;
use UniversalSocialProof\Storage\EventRepository;
use UniversalSocialProof\Storage\EventType;
use UniversalSocialProof\Storage\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes expired purchase (days) and cart (minutes) events in bounded batches.
 */
final class RetentionPurger {

	public const BATCH_SIZE = 100;

	public const HOOK = 'usp_retention_purge';

	/**
	 * Run one purge batch per type; schedule another if more remain.
	 */
	public static function run(): void {
		try {
			if ( ! Migrator::tables_exist() ) {
				return;
			}
			$purchase_deleted = EventRepository::delete_older_than_for_type(
				EventType::PURCHASE,
				RetentionSettings::cutoff_utc(),
				self::BATCH_SIZE
			);
			$cart_deleted     = EventRepository::delete_older_than_for_type(
				EventType::ADD_TO_CART,
				CartRetentionSettings::cutoff_utc(),
				self::BATCH_SIZE
			);
			if (
				( $purchase_deleted >= self::BATCH_SIZE || $cart_deleted >= self::BATCH_SIZE )
				&& function_exists( 'as_enqueue_async_action' )
			) {
				as_enqueue_async_action( self::HOOK, array(), RetentionScheduler::GROUP );
			}
		} catch ( Throwable $e ) {
			Logger::error( 'retention purge failed', array( 'error' => $e->getMessage() ) );
		}
	}
}
