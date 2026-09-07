<?php
/**
 * M4/v1.1 template source: settings + validated filter.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Template;

use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Storage\EventType;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves notification template strings for rendering.
 * Precedence: default → usp_settings → filter → validate (ADR-0015).
 */
final class TemplateSettings {

	public const FILTER      = 'usp_notification_template';
	public const CART_FILTER = 'usp_cart_notification_template';

	public const MAX_LENGTH = 500;

	/**
	 * Allowed token names (without braces).
	 */
	public const ALLOWED_TOKENS = array(
		'product',
		'country',
		'location',
		'time_ago',
		'quantity',
	);

	/**
	 * Default purchase template including country (v1.1).
	 */
	public static function default_purchase_template(): string {
		/* translators: {{country}} and {{product}} are merge tokens and must remain verbatim. */
		return __( 'Someone in {{country}} purchased {{product}}', 'universal-social-proof' );
	}

	/**
	 * Country-absent purchase fallback (grammatically valid).
	 */
	public static function fallback_purchase_template(): string {
		/* translators: {{product}} is a merge token and must remain verbatim. */
		return __( 'Someone purchased {{product}}', 'universal-social-proof' );
	}

	/**
	 * Default cart template including country.
	 */
	public static function default_cart_template(): string {
		/* translators: {{country}} and {{product}} are merge tokens and must remain verbatim. */
		return __( 'Someone in {{country}} just added {{product}} to their cart', 'universal-social-proof' );
	}

	/**
	 * Country-absent cart fallback (grammatically valid).
	 */
	public static function fallback_cart_template(): string {
		/* translators: {{product}} is a merge token and must remain verbatim. */
		return __( 'Someone just added {{product}} to their cart', 'universal-social-proof' );
	}

	/**
	 * Alias for purchase default (legacy callers / settings defaults).
	 */
	public static function default_template(): string {
		return self::default_purchase_template();
	}

	/**
	 * Resolve purchase template: settings → filter → validate.
	 */
	public static function get(): string {
		$base           = SettingsRepository::template();
		$validated_base = self::validate_template( $base );
		if ( null === $validated_base ) {
			$base = self::default_purchase_template();
		} else {
			$base = $validated_base;
		}
		/**
		 * Filter the USP purchase notification message template.
		 *
		 * Must use only the approved {{token}} grammar. Invalid output is ignored.
		 *
		 * @since 0.4.0
		 * @param string $template Template string.
		 */
		$filtered = apply_filters( self::FILTER, $base );
		if ( ! is_string( $filtered ) ) {
			return $base;
		}
		$validated = self::validate_template( $filtered );
		return null === $validated ? $base : $validated;
	}

	/**
	 * Resolve cart template: settings → filter → validate.
	 */
	public static function get_cart(): string {
		$base           = SettingsRepository::cart_template();
		$validated_base = self::validate_template( $base );
		if ( null === $validated_base ) {
			$base = self::default_cart_template();
		} else {
			$base = $validated_base;
		}
		/**
		 * Filter the USP cart notification message template.
		 *
		 * @since 1.1.0
		 * @param string $template Template string.
		 */
		$filtered = apply_filters( self::CART_FILTER, $base );
		if ( ! is_string( $filtered ) ) {
			return $base;
		}
		$validated = self::validate_template( $filtered );
		return null === $validated ? $base : $validated;
	}

	/**
	 * Template for an event type, switching to country-absent fallback when needed.
	 *
	 * Uses localized country label presence (not merely a stored ISO code) so
	 * "Someone in  purchased …" never ships when the label cannot be resolved.
	 *
	 * @param string      $event_type   purchase|add_to_cart.
	 * @param string|null $country_code ISO-2 or null/empty.
	 */
	public static function resolve_for_event( string $event_type, ?string $country_code ): string {
		$is_cart  = EventType::ADD_TO_CART === $event_type;
		$template = $is_cart ? self::get_cart() : self::get();
		$label    = TemplateContext::country_label( $country_code );
		if ( self::needs_country_fallback( $template, '' !== $label ? $country_code : null ) ) {
			return $is_cart ? self::fallback_cart_template() : self::fallback_purchase_template();
		}
		return $template;
	}

	/**
	 * Whether an empty country would produce a broken "in " phrase.
	 *
	 * @param string      $template     Template string.
	 * @param string|null $country_code Country code.
	 */
	public static function needs_country_fallback( string $template, ?string $country_code ): bool {
		if ( null !== $country_code && '' !== $country_code ) {
			return false;
		}
		return false !== strpos( $template, '{{country}}' ) || false !== strpos( $template, '{{location}}' );
	}

	/**
	 * Validate grammar without rendering. Returns template or null.
	 *
	 * Every `{` / `}` must belong to a syntactically valid approved `{{token}}`.
	 * No literal-brace escape syntax in M4.
	 *
	 * @param string $template Raw template.
	 */
	public static function validate_template( string $template ): ?string {
		if ( strlen( $template ) > self::MAX_LENGTH ) {
			return null;
		}
		$len = strlen( $template );
		$i   = 0;
		while ( $i < $len ) {
			$ch = $template[ $i ];
			if ( '}' === $ch ) {
				return null;
			}
			if ( '{' !== $ch ) {
				++$i;
				continue;
			}
			if ( $i + 1 >= $len || '{' !== $template[ $i + 1 ] ) {
				return null;
			}
			$close = strpos( $template, '}}', $i + 2 );
			if ( false === $close ) {
				return null;
			}
			$inner = substr( $template, $i + 2, $close - ( $i + 2 ) );
			if ( false !== strpos( $inner, '{' ) || false !== strpos( $inner, '}' ) ) {
				return null;
			}
			if ( 1 !== preg_match( '/^[a-z][a-z0-9_]*$/', $inner ) ) {
				return null;
			}
			if ( ! in_array( $inner, self::ALLOWED_TOKENS, true ) ) {
				return null;
			}
			$i = $close + 2;
		}
		return $template;
	}
}
