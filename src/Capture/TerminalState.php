<?php
/**
 * Shared terminal-state evaluation for capture/refund/suppress.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Capture;

use UniversalSocialProof\Storage\EventStatus;
use UniversalSocialProof\Storage\Quantity;
use WC_Order;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Determines whether a source order/item is currently terminal for USP.
 */
final class TerminalState {

	/**
	 * Order-level terminal reason (cancelled / failed).
	 *
	 * @param WC_Order $order Source order.
	 * @return string|null Suppress reason, or null if not terminal.
	 */
	public static function reason_for_order( WC_Order $order ): ?string {
		if ( $order->has_status( 'cancelled' ) ) {
			return EventStatus::REASON_CANCELLED;
		}
		if ( $order->has_status( 'failed' ) ) {
			return EventStatus::REASON_FAILED;
		}
		return null;
	}

	/**
	 * Line-level terminal reason including refunds and missing items.
	 *
	 * @param WC_Order              $order           Source order.
	 * @param int                   $item_id         Source item ID.
	 * @param float|int|string|null $stored_quantity Fallback when item missing.
	 * @return string|null Suppress reason for this line, or null.
	 */
	public static function reason_for_item( WC_Order $order, int $item_id, $stored_quantity = null ): ?string {
		$order_reason = self::reason_for_order( $order );
		if ( null !== $order_reason ) {
			return $order_reason;
		}

		$item = $order->get_item( $item_id );
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return EventStatus::REASON_LINE_REMOVED;
		}

		$ordered  = null !== $stored_quantity ? $stored_quantity : $item->get_quantity();
		$refunded = abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
		if ( Quantity::is_fully_refunded( $ordered, $refunded ) ) {
			return EventStatus::REASON_REFUND_FULL;
		}

		return null;
	}
}
