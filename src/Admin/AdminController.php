<?php
/**
 * WooCommerce admin menu registration.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Social Proof under WooCommerce.
 */
final class AdminController {

	public const MENU_SLUG = 'usp-social-proof';

	/**
	 * Hook admin surfaces.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		SettingsSaveHandler::register();
	}

	/**
	 * WooCommerce → Social Proof.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Social Proof', 'universal-social-proof' ),
			__( 'Social Proof', 'universal-social-proof' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( SettingsPage::class, 'render' )
		);
	}

	/**
	 * Admin assets only on USP Social Proof page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! self::is_usp_admin_screen( $hook_suffix ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );

		$base = trailingslashit( plugins_url( '', USP_PLUGIN_FILE ) );
		$ver  = defined( 'USP_VERSION' ) ? USP_VERSION : '1.0.0';

		wp_enqueue_style(
			'usp-admin',
			$base . 'assets/css/usp-admin.css',
			array( 'woocommerce_admin_styles' ),
			$ver
		);
		wp_enqueue_script(
			'usp-admin',
			$base . 'assets/js/usp-admin.js',
			array( 'jquery', 'wc-enhanced-select' ),
			$ver,
			true
		);
	}

	/**
	 * Whether the current admin screen is USP Social Proof.
	 *
	 * @param string $hook_suffix Admin hook.
	 */
	public static function is_usp_admin_screen( string $hook_suffix ): bool {
		return false !== strpos( $hook_suffix, self::MENU_SLUG );
	}
}
