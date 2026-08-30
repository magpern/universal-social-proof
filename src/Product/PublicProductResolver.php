<?php
/**
 * Current public product resolution with a hard load budget.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Product;

use Throwable;
use UniversalSocialProof\Logger;
use UniversalSocialProof\Selection\ProductResolutionBudget;
use UniversalSocialProof\Selection\StockExclusionSettings;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * USP-initiated wc_get_product() only; memoized per request.
 */
final class PublicProductResolver {

	/**
	 * Product loader.
	 *
	 * @var callable
	 */
	private $loader;

	/**
	 * Request-local product cache.
	 *
	 * @var array<int, WC_Product|false>
	 */
	private array $cache = array();

	/**
	 * Constructor.
	 *
	 * @param ProductResolutionBudget $budget Budget.
	 * @param callable|null           $loader Loader.
	 */
	public function __construct(
		private ProductResolutionBudget $budget,
		$loader = null
	) {
		$this->loader = $loader ?? array( self::class, 'default_loader' );
	}

	/**
	 * Default WooCommerce loader.
	 *
	 * @param int $id Product ID.
	 * @return WC_Product|false|null
	 */
	public static function default_loader( int $id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		return wc_get_product( $id );
	}

	/**
	 * Active product-resolution budget.
	 */
	public function budget(): ProductResolutionBudget {
		return $this->budget;
	}

	/**
	 * Whether this product ID was already loaded in this request.
	 *
	 * @param int $id Product ID.
	 */
	public function is_cached( int $id ): bool {
		return array_key_exists( $id, $this->cache );
	}

	/**
	 * Load a product counting uncached USP-initiated calls.
	 *
	 * @param int $id Product ID.
	 */
	public function get_product( int $id ): ?WC_Product {
		if ( $id <= 0 ) {
			return null;
		}
		if ( array_key_exists( $id, $this->cache ) ) {
			$hit = $this->cache[ $id ];
			return $hit instanceof WC_Product ? $hit : null;
		}
		if ( ! $this->budget->try_consume() ) {
			return null;
		}
		try {
			$loaded = ( $this->loader )( $id );
		} catch ( Throwable $e ) {
			Logger::warning(
				'product load failed',
				array(
					'error' => $e->getMessage(),
				)
			);
			$this->cache[ $id ] = false;
			return null;
		}
		if ( $loaded instanceof WC_Product ) {
			$this->cache[ $id ] = $loaded;
			return $loaded;
		}
		$this->cache[ $id ] = false;
		return null;
	}

	/**
	 * Resolve presentation for a stored event. Does not mutate the event row.
	 *
	 * @param int      $product_id   Stored parent ID.
	 * @param int|null $variation_id Stored variation ID.
	 */
	public function resolve_for_event( int $product_id, ?int $variation_id ): ?PublicProduct {
		if ( null !== $variation_id && $variation_id > 0 ) {
			$variation = $this->get_product( $variation_id );
			if ( $variation instanceof WC_Product && $variation->is_type( 'variation' ) ) {
				if ( $this->is_publicly_eligible( $variation ) ) {
					return $this->to_public( $variation );
				}
				return null;
			}
			$parent = $this->get_product( $product_id );
			if ( $parent instanceof WC_Product && $this->is_publicly_eligible( $parent ) ) {
				return $this->to_public( $parent );
			}
			return null;
		}

		$product = $this->get_product( $product_id );
		if ( $product instanceof WC_Product && $this->is_publicly_eligible( $product ) ) {
			return $this->to_public( $product );
		}
		return null;
	}

	/**
	 * Anonymous merchandising eligibility. Not is_visible() / is_purchasable().
	 *
	 * @param WC_Product $product Product.
	 */
	public function is_publicly_eligible( WC_Product $product ): bool {
		if ( 'publish' !== (string) $product->get_status() ) {
			return false;
		}
		if ( 'hidden' === (string) $product->get_catalog_visibility() ) {
			return false;
		}
		if ( '' !== (string) $product->get_post_password() ) {
			return false;
		}
		$permalink = $product->get_permalink();
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return false;
		}
		if ( $product->is_type( 'variation' ) ) {
			$parent_id = (int) $product->get_parent_id();
			if ( $parent_id <= 0 ) {
				return false;
			}
			$parent_status = function_exists( 'get_post_status' ) ? get_post_status( $parent_id ) : false;
			if ( 'publish' !== $parent_status ) {
				return false;
			}
		}
		if ( StockExclusionSettings::is_enabled() && ! $product->is_in_stock() ) {
			return false;
		}
		return true;
	}

	/**
	 * Map a WC product to the internal public presentation object.
	 *
	 * @param WC_Product $product Product.
	 */
	public function to_public( WC_Product $product ): PublicProduct {
		$image_id = (int) $product->get_image_id();
		$thumb    = null;
		if ( $image_id > 0 && function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
			if ( is_string( $url ) && '' !== $url ) {
				$thumb = $url;
			}
		}
		$is_variation = $product->is_type( 'variation' );
		$parent_id    = $is_variation ? (int) $product->get_parent_id() : (int) $product->get_id();

		return new PublicProduct(
			(int) $product->get_id(),
			$is_variation ? 'variation' : 'simple',
			(string) $product->get_permalink(),
			$thumb,
			(string) $product->get_name(),
			(bool) $product->is_in_stock(),
			$parent_id > 0 ? $parent_id : (int) $product->get_id()
		);
	}
}
