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
	 * Cart events require nullable provenance and no legacy unique key.
	 */
	public static function schema_supports_cart_events(): bool {
		$table = Schema::events_table();
		if ( ! self::column_exists( $table, 'event_type' ) ) {
			return false;
		}
		$order_col = self::column_row( $table, 'source_order_id' );
		$item_col  = self::column_row( $table, 'source_item_id' );
		$order_ok  = is_array( $order_col ) && isset( $order_col['Null'] ) && 'YES' === strtoupper( (string) $order_col['Null'] );
		$item_ok   = is_array( $item_col ) && isset( $item_col['Null'] ) && 'YES' === strtoupper( (string) $item_col['Null'] );
		if ( ! $order_ok || ! $item_ok ) {
			return false;
		}
		if ( self::index_exists( $table, 'source_order_item' ) ) {
			return false;
		}
		return self::index_exists( $table, 'event_source' );
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
			if ( ! self::tables_exist() || ! self::schema_supports_cart_events() ) {
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
	 * Apply CREATE TABLE via dbDelta, then idempotent ALTER for v1.1.
	 */
	private static function run_dbdelta(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( Schema::table_definitions() as $sql ) {
			dbDelta( $sql );
		}
		self::migrate_from_20260829m1();
	}

	/**
	 * Idempotent ALTER path from 20260829m1 → 20260907v11a.
	 *
	 * Adds event_type, backfills purchase, drops legacy unique, nullable provenance,
	 * swaps unique key, adds status_type_occurred. Safe on fresh installs that already match DDL.
	 *
	 * Order matters: drop UNIQUE source_order_item before MODIFY … NULL (MariaDB).
	 */
	private static function migrate_from_20260829m1(): void {
		global $wpdb;

		$table = Schema::events_table();
		if ( ! self::tables_exist() ) {
			return;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is Schema::events_table().

		if ( ! self::column_exists( $table, 'event_type' ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table}` ADD COLUMN event_type varchar(16) NOT NULL DEFAULT 'purchase' AFTER id"
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET event_type = %s WHERE event_type IS NULL OR event_type = '' OR event_type NOT IN (%s, %s)",
				EventType::PURCHASE,
				EventType::PURCHASE,
				EventType::ADD_TO_CART
			)
		);

		// Drop legacy unique before nullability change (required on MariaDB/MySQL).
		if ( self::index_exists( $table, 'source_order_item' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX source_order_item" );
		}

		$order_col  = self::column_row( $table, 'source_order_id' );
		$item_col   = self::column_row( $table, 'source_item_id' );
		$order_null = is_array( $order_col ) && isset( $order_col['Null'] ) && 'YES' === strtoupper( (string) $order_col['Null'] );
		$item_null  = is_array( $item_col ) && isset( $item_col['Null'] ) && 'YES' === strtoupper( (string) $item_col['Null'] );
		if ( ! $order_null || ! $item_null ) {
			$wpdb->query(
				"ALTER TABLE `{$table}`
				MODIFY source_order_id bigint(20) unsigned NULL,
				MODIFY source_item_id bigint(20) unsigned NULL"
			);
		}

		if ( ! self::index_exists( $table, 'event_source' ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table}` ADD UNIQUE KEY event_source (event_type, source_order_id, source_item_id)"
			);
		}

		if ( ! self::index_exists( $table, 'status_type_occurred' ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table}` ADD KEY status_type_occurred (status, event_type, occurred_at)"
			);
		}

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Whether a column exists on the events table.
	 *
	 * @param string $table  Fully qualified table.
	 * @param string $column Column name.
	 */
	private static function column_exists( string $table, string $column ): bool {
		return null !== self::column_row( $table, $column );
	}

	/**
	 * SHOW COLUMNS row for a column, or null.
	 *
	 * @param string $table  Fully qualified table.
	 * @param string $column Column name.
	 * @return array<string, mixed>|null
	 */
	private static function column_row( string $table, string $column ): ?array {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is Schema::events_table().
		$row = $wpdb->get_row(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Whether an index/key exists on the events table.
	 *
	 * @param string $table Fully qualified table.
	 * @param string $name  Key name.
	 */
	private static function index_exists( string $table, string $name ): bool {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is Schema::events_table().
		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
				$name
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return null !== $row && false !== $row && '' !== (string) $row;
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
