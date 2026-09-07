<?php
/**
 * Read-only diagnostics for USP admin (ADR-0016).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Admin;

use UniversalSocialProof\Cleanup\RetentionScheduler;
use UniversalSocialProof\Geo\UgcGeoContextAdapter;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Storage\EventStatus;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Schema;
use UniversalSocialProof\Template\TemplateSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates + cheap health. Call only from USP admin render path.
 */
final class DiagnosticsService {

	/**
	 * Collect diagnostics. Must only be invoked from USP Social Proof admin.
	 *
	 * @return array<string, mixed>
	 */
	public static function collect(): array {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return array();
		}
		return array(
			'runtime_version'    => defined( 'USP_VERSION' ) ? USP_VERSION : '',
			'db_version'         => Schema::DB_VERSION,
			'installed_db'       => (string) get_option( Migrator::OPTION_VERSION, '' ),
			'settings_version'   => SettingsRepository::settings_version(),
			'woocommerce'        => self::woocommerce_health(),
			'hpos'               => self::hpos_health(),
			'ugc'                => self::ugc_health(),
			'template_valid'     => null !== TemplateSettings::validate_template( SettingsRepository::template() ),
			'effective_settings' => SettingsRepository::get_persisted(),
			'cleanup'            => self::cleanup_health(),
			'events'             => self::event_aggregates(),
		);
	}

	/**
	 * Fixed event aggregates (no row materialization / WC loads).
	 *
	 * @return array{active_count: int|null, suppressed_count: int|null, newest_active_occurred_at: string|null, oldest_active_occurred_at: string|null}
	 */
	public static function event_aggregates(): array {
		global $wpdb;
		$table = Schema::events_table();

		$active     = EventStatus::ACTIVE;
		$suppressed = EventStatus::SUPPRESSED;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name is Schema::events_table().
		$active_count     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s",
				$active
			)
		);
		$suppressed_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s",
				$suppressed
			)
		);
		$newest           = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(occurred_at) FROM {$table} WHERE status = %s",
				$active
			)
		);
		$oldest           = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(occurred_at) FROM {$table} WHERE status = %s",
				$active
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'active_count'              => null !== $active_count ? (int) $active_count : null,
			'suppressed_count'          => null !== $suppressed_count ? (int) $suppressed_count : null,
			'newest_active_occurred_at' => is_string( $newest ) && '' !== $newest ? $newest : null,
			'oldest_active_occurred_at' => is_string( $oldest ) && '' !== $oldest ? $oldest : null,
		);
	}

	/**
	 * WooCommerce active/version health.
	 *
	 * @return array{active: bool, version: string}
	 */
	private static function woocommerce_health(): array {
		$active  = class_exists( 'WooCommerce', false ) || class_exists( 'WooCommerce' );
		$version = '';
		if ( defined( 'WC_VERSION' ) ) {
			$version = (string) WC_VERSION;
		} elseif ( function_exists( 'WC' ) && WC() && isset( WC()->version ) ) {
			$version = (string) WC()->version;
		}
		return array(
			'active'  => $active,
			'version' => $version,
		);
	}

	/**
	 * HPOS usage status.
	 *
	 * @return array{status: string}
	 */
	private static function hpos_health(): array {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return array( 'status' => 'unknown' );
		}
		try {
			$enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			return array( 'status' => $enabled ? 'enabled' : 'disabled' );
		} catch ( \Throwable $e ) {
			unset( $e );
			return array( 'status' => 'unknown' );
		}
	}

	/**
	 * Universal Geo Context availability.
	 *
	 * @return array{available: bool, version: string, api: string}
	 */
	private static function ugc_health(): array {
		$adapter = new UgcGeoContextAdapter();
		$version = '';
		$api     = '';
		if ( defined( 'UNIVERSAL_GEO_CONTEXT_VERSION' ) ) {
			$version = (string) UNIVERSAL_GEO_CONTEXT_VERSION;
		} elseif ( defined( 'UGC_VERSION' ) ) {
			$version = (string) UGC_VERSION;
		}
		if ( function_exists( 'universal_geo_get_country_code' ) ) {
			$api = 'universal_geo_get_country_code';
		}
		return array(
			'available' => $adapter->is_available(),
			'version'   => $version,
			'api'       => $api,
		);
	}

	/**
	 * Action Scheduler cleanup registration health.
	 *
	 * @return array{scheduler: string, next: int|false|null}
	 */
	private static function cleanup_health(): array {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return array(
				'scheduler' => 'unavailable',
				'next'      => null,
			);
		}
		$next = as_next_scheduled_action( RetentionScheduler::RECURRING_HOOK, array(), RetentionScheduler::GROUP );
		return array(
			'scheduler' => false !== $next ? 'scheduled' : 'not_scheduled',
			'next'      => $next,
		);
	}
}
