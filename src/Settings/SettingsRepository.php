<?php
/**
 * M6 operator settings: storage, migration, validation.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Settings;

use UniversalSocialProof\Cleanup\RetentionSettings;
use UniversalSocialProof\Targeting\ProductTargetingPolicy;
use UniversalSocialProof\Template\TemplateSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Sole persisted settings source after usp_settings_version >= 1 (ADR-0015).
 */
final class SettingsRepository {

	public const OPTION_KEY  = 'usp_settings';
	public const VERSION_KEY = 'usp_settings_version';
	public const VERSION     = 1;

	public const LEGACY_RETENTION = 'usp_retention_days';
	public const LEGACY_OOS       = 'usp_exclude_out_of_stock';

	/**
	 * Whether migrate() has run this request.
	 *
	 * @var bool
	 */
	private static bool $migrated_this_request = false;

	/**
	 * Code defaults (before persisted settings / filters).
	 *
	 * @return array{
	 *   display_enabled: bool,
	 *   template: string,
	 *   excluded_product_ids: list<int>,
	 *   exclude_out_of_stock: bool,
	 *   load_on_cart: bool,
	 *   load_on_account: bool,
	 *   geo_weighting_enabled: bool,
	 *   retention_days: int
	 * }
	 */
	public static function defaults(): array {
		return array(
			'display_enabled'       => true,
			'template'              => TemplateSettings::default_template(),
			'excluded_product_ids'  => array(),
			'exclude_out_of_stock'  => false,
			'load_on_cart'          => false,
			'load_on_account'       => false,
			'geo_weighting_enabled' => true,
			'retention_days'        => RetentionSettings::DEFAULT,
		);
	}

	/**
	 * Run one-shot migration when needed. Idempotent.
	 */
	public static function maybe_migrate(): void {
		if ( self::$migrated_this_request ) {
			return;
		}
		self::$migrated_this_request = true;

		$version = (int) get_option( self::VERSION_KEY, 0 );
		if ( $version >= self::VERSION ) {
			return;
		}

		$existing = get_option( self::OPTION_KEY, null );
		if ( is_array( $existing ) ) {
			$normalized = self::normalize( $existing );
			update_option( self::OPTION_KEY, $normalized, true );
			update_option( self::VERSION_KEY, self::VERSION, true );
			return;
		}

		$settings = self::defaults();

		if ( false !== get_option( self::LEGACY_RETENTION, false ) ) {
			$settings['retention_days'] = self::normalize_retention_days(
				get_option( self::LEGACY_RETENTION, RetentionSettings::DEFAULT )
			);
		}

		if ( false !== get_option( self::LEGACY_OOS, false ) ) {
			$settings['exclude_out_of_stock'] = self::normalize_bool(
				get_option( self::LEGACY_OOS, 'no' )
			);
		}

		update_option( self::OPTION_KEY, $settings, true );
		update_option( self::VERSION_KEY, self::VERSION, true );
	}

	/**
	 * Persisted settings normalized to full shape (defaults for missing keys).
	 * Does not apply filters.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_persisted(): array {
		self::maybe_migrate();
		$raw = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $raw ) ) {
			return self::defaults();
		}
		return self::normalize( $raw );
	}

	/**
	 * Normalize/clamp a raw settings array (does not apply filters).
	 *
	 * @param array<string, mixed> $raw Raw input.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $raw ): array {
		$defaults  = self::defaults();
		$template  = isset( $raw['template'] ) && is_string( $raw['template'] )
			? $raw['template']
			: $defaults['template'];
		$validated = TemplateSettings::validate_template( $template );
		if ( null === $validated ) {
			$template = $defaults['template'];
		} else {
			$template = $validated;
		}

		return array(
			'display_enabled'       => array_key_exists( 'display_enabled', $raw )
				? self::normalize_bool( $raw['display_enabled'] )
				: $defaults['display_enabled'],
			'template'              => $template,
			'excluded_product_ids'  => self::normalize_product_ids( $raw['excluded_product_ids'] ?? array() ),
			'exclude_out_of_stock'  => array_key_exists( 'exclude_out_of_stock', $raw )
				? self::normalize_bool( $raw['exclude_out_of_stock'] )
				: $defaults['exclude_out_of_stock'],
			'load_on_cart'          => array_key_exists( 'load_on_cart', $raw )
				? self::normalize_bool( $raw['load_on_cart'] )
				: $defaults['load_on_cart'],
			'load_on_account'       => array_key_exists( 'load_on_account', $raw )
				? self::normalize_bool( $raw['load_on_account'] )
				: $defaults['load_on_account'],
			'geo_weighting_enabled' => array_key_exists( 'geo_weighting_enabled', $raw )
				? self::normalize_bool( $raw['geo_weighting_enabled'] )
				: $defaults['geo_weighting_enabled'],
			'retention_days'        => self::normalize_retention_days(
				$raw['retention_days'] ?? $defaults['retention_days']
			),
		);
	}

	/**
	 * Validate and save a complete settings payload atomically.
	 *
	 * @param array<string, mixed> $raw Submitted settings.
	 * @return true|\WP_Error
	 */
	public static function save( array $raw ) {
		self::maybe_migrate();

		if ( isset( $raw['template'] ) && is_string( $raw['template'] ) ) {
			if ( null === TemplateSettings::validate_template( $raw['template'] ) ) {
				return new \WP_Error(
					'usp_invalid_template',
					__( 'Notification template is invalid. Use only allowed {{tokens}} within length limits.', 'universal-social-proof' )
				);
			}
		}

		if ( isset( $raw['retention_days'] ) ) {
			$days = (int) $raw['retention_days'];
			if ( $days < RetentionSettings::MIN || $days > RetentionSettings::MAX ) {
				return new \WP_Error(
					'usp_invalid_retention',
					sprintf(
						/* translators: 1: min days, 2: max days */
						__( 'Retention days must be between %1$d and %2$d.', 'universal-social-proof' ),
						RetentionSettings::MIN,
						RetentionSettings::MAX
					)
				);
			}
		}

		$normalized = self::normalize( $raw );
		update_option( self::OPTION_KEY, $normalized, true );
		update_option( self::VERSION_KEY, self::VERSION, true );
		return true;
	}

