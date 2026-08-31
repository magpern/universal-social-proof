<?php
/**
 * Localized frontend bootstrap configuration.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Frontend;

use UniversalSocialProof\Rest\NotificationsController;

defined( 'ABSPATH' ) || exit;

/**
 * Builds FPC-safe toaster config (no selected events).
 */
final class BootstrapConfig {

	public const LIMIT            = 5;
	public const MAX_BATCHES      = 3;
	public const INITIAL_DELAY_MS = 3000;
	public const VISIBLE_MS       = 6000;
	public const GAP_MS           = 2000;
	public const MOTION_MS        = 280;
	public const STORAGE_KEY      = 'usp.v1';

	/**
	 * Build the localized config array.
	 *
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$page_context = 'unknown';
		$product_id   = null;

		if ( function_exists( 'is_product' ) && is_product() ) {
			$id = (int) get_queried_object_id();
			if ( $id > 0 ) {
				$page_context = 'product';
				$product_id   = $id;
			}
		}

		$rest_url = rest_url( NotificationsController::NAMESPACE . NotificationsController::ROUTE );

		return array(
			'restUrl'     => esc_url_raw( $rest_url ),
			'limit'       => self::LIMIT,
			'pageContext' => $page_context,
			'productId'   => $product_id,
			'maxBatches'  => self::MAX_BATCHES,
			'storageKey'  => self::STORAGE_KEY,
			'timing'      => array(
				'initialDelayMs' => self::INITIAL_DELAY_MS,
				'visibleMs'      => self::VISIBLE_MS,
				'gapMs'          => self::GAP_MS,
				'motionMs'       => self::MOTION_MS,
			),
			'i18n'        => array(
				'dismiss'    => __( 'Dismiss notification', 'universal-social-proof' ),
				'justNow'    => __( 'just now', 'universal-social-proof' ),
				/* translators: %d: number of minutes */
				'minutesAgo' => __( '%d minutes ago', 'universal-social-proof' ),
				/* translators: %d: one minute */
				'minuteAgo'  => __( '%d minute ago', 'universal-social-proof' ),
				/* translators: %d: number of hours */
				'hoursAgo'   => __( '%d hours ago', 'universal-social-proof' ),
				/* translators: %d: one hour */
				'hourAgo'    => __( '%d hour ago', 'universal-social-proof' ),
				/* translators: %d: number of days */
				'daysAgo'    => __( '%d days ago', 'universal-social-proof' ),
				/* translators: %d: one day */
				'dayAgo'     => __( '%d day ago', 'universal-social-proof' ),
			),
		);
	}
}
