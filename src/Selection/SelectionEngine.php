<?php
/**
 * Bounded selection engine: preferred/global pools with optional geo tiers (M5/v1.1).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

use UniversalSocialProof\Cleanup\CartRetentionSettings;
use UniversalSocialProof\Cleanup\RetentionSettings;
use UniversalSocialProof\Product\PublicProduct;
use UniversalSocialProof\Product\PublicProductResolver;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Storage\EventType;
use UniversalSocialProof\Targeting\ProductTargetingPolicy;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side selection. Never mutates usp_events.
 *
 * V1.1 fills purchases first, then cart into remaining slots (separate cutoffs).
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
	 * Exposed reader for budget/SQL instrumentation in tests.
	 */
	public function reader(): CandidateReader {
		return $this->reader;
	}

	/**
	 * Exposed resolver for budget instrumentation in tests.
	 */
	public function resolver(): PublicProductResolver {
		return $this->resolver;
	}

	/**
	 * Select up to K public-eligible events (purchases then cart).
	 *
	 * @param SelectionRequest $request Validated request.
	 * @return array Selected events.
	 */
	public function select( SelectionRequest $request ): array {
		$selected     = array();
		$selected_ids = array();
		$attempted    = array();

		if ( SettingsRepository::purchase_enabled() ) {
			$selected = $this->select_for_type(
				$request,
				EventType::PURCHASE,
				RetentionSettings::cutoff_utc(),
				$selected,
				$selected_ids,
				$attempted
			);
		}

		if ( SettingsRepository::cart_enabled() && count( $selected ) < $request->limit ) {
			$selected = $this->select_for_type(
				$request,
				EventType::ADD_TO_CART,
				CartRetentionSettings::cutoff_utc(),
				$selected,
				$selected_ids,
				$attempted
			);
		}

		return $selected;
	}

	/**
	 * Run M2/M5 selection for one event type into remaining slots.
	 *
	 * @param SelectionRequest $request      Request.
	 * @param string           $event_type   Event type.
	 * @param string           $cutoff       Cutoff UTC.
	 * @param array            $selected     Selected so far.
	 * @param array            $selected_ids Selected public IDs.
	 * @param array            $attempted    Attempted public IDs.
	 * @return array Selected events.
	 */
	private function select_for_type(
		SelectionRequest $request,
		string $event_type,
		string $cutoff,
		array $selected,
		array &$selected_ids,
		array &$attempted
	): array {
		if ( $request->has_geo() ) {
			return $this->select_with_geo( $request, $event_type, $cutoff, $selected, $selected_ids, $attempted );
		}
		return $this->select_m2( $request, $event_type, $cutoff, $selected, $selected_ids, $attempted );
	}

	/**
	 * Exact M2 path (no country SQL).
	 *
	 * @param SelectionRequest $request      Request without visitor country.
	 * @param string           $event_type   Event type.
	 * @param string           $cutoff       Cutoff UTC.
	 * @param array            $selected     Selected so far.
	 * @param array            $selected_ids Selected public IDs.
	 * @param array            $attempted    Attempted public IDs.
	 * @return array Selected events.
	 */
	private function select_m2(
		SelectionRequest $request,
		string $event_type,
		string $cutoff,
		array $selected,
		array &$selected_ids,
		array &$attempted
	): array {
		$exclude = $request->exclude_public_ids;
		$limit   = $request->limit;

		$preferred_parent    = null;
		$preferred_variation = null;
		$this->resolve_preferred_ids( $request, $preferred_parent, $preferred_variation );

		$preferred = array();
		if ( null !== $preferred_parent ) {
			$preferred = $this->reader->find_recent_active(
				CandidateQuery::preferred( $cutoff, $exclude, $preferred_parent, $event_type )
			);
		}

		$global = $this->reader->find_recent_active(
			CandidateQuery::global( $cutoff, $exclude, $event_type )
		);

		$preferred = ( $this->shuffle )( $preferred );
		$global    = ( $this->shuffle )( $global );

		if ( null !== $preferred_variation ) {
			$preferred = $this->order_preferred_for_variation( $preferred, $preferred_variation );
		}

		if ( null !== $preferred_parent && count( $selected ) < $limit ) {
			$this->resolver->budget()->begin_additional_cap( ProductResolutionBudget::PDP_SEARCH_CAP );
			try {
				$selected = $this->seek_one_preferred( $preferred, $selected, $selected_ids, $attempted );
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
	 * Tiered geography preference (M5).
	 *
	 * @param SelectionRequest $request      Request with visitor country.
	 * @param string           $event_type   Event type.
	 * @param string           $cutoff       Cutoff UTC.
	 * @param array            $selected     Selected so far.
	 * @param array            $selected_ids Selected public IDs.
	 * @param array            $attempted    Attempted public IDs.
	 * @return array Selected events.
	 */
	private function select_with_geo(
		SelectionRequest $request,
		string $event_type,
		string $cutoff,
		array $selected,
		array &$selected_ids,
		array &$attempted
	): array {
		$exclude = $request->exclude_public_ids;
		$visitor = (string) $request->visitor_country;
		$limit   = $request->limit;

		$preferred_parent    = null;
		$preferred_variation = null;
		$this->resolve_preferred_ids( $request, $preferred_parent, $preferred_variation );

		if ( null !== $preferred_parent ) {
			return $this->select_pdp_geo(
				$cutoff,
				$exclude,
				$visitor,
				$limit,
				$preferred_parent,
				$preferred_variation,
				$event_type,
				$selected,
				$selected_ids,
				$attempted
			);
		}

		$country_pool = ( $this->shuffle )(
			$this->reader->find_recent_active(
				CandidateQuery::country( $cutoff, $exclude, $visitor, $event_type )
			)
		);
		$selected     = $this->fill_from_pool( $country_pool, $selected, $selected_ids, $attempted, $limit );

		if ( count( $selected ) < $limit ) {
			$global   = ( $this->shuffle )(
				$this->reader->find_recent_active(
					CandidateQuery::global( $cutoff, $exclude, $event_type )
				)
			);
			$selected = $this->fill_from_pool( $global, $selected, $selected_ids, $attempted, $limit );
		}

		return $selected;
	}

	/**
	 * PDP + visitor country: shared PDP_SEARCH_CAP across Tier1+Tier2.
	 *
	 * @param string   $cutoff               Cutoff UTC.
	 * @param array    $exclude              Exclusions.
	 * @param string   $visitor              Visitor country.
	 * @param int      $limit                K.
	 * @param int      $preferred_parent     Parent product ID.
	 * @param int|null $preferred_variation  Variation ID when request is a variation.
	 * @param string   $event_type           Event type.
	 * @param array    $selected             Selected so far.
	 * @param array    $selected_ids         Selected public IDs.
	 * @param array    $attempted            Attempted public IDs.
	 * @return array Selected events.
	 */
	private function select_pdp_geo(
		string $cutoff,
		array $exclude,
		string $visitor,
		int $limit,
		int $preferred_parent,
		?int $preferred_variation,
		string $event_type,
		array $selected,
		array &$selected_ids,
		array &$attempted
	): array {
		$preferred_country = array();
		$preferred_any     = array();

		$this->resolver->budget()->begin_additional_cap( ProductResolutionBudget::PDP_SEARCH_CAP );
		try {
			$preferred_country = ( $this->shuffle )(
				$this->reader->find_recent_active(
					CandidateQuery::preferred_country( $cutoff, $exclude, $preferred_parent, $visitor, $event_type )
				)
			);
			if ( null !== $preferred_variation ) {
				$preferred_country = $this->order_preferred_for_variation( $preferred_country, $preferred_variation );
			}

			$before_preferred = count( $selected );
			$selected         = $this->seek_one_preferred( $preferred_country, $selected, $selected_ids, $attempted );

			if ( count( $selected ) === $before_preferred ) {
				$preferred_any = ( $this->shuffle )(
					$this->reader->find_recent_active(
						CandidateQuery::preferred( $cutoff, $exclude, $preferred_parent, $event_type )
					)
				);
				if ( null !== $preferred_variation ) {
					$preferred_any = $this->order_preferred_for_variation( $preferred_any, $preferred_variation );
				}
				$selected = $this->seek_one_preferred( $preferred_any, $selected, $selected_ids, $attempted );
			}
		} finally {
			$this->resolver->budget()->end_additional_cap();
		}

		if ( count( $selected ) < $limit ) {
			$country_global = ( $this->shuffle )(
				$this->reader->find_recent_active(
					CandidateQuery::country( $cutoff, $exclude, $visitor, $event_type )
				)
			);
			$selected       = $this->fill_from_pool( $country_global, $selected, $selected_ids, $attempted, $limit );
		}

		if ( count( $selected ) < $limit ) {
			$global   = ( $this->shuffle )(
				$this->reader->find_recent_active(
					CandidateQuery::global( $cutoff, $exclude, $event_type )
				)
			);
			$selected = $this->fill_from_pool( $global, $selected, $selected_ids, $attempted, $limit );
		}

		if ( count( $selected ) < $limit ) {
			$selected = $this->fill_from_pool( $preferred_country, $selected, $selected_ids, $attempted, $limit );
		}
		if ( count( $selected ) < $limit && array() !== $preferred_any ) {
			$selected = $this->fill_from_pool( $preferred_any, $selected, $selected_ids, $attempted, $limit );
		}

		return $selected;
	}

	/**
	 * Resolve preferred parent / variation IDs for a PDP request.
	 *
	 * @param SelectionRequest $request              Request.
	 * @param int|null         $preferred_parent     Out parent.
	 * @param int|null         $preferred_variation  Out variation.
	 */
	private function resolve_preferred_ids( SelectionRequest $request, ?int &$preferred_parent, ?int &$preferred_variation ): void {
		$preferred_parent    = null;
		$preferred_variation = null;

		if ( ! $request->is_pdp() ) {
			return;
		}

		$req = $this->resolver->get_product( (int) $request->product_id );
		if ( ! $req instanceof WC_Product ) {
			return;
		}

		if ( $req->is_type( 'variation' ) ) {
			$parent = (int) $req->get_parent_id();
			if ( $parent > 0 ) {
				$preferred_parent    = $parent;
				$preferred_variation = (int) $req->get_id();
			}
			return;
		}

		$preferred_parent = (int) $req->get_id();
	}

	/**
	 * Seek at most one preferred accepted event under the current budget cap.
	 *
	 * @param array $preferred    Preferred pool.
	 * @param array $selected     Selected events.
	 * @param array $selected_ids Selected public IDs.
	 * @param array $attempted    Attempted IDs.
	 * @return array Selected events.
	 */
	private function seek_one_preferred( array $preferred, array $selected, array &$selected_ids, array &$attempted ): array {
		$before = count( $selected );
		foreach ( $preferred as $candidate ) {
			if ( count( $selected ) >= $before + 1 ) {
				break;
			}
			if ( isset( $selected_ids[ $candidate->public_id ] ) || isset( $attempted[ $candidate->public_id ] ) ) {
				continue;
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