	/**
	 * Reset settings to defaults. Does not delete events.
	 */
	public static function reset_to_defaults(): void {
		self::maybe_migrate();
		update_option( self::OPTION_KEY, self::defaults(), true );
		update_option( self::VERSION_KEY, self::VERSION, true );
	}

	/**
	 * Display master switch (persisted only; no filter in v1).
	 */
	public static function display_enabled(): bool {
		return (bool) self::get_persisted()['display_enabled'];
	}

	/**
	 * Persisted template before filter.
	 */
	public static function template(): string {
		return (string) self::get_persisted()['template'];
	}

	/**
	 * Persisted product exclusion IDs before filter.
	 *
	 * @return list<int>
	 */
	public static function excluded_product_ids(): array {
		$ids = self::get_persisted()['excluded_product_ids'];
		return is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : array();
	}

	/**
	 * Persisted OOS exclusion before filter.
	 */
	public static function exclude_out_of_stock(): bool {
		return (bool) self::get_persisted()['exclude_out_of_stock'];
	}

	/**
	 * Whether cart pages may load the toaster.
	 */
	public static function load_on_cart(): bool {
		return (bool) self::get_persisted()['load_on_cart'];
	}

	/**
	 * Whether account pages may load the toaster.
	 */
	public static function load_on_account(): bool {
		return (bool) self::get_persisted()['load_on_account'];
	}

	/**
	 * Persisted geo weighting before filter.
	 */
	public static function geo_weighting_enabled(): bool {
		return (bool) self::get_persisted()['geo_weighting_enabled'];
	}

	/**
	 * Persisted retention days before filter.
	 */
	public static function retention_days(): int {
		return (int) self::get_persisted()['retention_days'];
	}

	/**
	 * Settings schema version currently stored.
	 */
	public static function settings_version(): int {
		self::maybe_migrate();
		return (int) get_option( self::VERSION_KEY, 0 );
	}

	/**
	 * Normalize product ID list (positive ints, dedupe, max 200).
	 *
	 * @param mixed $raw Raw IDs.
	 * @return list<int>
	 */
	public static function normalize_product_ids( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			if ( is_string( $raw ) && '' !== $raw ) {
				$parts = preg_split( '/[\s,]+/', $raw );
				$raw   = false === $parts ? array() : $parts;
			} else {
				return array();
			}
		}
		$out = array();
		foreach ( $raw as $id ) {
			if ( is_string( $id ) && 1 === preg_match( '/^[1-9][0-9]*$/', $id ) ) {
				$id = (int) $id;
			}
			if ( ! is_int( $id ) && ! is_float( $id ) ) {
				continue;
			}
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$out[ $id ] = $id;
			if ( count( $out ) >= ProductTargetingPolicy::MAX_IDS ) {
				break;
			}
		}
		return array_values( $out );
	}

	/**
	 * Coerce mixed values to bool.
	 *
	 * @param mixed $raw Raw bool-ish.
	 */
	public static function normalize_bool( mixed $raw ): bool {
		return true === $raw || 1 === $raw || '1' === $raw || 'yes' === $raw || 'true' === $raw || 'on' === $raw;
	}

	/**
	 * Coerce and clamp retention days.
	 *
	 * @param mixed $raw Raw days.
	 */
	public static function normalize_retention_days( mixed $raw ): int {
		$days = (int) $raw;
		return max( RetentionSettings::MIN, min( RetentionSettings::MAX, $days ) );
	}

	/**
	 * Test seam.
	 */
	public static function reset_for_tests(): void {
		self::$migrated_this_request = false;
	}
}
