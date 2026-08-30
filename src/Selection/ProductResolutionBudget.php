<?php
/**
 * Hard cap on USP-initiated wc_get_product() calls per request.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

defined( 'ABSPATH' ) || exit;

/**
 * Request-local product-resolution budget.
 */
final class ProductResolutionBudget {

	public const MAX            = 20;
	public const PDP_SEARCH_CAP = 5;

	/**
	 * Consumed uncached loads.
	 *
	 * @var int
	 */
	private int $used = 0;

	/**
	 * Whether another USP-initiated load is allowed.
	 */
	public function can_consume(): bool {
		return $this->used < self::MAX;
	}

	/**
	 * Consume one uncached load. Returns false if exhausted.
	 */
	public function try_consume(): bool {
		if ( $this->used >= self::MAX ) {
			return false;
		}
		++$this->used;
		return true;
	}

	/**
	 * Uncached loads consumed so far.
	 */
	public function used(): int {
		return $this->used;
	}

	/**
	 * Remaining uncached loads before the hard cap.
	 */
	public function remaining(): int {
		return max( 0, self::MAX - $this->used );
	}
}
