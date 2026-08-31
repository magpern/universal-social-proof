<?php
/**
 * Anonymous GET notifications API.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Rest;

use Throwable;
use UniversalSocialProof\Logger;
use UniversalSocialProof\Product\PublicProductResolver;
use UniversalSocialProof\Selection\CandidateQuery;
use UniversalSocialProof\Selection\CandidateReader;
use UniversalSocialProof\Selection\ProductResolutionBudget;
use UniversalSocialProof\Selection\SelectionEngine;
use UniversalSocialProof\Selection\SelectionRequest;
use UniversalSocialProof\Storage\Migrator;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Cache-safe public read of selected purchase notifications.
 */
final class NotificationsController {

	public const NAMESPACE = 'universal-social-proof/v1';
	public const ROUTE     = '/notifications';

	public const ALLOWLIST = array(
		'public_id',
		'product_url',
		'thumbnail_url',
		'occurred_at',
		'message',
		'show_relative_time',
	);

	/**
	 * Whether routes/filters were registered this process.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register route and cache-header filter.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
		add_filter( 'rest_post_dispatch', array( self::class, 'filter_post_dispatch' ), 10, 3 );
		if ( did_action( 'rest_api_init' ) ) {
			self::register_route();
		}
	}

	/**
	 * Register GET /notifications.
	 */
	public static function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_notifications' ),
				'permission_callback' => '__return_true',
				'args'                => self::route_args(),
			)
		);
	}

	/**
	 * REST argument schema. Avoid native integer/enum types that coerce or 400 incorrectly.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function route_args(): array {
		return array(
			'limit'        => array(
				'required'          => false,
				'default'           => SelectionRequest::LIMIT_DEFAULT,
				'sanitize_callback' => array( self::class, 'sanitize_limit' ),
				'validate_callback' => array( self::class, 'validate_limit' ),
			),
			'product_id'   => array(
				'required'          => false,
				'default'           => null,
				'sanitize_callback' => array( self::class, 'sanitize_product_id' ),
				'validate_callback' => array( self::class, 'validate_product_id' ),
			),
			'page_context' => array(
				'required'          => false,
				'default'           => SelectionRequest::CONTEXT_UNKNOWN,
				'sanitize_callback' => array( self::class, 'sanitize_page_context' ),
			),
			'exclude'      => array(
				'required'          => false,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_exclude' ),
				'validate_callback' => array( self::class, 'validate_exclude' ),
			),
		);
	}

	/**
	 * GET handler: 200 JSON array. Fail closed to [].
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_notifications( WP_REST_Request $request ) {
		try {
			Migrator::maybe_upgrade_controlled();
			$engine = self::make_engine();
			$events = $engine->select( self::selection_request_from_rest( $request ) );
			$body   = array();
			foreach ( $events as $event ) {
				$dto = $event->to_public_array();
				if ( is_array( $dto ) ) {
					$body[] = self::allowlist( $dto );
				}
			}
			return self::ok( $body );
		} catch ( Throwable $e ) {
			Logger::error(
				'notifications request failed',
				array(
					'error' => $e->getMessage(),
				)
			);
			return self::ok( array() );
		}
	}

	/**
	 * Scope Cache-Control: no-store to the exact notifications route (covers 400/405).
	 *
	 * @param mixed           $result  Dispatch result.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function filter_post_dispatch( $result, $server, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $request instanceof WP_REST_Request ) {
			return $result;
		}
		if ( ! self::is_notifications_route( $request ) ) {
			return $result;
		}
		$result = rest_ensure_response( $result );
		if ( $result instanceof WP_REST_Response || $result instanceof WP_HTTP_Response ) {
			$result->header( 'Cache-Control', 'no-store', true );
		}
		return $result;
	}

	/**
	 * Clampable integer limit; reject non-integral values before WP coercion.
	 *
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Param name.
	 * @return int|WP_Error
	 */
	public static function sanitize_limit( $value, $request, $param ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$raw = self::raw_param( $request, 'limit', $value );
		if ( null === $raw || '' === $raw ) {
			return SelectionRequest::LIMIT_DEFAULT;
		}
		if ( is_int( $raw ) ) {
			return SelectionRequest::clamp_limit( $raw );
		}
		if ( is_string( $raw ) && 1 === preg_match( '/^-?\d+$/', $raw ) ) {
			return SelectionRequest::clamp_limit( (int) $raw );
		}
		if ( is_float( $raw ) && floor( $raw ) === $raw ) {
			return SelectionRequest::clamp_limit( (int) $raw );
		}
		return new WP_Error(
			'rest_invalid_param',
			__( 'Invalid limit.', 'universal-social-proof' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Validate a sanitized limit.
	 *
	 * WordPress may skip sanitize_callback when the value is empty/`0`.
	 * Integral numbers are valid and clamped in selection_request_from_rest().
	 *
	 * @param mixed $value Sanitized limit.
	 * @return true|WP_Error
	 */
	public static function validate_limit( $value ) {
		if ( $value instanceof WP_Error ) {
			return $value;
		}
		if ( null === $value || '' === $value ) {
			return true;
		}
		if ( is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', $value ) ) ) {
			return true;
		}
		if ( is_float( $value ) && floor( $value ) === $value ) {
			return true;
		}
		return new WP_Error(
			'rest_invalid_param',
			__( 'Invalid limit.', 'universal-social-proof' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Sanitize optional product_id; reject non-integral values.
	 *
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Param name.
	 * @return int|null|WP_Error
	 */
	public static function sanitize_product_id( $value, $request, $param ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$raw = self::raw_param( $request, 'product_id', $value );
		if ( null === $raw || '' === $raw ) {
			return null;
		}
		if ( is_int( $raw ) && $raw > 0 ) {
			return $raw;
		}
		if ( is_string( $raw ) && 1 === preg_match( '/^[1-9][0-9]*$/', $raw ) ) {
			return (int) $raw;
		}
		return new WP_Error(
			'rest_invalid_param',
			__( 'Invalid product_id.', 'universal-social-proof' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Validate a sanitized product_id.
	 *
	 * @param mixed $value Sanitized product_id.
	 * @return true|WP_Error
	 */
	public static function validate_product_id( $value ) {
		if ( $value instanceof WP_Error ) {
			return $value;
		}
		if ( null === $value ) {
			return true;
		}
		if ( is_int( $value ) && $value > 0 ) {
			return true;
		}
		if ( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*$/', $value ) ) {
			return true;
		}
		return new WP_Error(
			'rest_invalid_param',
			__( 'Invalid product_id.', 'universal-social-proof' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Unknown strings become unknown; never 400.
	 *
	 * @param mixed $value Value.
	 */
	public static function sanitize_page_context( $value ): string {
		return SelectionRequest::normalize_page_context( $value );
	}

	/**
	 * Count supplied entries before dropping malformed UUIDs.
	 *
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Param name.
	 * @return array<int, string>|WP_Error
	 */
	public static function sanitize_exclude( $value, $request, $param ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$raw = self::raw_param( $request, 'exclude', $value );
		if ( null === $raw || '' === $raw ) {
			return array();
		}
		if ( is_string( $raw ) ) {
			$raw = array_map( 'trim', explode( ',', $raw ) );
		}
		if ( ! is_array( $raw ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Invalid exclude.', 'universal-social-proof' ),
				array( 'status' => 400 )
			);
		}
		$entries = array_values( $raw );
		if ( count( $entries ) > CandidateQuery::EXCLUDE_MAX ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Too many exclusions.', 'universal-social-proof' ),
				array( 'status' => 400 )
			);
		}
		$out = array();
		foreach ( $entries as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$item = strtolower( $item );
			if ( function_exists( 'wp_is_uuid' ) && wp_is_uuid( $item, 4 ) ) {
				$out[] = $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Validate sanitized exclusions.
	 *
	 * @param mixed $value Sanitized exclude.
	 * @return true|WP_Error
	 */
	public static function validate_exclude( $value ) {
		if ( $value instanceof WP_Error ) {
			return $value;
		}
		if ( null === $value || '' === $value ) {
			return true;
		}
		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Invalid exclude.', 'universal-social-proof' ),
				array( 'status' => 400 )
			);
		}
		if ( count( $value ) > CandidateQuery::EXCLUDE_MAX ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Too many exclusions.', 'universal-social-proof' ),
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * Build a request-local selection engine.
	 *
	 * @param callable|null $shuffle Optional shuffle for tests.
	 * @param callable|null $loader  Optional wc_get_product loader for tests.
	 */
	public static function make_engine( $shuffle = null, $loader = null ): SelectionEngine {
		$budget   = new ProductResolutionBudget();
		$resolver = new PublicProductResolver( $budget, $loader );
		return new SelectionEngine( new CandidateReader(), $resolver, $shuffle );
	}

	/**
	 * Map a validated REST request to a selection request.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function selection_request_from_rest( WP_REST_Request $request ): SelectionRequest {
		$limit = $request->get_param( 'limit' );
		if ( is_string( $limit ) && 1 === preg_match( '/^-?\d+$/', $limit ) ) {
			$limit = (int) $limit;
		}
		if ( is_float( $limit ) && floor( $limit ) === $limit ) {
			$limit = (int) $limit;
		}
		$limit   = is_int( $limit ) ? SelectionRequest::clamp_limit( $limit ) : SelectionRequest::LIMIT_DEFAULT;
		$product = $request->get_param( 'product_id' );
		if ( is_string( $product ) && 1 === preg_match( '/^[1-9][0-9]*$/', $product ) ) {
			$product = (int) $product;
		}
		$context = SelectionRequest::normalize_page_context( $request->get_param( 'page_context' ) );
		$exclude = $request->get_param( 'exclude' );
		$exclude = is_array( $exclude ) ? $exclude : array();
		return new SelectionRequest( $limit, $product, $context, $exclude );
	}

	/**
	 * Public DTO allowlist mapper.
	 *
	 * @param array<string, mixed> $dto Mapped DTO.
	 * @return array{public_id: string, product_url: string, thumbnail_url: string|null, occurred_at: string, message: string, show_relative_time: bool}
	 */
	public static function allowlist( array $dto ): array {
		return array(
			'public_id'          => (string) ( $dto['public_id'] ?? '' ),
			'product_url'        => (string) ( $dto['product_url'] ?? '' ),
			'thumbnail_url'      => array_key_exists( 'thumbnail_url', $dto ) && is_string( $dto['thumbnail_url'] ) ? $dto['thumbnail_url'] : null,
			'occurred_at'        => (string) ( $dto['occurred_at'] ?? '' ),
			'message'            => (string) ( $dto['message'] ?? '' ),
			'show_relative_time' => array_key_exists( 'show_relative_time', $dto ) ? (bool) $dto['show_relative_time'] : true,
		);
	}

	/**
	 * 200 JSON array with no-store.
	 *
	 * @param array $body Body.
	 */
	private static function ok( array $body ): WP_REST_Response {
		$response = new WP_REST_Response( $body, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Whether this request is the exact USP notifications route.
	 *
	 * Matches `/universal-social-proof/v1/notifications` only. Prefix
	 * lookalikes such as `notifications-other` are not this endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private static function is_notifications_route( WP_REST_Request $request ): bool {
		return '/' . self::NAMESPACE . self::ROUTE === (string) $request->get_route();
	}

	/**
	 * Prefer the original query/body value so sanitization cannot hide 5.9 → 5.
	 *
	 * @param WP_REST_Request $request  Request.
	 * @param string          $key      Param name.
	 * @param mixed           $fallback Already-received value.
	 * @return mixed
	 */
	private static function raw_param( WP_REST_Request $request, string $key, $fallback ) {
		$query = $request->get_query_params();
		if ( is_array( $query ) && array_key_exists( $key, $query ) ) {
			return $query[ $key ];
		}
		$body = $request->get_body_params();
		if ( is_array( $body ) && array_key_exists( $key, $body ) ) {
			return $body[ $key ];
		}
		return $fallback;
	}
}
