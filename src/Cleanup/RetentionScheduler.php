<?php
/**
 * Action Scheduler registration for retention.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Cleanup;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures a single daily recurring retention action.
 */
final class RetentionScheduler {

	public const GROUP = 'universal-social-proof';

	public const RECURRING_HOOK = 'usp_retention_daily';

	/**
	 * Register handlers and ensure schedule.
	 */
	public static function register(): void {
		add_action( RetentionPurger::HOOK, array( RetentionPurger::class, 'run' ) );
		add_action( self::RECURRING_HOOK, array( RetentionPurger::class, 'run' ) );
		add_action( 'init', array( self::class, 'ensure_scheduled' ), 20 );
	}

	/**
	 * Idempotent daily schedule via Action Scheduler when available.
	 */
	public static function ensure_scheduled(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		$next = as_next_scheduled_action( self::RECURRING_HOOK, array(), self::GROUP );
		if ( false !== $next ) {
			return;
		}

		as_schedule_recurring_action(
			time() + HOUR_IN_SECONDS,
			DAY_IN_SECONDS,
			self::RECURRING_HOOK,
			array(),
			self::GROUP
		);
	}
}
