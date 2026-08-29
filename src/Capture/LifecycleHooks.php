<?php
/**
 * WooCommerce lifecycle hooks for capture and suppression.
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
 * Registers M1 capture and terminal-suppression seams.
 */
final class LifecycleHooks {

	/**
	 * Register all M1 WooCommerce hooks.
	 */
	public static function register(): void {
		add_action( 'woocommerce_order_status_changed', array( self::class, 'on_status_changed' ), 20, 4 );
		add_action( 'woocommerce_order_status_cancelled', array( self::class, 'on_cancelled' ), 20, 1 );
		add_action( 'woocommerce_order_status_failed', array( self::class, 'on_failed' ), 20, 1 );
		add_action( 'woocommerce_order_refunded', array( self::class, 'on_refund' ), 20, 2 );
		add_action( 'woocommerce_order_partially_refunded', array( self::class, 'on_refund' ), 20, 2 );
		add_action( 'woocommerce_order_fully_refunded', array( self::class, 'on_refund' ), 20, 2 );
		add_action( 'woocommerce_before_delete_order_item', array( self::class, 'on_before_delete_item' ), 20, 1 );
		add_action( 'woocommerce_before_trash_order', array( self::class, 'on_before_order_gone' ), 20, 2 );
		add_action( 'woocommerce_before_delete_order', array( self::class, 'on_before_order_gone' ), 20, 2 );
	}

	/**
	 * Capture when destination status is processing or completed.
	 *
	 * @param mixed $order_id Order ID.
	 * @param mixed $from     From status.
	 * @param mixed $to       To status.
	 * @param mixed $order    Order object.
	 */
	public static function on_status_changed( $order_id, $from, $to, $order = null ): void {
		try {
			$to = (string) $to;
			if ( ! in_array( $to, array( 'processing', 'completed' ), true ) ) {
				return;
			}
			if ( (string) $from === $to ) {
				return;
			}
			$wc_order = $order instanceof WC_Order ? $order : wc_get_order( (int) $order_id );
			if ( ! $wc_order instanceof WC_Order ) {
				return;
			}
			CaptureService::capture_order( $wc_order );
		} catch ( Throwable $e ) {
			Logger::error( 'on_status_changed failed', array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Suppress on cancelled.
	 *
	 * @param mixed $order_id Order ID.
	 */
	public static function on_cancelled( $order_id ): void {
		self::suppress_order_safe( (int) $order_id, EventStatus::REASON_CANCELLED );
	}

	/**
	 * Suppress on failed.
	 *
	 * @param mixed $order_id Order ID.
	 */
	public static function on_failed( $order_id ): void {
		self::suppress_order_safe( (int) $order_id, EventStatus::REASON_FAILED );
	}

	/**
	 * Evaluate cumulative full-line refunds.
	 *
	 * @param mixed $order_id  Order ID.
	 * @param mixed $refund_id Refund ID (unused).
	 */
	public static function on_refund( $order_id, $refund_id = 0 ): void {
		unset( $refund_id );
		try {
			if ( ! Migrator::tables_exist() ) {
				return;
			}
			$order = wc_get_order( (int) $order_id );
			if ( ! $order instanceof WC_Order ) {
				return;
			}
			foreach ( EventRepository::find_by_order( (int) $order_id ) as $row ) {
				$item_id  = (int) $row['source_item_id'];
				$item     = $order->get_item( $item_id );
				$ordered  = $item instanceof WC_Order_Item_Product
					? $item->get_quantity()
					: ( $row['quantity'] ?? 0 );
				$refunded = abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
				if ( Quantity::is_fully_refunded( $ordered, $refunded ) ) {
					EventRepository::suppress_item( (int) $order_id, $item_id, EventStatus::REASON_REFUND_FULL );
				}
			}
		} catch ( Throwable $e ) {
			Logger::error( 'on_refund failed', array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Suppress before order item deletion.
	 *
	 * @param mixed $item_id Order item ID.
	 */
	public static function on_before_delete_item( $item_id ): void {
		try {
			if ( ! Migrator::tables_exist() ) {
				return;
			}
			$item_id  = (int) $item_id;
			$order_id = (int) wc_get_order_id_by_order_item_id( $item_id );
			if ( $order_id <= 0 || $item_id <= 0 ) {
				return;
			}
			EventRepository::suppress_item( $order_id, $item_id, EventStatus::REASON_LINE_REMOVED );
		} catch ( Throwable $e ) {
			Logger::error( 'on_before_delete_item failed', array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Suppress before trash or hard delete of an order.
	 *
	 * @param mixed $order_id Order ID.
	 * @param mixed $order    Order object.
	 */
	public static function on_before_order_gone( $order_id, $order = null ): void {
		unset( $order );
		self::suppress_order_safe( (int) $order_id, EventStatus::REASON_ORDER_DELETED );
	}

	/**
	 * Soft-failing order-level suppress.
	 *
	 * @param int    $order_id Source order ID.
	 * @param string $reason   Controlled suppress reason.
	 */
	private static function suppress_order_safe( int $order_id, string $reason ): void {
		try {
			if ( $order_id <= 0 || ! Migrator::tables_exist() ) {
				return;
			}
			EventRepository::suppress_order( $order_id, $reason );
		} catch ( Throwable $e ) {
			Logger::error(
				'suppress_order failed',
				array(
					'order_id' => $order_id,
					'error'    => $e->getMessage(),
				)
			);
		}
	}
}
