<?php
/**
 * Bounded candidate query (no raw SQL fragments).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

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
	 */
	private function __construct(
		private string $cutoff_utc,
		private array $exclude_public_ids,
		private ?int $product_id,
		private int $limit
	) {}

	/**
	 * Global recency window (max 80).
	 *
	 * @param string             $cutoff_utc         UTC MySQL datetime.
	 * @param array<int, string> $exclude_public_ids Validated UUIDs.
	 */
	public static function global( string $cutoff_utc, array $exclude_public_ids ): self {
		return new self( $cutoff_utc, self::normalize_exclude( $exclude_public_ids ), null, self::GLOBAL_LIMIT );
	}

	/**
	 * PDP preferred window (max 20) for a stored parent product_id.
	 *
	 * @param string             $cutoff_utc         UTC MySQL datetime.
	 * @param array<int, string> $exclude_public_ids Validated UUIDs.
	 * @param int                $product_id          Preferred parent product ID.
	 */
	public static function preferred( string $cutoff_utc, array $exclude_public_ids, int $product_id ): self {
		$product_id = max( 1, $product_id );
		return new self( $cutoff_utc, self::normalize_exclude( $exclude_public_ids ), $product_id, self::PREFERRED_LIMIT );
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
