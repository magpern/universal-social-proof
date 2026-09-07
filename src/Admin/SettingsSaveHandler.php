<?php
/**
 * Admin settings save / reset handlers.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Admin;

use UniversalSocialProof\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Capability + nonce protected mutations.
 */
final class SettingsSaveHandler {

	public const ACTION_SAVE  = 'usp_save_settings';
	public const ACTION_RESET = 'usp_reset_settings';
	public const NONCE_SAVE   = 'usp_save_settings';
	public const NONCE_RESET  = 'usp_reset_settings';

	/**
	 * Register admin-post handlers.
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_RESET, array( self::class, 'handle_reset' ) );
	}

	/**
	 * Save settings atomically.
	 */
	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Social Proof settings.', 'universal-social-proof' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );

		$raw = array(
			'display_enabled'       => isset( $_POST['usp_display_enabled'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
			'template'              => isset( $_POST['usp_template'] ) ? wp_unslash( (string) $_POST['usp_template'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification.Missing
			'excluded_product_ids'  => isset( $_POST['usp_excluded_product_ids'] ) ? wp_unslash( $_POST['usp_excluded_product_ids'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification.Missing
			'exclude_out_of_stock'  => isset( $_POST['usp_exclude_out_of_stock'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'load_on_cart'          => isset( $_POST['usp_load_on_cart'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'load_on_account'       => isset( $_POST['usp_load_on_account'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'geo_weighting_enabled' => isset( $_POST['usp_geo_weighting_enabled'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'retention_days'        => isset( $_POST['usp_retention_days'] ) ? (int) $_POST['usp_retention_days'] : 60, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$result = SettingsRepository::save( $raw );
		if ( is_wp_error( $result ) ) {
			self::redirect( 'error', $result->get_error_message() );
		}
		self::redirect( 'saved' );
	}

	/**
	 * Reset to defaults (events untouched).
	 */
	public static function handle_reset(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Social Proof settings.', 'universal-social-proof' ), 403 );
		}
		check_admin_referer( self::NONCE_RESET );

		if ( empty( $_POST['usp_confirm_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::redirect( 'error', __( 'Reset confirmation is required.', 'universal-social-proof' ) );
		}

		SettingsRepository::reset_to_defaults();
		self::redirect( 'reset' );
	}

	/**
	 * Redirect back to the settings page with a notice.
	 *
	 * @param string $notice Notice key.
	 * @param string $message Optional error message.
	 */
	private static function redirect( string $notice, string $message = '' ): void {
		$args = array(
			'page'       => AdminController::MENU_SLUG,
			'usp_notice' => $notice,
		);
		if ( '' !== $message ) {
			$args['usp_msg'] = rawurlencode( $message );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
