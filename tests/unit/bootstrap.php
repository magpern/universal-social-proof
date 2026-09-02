<?php
/**
 * Unit test bootstrap — WordPress is not loaded.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'USP_VERSION' ) ) {
	define( 'USP_VERSION', '0.4.1' );
}

if ( ! defined( 'USP_PLUGIN_FILE' ) ) {
	define( 'USP_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/universal-social-proof.php' );
}

if ( ! defined( 'USP_PLUGIN_DIR' ) ) {
	define( 'USP_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Minimal gettext stub for unit tests.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! isset( $GLOBALS['usp_unit_filters'] ) ) {
	$GLOBALS['usp_unit_filters'] = array();
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Minimal add_filter stub.
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) {
		unset( $accepted );
		$GLOBALS['usp_unit_filters'][ $hook ][ $priority ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	/**
	 * Clear stub filters.
	 *
	 * @param string $hook Hook.
	 */
	function remove_all_filters( $hook = null ) {
		if ( null === $hook ) {
			$GLOBALS['usp_unit_filters'] = array();
			return;
		}
		unset( $GLOBALS['usp_unit_filters'][ $hook ] );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Stub WordPress apply_filters for unit tests (not a production hook).
	 *
	 * @param string $hook  Filter name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) { // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WP API stub.
		if ( empty( $GLOBALS['usp_unit_filters'][ $hook ] ) ) {
			return $value;
		}
		ksort( $GLOBALS['usp_unit_filters'][ $hook ] );
		foreach ( $GLOBALS['usp_unit_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value );
			}
		}
		return $value;
	}
}
