<?php
/**
 * WordPress personal-data eraser + Woo pre-anonymization hook.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Privacy;

use Throwable;
use UniversalSocialProof\Logger;
use UniversalSocialProof\Storage\EventRepository;
use UniversalSocialProof\Storage\Migrator;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Prospective dual-path erasure. Retrospective anonymized history is fail-closed.
 */
final class PersonalDataEraser {

	public const ERASER_ID = 'universal-social-proof-events';

	/**
	 * Register eraser early (priority 5) before WooCommerce default (10).
	 */
	public static function bootstrap(): void {
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register' ), 5 );
		add_action( 'woocommerce_privacy_before_remove_order_personal_data', array( self::class, 'on_before_anonymize' ), 10, 1 );
	}

	/**
	 * Register the WordPress personal-data eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register( array $erasers ): array {
		$erasers[ self::ERASER_ID ] = array(
			'eraser_friendly_name' => __( 'Universal Social Proof', 'universal-social-proof' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Erase USP events for a privacy request email.
	 *
	 * @param string $email_address Request email.
	 * @param int    $page          Page (1-based).
	 * @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool}
	 */
	public static function erase( string $email_address, int $page ): array {
		$page     = max( 1, $page );
		$response = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		if ( ! Migrator::tables_exist() ) {
			return $response;
		}

		$orders = PersonalDataExporter::orders_for_email( $email_address, $page );
		if ( array() === $orders ) {
			// Fail closed when no recoverable orders (including already-anonymized history).
			$response['done'] = true;
			return $response;
		}

		$ids = array();
		foreach ( $orders as $order ) {
			if ( $order instanceof WC_Order ) {
				$ids[] = (int) $order->get_id();
			}
		}

		$deleted = EventRepository::delete_by_order_ids( $ids );
		if ( $deleted > 0 ) {
			$response['items_removed'] = true;
			$response['messages'][]    = sprintf(
				/* translators: %d: number of rows removed */
				__( 'Removed %d Universal Social Proof purchase-event record(s).', 'universal-social-proof' ),
				$deleted
			);
		}

		$response['done'] = count( $orders ) < 10;
		return $response;
	}

	/**
	 * Path B: hard-delete while Woo still has the live order.
	 *
	 * @param mixed $order Order being anonymized.
	 */
	public static function on_before_anonymize( $order ): void {
		try {
			if ( ! $order instanceof WC_Order || ! Migrator::tables_exist() ) {
				return;
			}
			EventRepository::delete_by_order_ids( array( (int) $order->get_id() ) );
		} catch ( Throwable $e ) {
			Logger::error( 'privacy before_anonymize failed', array( 'error' => $e->getMessage() ) );
		}
	}
}
