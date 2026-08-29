<?php
/**
 * Integration test bootstrap.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

$usp_root = dirname( __DIR__, 2 );

require_once $usp_root . '/vendor/autoload.php';

$usp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: $usp_root . '/vendor/wp-phpunit/wp-phpunit';

if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . dirname( __DIR__ ) . '/wp-tests-config.php' );
}

require_once $usp_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
	}
);

/*
 * Declare HPOS compatibility before WooCommerce finishes bootstrapping.
 * Use the install-wp.sh copy under WP_PLUGIN_DIR so FeaturesUtil plugin IDs match.
 */
tests_add_filter(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		$plugin_file = WP_PLUGIN_DIR . '/universal-social-proof/universal-social-proof.php';
		if ( ! is_readable( $plugin_file ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			$plugin_file,
			true
		);
	}
);

require_once $usp_tests_dir . '/includes/bootstrap.php';

$usp_plugin_file = WP_PLUGIN_DIR . '/universal-social-proof/universal-social-proof.php';
if ( ! is_readable( $usp_plugin_file ) ) {
	$usp_plugin_file = $usp_root . '/universal-social-proof.php';
}

require_once $usp_plugin_file;

// plugins_loaded already fired in the WP test bootstrap; invoke init explicitly.
\UniversalSocialProof\Plugin::init();
