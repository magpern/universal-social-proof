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
	define( 'USP_VERSION', '0.0.0' );
}

if ( ! defined( 'USP_PLUGIN_FILE' ) ) {
	define( 'USP_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/universal-social-proof.php' );
}

if ( ! defined( 'USP_PLUGIN_DIR' ) ) {
	define( 'USP_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}
