<?php
/**
 * Constrained whitelist token renderer (plain text).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Deterministic template → message + used_time_ago.
 */
final class TemplateRenderer {

	/**
	 * Render a template against context, or null on failure.
	 *
	 * @param string          $template Already-validated template preferred.
	 * @param TemplateContext $context  Presentation values.
	 */
	public function render( string $template, TemplateContext $context ): ?RenderResult {
		if ( null === TemplateSettings::validate_template( $template ) ) {
			return null;
		}

		$used_time = false;
		$len       = strlen( $template );
		$out       = '';
		$i         = 0;

		while ( $i < $len ) {
			if ( '{' !== $template[ $i ] ) {
				$out .= $template[ $i ];
				++$i;
				continue;
			}
			// validate_template already ensured well-formed {{name}}.
			$close = strpos( $template, '}}', $i + 2 );
			if ( false === $close ) {
				return null;
			}
			$name  = substr( $template, $i + 2, $close - ( $i + 2 ) );
			$value = $this->resolve_token( $name, $context, $used_time );
			if ( null === $value ) {
				return null;
			}
			$out .= $value;
			$i    = $close + 2;
		}

		$message = trim( $out );
		if ( '' === $message ) {
			return null;
		}

		return new RenderResult( $message, $used_time );
	}

	/**
	 * Resolve one token value, or null to fail the render.
	 *
	 * @param string          $name      Token name.
	 * @param TemplateContext $context   Context.
	 * @param bool            $used_time Updated when time_ago used.
	 */
	private function resolve_token( string $name, TemplateContext $context, bool &$used_time ): ?string {
		switch ( $name ) {
			case 'product':
				return '' === $context->product ? null : $context->product;
			case 'country':
			case 'location':
				return $context->country_label;
			case 'quantity':
				if ( ! $context->quantity_valid || '' === $context->quantity_display ) {
					return null;
				}
				return $context->quantity_display;
			case 'time_ago':
				if ( ! $context->occurred_at_utc instanceof \DateTimeImmutable ) {
					return null;
				}
				$phrase = RelativeTimeFormatter::format( $context->occurred_at_utc );
				if ( null === $phrase || '' === $phrase ) {
					return null;
				}
				$used_time = true;
				return $phrase;
			default:
				return null;
		}
	}
}
