<?php
/**
 * Normalized selection request (post REST validation).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

defined( 'ABSPATH' ) || exit;

/**
 * Validated limit, optional PDP product, page context, exclusions.
 */
final class SelectionRequest {

	public const CONTEXT_PRODUCT = 'product';
	public const CONTEXT_UNKNOWN = 'unknown';

	public const LIMIT_DEFAULT = 5;
	public const LIMIT_MIN     = 1;
	public const LIMIT_MAX     = 10;

	/**
	 * Constructor.
	 *
	 * @param int                $limit              Clamped 1..10.
	 * @param int|null           $product_id          Positive ID or null.
	 * @param string             $page_context       product|unknown.
	 * @param array<int, string> $exclude_public_ids Valid UUIDv4.
	 */
	public function __construct(
		public readonly int $limit,
		public readonly ?int $product_id,
		public readonly string $page_context,
		public readonly array $exclude_public_ids
	) {}

	/**
	 * Whether PDP preferred selection should run.
	 */
	public function is_pdp(): bool {
		return self::CONTEXT_PRODUCT === $this->page_context && null !== $this->product_id && $this->product_id > 0;
	}

	/**
	 * Clamp a numeric limit into 1..10.
	 *
	 * @param int $limit Raw numeric limit.
	 */
	public static function clamp_limit( int $limit ): int {
		return max( self::LIMIT_MIN, min( self::LIMIT_MAX, $limit ) );
	}

	/**
	 * Normalize page_context; unknown values become unknown.
	 *
	 * @param mixed $value Raw page_context.
	 */
	public static function normalize_page_context( mixed $value ): string {
		if ( is_string( $value ) && self::CONTEXT_PRODUCT === $value ) {
			return self::CONTEXT_PRODUCT;
		}
		return self::CONTEXT_UNKNOWN;
	}
}
