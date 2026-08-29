<?php
/**
 * Genuine WooCommerce purchase capture.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Capture;

use Throwable;
use UniversalSocialProof\Logger;
use UniversalSocialProof\Storage\EventRepository;
use UniversalSocialProof\Storage\EventStatus;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Quantity;
use WC_Order;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Captures one event per product line with post-insert terminal reconciliation.
 */
final class CaptureService {

	/**
	 * Capture all product lines from a WooCommerce order.
	 *
	 * Safe to call from hooks and future reconciliation. Never throws to callers.
	 *
	 * @param WC_Order $order Source order.
	 */
	public static function capture_order( WC_Order $order ): void {
		try {
			if ( ! Migrator::tables_exist() && ! Migrator::upgrade_now() ) {
				return;
			}

			$order_id = (int) $order->get_id();
			if ( $order_id <= 0 ) {
				return;
			}

			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				self::capture_item( $order, $item );
			}
		} catch ( Throwable $e ) {
			Logger::error(
				'capture_order failed',
				array(
					'order_id' => (int) $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Capture a single product line with post-insert terminal reconciliation.
	 *
	 * @param WC_Order              $order Source order.
	 * @param WC_Order_Item_Product $item  Product line item.
	 */
	private static function capture_item( WC_Order $order, WC_Order_Item_Product $item ): void {
		$order_id = (int) $order->get_id();
		$item_id  = (int) $item->get_id();
		if ( $item_id <= 0 ) {
			return;
		}

		if ( null !== EventRepository::find_by_source( $order_id, $item_id ) ) {
			// Existing row: still reconcile terminal state (race convergence).
			$fresh = wc_get_order( $order_id );
			if ( $fresh instanceof WC_Order ) {
				$row    = EventRepository::find_by_source( $order_id, $item_id );
				$reason = TerminalState::reason_for_item( $fresh, $item_id, $row['quantity'] ?? null );
				if ( null !== $reason ) {
					EventRepository::suppress_item( $order_id, $item_id, $reason );
				}
			}
			return;
		}

		// Best-effort pre-check (not the race invariant).
		$pre = TerminalState::reason_for_item( $order, $item_id );
		if ( null !== $pre ) {
			return;
		}

		$occurred = OccurredAtResolver::resolve( $order );
		if ( null === $occurred ) {
			Logger::warning(
				'skipped capture: no authoritative occurred_at',
				array(
					'order_id' => $order_id,
					'item_id'  => $item_id,
				)
			);
			return;
		}

		$quantity = $item->get_quantity();
		if ( ! Quantity::is_positive( $quantity ) ) {
			return;
		}

		$variation_raw = (int) $item->get_variation_id();
		$now           = gmdate( 'Y-m-d H:i:s' );
		$payload       = array(
			'source_order_id' => $order_id,
			'source_item_id'  => $item_id,
			'public_id'       => self::generate_public_id(),
			'product_id'      => (int) $item->get_product_id(),
			'variation_id'    => $variation_raw > 0 ? $variation_raw : null,
			'quantity'        => Quantity::format( $quantity ),
			'country_code'    => CountryExtractor::extract( $order ),
			'occurred_at'     => OccurredAtResolver::to_mysql_utc( $occurred ),
			'captured_at'     => $now,
			'updated_at'      => $now,
		);

		$row = self::insert_with_uuid_retry( $payload );
		if ( null === $row ) {
			Logger::error(
				'event insert failed',
				array(
					'order_id' => $order_id,
					'item_id'  => $item_id,
				)
			);
			return;
		}

		// Required convergence: re-fetch canonical order and re-evaluate terminal state.
		$fresh = wc_get_order( $order_id );
		if ( ! $fresh instanceof WC_Order ) {
			EventRepository::suppress_item( $order_id, $item_id, EventStatus::REASON_ORDER_DELETED );
			return;
		}

		$reason = TerminalState::reason_for_item( $fresh, $item_id, $row['quantity'] ?? null );
		if ( null !== $reason ) {
			EventRepository::suppress_item( $order_id, $item_id, $reason );
		}
	}

	/**
	 * Insert with one UUID collision retry.
	 *
	 * @param array<string, mixed> $payload Insert payload.
	 * @return array<string, mixed>|null
	 */
	private static function insert_with_uuid_retry( array $payload ): ?array {
		$row = EventRepository::insert_event( $payload );
		if ( null !== $row ) {
			return $row;
		}
		$payload['public_id'] = self::generate_public_id();
		return EventRepository::insert_event( $payload );
	}

	/**
	 * Opaque UUIDv4 public identity.
	 */
	private static function generate_public_id(): string {
		return wp_generate_uuid4();
	}
}
