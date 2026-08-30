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
	 * Optional additional ceiling (absolute used() value) for a nested phase.
	 *
	 * @var int|null
	 */
	private ?int $allowance_ceiling = null;

	/**
	 * Whether another USP-initiated load is allowed.
	 */
	public function can_consume(): bool {
		if ( $this->used >= self::MAX ) {
			return false;
		}
		if ( null !== $this->allowance_ceiling && $this->used >= $this->allowance_ceiling ) {
			return false;
		}
		return true;
	}

	/**
	 * Consume one uncached load. Returns false if exhausted.
	 */
	public function try_consume(): bool {
		if ( ! $this->can_consume() ) {
			return false;
		}
		++$this->used;
		return true;
	}

	/**
	 * Limit further uncached loads in the current phase (e.g. PDP search cap 5).
	 *
	 * Effective remaining is min(global remaining, $max_additional).
	 *
	 * @param int $max_additional Additional uncached loads allowed from now.
	 */
	public function begin_additional_cap( int $max_additional ): void {
		$max_additional          = max( 0, $max_additional );
		$this->allowance_ceiling = min( self::MAX, $this->used + $max_additional );
	}

	/**
	 * Clear the phase ceiling; global MAX still applies.
	 */
	public function end_additional_cap(): void {
		$this->allowance_ceiling = null;
	}

	/**
	 * Uncached loads consumed so far.
	 */
	public function used(): int {
		return $this->used;
	}

	/**
	 * Remaining uncached loads before the effective cap.
	 *
	 * During a phase this is min(global remaining, phase remaining).
	 */
	public function remaining(): int {
		$global = max( 0, self::MAX - $this->used );
		if ( null === $this->allowance_ceiling ) {
			return $global;
		}
		return min( $global, max( 0, $this->allowance_ceiling - $this->used ) );
	}
}
