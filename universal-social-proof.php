<?php
/**
 * Plugin Name: Universal Social Proof
 * Plugin URI: https://github.com/magpern/universal-social-proof
 * Description: Genuine, privacy-conscious WooCommerce purchase social-proof notifications. M3 storefront toaster infrastructure (visually inert until M4 message).
 * Version: 0.3.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * WC tested up to: 11.0
 * Author: magpern
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: universal-social-proof
 *
 * @package UniversalSocialProof
 */

defined( 'ABSPATH' ) || exit;

define( 'USP_VERSION', '0.3.0' );
define( 'USP_PLUGIN_FILE', __FILE__ );
define( 'USP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/*
 * PHP version guard. The "Requires PHP" header stops activation on WP 5.1+,
 * but a file-drop install can bypass it, so fail closed with a notice.
 */
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Universal Social Proof requires PHP 8.1 or newer and is inactive.', 'universal-social-proof' )
			);
		}
	);
	return;
}

$usp_autoload = USP_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! is_readable( $usp_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Universal Social Proof requires Composer dependencies (run composer install).', 'universal-social-proof' )
			);
		}
	);
	return;
}

require_once $usp_autoload;

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( class_exists( \UniversalSocialProof\Storage\Migrator::class ) ) {
			\UniversalSocialProof\Storage\Migrator::upgrade_now();
		}
	}
);

/*
 * HPOS compatibility declaration.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				USP_PLUGIN_FILE,
				true
			);
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( \UniversalSocialProof\WooCommerce\WooCommerceGate::class ) ) {
			return;
		}

		if ( ! \UniversalSocialProof\WooCommerce\WooCommerceGate::is_active() ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_woocommerce' ) ) {
						return;
					}
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Universal Social Proof requires WooCommerce to be installed and active.', 'universal-social-proof' )
					);
				}
			);
			return;
		}

		if ( class_exists( \UniversalSocialProof\Plugin::class ) ) {
			\UniversalSocialProof\Plugin::init();
		}
	}
);
