<?php
/**
 * Bounded selection candidate (internal; not a public DTO).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

defined( 'ABSPATH' ) || exit;

/**
 * One active event row used by the selection engine.
 */
final class Candidate {

	/**
	 * Constructor.
	 *
	 * @param string      $public_id     UUIDv4.
	 * @param int         $product_id    Stored parent product ID.
	 * @param int|null    $variation_id  Stored variation ID or null.
	 * @param string      $quantity      Original quantity as stored.
	 * @param string|null $country_code  ISO country or null.
	 * @param string      $occurred_at  UTC MySQL datetime.
	 */
	public function __construct(
		public readonly string $public_id,
		public readonly int $product_id,
		public readonly ?int $variation_id,
		public readonly string $quantity,
		public readonly ?string $country_code,
		public readonly string $occurred_at
	) {}

	/**
	 * Hydrate from a selected-column database row.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	public static function from_row( array $row ): ?self {
		$public_id = isset( $row['public_id'] ) ? (string) $row['public_id'] : '';
		if ( '' === $public_id ) {
			return null;
		}
		$product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
		if ( $product_id <= 0 ) {
			return null;
		}
		$variation = null;
		if ( isset( $row['variation_id'] ) && '' !== $row['variation_id'] && null !== $row['variation_id'] ) {
			$vid       = (int) $row['variation_id'];
			$variation = $vid > 0 ? $vid : null;
		}
		$occurred = isset( $row['occurred_at'] ) ? (string) $row['occurred_at'] : '';
		if ( '' === $occurred ) {
			return null;
		}
		$country = isset( $row['country_code'] ) && '' !== $row['country_code'] ? (string) $row['country_code'] : null;

		return new self(
			$public_id,
			$product_id,
			$variation,
			isset( $row['quantity'] ) ? (string) $row['quantity'] : '0',
			$country,
			$occurred
		);
	}
}
