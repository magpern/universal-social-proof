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
	define( 'USP_VERSION', '1.0.1' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! isset( $GLOBALS['usp_test_options'] ) ) {
	$GLOBALS['usp_test_options'] = array();
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Option stub for unit tests.
	 *
	 * @param string $key           Option key.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	function get_option( $key, $default_value = false ) {
		if ( array_key_exists( $key, $GLOBALS['usp_test_options'] ) ) {
			return $GLOBALS['usp_test_options'][ $key ];
		}
		return $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Option stub for unit tests.
	 *
	 * @param string $key        Option key.
	 * @param mixed  $value      Value.
	 * @param mixed  $autoload   Autoload.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['usp_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Delete option stub.
	 *
	 * @param string $key Option key.
	 */
	function delete_option( $key ) {
		unset( $GLOBALS['usp_test_options'][ $key ] );
		return true;
	}
}

if ( ! class_exists( 'WP_Error', false ) ) {
	require_once __DIR__ . '/stubs/class-wp-error.php';
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * WP_Error detector stub.
	 *
	 * @param mixed $thing Value.
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
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

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Minimal wp_strip_all_tags stub.
	 *
	 * @param string $text          Input.
	 * @param bool   $remove_breaks Collapse whitespace.
	 */
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		$text = strip_tags( (string) $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- stub body for wp_strip_all_tags.
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode stub.
	 *
	 * @param mixed $data Data.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- stub implements wp_json_encode.
		return json_encode( $data );
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
	 * @param mixed  ...$args Extra args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) { // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WP API stub.
		if ( empty( $GLOBALS['usp_unit_filters'][ $hook ] ) ) {
			return $value;
		}
		ksort( $GLOBALS['usp_unit_filters'][ $hook ] );
		foreach ( $GLOBALS['usp_unit_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}
		return $value;
	}
}
