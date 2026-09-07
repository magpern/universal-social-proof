<?php
/**
 * Bounded candidate query (no raw SQL fragments).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

use UniversalSocialProof\Storage\EventType;

defined( 'ABSPATH' ) || exit;

/**
 * Parameters for CandidateReader. Limits cannot exceed frozen caps.
 */
final class CandidateQuery {

	public const GLOBAL_LIMIT    = 80;
	public const PREFERRED_LIMIT = 20;
	public const EXCLUDE_MAX     = 20;

	/**
	 * Constructor.
	 *
	 * @param string             $cutoff_utc         UTC MySQL datetime.
	 * @param array<int, string> $exclude_public_ids Validated UUIDv4 list.
	 * @param int|null           $product_id          Preferred parent product ID.
	 * @param int                $limit               Row cap.
	 * @param string|null        $country_code        Purchase-country filter (ISO-2).
	 * @param string|null        $event_type          Optional event_type filter.
	 */
	private function __construct(
		private string $cutoff_utc,
		private array $exclude_public_ids,
		private ?int $product_id,
		private int $limit,
		private ?string $country_code = null,
		private ?string $event_type = null
	) {}

	/**
	 * Global recency window (max 80).
	 *
	 * @param string             $cutoff_utc         UTC MySQL datetime.
	 * @param array<int, string> $exclude_public_ids Validated UUIDs.
	 * @param string|null        $event_type         Optional event type.
	 */
	public static function global( string $cutoff_utc, array $exclude_public_ids, ?string $event_type = null ): self {
		return new self( $cutoff_utc, self::normalize_exclude( $exclude_public_ids ), null, self::GLOBAL_LIMIT, null, self::normalize_event_type( $event_type ) );
	}

	/**
	 * PDP preferred window (max 20) for a stored parent product_id.
	 *
	 * @param string             $cutoff_utc         UTC MySQL datetime.
	 * @param array<int, string> $exclude_public_ids Validated UUIDs.
	 * @param int                $product_id          Preferred parent product ID.
	 * @param string|null        $event_type         Optional event type.
	 */
	public static function preferred( string $cutoff_utc, array $exclude_public_ids, int $product_id, ?string $event_type = null ): self {
		$product_id = max( 1, $product_id );
		return new self( $cutoff_utc, self::normalize_exclude( $exclude_public_ids ), $product_id, self::PREFERRED_LIMIT, null, self::normalize_event_type( $event_type ) );
	}

	/**
	 * Country-only recency window (max 80).
	 *
	 * @param string             $cutoff_utc         UTC MySQL datetime.
	 * @param array<int, string> $exclude_public_ids Validated UUIDs.
	 * @param string             $country_code       ISO-2 purchase country.
	 * @param string|null        $event_type         Optional event type.
	 */
	public static function country( string $cutoff_utc, array $exclude_public_ids, string $country_code, ?string $event_type = null ): self {
		return new self(
			$cutoff_utc,
			self::normalize_exclude( $exclude_public_ids ),
			null,
			self::GLOBAL_LIMIT,
			self::normalize_country( $country_code ),
			self::normalize_event_type( $event_type )
		);
	}

	/**
	 * Preferred product restricted to a purchase country (max 20).
	 *
	 * @param string             $cutoff_utc         UTC MySQL datetime.
	 * @param array<int, string> $exclude_public_ids Validated UUIDs.
	 * @param int                $product_id          Preferred parent product ID.
	 * @param string             $country_code       ISO-2 purchase country.
	 * @param string|null        $event_type         Optional event type.
	 */
	public static function preferred_country( string $cutoff_utc, array $exclude_public_ids, int $product_id, string $country_code, ?string $event_type = null ): self {
		$product_id = max( 1, $product_id );
		return new self(
			$cutoff_utc,
			self::normalize_exclude( $exclude_public_ids ),
			$product_id,
			self::PREFERRED_LIMIT,
			self::normalize_country( $country_code ),
			self::normalize_event_type( $event_type )
		);
	}

	/**
	 * Deduplicate and cap exclusion UUIDs.
	 *
	 * @param array<int, string> $ids IDs.
	 * @return array<int, string>
	 */
	private static function normalize_exclude( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			if ( is_string( $id ) && '' !== $id ) {
				$out[] = $id;
			}
		}
		$out = array_values( array_unique( $out ) );
		if ( count( $out ) > self::EXCLUDE_MAX ) {
			$out = array_slice( $out, 0, self::EXCLUDE_MAX );
		}
		return $out;
	}

	/**
	 * Defensive ISO-2 normalize; invalid → null (query without country).
	 *
	 * @param string $country_code Raw.
	 */
	private static function normalize_country( string $country_code ): ?string {
		$code = strtoupper( trim( $country_code ) );
		return ( 1 === preg_match( '/^[A-Z]{2}$/', $code ) ) ? $code : null;
	}

	/**
	 * Normalize optional event type filter.
	 *
	 * @param string|null $event_type Raw.
	 */
	private static function normalize_event_type( ?string $event_type ): ?string {
		if ( null === $event_type || '' === $event_type ) {
			return null;
		}
		return EventType::is_valid( $event_type ) ? $event_type : null;
	}

	/**
	 * UTC MySQL cutoff datetime.
	 */
	public function cutoff_utc(): string {
		return $this->cutoff_utc;
	}

	/**
	 * Exclusion UUIDs.
	 *
	 * @return array<int, string>
	 */
	public function exclude_public_ids(): array {
		return $this->exclude_public_ids;
	}

	/**
	 * Preferred parent product ID, or null for the global pool.
	 */
	public function product_id(): ?int {
		return $this->product_id;
	}

	/**
	 * Purchase-country filter, or null.
	 */
	public function country_code(): ?string {
		return $this->country_code;
	}

	/**
	 * Whether this query filters by purchase country.
	 */
	public function has_country(): bool {
		return null !== $this->country_code;
	}

	/**
	 * Event type filter, or null.
	 */
	public function event_type(): ?string {
		return $this->event_type;
	}

	/**
	 * Whether this query filters by event type.
	 */
	public function has_event_type(): bool {
		return null !== $this->event_type;
	}

	/**
	 * SQL LIMIT for this query.
	 */
	public function limit(): int {
		return $this->limit;
	}

	/**
	 * Whether this is the PDP preferred pool query.
	 */
	public function is_preferred(): bool {
		return null !== $this->product_id;
	}
}
