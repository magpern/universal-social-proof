<?php
/**
 * Presentation-safe template values (no provenance / orders).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Template;

use DateTimeImmutable;
use UniversalSocialProof\Selection\SelectedEvent;
use UniversalSocialProof\Storage\Quantity;

defined( 'ABSPATH' ) || exit;

/**
 * Values available to the constrained token grammar.
 */
final class TemplateContext {

	/**
	 * Constructor.
	 *
	 * @param string                 $product            Current public product name.
	 * @param string                 $country_label      Localized purchase-country label or ''.
	 * @param string                 $quantity_display   Display-normalized quantity or ''.
	 * @param bool                   $quantity_valid     Whether quantity may be substituted.
	 * @param DateTimeImmutable|null $occurred_at_utc  Authoritative event time.
	 */
	public function __construct(
		public readonly string $product,
		public readonly string $country_label,
		public readonly string $quantity_display,
		public readonly bool $quantity_valid,
		public readonly ?DateTimeImmutable $occurred_at_utc
	) {}

	/**
	 * Build from an internal selected event.
	 *
	 * @param SelectedEvent $event Selected event with resolved PublicProduct.
	 */
	public static function from_selected_event( SelectedEvent $event ): self {
		$qty_raw   = $event->quantity;
		$qty_valid = Quantity::is_positive( $qty_raw );
		$qty_disp  = $qty_valid ? Quantity::format_display( $qty_raw ) : '';

		return new self(
			trim( $event->product->name ),
			self::country_label( $event->country_code ),
			$qty_disp,
			$qty_valid,
			$event->occurred_at_utc()
		);
	}

	/**
	 * Localized purchase-country display from ISO code.
	 *
	 * @param string|null $code ISO 3166-1 alpha-2 or null.
	 */
	public static function country_label( ?string $code ): string {
		if ( null === $code || '' === $code ) {
			return '';
		}
		$code = strtoupper( $code );
		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $code ) ) {
			return '';
		}
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}
		$wc = WC();
		if ( ! is_object( $wc ) || ! isset( $wc->countries ) || ! is_object( $wc->countries ) ) {
			return '';
		}
		$countries = $wc->countries->get_countries();
		if ( ! is_array( $countries ) || ! isset( $countries[ $code ] ) ) {
			return '';
		}
		$label = $countries[ $code ];
		return is_string( $label ) ? $label : '';
	}
}
