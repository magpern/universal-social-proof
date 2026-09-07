<?php
/**
 * M6/v1.1 operator settings: storage, migration, validation.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Settings;

use UniversalSocialProof\Cleanup\CartRetentionSettings;
use UniversalSocialProof\Cleanup\RetentionSettings;
use UniversalSocialProof\Targeting\ProductTargetingPolicy;
use UniversalSocialProof\Template\TemplateSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Sole persisted settings source after usp_settings_version >= 1 (ADR-0015 / v1.1 → 2).
 */
final class SettingsRepository {

	public const OPTION_KEY  = 'usp_settings';
	public const VERSION_KEY = 'usp_settings_version';
	public const VERSION     = 2;

	public const LEGACY_RETENTION = 'usp_retention_days';
	public const LEGACY_OOS       = 'usp_exclude_out_of_stock';

	public const CUSTOM_CSS_MAX = 4000;

	public const APPEARANCE_POSITIONS = array( 'bottom-left', 'bottom-right' );
	public const APPEARANCE_SHADOWS   = array( 'soft', 'none', 'medium' );

	/**
	 * Whether migrate() has run this request.
	 *
	 * @var bool
	 */
	private static bool $migrated_this_request = false;

	/**
	 * Code defaults (before persisted settings / filters).
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'display_enabled'               => true,
			'purchase_enabled'              => true,
			'cart_enabled'                  => false,
			'template'                      => TemplateSettings::default_purchase_template(),
			'cart_template'                 => TemplateSettings::default_cart_template(),
			'excluded_product_ids'          => array(),
			'exclude_out_of_stock'          => false,
			'load_on_cart'                  => false,
			'load_on_account'               => false,
			'geo_weighting_enabled'         => true,
			'retention_days'                => RetentionSettings::DEFAULT,
			'cart_retention_minutes'        => CartRetentionSettings::DEFAULT,
			'appearance_position'           => 'bottom-left',
			'appearance_background'         => '#ffffff',
			'appearance_text'               => '#111111',
			'appearance_accent'             => '#0b5fff',
			'appearance_radius'             => 12,
			'appearance_shadow'             => 'soft',
			'appearance_show_product_image' => true,
			'appearance_show_close'         => true,
			'appearance_custom_css'         => '',
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
		$defaults = self::defaults();

		$template  = isset( $raw['template'] ) && is_string( $raw['template'] )
			? $raw['template']
			: $defaults['template'];
		$validated = TemplateSettings::validate_template( $template );
		$template  = null === $validated ? $defaults['template'] : $validated;

		$cart_template  = isset( $raw['cart_template'] ) && is_string( $raw['cart_template'] )
			? $raw['cart_template']
			: $defaults['cart_template'];
		$cart_validated = TemplateSettings::validate_template( $cart_template );
		$cart_template  = null === $cart_validated ? $defaults['cart_template'] : $cart_validated;

		return array(
			'display_enabled'               => array_key_exists( 'display_enabled', $raw )
				? self::normalize_bool( $raw['display_enabled'] )
				: $defaults['display_enabled'],
			'purchase_enabled'              => array_key_exists( 'purchase_enabled', $raw )
				? self::normalize_bool( $raw['purchase_enabled'] )
				: $defaults['purchase_enabled'],
			'cart_enabled'                  => array_key_exists( 'cart_enabled', $raw )
				? self::normalize_bool( $raw['cart_enabled'] )
				: $defaults['cart_enabled'],
			'template'                      => $template,
			'cart_template'                 => $cart_template,
			'excluded_product_ids'          => self::normalize_product_ids( $raw['excluded_product_ids'] ?? array() ),
			'exclude_out_of_stock'          => array_key_exists( 'exclude_out_of_stock', $raw )
				? self::normalize_bool( $raw['exclude_out_of_stock'] )
				: $defaults['exclude_out_of_stock'],
			'load_on_cart'                  => array_key_exists( 'load_on_cart', $raw )
				? self::normalize_bool( $raw['load_on_cart'] )
				: $defaults['load_on_cart'],
			'load_on_account'               => array_key_exists( 'load_on_account', $raw )
				? self::normalize_bool( $raw['load_on_account'] )
				: $defaults['load_on_account'],
			'geo_weighting_enabled'         => array_key_exists( 'geo_weighting_enabled', $raw )
				? self::normalize_bool( $raw['geo_weighting_enabled'] )
				: $defaults['geo_weighting_enabled'],
			'retention_days'                => self::normalize_retention_days(
				$raw['retention_days'] ?? $defaults['retention_days']
			),
			'cart_retention_minutes'        => self::normalize_cart_retention_minutes(
				$raw['cart_retention_minutes'] ?? $defaults['cart_retention_minutes']
			),
			'appearance_position'           => self::normalize_appearance_position(
				$raw['appearance_position'] ?? $defaults['appearance_position']
			),
			'appearance_background'         => self::normalize_hex_color(
				$raw['appearance_background'] ?? $defaults['appearance_background'],
				(string) $defaults['appearance_background']
			),
			'appearance_text'               => self::normalize_hex_color(
				$raw['appearance_text'] ?? $defaults['appearance_text'],
				(string) $defaults['appearance_text']
			),
			'appearance_accent'             => self::normalize_hex_color(
				$raw['appearance_accent'] ?? $defaults['appearance_accent'],
				(string) $defaults['appearance_accent']
			),
			'appearance_radius'             => self::normalize_radius(
				$raw['appearance_radius'] ?? $defaults['appearance_radius']
			),
			'appearance_shadow'             => self::normalize_shadow(
				$raw['appearance_shadow'] ?? $defaults['appearance_shadow']
			),
			'appearance_show_product_image' => array_key_exists( 'appearance_show_product_image', $raw )
				? self::normalize_bool( $raw['appearance_show_product_image'] )
				: $defaults['appearance_show_product_image'],
			'appearance_show_close'         => array_key_exists( 'appearance_show_close', $raw )
				? self::normalize_bool( $raw['appearance_show_close'] )
				: $defaults['appearance_show_close'],
			'appearance_custom_css'         => self::normalize_custom_css(
				$raw['appearance_custom_css'] ?? $defaults['appearance_custom_css']
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
					__( 'Purchase notification template is invalid. Use only allowed {{tokens}} within length limits.', 'universal-social-proof' )
				);
			}
		}

		if ( isset( $raw['cart_template'] ) && is_string( $raw['cart_template'] ) ) {
			if ( null === TemplateSettings::validate_template( $raw['cart_template'] ) ) {
				return new \WP_Error(
					'usp_invalid_cart_template',
					__( 'Cart notification template is invalid. Use only allowed {{tokens}} within length limits.', 'universal-social-proof' )
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
						__( 'Purchase retention days must be between %1$d and %2$d.', 'universal-social-proof' ),
						RetentionSettings::MIN,
						RetentionSettings::MAX
					)
				);
			}
		}

		if ( isset( $raw['cart_retention_minutes'] ) ) {
			$minutes = (int) $raw['cart_retention_minutes'];
			if ( $minutes < CartRetentionSettings::MIN || $minutes > CartRetentionSettings::MAX ) {
				return new \WP_Error(
					'usp_invalid_cart_retention',
					sprintf(
						/* translators: 1: min minutes, 2: max minutes */
						__( 'Cart retention minutes must be between %1$d and %2$d.', 'universal-social-proof' ),
						CartRetentionSettings::MIN,
						CartRetentionSettings::MAX
					)
				);
			}
		}

		if ( isset( $raw['appearance_custom_css'] ) && is_string( $raw['appearance_custom_css'] ) ) {
			if ( strlen( $raw['appearance_custom_css'] ) > self::CUSTOM_CSS_MAX ) {
				return new \WP_Error(
					'usp_invalid_custom_css',
					sprintf(
						/* translators: %d: max characters */
						__( 'Custom CSS must be at most %d characters.', 'universal-social-proof' ),
						self::CUSTOM_CSS_MAX
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
	 * Whether purchase events may be selected for display.
	 */
	public static function purchase_enabled(): bool {
		return (bool) self::get_persisted()['purchase_enabled'];
	}

	/**
	 * Whether cart capture and selection are enabled.
	 */
	public static function cart_enabled(): bool {
		return (bool) self::get_persisted()['cart_enabled'];
	}

	/**
	 * Persisted purchase template before filter.
	 */
	public static function template(): string {
		return (string) self::get_persisted()['template'];
	}

	/**
	 * Persisted cart template before filter.
	 */
	public static function cart_template(): string {
		return (string) self::get_persisted()['cart_template'];
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
	 * Persisted purchase retention days before filter.
	 */
	public static function retention_days(): int {
		return (int) self::get_persisted()['retention_days'];
	}

	/**
	 * Persisted cart retention minutes before filter.
	 */
	public static function cart_retention_minutes(): int {
		return (int) self::get_persisted()['cart_retention_minutes'];
	}

	/**
	 * Appearance subset for frontend bootstrap / shell.
	 *
	 * @return array<string, mixed>
	 */
	public static function appearance(): array {
		$s = self::get_persisted();
		return array(
			'position'           => (string) $s['appearance_position'],
			'background'         => (string) $s['appearance_background'],
			'text'               => (string) $s['appearance_text'],
			'accent'             => (string) $s['appearance_accent'],
			'radius'             => (int) $s['appearance_radius'],
			'shadow'             => (string) $s['appearance_shadow'],
			'show_product_image' => (bool) $s['appearance_show_product_image'],
			'show_close'         => (bool) $s['appearance_show_close'],
			'custom_css'         => (string) $s['appearance_custom_css'],
		);
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
	 * Coerce and clamp cart retention minutes.
	 *
	 * @param mixed $raw Raw minutes.
	 */
	public static function normalize_cart_retention_minutes( mixed $raw ): int {
		$minutes = (int) $raw;
		return max( CartRetentionSettings::MIN, min( CartRetentionSettings::MAX, $minutes ) );
	}

	/**
	 * Normalize toaster position.
	 *
	 * @param mixed $raw Raw position.
	 */
	public static function normalize_appearance_position( mixed $raw ): string {
		$pos = is_string( $raw ) ? $raw : '';
		return in_array( $pos, self::APPEARANCE_POSITIONS, true ) ? $pos : 'bottom-left';
	}

	/**
	 * Normalize hex color (#rgb or #rrggbb) to lowercase #rrggbb.
	 *
	 * @param mixed  $raw           Raw color.
	 * @param string $fallback_hex Fallback.
	 */
	public static function normalize_hex_color( mixed $raw, string $fallback_hex ): string {
		if ( ! is_string( $raw ) ) {
			return $fallback_hex;
		}
		$raw = trim( $raw );
		if ( 1 === preg_match( '/^#([0-9A-Fa-f]{6})$/', $raw, $m ) ) {
			return '#' . strtolower( $m[1] );
		}
		if ( 1 === preg_match( '/^#([0-9A-Fa-f]{3})$/', $raw, $m ) ) {
			$h = strtolower( $m[1] );
			return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		return $fallback_hex;
	}

	/**
	 * Normalize border radius (0–32).
	 *
	 * @param mixed $raw Raw radius.
	 */
	public static function normalize_radius( mixed $raw ): int {
		return max( 0, min( 32, (int) $raw ) );
	}

	/**
	 * Normalize shadow preset.
	 *
	 * @param mixed $raw Raw shadow.
	 */
	public static function normalize_shadow( mixed $raw ): string {
		$shadow = is_string( $raw ) ? $raw : '';
		return in_array( $shadow, self::APPEARANCE_SHADOWS, true ) ? $shadow : 'soft';
	}

	/**
	 * Normalize custom CSS (truncate on normalize; save rejects over max).
	 *
	 * @param mixed $raw Raw CSS.
	 */
	public static function normalize_custom_css( mixed $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		// Strip tags so HTML/JS cannot be smuggled through admin CSS.
		$css = wp_strip_all_tags( $raw );
		$css = str_replace( array( '</style>', '</STYLE>', '<?', '?>' ), '', $css );
		if ( strlen( $css ) > self::CUSTOM_CSS_MAX ) {
			return substr( $css, 0, self::CUSTOM_CSS_MAX );
		}
		return $css;
	}

	/**
	 * Test seam.
	 */
	public static function reset_for_tests(): void {
		self::$migrated_this_request = false;
	}
}
