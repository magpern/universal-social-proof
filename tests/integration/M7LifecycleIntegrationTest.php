<?php
/**
 * M7 lifecycle / upgrade / uninstall integration tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Integration;

use UniversalSocialProof\Cleanup\RetentionScheduler;
use UniversalSocialProof\Plugin;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Storage\EventStatus;
use UniversalSocialProof\Storage\Migrator;
use UniversalSocialProof\Storage\Schema;
use WP_UnitTestCase;

final class M7LifecycleIntegrationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Plugin::reset_for_tests();
		delete_option( SettingsRepository::OPTION_KEY );
		delete_option( SettingsRepository::VERSION_KEY );
		delete_option( SettingsRepository::LEGACY_RETENTION );
		delete_option( SettingsRepository::LEGACY_OOS );
		SettingsRepository::reset_for_tests();
		Migrator::upgrade_now();
		Plugin::init();
	}

	public function test_runtime_is_v1_candidate_and_schema_unchanged(): void {
		$this->assertSame( '1.0.0', USP_VERSION );
		$this->assertSame( '20260829m1', Schema::DB_VERSION );
		$this->assertSame( Schema::DB_VERSION, (string) get_option( 'usp_db_version' ) );
	}

	public function test_settings_migration_idempotent_from_legacy_v05_state(): void {
		delete_option( SettingsRepository::OPTION_KEY );
		delete_option( SettingsRepository::VERSION_KEY );
		SettingsRepository::reset_for_tests();
		update_option( SettingsRepository::LEGACY_RETENTION, 30 );
		update_option( SettingsRepository::LEGACY_OOS, 'no' );

		SettingsRepository::maybe_migrate();
		$first = SettingsRepository::get_persisted();
		$this->assertSame( 1, (int) get_option( SettingsRepository::VERSION_KEY ) );
		$this->assertSame( 30, $first['retention_days'] );
		$this->assertFalse( $first['exclude_out_of_stock'] );

		SettingsRepository::maybe_migrate();
		$second = SettingsRepository::get_persisted();
		$this->assertSame( $first, $second );
		$this->assertSame( 1, (int) get_option( SettingsRepository::VERSION_KEY ) );
	}

	public function test_events_survive_settings_migration(): void {
		global $wpdb;
		$table = Schema::events_table();
		$pid   = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
		$now   = gmdate( 'Y-m-d H:i:s' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->insert(
			$table,
			array(
				'source_order_id' => 900001,
				'source_item_id'  => 900002,
				'public_id'       => $pid,
				'product_id'      => 55,
				'variation_id'    => null,
				'quantity'        => '1.000000',
				'country_code'    => 'SE',
				'status'          => EventStatus::ACTIVE,
				'suppress_reason' => null,
				'occurred_at'     => $now,
				'captured_at'     => $now,
				'updated_at'      => $now,
			)
		);
		// phpcs:enable

		delete_option( SettingsRepository::OPTION_KEY );
		delete_option( SettingsRepository::VERSION_KEY );
		SettingsRepository::reset_for_tests();
		update_option( SettingsRepository::LEGACY_RETENTION, 14 );
		SettingsRepository::maybe_migrate();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT public_id FROM {$table} WHERE public_id = %s", $pid ) );
		// phpcs:enable
		$this->assertSame( $pid, $found );
	}

	public function test_uninstall_removes_usp_owned_data_only(): void {
		global $wpdb;
		SettingsRepository::maybe_migrate();
		update_option( 'usp_unrelated_sentinel', 'keep-me' );

		$table = Schema::events_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		// phpcs:enable
		$this->assertSame( $table, $exists );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'universal-social-proof/universal-social-proof.php' );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertFalse( get_option( SettingsRepository::OPTION_KEY ) );
		$this->assertFalse( get_option( SettingsRepository::VERSION_KEY ) );
		$this->assertFalse( get_option( 'usp_db_version' ) );
		$this->assertSame( 'keep-me', get_option( 'usp_unrelated_sentinel' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$gone = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		// phpcs:enable
		$this->assertNull( $gone );

		delete_option( 'usp_unrelated_sentinel' );
		Migrator::upgrade_now();
	}

	public function test_scheduler_class_and_hook_names_stable(): void {
		$this->assertTrue( class_exists( RetentionScheduler::class ) );
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Cleanup/RetentionScheduler.php' );
		$this->assertStringContainsString( 'usp_retention_daily', $src );
		$this->assertStringContainsString( 'as_next_scheduled_action', $src );
	}
}
