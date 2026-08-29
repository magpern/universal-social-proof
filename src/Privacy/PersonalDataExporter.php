<?php
/**
 * WordPress personal-data exporter.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Privacy;

use UniversalSocialProof\Storage\EventRepository;
use UniversalSocialProof\Storage\Migrator;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal privacy export — no internal provenance IDs.
 */
final class PersonalDataExporter {

	public const EXPORTER_ID = 'universal-social-proof-events';

	/**
	 * Register the WordPress personal-data exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register( array $exporters ): array {
		$exporters[ self::EXPORTER_ID ] = array(
			'exporter_friendly_name' => __( 'Universal Social Proof', 'universal-social-proof' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Export USP events for a privacy request email.
	 *
	 * @param string $email_address Request email.
	 * @param int    $page          Page (1-based).
	 * @return array{data: list<array<string, mixed>>, done: bool}
	 */
	public static function export( string $email_address, int $page ): array {
		$page = max( 1, $page );
		$out  = array(
			'data' => array(),
			'done' => true,
		);

		if ( ! Migrator::tables_exist() ) {
			return $out;
		}

		$orders = self::orders_for_email( $email_address, $page );
		if ( array() === $orders ) {
			return $out;
		}

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			foreach ( EventRepository::find_by_order( (int) $order->get_id() ) as $row ) {
				$out['data'][] = array(
					'group_id'          => 'usp-events',
					'group_label'       => __( 'Purchase social-proof records', 'universal-social-proof' ),
					'group_description' => __( 'Marketing social-proof records derived from WooCommerce purchases.', 'universal-social-proof' ),
					'item_id'           => 'usp-event-' . (string) ( $row['public_id'] ?? '' ),
					'data'              => array(
						array(
							'name'  => __( 'Occurrence time (UTC)', 'universal-social-proof' ),
							'value' => (string) ( $row['occurred_at'] ?? '' ),
						),
						array(
							'name'  => __( 'Country', 'universal-social-proof' ),
							'value' => (string) ( $row['country_code'] ?? '' ),
						),
						array(
							'name'  => __( 'Original quantity', 'universal-social-proof' ),
							'value' => (string) ( $row['quantity'] ?? '' ),
						),
						array(
							'name'  => __( 'Public event ID', 'universal-social-proof' ),
							'value' => (string) ( $row['public_id'] ?? '' ),
						),
					),
				);
			}
		}

		$out['done'] = count( $orders ) < 10;
		return $out;
	}

	/**
	 * Look up WooCommerce orders for a privacy email (HPOS-safe).
	 *
	 * @param string $email_address Request email.
	 * @param int    $page          Page (1-based).
	 * @return list<WC_Order>
	 */
	public static function orders_for_email( string $email_address, int $page ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$query = array(
			'limit'    => 10,
			'page'     => $page,
			'customer' => array( $email_address ),
			'return'   => 'objects',
		);

		$user = get_user_by( 'email', $email_address );
		if ( $user instanceof \WP_User ) {
			$query['customer'][] = (int) $user->ID;
		}

		$orders = wc_get_orders( $query );
		return is_array( $orders ) ? $orders : array();
	}
}
