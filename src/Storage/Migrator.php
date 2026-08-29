<?php
/**
 * Versioned schema migrator with lease lock.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Runs dbDelta under a short lease; updates usp_db_version only after success.
 */
final class Migrator {

	public const OPTION_VERSION = 'usp_db_version';
	public const LOCK_KEY       = 'usp_db_migrate_lock';
	public const LOCK_TTL       = 120;

	/**
	 * Owner token for the current process lease.
	 *
	 * @var string|null
	 */
	private static ?string $owner_token = null;

	/**
	 * Whether schema version or table presence requires upgrade.
	 */
	public static function needs_upgrade(): bool {
		$installed = (string) get_option( self::OPTION_VERSION, '' );
		if ( Schema::DB_VERSION !== $installed ) {
			return true;
		}
		return ! self::tables_exist();
	}

	/**
	 * True when the events table is present.
	 */
	public static function tables_exist(): bool {
		global $wpdb;
		foreach ( array_keys( Schema::table_definitions() ) as $table ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found !== $table ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Run upgrade under lock. Safe for activation, admin, CLI, or cron.
	 *
	 * @return bool True if schema is at target version after call.
	 */
	public static function upgrade_now(): bool {
		self::reap_expired_lock();

		if ( ! self::needs_upgrade() ) {
			return true;
		}

		$acquired = false;
		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			if ( self::acquire_lock() ) {
				$acquired = true;
				break;
			}
			usleep( 100000 );
			self::reap_expired_lock();
			if ( ! self::needs_upgrade() ) {
				return true;
			}
		}

		if ( ! $acquired ) {
			return ! self::needs_upgrade();
		}

		try {
			if ( ! self::needs_upgrade() ) {
				return true;
			}
			self::run_dbdelta();
			if ( ! self::tables_exist() ) {
				return false;
			}
			update_option( self::OPTION_VERSION, Schema::DB_VERSION, true );
			return true;
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Controlled upgrade for admin/cron/CLI (and always when needs_upgrade on init of WC plugins).
	 */
	public static function maybe_upgrade_controlled(): void {
		if ( ! self::needs_upgrade() ) {
			return;
		}

		$allowed = is_admin()
			|| ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( defined( 'DOING_CRON' ) && DOING_CRON );

		// Also allow during WooCommerce-loaded plugin boot so deploys without activation still migrate.
		if ( ! $allowed && did_action( 'woocommerce_loaded' ) ) {
			$allowed = true;
		}

		if ( ! $allowed ) {
			return;
		}

		self::upgrade_now();
	}

	/**
	 * Apply CREATE TABLE via dbDelta.
	 */
	private static function run_dbdelta(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( Schema::table_definitions() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Acquire a short migration lease.
	 */
	private static function acquire_lock(): bool {
		$token = wp_generate_uuid4();
		$ok    = add_option(
			self::LOCK_KEY,
			array(
				'token'   => $token,
				'expires' => time() + self::LOCK_TTL,
			),
			'',
			false
		);
		if ( $ok ) {
			self::$owner_token = $token;
		}
		return (bool) $ok;
	}

	/**
	 * Release lease if this process still owns it.
	 */
	private static function release_lock(): void {
		$current = get_option( self::LOCK_KEY, null );
		if ( is_array( $current ) && isset( $current['token'] ) && $current['token'] === self::$owner_token ) {
			delete_option( self::LOCK_KEY );
		}
		self::$owner_token = null;
	}

	/**
	 * Delete expired migration locks left by crashed processes.
	 */
	private static function reap_expired_lock(): void {
		$current = get_option( self::LOCK_KEY, null );
		if ( ! is_array( $current ) ) {
			return;
		}
		$expires = isset( $current['expires'] ) ? (int) $current['expires'] : 0;
		if ( $expires > 0 && $expires < time() ) {
			delete_option( self::LOCK_KEY );
		}
	}
}
