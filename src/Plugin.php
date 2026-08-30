<?php
/**
 * Plugin composition root (M1 capture + M2 selection/REST).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof;

use UniversalSocialProof\Capture\LifecycleHooks;
use UniversalSocialProof\Cleanup\RetentionScheduler;
use UniversalSocialProof\Privacy\PersonalDataEraser;
use UniversalSocialProof\Privacy\PersonalDataExporter;
use UniversalSocialProof\Rest\NotificationsController;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\WooCommerce\WooCommerceGate;

defined( 'ABSPATH' ) || exit;

/**
 * Idempotent bootstrap for M1 capture and M2 selection/REST.
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

		LifecycleHooks::register();
		RetentionScheduler::register();
		NotificationsController::register();

		add_filter( 'wp_privacy_personal_data_exporters', array( PersonalDataExporter::class, 'register' ) );
		PersonalDataEraser::bootstrap();
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
	}
}
