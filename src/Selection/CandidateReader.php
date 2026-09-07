<?php
/**
 * Bounded active-event reader. Does not mutate usp_events.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Selection;

use UniversalSocialProof\Logger;
use UniversalSocialProof\Storage\EventStatus;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Indexed prefilter: status=active, occurred_at >= cutoff, LIMIT 80/20.
 */
final class CandidateReader {

	/**
	 * Candidate SQL executions this instance (request-local instrumentation).
	 *
	 * @var int
	 */
	private int $query_count = 0;

	/**
	 * Rows returned across find_recent_active calls this instance.
	 *
	 * @var int
	 */
	private int $rows_fetched = 0;

	/**
	 * Candidate SQL executions so far.
	 */
	public function query_count(): int {
		return $this->query_count;
	}

	/**
	 * Total candidate rows returned so far.
	 */
	public function rows_fetched(): int {
		return $this->rows_fetched;
	}

	/**
	 * Fetch recent active candidates. Never SELECT provenance columns.
	 *
	 * @param CandidateQuery $query Bounded query.
	 * @return array Candidate list.
	 */
	public function find_recent_active( CandidateQuery $query ): array {
		global $wpdb;

		if ( ! $this->ensure_table() ) {
			return array();
		}

		$prepared = $this->prepare_select_sql( $query );
		if ( null === $prepared ) {
			return array();
		}

		++$this->query_count;

		$wpdb->last_error = '';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$rows = $wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			Logger::error( 'candidate query failed' );
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$candidate = Candidate::from_row( $row );
			if ( $candidate instanceof Candidate ) {
				$out[] = $candidate;
			}
		}

		$this->rows_fetched += count( $out );

		return $out;
	}

	/**
	 * EXPLAIN the same query shape (integration evidence).
	 *
	 * @param CandidateQuery $query Bounded query.
	 * @return array EXPLAIN rows.
	 */
	public function explain_recent_active( CandidateQuery $query ): array {
		global $wpdb;

		$prepared = $this->prepare_select_sql( $query );
		if ( null === $prepared ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$plan = $wpdb->get_results( 'EXPLAIN ' . $prepared, ARRAY_A );
		return is_array( $plan ) ? $plan : array();
	}

	/**
	 * Build the prepared SELECT for a candidate query (no RAND).
	 *
	 * @param CandidateQuery $query Bounded query.
	 */
	private function prepare_select_sql( CandidateQuery $query ): ?string {
		global $wpdb;

		$table = Schema::events_table();
		$limit = $query->limit();
		if ( $query->is_preferred() ) {
			$limit = min( $limit, CandidateQuery::PREFERRED_LIMIT );
		} else {
			$limit = min( $limit, CandidateQuery::GLOBAL_LIMIT );
		}
		$limit = max( 1, $limit );

		$exclude = $query->exclude_public_ids();
		$args    = array( EventStatus::ACTIVE, $query->cutoff_utc() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name is Schema::events_table(); SQL is built then prepared.
		$sql = "SELECT public_id, event_type, product_id, variation_id, quantity, country_code, occurred_at
			FROM {$table}
			WHERE status = %s AND occurred_at >= %s";

		$product_id = $query->product_id();
		if ( null !== $product_id ) {
			$sql   .= ' AND product_id = %d';
			$args[] = $product_id;
		}

		if ( $query->has_event_type() ) {
			$sql   .= ' AND event_type = %s';
			$args[] = (string) $query->event_type();
		}

		if ( $query->has_country() ) {
			$sql   .= ' AND country_code = %s';
			$args[] = (string) $query->country_code();
		}

		if ( array() !== $exclude ) {
			$placeholders = implode( ',', array_fill( 0, count( $exclude ), '%s' ) );
			$sql         .= " AND public_id NOT IN ({$placeholders})";
			foreach ( $exclude as $id ) {
				$args[] = $id;
			}
		}

		$sql   .= ' ORDER BY occurred_at DESC LIMIT %d';
		$args[] = $limit;

		$prepared = $wpdb->prepare( $sql, ...$args );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_string( $prepared ) || false !== stripos( $prepared, 'RAND(' ) ) {
			Logger::error( 'candidate query prepare failed' );
			return null;
		}

		return $prepared;
	}

	/**
	 * Attempt the existing M1 migration path once if the table is missing.
	 */
	private function ensure_table(): bool {
		if ( Migrator::tables_exist() ) {
			return true;
		}
		Migrator::maybe_upgrade_controlled();
		if ( Migrator::tables_exist() ) {
			return true;
		}
		Logger::error( 'candidate table unavailable' );
		return false;
	}
}
