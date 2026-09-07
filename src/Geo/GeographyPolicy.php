<?php
/**
 * Whether visitor-country weighting is active for a request.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Geo;

use UniversalSocialProof\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Settings + filter seam (ADR-0015).
 */
final class GeographyPolicy {

	public const FILTER = 'usp_geo_weighting_enabled';

	/**
	 * Effective visitor country for selection, or null to use the M2 path.
	 *
	 * @param GeoContextAdapter $adapter Adapter.
	 */
	public static function visitor_country_for_selection( GeoContextAdapter $adapter ): ?string {
		if ( ! $adapter->is_available() ) {
			return null;
		}
		$code = $adapter->country_code();
		if ( null === $code || '' === $code ) {
			return null;
		}
		$enabled = SettingsRepository::geo_weighting_enabled();
		/**
		 * Filter whether USP applies visitor-country weighting.
		 *
		 * @since 0.5.0
		 *
		 * @param bool              $enabled Default from settings (true when country available).
		 * @param string            $code    Normalized visitor country.
		 * @param GeoContextAdapter $adapter Adapter instance.
		 */
		$enabled = (bool) apply_filters( self::FILTER, $enabled, $code, $adapter );
		return $enabled ? $code : null;
	}
}
