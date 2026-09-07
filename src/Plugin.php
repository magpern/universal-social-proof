<?php
/**
 * Plugin composition root (M1–M3 capture, selection/REST, frontend).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof;

use UniversalSocialProof\Admin\AdminController;
use UniversalSocialProof\Capture\LifecycleHooks;
use UniversalSocialProof\Cleanup\RetentionScheduler;
use UniversalSocialProof\Frontend\FrontendController;
use UniversalSocialProof\Privacy\PersonalDataEraser;
use UniversalSocialProof\Privacy\PersonalDataExporter;
use UniversalSocialProof\Privacy\PrivacyPolicyContent;
use UniversalSocialProof\Rest\NotificationsController;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\WooCommerce\WooCommerceGate;

defined( 'ABSPATH' ) || exit;

/**
 * Idempotent bootstrap for M1–M6.
 */
final class Plugin {

	/**
	 * Whether init() has completed for this request.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Boot the plugin when WooCommerce is available.
	 */
	public static function init(): void {
		if ( self::$initialized || ! WooCommerceGate::is_active() ) {
			return;
		}

		self::$initialized = true;

		Migrator::maybe_upgrade_controlled();
		SettingsRepository::maybe_migrate();

		LifecycleHooks::register();
		RetentionScheduler::register();
		NotificationsController::register();
		FrontendController::register();
		AdminController::register();

		add_filter( 'wp_privacy_personal_data_exporters', array( PersonalDataExporter::class, 'register' ) );
		PersonalDataEraser::bootstrap();
		PrivacyPolicyContent::register();
	}

	/**
	 * Whether the composition root has completed init.
	 */
	public static function is_initialized(): bool {
		return self::$initialized;
	}

	/**
	 * Test seam: reset bootstrap state between integration tests.
	 */
	public static function reset_for_tests(): void {
		self::$initialized = false;
		SettingsRepository::reset_for_tests();
		FrontendController::reset_for_tests();
		PrivacyPolicyContent::reset_for_tests();
	}
}
