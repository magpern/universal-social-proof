<?php
/**
 * Persistence for usp_events.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Insert, find, suppress, erase, retention purge (purchase + cart).
 */
final class EventRepository {

	/**
	 * Find a purchase event by WooCommerce source order/item identity.
	 *
	 * @param int $order_id Source order ID.
	 * @param int $item_id  Source order item ID.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_source( int $order_id, int $item_id ): ?array {
		global $wpdb;
		$table = Schema::events_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal Schema::events_table().
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_type = %s AND source_order_id = %d AND source_item_id = %d LIMIT 1",
				EventType::PURCHASE,
				$order_id,
				$item_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a new purchase event. On duplicate key, return the existing row without mutation.
	 *
	 * @param array<string, mixed> $data Insert payload (source ids, public_id, product ids, quantity, country, timestamps).
	 * @return array<string, mixed>|null Row after insert or existing row; null on hard failure.
	 */
	public static function insert_event( array $data ): ?array {
		global $wpdb;

		$existing = self::find_by_source( (int) $data['source_order_id'], (int) $data['source_item_id'] );
		if ( null !== $existing ) {
			return $existing;
		}

		$table   = Schema::events_table();
		$payload = array(
			'event_type'      => EventType::PURCHASE,
			'source_order_id' => (int) $data['source_order_id'],
			'source_item_id'  => (int) $data['source_item_id'],
			'status'          => EventStatus::ACTIVE,
			'suppress_reason' => null,
			'public_id'       => (string) $data['public_id'],
			'product_id'      => (int) $data['product_id'],
			'variation_id'    => null === $data['variation_id'] ? null : (int) $data['variation_id'],
			'quantity'        => (string) $data['quantity'],
			'country_code'    => $data['country_code'],
			'occurred_at'     => (string) $data['occurred_at'],
			'captured_at'     => (string) $data['captured_at'],
			'updated_at'      => (string) $data['updated_at'],
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- intentional repository write.
		$ok = $wpdb->insert( $table, $payload );

		if ( false === $ok ) {
			// Race: another request inserted first (or UUID collision).
			return self::find_by_source( (int) $payload['source_order_id'], (int) $payload['source_item_id'] );
		}

		return self::find_by_source( (int) $payload['source_order_id'], (int) $payload['source_item_id'] );
	}

	/**
	 * Insert an add-to-cart event without order/item provenance.
	 *
	 * @param array<string, mixed> $data Insert payload (public_id, product ids, quantity, country, timestamps).
	 * @return array<string, mixed>|null Inserted row, or null on failure.
	 */
	public static function insert_cart_event( array $data ): ?array {
		global $wpdb;

		$table   = Schema::events_table();
		$payload = array(
			'event_type'      => EventType::ADD_TO_CART,
			'source_order_id' => null,
			'source_item_id'  => null,
			'status'          => EventStatus::ACTIVE,
			'suppress_reason' => null,
			'public_id'       => (string) $data['public_id'],
			'product_id'      => (int) $data['product_id'],
			'variation_id'    => null === ( $data['variation_id'] ?? null ) ? null : (int) $data['variation_id'],
			'quantity'        => (string) $data['quantity'],
			'country_code'    => $data['country_code'] ?? null,
			'occurred_at'     => (string) $data['occurred_at'],
			'captured_at'     => (string) $data['captured_at'],
			'updated_at'      => (string) $data['updated_at'],
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- intentional repository write.
		$ok = $wpdb->insert( $table, $payload );
		if ( false === $ok ) {
			return null;
		}

		$id = (int) $wpdb->insert_id;
		if ( $id <= 0 ) {
			return null;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Terminal suppress one item. Never reactivates.
	 *
	 * @param int    $order_id Source order ID.
	 * @param int    $item_id  Source item ID.
	 * @param string $reason   Controlled suppress reason.
	 */
	public static function suppress_item( int $order_id, int $item_id, string $reason ): bool {
		global $wpdb;
		$table  = Schema::events_table();
		$reason = substr( $reason, 0, 32 );
		$now    = gmdate( 'Y-m-d H:i:s' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$n = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, suppress_reason = %s, updated_at = %s
				WHERE event_type = %s AND source_order_id = %d AND source_item_id = %d AND status = %s",
				EventStatus::SUPPRESSED,
				$reason,
				$now,
				EventType::PURCHASE,
				$order_id,
				$item_id,
				EventStatus::ACTIVE
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false !== $n;
	}

	/**
	 * Terminal suppress all active purchase rows for an order.
	 *
	 * @param int    $order_id Source order ID.
	 * @param string $reason   Controlled suppress reason.
	 */
	public static function suppress_order( int $order_id, string $reason ): bool {
		global $wpdb;
		$table  = Schema::events_table();
		$reason = substr( $reason, 0, 32 );
		$now    = gmdate( 'Y-m-d H:i:s' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$n = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, suppress_reason = %s, updated_at = %s
				WHERE event_type = %s AND source_order_id = %d AND status = %s",
				EventStatus::SUPPRESSED,
				$reason,
				$now,
				EventType::PURCHASE,
				$order_id,
				EventStatus::ACTIVE
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false !== $n;
	}

	/**
	 * Hard-delete events for privacy erasure.
	 *
	 * @param array<int, int> $order_ids Source order IDs.
	 * @return int Rows deleted.
	 */
	public static function delete_by_order_ids( array $order_ids ): int {
		global $wpdb;
		$order_ids = array_values( array_unique( array_filter( array_map( 'intval', $order_ids ) ) ) );
		if ( array() === $order_ids ) {
			return 0;
		}
		$table        = Schema::events_table();
		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- dynamic IN list; table name internal.
		$sql = $wpdb->prepare(
			"DELETE FROM {$table} WHERE event_type = %s AND source_order_id IN ({$placeholders})",
			EventType::PURCHASE,
			...$order_ids
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$n = $wpdb->query( $sql );
		return false === $n ? 0 : (int) $n;
	}

	/**
	 * List purchase events for a source order.
	 *
	 * @param int $order_id Source order ID.
	 * @return list<array<string, mixed>>
	 */
	public static function find_by_order( int $order_id ): array {
		global $wpdb;
		$table = Schema::events_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_type = %s AND source_order_id = %d ORDER BY id ASC",
				EventType::PURCHASE,
				$order_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Purge events older than cutoff by occurred_at (any type).
	 *
	 * @param string $cutoff_utc UTC MySQL datetime.
	 * @param int    $limit      Max rows to delete.
	 * @return int Rows deleted.
	 */
	public static function delete_older_than( string $cutoff_utc, int $limit = 100 ): int {
		global $wpdb;
		$table = Schema::events_table();
		$limit = max( 1, min( 500, $limit ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$n = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE occurred_at < %s ORDER BY occurred_at ASC LIMIT %d",
				$cutoff_utc,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false === $n ? 0 : (int) $n;
	}

	/**
	 * Purge events of one type older than cutoff by occurred_at.
	 *
	 * @param string $type       Event type (purchase|add_to_cart).
	 * @param string $cutoff_utc UTC MySQL datetime.
	 * @param int    $limit      Max rows to delete.
	 * @return int Rows deleted.
	 */
	public static function delete_older_than_for_type( string $type, string $cutoff_utc, int $limit = 100 ): int {
		global $wpdb;
		if ( ! EventType::is_valid( $type ) ) {
			return 0;
		}
		$table = Schema::events_table();
		$limit = max( 1, min( 500, $limit ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$n = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE event_type = %s AND occurred_at < %s ORDER BY occurred_at ASC LIMIT %d",
				$type,
				$cutoff_utc,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false === $n ? 0 : (int) $n;
	}
}
