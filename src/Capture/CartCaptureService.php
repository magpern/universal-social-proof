<?php
/**
 * Genuine WooCommerce add-to-cart capture.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Capture;

use Throwable;
use UniversalSocialProof\Geo\UgcGeoContextAdapter;
use UniversalSocialProof\Logger;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Storage\EventRepository;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Quantity;

defined( 'ABSPATH' ) || exit;

/**
 * Captures successful WC add-to-cart events when cart source is enabled.
 */
final class CartCaptureService {

	/**
	 * Request-local keys already captured this request.
	 *
	 * @var array<string, true>
	 */
	private static array $seen_keys = array();

	/**
	 * Register the add-to-cart hook.
	 */
	public static function register(): void {
		add_action( 'woocommerce_add_to_cart', array( self::class, 'on_add_to_cart' ), 20, 6 );
	}

	/**
	 * Capture after WooCommerce accepts an add-to-cart.
	 *
	 * @param string $cart_item_key   Cart item key.
	 * @param int    $product_id      Product ID.
	 * @param int    $quantity        Quantity added.
	 * @param int    $variation_id    Variation ID.
	 * @param array  $variation       Variation attributes.
	 * @param array  $cart_item_data  Cart item data.
	 */
	public static function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id = 0, $variation = array(), $cart_item_data = array() ): void {
		unset( $cart_item_key, $variation, $cart_item_data );
		try {
			if ( ! SettingsRepository::cart_enabled() ) {
				return;
			}
			if ( ! Migrator::tables_exist() && ! Migrator::upgrade_now() ) {
				return;
			}

			$product_id   = (int) $product_id;
			$variation_id = (int) $variation_id;
			if ( $product_id <= 0 ) {
				return;
			}
			if ( ! Quantity::is_positive( $quantity ) ) {
				return;
			}

			$dedupe_key = $product_id . ':' . $variation_id . ':' . Quantity::format( $quantity );
			if ( isset( self::$seen_keys[ $dedupe_key ] ) ) {
				return;
			}
			self::$seen_keys[ $dedupe_key ] = true;

			$now     = gmdate( 'Y-m-d H:i:s' );
			$payload = array(
				'public_id'    => wp_generate_uuid4(),
				'product_id'   => $product_id,
				'variation_id' => $variation_id > 0 ? $variation_id : null,
				'quantity'     => Quantity::format( $quantity ),
				'country_code' => self::visitor_country(),
				'occurred_at'  => $now,
				'captured_at'  => $now,
				'updated_at'   => $now,
			);

			$row = EventRepository::insert_cart_event( $payload );
			if ( null === $row ) {
				$payload['public_id'] = wp_generate_uuid4();
				$row                  = EventRepository::insert_cart_event( $payload );
			}
			if ( null === $row ) {
				Logger::error( 'cart event insert failed', array( 'code' => 'cart_insert' ) );
			}
		} catch ( Throwable $e ) {
			Logger::error( 'on_add_to_cart failed', array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Optional UGC country at add time. Never persists visitor identity beyond the event row.
	 */
	private static function visitor_country(): ?string {
		$adapter = new UgcGeoContextAdapter();
		if ( ! $adapter->is_available() ) {
			return null;
		}
		return $adapter->country_code();
	}

	/**
	 * Test seam: clear request-local dedupe.
	 */
	public static function reset_for_tests(): void {
		self::$seen_keys = array();
	}
}
