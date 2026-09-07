<?php
/**
 * Visitor-country adapter boundary for soft UGC integration (M5).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Geo;

defined( 'ABSPATH' ) || exit;

/**
 * Isolates Universal Geo Context from selection/REST.
 */
interface GeoContextAdapter {

	/**
	 * Normalized visitor ISO country, or null when unavailable.
	 */
	public function country_code(): ?string;

	/**
	 * Whether a UGC-compatible provider appears loadable.
	 */
	public function is_available(): bool;
}
