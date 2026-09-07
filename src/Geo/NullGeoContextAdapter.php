<?php
/**
 * Null visitor-country adapter (UGC absent / tests).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Geo;

defined( 'ABSPATH' ) || exit;

/**
 * Always unavailable; selection uses the M2 path.
 */
final class NullGeoContextAdapter implements GeoContextAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function country_code(): ?string {
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return false;
	}
}
