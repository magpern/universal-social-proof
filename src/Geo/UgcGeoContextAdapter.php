<?php
/**
 * UGC-backed visitor-country adapter.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Geo;

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Calls universal_geo_get_country_code() once per instance (request-local).
 */
final class UgcGeoContextAdapter implements GeoContextAdapter {

	/**
	 * Whether country_code() has resolved this instance.
	 *
	 * @var bool
	 */
	private bool $resolved = false;

	/**
	 * Memoized normalized country.
	 *
	 * @var string|null
	 */
	private ?string $memo = null;

	/**
	 * Optional callable for tests: (): ?string
	 *
	 * @var callable|null
	 */
	private $fetcher;

	/**
	 * Constructor.
	 *
	 * @param callable|null $fetcher Optional override returning ?string.
	 */
	public function __construct( $fetcher = null ) {
		$this->fetcher = $fetcher;
	}

	/**
	 * Whether the UGC country helper is present (and API v1 when gated).
	 */
	public function is_available(): bool {
		if ( null !== $this->fetcher ) {
			return true;
		}
		if ( ! function_exists( 'universal_geo_get_country_code' ) ) {
			return false;
		}
		if ( function_exists( 'universal_geo_api_version' ) ) {
			return 1 === (int) universal_geo_api_version();
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function country_code(): ?string {
		if ( $this->resolved ) {
			return $this->memo;
		}
		$this->resolved = true;
		$this->memo     = null;

		if ( ! $this->is_available() ) {
			return null;
		}

		try {
			$raw        = null !== $this->fetcher
				? ( $this->fetcher )()
				: universal_geo_get_country_code();
			$this->memo = self::normalize( is_string( $raw ) || null === $raw ? $raw : null );
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail closed.
			unset( $e );
			$this->memo = null;
		}

		return $this->memo;
	}

	/**
	 * Defensive visitor-country normalization.
	 *
	 * @param string|null $raw Raw value.
	 */
	public static function normalize( ?string $raw ): ?string {
		if ( null === $raw ) {
			return null;
		}
		$code = strtoupper( trim( $raw ) );
		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $code ) ) {
			return null;
		}
		return $code;
	}
}
