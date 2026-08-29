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
use UniversalSocialProof\Storage\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes expired events in batches of 100.
 */
final class RetentionPurger {

	public const BATCH_SIZE = 100;

	public const HOOK = 'usp_retention_purge';

	/**
	 * Run one purge batch; schedule another if more remain.
	 */
	public static function run(): void {
		try {
			if ( ! Migrator::tables_exist() ) {
				return;
			}
			$deleted = EventRepository::delete_older_than( RetentionSettings::cutoff_utc(), self::BATCH_SIZE );
			if ( $deleted >= self::BATCH_SIZE && function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( self::HOOK, array(), RetentionScheduler::GROUP );
			}
		} catch ( Throwable $e ) {
			Logger::error( 'retention purge failed', array( 'error' => $e->getMessage() ) );
		}
	}
}
