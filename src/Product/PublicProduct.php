<?php
/**
 * Current public product presentation (internal).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Resolved merchandising view for a selected event.
 */
final class PublicProduct {

	/**
	 * Constructor.
	 *
	 * @param int         $id            Presented WC product ID.
	 * @param string      $type          simple|variation (presentation).
	 * @param string      $permalink     Current public URL.
	 * @param string|null $thumbnail_url Thumbnail or null (never placeholder).
	 * @param string      $name          Current name (M4 only; not in M2 DTO).
	 * @param bool        $is_in_stock   WC is_in_stock() on presented product.
	 * @param int         $parent_id     Parent ID (same as id for simples).
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $type,
		public readonly string $permalink,
		public readonly ?string $thumbnail_url,
		public readonly string $name,
		public readonly bool $is_in_stock,
		public readonly int $parent_id
	) {}
}
