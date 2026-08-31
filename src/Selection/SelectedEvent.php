<?php
/**
 * Internal selected event (not the public REST DTO).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

use DateTimeImmutable;
use DateTimeZone;
use UniversalSocialProof\Product\PublicProduct;
use UniversalSocialProof\Template\TemplateContext;
use UniversalSocialProof\Template\TemplateRenderer;
use UniversalSocialProof\Template\TemplateSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Structured selection result for REST mapping and M4 templates.
 */
final class SelectedEvent {

	/**
	 * Shared renderer instance for request-scoped projection.
	 *
	 * @var TemplateRenderer|null
	 */
	private static ?TemplateRenderer $renderer = null;

	/**
	 * Constructor.
	 *
	 * @param string        $public_id     UUIDv4.
	 * @param string        $occurred_at   UTC MySQL datetime.
	 * @param string|null   $country_code  ISO or null.
	 * @param string        $quantity      Original stored quantity.
	 * @param int           $product_id    Stored parent ID.
	 * @param int|null      $variation_id  Stored variation ID.
	 * @param PublicProduct $product       Current public presentation.
	 */
	public function __construct(
		public readonly string $public_id,
		public readonly string $occurred_at,
		public readonly ?string $country_code,
		public readonly string $quantity,
		public readonly int $product_id,
		public readonly ?int $variation_id,
		public readonly PublicProduct $product
	) {}

	/**
	 * UTC occurred_at as DateTimeImmutable, or null if unparseable.
	 */
	public function occurred_at_utc(): ?DateTimeImmutable {
		$tz = new DateTimeZone( 'UTC' );
		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $this->occurred_at, $tz );
		return $dt instanceof DateTimeImmutable ? $dt : null;
	}

	/**
	 * Public REST allowlist including M4 message fields.
	 *
	 * @return array{public_id: string, product_url: string, thumbnail_url: string|null, occurred_at: string, message: string, show_relative_time: bool}|null
	 */
	public function to_public_array(): ?array {
		$dt = $this->occurred_at_utc();
		if ( ! $dt instanceof DateTimeImmutable ) {
			return null;
		}

		$renderer = self::$renderer ?? new TemplateRenderer();
		$result   = $renderer->render(
			TemplateSettings::get(),
			TemplateContext::from_selected_event( $this )
		);
		if ( null === $result ) {
			return null;
		}

		return array(
			'public_id'          => $this->public_id,
			'product_url'        => $this->product->permalink,
			'thumbnail_url'      => $this->product->thumbnail_url,
			'occurred_at'        => $dt->format( 'Y-m-d\TH:i:s\Z' ),
			'message'            => $result->message,
			'show_relative_time' => ! $result->used_time_ago,
		);
	}

	/**
	 * Build from a candidate plus resolved public product.
	 *
	 * @param Candidate     $candidate Candidate row.
	 * @param PublicProduct $product   Resolved presentation.
	 */
	public static function from_candidate( Candidate $candidate, PublicProduct $product ): self {
		return new self(
			$candidate->public_id,
			$candidate->occurred_at,
			$candidate->country_code,
			$candidate->quantity,
			$candidate->product_id,
			$candidate->variation_id,
			$product
		);
	}

	/**
	 * Test seam: inject renderer.
	 *
	 * @param TemplateRenderer|null $renderer Renderer or null to reset.
	 */
	public static function set_renderer_for_tests( ?TemplateRenderer $renderer ): void {
		self::$renderer = $renderer;
	}
}
