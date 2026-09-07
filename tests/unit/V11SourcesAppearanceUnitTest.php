<?php
/**
 * V1.1 sources, appearance, templates, and settings migration unit tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UniversalSocialProof\Cleanup\CartRetentionSettings;
use UniversalSocialProof\Settings\SettingsRepository;
use UniversalSocialProof\Storage\EventType;
use UniversalSocialProof\Template\TemplateSettings;

final class V11SourcesAppearanceUnitTest extends TestCase {

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

	public function test_event_type_constants(): void {
		$this->assertTrue( EventType::is_valid( EventType::PURCHASE ) );
		$this->assertTrue( EventType::is_valid( EventType::ADD_TO_CART ) );
		$this->assertFalse( EventType::is_valid( 'fake' ) );
		$this->assertFalse( EventType::is_valid( '' ) );
	}

	public function test_v11_defaults_purchase_on_cart_off(): void {
		SettingsRepository::maybe_migrate();
		$s = SettingsRepository::get_persisted();
		$this->assertTrue( $s['purchase_enabled'] );
		$this->assertFalse( $s['cart_enabled'] );
		$this->assertSame( 60, $s['cart_retention_minutes'] );
		$this->assertSame( 'bottom-left', $s['appearance_position'] );
		$this->assertTrue( $s['appearance_show_product_image'] );
		$this->assertTrue( $s['appearance_show_close'] );
		$this->assertSame( '', $s['appearance_custom_css'] );
		$this->assertSame( 2, SettingsRepository::settings_version() );
	}

	public function test_settings_v1_array_upgrades_to_v2_without_overwrite(): void {
		$GLOBALS['usp_test_options'][ SettingsRepository::OPTION_KEY ]  = array(
			'display_enabled' => false,
			'retention_days'  => 21,
			'template'        => 'Bought {{product}}',
		);
		$GLOBALS['usp_test_options'][ SettingsRepository::VERSION_KEY ] = 1;
		SettingsRepository::maybe_migrate();
		$s = SettingsRepository::get_persisted();
		$this->assertFalse( $s['display_enabled'] );
		$this->assertSame( 21, $s['retention_days'] );
		$this->assertSame( 'Bought {{product}}', $s['template'] );
		$this->assertFalse( $s['cart_enabled'] );
		$this->assertTrue( $s['purchase_enabled'] );
		$this->assertSame( CartRetentionSettings::DEFAULT, $s['cart_retention_minutes'] );
		$this->assertSame( 2, SettingsRepository::settings_version() );
	}

	public function test_cart_retention_clamped(): void {
		$this->assertSame( 5, SettingsRepository::normalize_cart_retention_minutes( 1 ) );
		$this->assertSame( 1440, SettingsRepository::normalize_cart_retention_minutes( 99999 ) );
		$this->assertSame( 60, SettingsRepository::normalize_cart_retention_minutes( '60' ) );
	}

	public function test_appearance_normalize_malformed(): void {
		$n = SettingsRepository::normalize(
			array(
				'appearance_position'   => 'top-left',
				'appearance_background' => 'red',
				'appearance_text'       => '#gg0000',
				'appearance_accent'     => '#0bf',
				'appearance_radius'     => 99,
				'appearance_shadow'     => 'huge',
			)
		);
		$this->assertSame( 'bottom-left', $n['appearance_position'] );
		$this->assertSame( '#ffffff', $n['appearance_background'] );
		$this->assertSame( '#111111', $n['appearance_text'] );
		$this->assertSame( '#00bbff', $n['appearance_accent'] );
		$this->assertSame( 32, $n['appearance_radius'] );
		$this->assertSame( 'soft', $n['appearance_shadow'] );
	}

	public function test_custom_css_strips_tags_and_bounds(): void {
		$css = SettingsRepository::normalize_custom_css( '.usp-toaster { color: red; } <script>alert(1)</script>' );
		$this->assertStringContainsString( '.usp-toaster', $css );
		$this->assertStringNotContainsString( '<script>', $css );
		$this->assertStringNotContainsString( 'alert', $css );

		$long = str_repeat( 'a', SettingsRepository::CUSTOM_CSS_MAX + 50 );
		$this->assertSame( SettingsRepository::CUSTOM_CSS_MAX, strlen( SettingsRepository::normalize_custom_css( $long ) ) );
	}

	public function test_country_fallback_templates(): void {
		$this->assertTrue( TemplateSettings::needs_country_fallback( TemplateSettings::default_purchase_template(), null ) );
		$this->assertFalse( TemplateSettings::needs_country_fallback( TemplateSettings::default_purchase_template(), 'SE' ) );
		$this->assertSame(
			TemplateSettings::fallback_purchase_template(),
			TemplateSettings::resolve_for_event( EventType::PURCHASE, null )
		);
		$this->assertSame(
			TemplateSettings::fallback_cart_template(),
			TemplateSettings::resolve_for_event( EventType::ADD_TO_CART, '' )
		);
		// When a label cannot be resolved (unit env has no WC countries), resolve_for_event
		// must still pick the grammatical fallback rather than a broken "in " phrase.
		$resolved = TemplateSettings::resolve_for_event( EventType::ADD_TO_CART, 'SE' );
		$this->assertTrue(
			TemplateSettings::default_cart_template() === $resolved
			|| TemplateSettings::fallback_cart_template() === $resolved
		);
		$this->assertStringNotContainsString( 'Someone in  ', $resolved );
	}

	public function test_purchase_and_cart_source_toggles(): void {
		SettingsRepository::maybe_migrate();
		SettingsRepository::save(
			array_merge(
				SettingsRepository::defaults(),
				array(
					'purchase_enabled' => false,
					'cart_enabled'     => true,
				)
			)
		);
		$this->assertFalse( SettingsRepository::purchase_enabled() );
		$this->assertTrue( SettingsRepository::cart_enabled() );
	}
}
