<?php
/**
 * Bounded selection engine: separate preferred/global pools.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

use UniversalSocialProof\Cleanup\RetentionSettings;
use UniversalSocialProof\Product\PublicProduct;
use UniversalSocialProof\Product\PublicProductResolver;
use UniversalSocialProof\Targeting\ProductTargetingPolicy;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side selection. Never mutates usp_events.
 */
final class SelectionEngine {

	/**
	 * Shuffle callback.
	 *
	 * @var callable
	 */
	private $shuffle;

	/**
	 * Constructor.
	 *
	 * @param CandidateReader       $reader   Bounded SQL reader.
	 * @param PublicProductResolver $resolver Product resolver.
	 * @param callable|null         $shuffle  Shuffle (list in, list out).
	 */
	public function __construct(
		private CandidateReader $reader,
		private PublicProductResolver $resolver,
		$shuffle = null
	) {
		$this->shuffle = $shuffle ?? array( self::class, 'default_shuffle' );
	}

	/**
	 * Default PHP shuffle.
	 *
	 * @param array $items Candidates.
	 * @return array Shuffled candidates.
	 */
	public static function default_shuffle( array $items ): array {
		$copy = array_values( $items );
		shuffle( $copy );
		return $copy;
	}

	/**
	 * Select up to K public-eligible events.
	 *
	 * @param SelectionRequest $request Validated request.
	 * @return array Selected events.
	 */
	public function select( SelectionRequest $request ): array {
		$cutoff  = RetentionSettings::cutoff_utc();
		$exclude = $request->exclude_public_ids;

		$preferred_parent    = null;
		$preferred_variation = null;

		if ( $request->is_pdp() ) {
			$req = $this->resolver->get_product( (int) $request->product_id );
			if ( $req instanceof WC_Product ) {
				if ( $req->is_type( 'variation' ) ) {
					$parent = (int) $req->get_parent_id();
					if ( $parent > 0 ) {
						$preferred_parent    = $parent;
						$preferred_variation = (int) $req->get_id();
					}
				} else {
					$preferred_parent = (int) $req->get_id();
				}
			}
		}

		$preferred = array();
		if ( null !== $preferred_parent ) {
			$preferred = $this->reader->find_recent_active(
				CandidateQuery::preferred( $cutoff, $exclude, $preferred_parent )
			);
		}

		$global = $this->reader->find_recent_active(
			CandidateQuery::global( $cutoff, $exclude )
		);

		$preferred = ( $this->shuffle )( $preferred );
		$global    = ( $this->shuffle )( $global );

		if ( null !== $preferred_variation ) {
			$preferred = $this->order_preferred_for_variation( $preferred, $preferred_variation );
		}

		$limit        = $request->limit;
		$selected     = array();
		$selected_ids = array();
		$attempted    = array();

		if ( null !== $preferred_parent ) {
			$this->resolver->budget()->begin_additional_cap( ProductResolutionBudget::PDP_SEARCH_CAP );
			try {
				foreach ( $preferred as $candidate ) {
					if ( count( $selected ) >= 1 ) {
						break;
					}
					$first = $this->candidate_first_id( $candidate );
					if ( ! $this->resolver->budget()->can_consume() && ! $this->resolver->is_cached( $first ) ) {
						break;
					}
					$event                              = $this->resolve_candidate( $candidate );
					$attempted[ $candidate->public_id ] = true;
					if ( $event instanceof SelectedEvent ) {
						$selected[]                            = $event;
						$selected_ids[ $candidate->public_id ] = true;
					}
				}
			} finally {
				$this->resolver->budget()->end_additional_cap();
			}
		}

		$selected = $this->fill_from_pool( $global, $selected, $selected_ids, $attempted, $limit );
		if ( count( $selected ) < $limit ) {
			$selected = $this->fill_from_pool( $preferred, $selected, $selected_ids, $attempted, $limit );
		}
		if ( count( $selected ) < $limit ) {
			$selected = $this->fill_from_pool( $global, $selected, $selected_ids, $attempted, $limit );
		}

		return $selected;
	}

	/**
	 * Variation request: matching variation_id first, then other parent events.
	 *
	 * @param array $preferred    Preferred pool (already shuffled).
	 * @param int   $variation_id Requested variation ID.
	 * @return array Ordered preferred pool.
	 */
	private function order_preferred_for_variation( array $preferred, int $variation_id ): array {
		$tier_a = array();
		$tier_b = array();
		foreach ( $preferred as $candidate ) {
			if ( $candidate->variation_id === $variation_id ) {
				$tier_a[] = $candidate;
			} else {
				$tier_b[] = $candidate;
			}
		}
		return array_merge( $tier_a, $tier_b );
	}

	/**
	 * First product ID this candidate would resolve (variation, else parent).
	 *
	 * @param Candidate $candidate Candidate.
	 */
	private function candidate_first_id( Candidate $candidate ): int {
		return ( null !== $candidate->variation_id && $candidate->variation_id > 0 )
			? $candidate->variation_id
			: $candidate->product_id;
	}

	/**
	 * Fill remaining result slots from a pool.
	 *
	 * @param array $pool         Pool.
	 * @param array $selected     Selected events.
	 * @param array $selected_ids Selected public IDs.
	 * @param array $attempted    Attempted IDs.
	 * @param int   $limit        K.
	 * @return array Selected events.
	 */
	private function fill_from_pool( array $pool, array $selected, array &$selected_ids, array &$attempted, int $limit ): array {
		foreach ( $pool as $candidate ) {
			if ( count( $selected ) >= $limit ) {
				break;
			}
			if ( isset( $selected_ids[ $candidate->public_id ] ) || isset( $attempted[ $candidate->public_id ] ) ) {
				continue;
			}
			$event                              = $this->resolve_candidate( $candidate );
			$attempted[ $candidate->public_id ] = true;
			if ( $event instanceof SelectedEvent ) {
				$selected[]                            = $event;
				$selected_ids[ $candidate->public_id ] = true;
			}
		}
		return $selected;
	}

	/**
	 * Resolve a candidate to a selected event, or skip it.
	 *
	 * @param Candidate $candidate Candidate.
	 */
	private function resolve_candidate( Candidate $candidate ): ?SelectedEvent {
		$product = $this->resolver->resolve_for_event( $candidate->product_id, $candidate->variation_id );
		if ( ! $product instanceof PublicProduct ) {
			return null;
		}
		if ( ProductTargetingPolicy::is_excluded( $product ) ) {
			return null;
		}
		$event = SelectedEvent::from_candidate( $candidate, $product );
		// Selection accepts on merchandising + targeting + parseable occurred_at.
		// Template rendering happens after select(); failures omit without refill.
		if ( null === $event->occurred_at_utc() ) {
			return null;
		}
		return $event;
	}
}
