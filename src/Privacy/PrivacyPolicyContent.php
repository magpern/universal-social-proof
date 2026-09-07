<?php
/**
 * Suggested privacy-policy content for USP (M5).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Registers wp_add_privacy_policy_content() for purchase and visitor country.
 */
final class PrivacyPolicyContent {

	/**
	 * Whether content was registered this process.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register privacy policy suggestion once.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_action( 'admin_init', array( self::class, 'add_privacy_policy_content' ) );
	}

	/**
	 * Add suggested privacy policy text.
	 */
	public static function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__(
			'Universal Social Proof displays recent purchase notifications using product and purchase-country information stored from completed WooCommerce orders. Notifications do not include customer names, emails, IP addresses, or order identifiers.',
			'universal-social-proof'
		) . '</p>';

		$content .= '<p>' . esc_html__(
			'When Universal Geo Context is available, the visitor country code it supplies may be used ephemerally during a notifications request to prefer same-country purchase events. Universal Social Proof does not store visitor IP addresses, visitor country, region, or city, and does not keep visitor geography in cookies, browser storage, or analytics of its own.',
			'universal-social-proof'
		) . '</p>';

		$content .= '<p>' . esc_html__(
			'Purchase events are retained according to the plugin retention settings and may be erased through WordPress personal-data tools when linked to a WooCommerce order for the requesting email.',
			'universal-social-proof'
		) . '</p>';

		wp_add_privacy_policy_content(
			__( 'Universal Social Proof', 'universal-social-proof' ),
			wp_kses_post( $content )
		);
	}

	/**
	 * Test seam.
	 */
	public static function reset_for_tests(): void {
		self::$registered = false;
	}
}
