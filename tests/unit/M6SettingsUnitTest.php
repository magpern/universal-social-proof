<?php
/**
 * M6 settings migration and precedence unit tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UniversalSocialProof\Cleanup\RetentionSettings;
use UniversalSocialProof\Geo\GeographyPolicy;
use UniversalSocialProof\Selection\StockExclusionSettings;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Targeting\ProductTargetingPolicy;
use UniversalSocialProof\Template\TemplateSettings;

final class M6SettingsUnitTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['usp_test_options'] = array();
		$GLOBALS['usp_unit_filters'] = array();
		SettingsRepository::reset_for_tests();
	}

	protected function tearDown(): void {
		$GLOBALS['usp_test_options'] = array();
		remove_all_filters();
		SettingsRepository::reset_for_tests();
		parent::tearDown();
	}

	public function test_defaults_and_migration_without_legacy(): void {
		SettingsRepository::maybe_migrate();
		$this->assertSame( 1, SettingsRepository::settings_version() );
		$s = SettingsRepository::get_persisted();
		$this->assertTrue( $s['display_enabled'] );
		$this->assertFalse( $s['exclude_out_of_stock'] );
		$this->assertSame( 60, $s['retention_days'] );
		$this->assertArrayHasKey( SettingsRepository::OPTION_KEY, $GLOBALS['usp_test_options'] );
		$this->assertArrayNotHasKey( SettingsRepository::LEGACY_RETENTION, $GLOBALS['usp_test_options'] );
	}

	public function test_migration_from_valid_legacy(): void {
		$GLOBALS['usp_test_options'][ SettingsRepository::LEGACY_RETENTION ] = 45;
		$GLOBALS['usp_test_options'][ SettingsRepository::LEGACY_OOS ]       = 'yes';
		SettingsRepository::maybe_migrate();
		$s = SettingsRepository::get_persisted();
		$this->assertSame( 45, $s['retention_days'] );
		$this->assertTrue( $s['exclude_out_of_stock'] );
		$this->assertSame( 45, $GLOBALS['usp_test_options'][ SettingsRepository::LEGACY_RETENTION ] );
		$this->assertSame( 'yes', $GLOBALS['usp_test_options'][ SettingsRepository::LEGACY_OOS ] );
	}

	public function test_migration_clamps_invalid_legacy(): void {
		$GLOBALS['usp_test_options'][ SettingsRepository::LEGACY_RETENTION ] = 999;
		SettingsRepository::maybe_migrate();
		$this->assertSame( RetentionSettings::MAX, SettingsRepository::get_persisted()['retention_days'] );
	}

	public function test_existing_settings_not_overwritten_by_legacy(): void {
		$GLOBALS['usp_test_options'][ SettingsRepository::OPTION_KEY ]       = array(
			'display_enabled' => false,
			'retention_days'  => 30,
		);
		$GLOBALS['usp_test_options'][ SettingsRepository::LEGACY_RETENTION ] = 90;
		SettingsRepository::maybe_migrate();
		$s = SettingsRepository::get_persisted();
		$this->assertFalse( $s['display_enabled'] );
		$this->assertSame( 30, $s['retention_days'] );
		$this->assertSame( 1, SettingsRepository::settings_version() );
	}

	public function test_migration_idempotent(): void {
		SettingsRepository::maybe_migrate();
		$first = $GLOBALS['usp_test_options'][ SettingsRepository::OPTION_KEY ];
		SettingsRepository::reset_for_tests();
		SettingsRepository::maybe_migrate();
		$this->assertSame( $first, $GLOBALS['usp_test_options'][ SettingsRepository::OPTION_KEY ] );
	}

	public function test_filter_overrides_settings_retention(): void {
		SettingsRepository::maybe_migrate();
		SettingsRepository::save( array_merge( SettingsRepository::defaults(), array( 'retention_days' => 40 ) ) );
		add_filter(
			'usp_retention_days',
			static function () {
				return 20;
			}
		);
		$this->assertSame( 20, RetentionSettings::days() );
	}

	public function test_template_filter_overrides_settings(): void {
		SettingsRepository::maybe_migrate();
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array( 'template' => 'Bought {{product}}' )
			)
		);
		add_filter(
			TemplateSettings::FILTER,
			static function () {
				return 'Filter {{product}}';
			}
		);
		$this->assertSame( 'Filter {{product}}', TemplateSettings::get() );
	}

	public function test_invalid_template_rejected_on_save(): void {
		SettingsRepository::maybe_migrate();
		$result = SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array( 'template' => 'Bad {{unknown}}' )
			)
		);
		$this->assertTrue( is_wp_error( $result ) );
	}

	public function test_product_id_normalize_dedupe_cap(): void {
		$ids = SettingsRepository::normalize_product_ids( array( 1, '1', 2, -3, 'x', 0 ) );
		$this->assertSame( array( 1, 2 ), $ids );
		$many = range( 1, 250 );
		$this->assertCount( ProductTargetingPolicy::MAX_IDS, SettingsRepository::normalize_product_ids( $many ) );
	}

	public function test_product_exclusion_filter_overrides_settings(): void {
		SettingsRepository::maybe_migrate();
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array( 'excluded_product_ids' => array( 10 ) )
			)
		);
		add_filter(
			ProductTargetingPolicy::FILTER,
			static function () {
				return array( 99 );
			}
		);
		$ids = ProductTargetingPolicy::excluded_ids();
		$this->assertArrayHasKey( 99, $ids );
		$this->assertArrayNotHasKey( 10, $ids );
	}

	public function test_oos_and_geo_filter_overrides(): void {
		SettingsRepository::maybe_migrate();
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array(
					'exclude_out_of_stock'  => true,
					'geo_weighting_enabled' => false,
				)
			)
		);
		$this->assertTrue( StockExclusionSettings::is_enabled() );
		add_filter(
			'usp_exclude_out_of_stock',
			static function () {
				return false;
			}
		);
		$this->assertFalse( StockExclusionSettings::is_enabled() );

		$adapter = new class() implements \UniversalSocialProof\Geo\GeoContextAdapter {
			public function is_available(): bool {
				return true;
			}
			public function country_code(): ?string {
				return 'SE';
			}
		};
		$this->assertNull( GeographyPolicy::visitor_country_for_selection( $adapter ) );
		add_filter(
			GeographyPolicy::FILTER,
			static function () {
				return true;
			}
		);
		$this->assertSame( 'SE', GeographyPolicy::visitor_country_for_selection( $adapter ) );
	}

	public function test_reset_to_defaults(): void {
		SettingsRepository::maybe_migrate();
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array(
					'display_enabled' => false,
					'retention_days'  => 14,
				)
			)
		);
		SettingsRepository::reset_to_defaults();
		$s = SettingsRepository::get_persisted();
		$this->assertTrue( $s['display_enabled'] );
		$this->assertSame( 60, $s['retention_days'] );
		$this->assertSame( 1, SettingsRepository::settings_version() );
	}

	public function test_display_enabled_default_true(): void {
		SettingsRepository::maybe_migrate();
		$this->assertTrue( SettingsRepository::display_enabled() );
	}
}
