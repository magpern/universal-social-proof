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
		$sql = "SELECT public_id, product_id, variation_id, quantity, country_code, occurred_at
			FROM {$table}
			WHERE status = %s AND occurred_at >= %s";

		$product_id = $query->product_id();
		if ( null !== $product_id ) {
			$sql   .= ' AND product_id = %d';
			$args[] = $product_id;
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
			return array();
		}

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

		$table = Schema::events_table();
		$limit = $query->is_preferred() ? CandidateQuery::PREFERRED_LIMIT : CandidateQuery::GLOBAL_LIMIT;
		$args  = array( EventStatus::ACTIVE, $query->cutoff_utc() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name is Schema::events_table(); SQL is built then prepared.
		$sql        = "SELECT public_id, product_id, variation_id, quantity, country_code, occurred_at
			FROM {$table}
			WHERE status = %s AND occurred_at >= %s";
		$product_id = $query->product_id();
		if ( null !== $product_id ) {
			$sql   .= ' AND product_id = %d';
			$args[] = $product_id;
		}
		$sql     .= ' ORDER BY occurred_at DESC LIMIT %d';
		$args[]   = $limit;
		$prepared = $wpdb->prepare( $sql, ...$args );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_string( $prepared ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$plan = $wpdb->get_results( 'EXPLAIN ' . $prepared, ARRAY_A );
		return is_array( $plan ) ? $plan : array();
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
