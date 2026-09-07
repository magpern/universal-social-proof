<?php
/**
 * M4 template source: settings + validated filter.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Template;

use UniversalSocialProof\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the notification template string for rendering.
 * Precedence: default → usp_settings → filter → validate (ADR-0015).
 */
final class TemplateSettings {

	public const FILTER = 'usp_notification_template';

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
	 * Translated default template (tokens intact).
	 */
	public static function default_template(): string {
		/* translators: {{product}} is a merge token and must remain verbatim. */
		return __( 'Someone purchased {{product}}', 'universal-social-proof' );
	}

	/**
	 * Resolve template: settings → filter → validate; invalid filter falls back to settings/default.
	 */
	public static function get(): string {
		$base           = SettingsRepository::template();
		$validated_base = self::validate_template( $base );
		if ( null === $validated_base ) {
			$base = self::default_template();
		} else {
			$base = $validated_base;
		}
		/**
		 * Filter the USP notification message template.
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
