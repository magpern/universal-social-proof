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
use UniversalSocialProof\Storage\EventType;
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
		$this->assertSame( '1.1.0', USP_VERSION );
		$this->assertSame( '20260907v11a', Schema::DB_VERSION );
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
		$this->assertSame( 2, (int) get_option( SettingsRepository::VERSION_KEY ) );
		$this->assertSame( 30, $first['retention_days'] );
		$this->assertFalse( $first['exclude_out_of_stock'] );

		SettingsRepository::maybe_migrate();
		$second = SettingsRepository::get_persisted();
		$this->assertSame( $first, $second );
		$this->assertSame( 2, (int) get_option( SettingsRepository::VERSION_KEY ) );
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
				'event_type'      => EventType::PURCHASE,
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

	public function test_uninstall_contract_preserves_unrelated_options(): void {
		$path = dirname( __DIR__, 2 ) . '/uninstall.php';
		$src  = (string) file_get_contents( $path );
		$this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' ) || exit", $src );
		foreach ( array( 'usp_settings', 'usp_settings_version', 'usp_db_version', 'usp_db_migrate_lock', 'usp_retention_days', 'usp_exclude_out_of_stock', 'DROP TABLE IF EXISTS' ) as $needle ) {
			$this->assertStringContainsString( $needle, $src );
		}
		$this->assertStringContainsString( '`{$table}`', $src );

		SettingsRepository::maybe_migrate();
		update_option( 'usp_unrelated_sentinel', 'keep-me' );

		foreach ( array( 'usp_settings', 'usp_settings_version', 'usp_retention_days', 'usp_exclude_out_of_stock', 'usp_db_version', 'usp_db_migrate_lock' ) as $option ) {
			delete_option( $option );
		}
		$this->assertFalse( get_option( SettingsRepository::OPTION_KEY ) );
		$this->assertFalse( get_option( 'usp_db_version' ) );
		$this->assertSame( 'keep-me', get_option( 'usp_unrelated_sentinel' ) );

		delete_option( 'usp_unrelated_sentinel' );
		Migrator::upgrade_now();
		$this->assertSame( Schema::DB_VERSION, (string) get_option( 'usp_db_version' ) );
	}

	public function test_scheduler_class_and_hook_names_stable(): void {
		$this->assertTrue( class_exists( RetentionScheduler::class ) );
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Cleanup/RetentionScheduler.php' );
		$this->assertStringContainsString( 'usp_retention_daily', $src );
		$this->assertStringContainsString( 'as_next_scheduled_action', $src );
	}
}
