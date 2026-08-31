<?php
/**
 * M4 template unit tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use UniversalSocialProof\Product\PublicProduct;
use UniversalSocialProof\Selection\SelectedEvent;
use UniversalSocialProof\Storage\Quantity;
use UniversalSocialProof\Template\RelativeTimeFormatter;
use UniversalSocialProof\Template\TemplateContext;
use UniversalSocialProof\Template\TemplateRenderer;
use UniversalSocialProof\Template\TemplateSettings;

final class M4TemplateUnitTest extends TestCase {

	/** @var TemplateRenderer */
	private TemplateRenderer $renderer;

	protected function setUp(): void {
		parent::setUp();
		$this->renderer = new TemplateRenderer();
	}

	public function test_quantity_display_matrix(): void {
		$this->assertSame( '1', Quantity::format_display( '1.000000' ) );
		$this->assertSame( '2', Quantity::format_display( '2.000000' ) );
		$this->assertSame( '1.5', Quantity::format_display( '1.500000' ) );
		$this->assertSame( '0.25', Quantity::format_display( '0.250000' ) );
		$this->assertSame( '10', Quantity::format_display( '10.000000' ) );
	}

	public function test_default_template_product_only(): void {
		$ctx    = $this->context( 'Tirzepatide 10mg' );
		$result = $this->renderer->render( TemplateSettings::default_template(), $ctx );
		$this->assertNotNull( $result );
		$this->assertSame( 'Someone purchased Tirzepatide 10mg', $result->message );
		$this->assertFalse( $result->used_time_ago );
	}

	public function test_country_and_location_alias(): void {
		$ctx = new TemplateContext( 'P', 'Sweden', '1', true, new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
		$c   = $this->renderer->render( 'From {{country}}', $ctx );
		$l   = $this->renderer->render( 'From {{location}}', $ctx );
		$this->assertSame( 'From Sweden', $c->message );
		$this->assertSame( $c->message, $l->message );
	}

	public function test_empty_optional_country_substitutes_empty(): void {
		$ctx    = $this->context( 'P', '' );
		$result = $this->renderer->render( 'In {{country}} bought {{product}}', $ctx );
		$this->assertNotNull( $result );
		$this->assertSame( 'In  bought P', $result->message );
	}

	public function test_quantity_token(): void {
		$ctx    = $this->context( 'P', '', '1.500000' );
		$result = $this->renderer->render( 'Bought {{quantity}} of {{product}}', $ctx );
		$this->assertSame( 'Bought 1.5 of P', $result->message );
	}

	public function test_invalid_quantity_when_used_fails(): void {
		$ctx = new TemplateContext( 'P', '', '', false, new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
		$this->assertNull( $this->renderer->render( 'Qty {{quantity}}', $ctx ) );
	}

	public function test_invalid_quantity_ignored_when_unused(): void {
		$ctx    = new TemplateContext( 'P', '', '', false, new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
		$result = $this->renderer->render( 'Someone purchased {{product}}', $ctx );
		$this->assertNotNull( $result );
	}

	public function test_time_ago_sets_used_flag(): void {
		$occurred = new DateTimeImmutable( '2026-08-30 18:00:00', new DateTimeZone( 'UTC' ) );
		$now      = new DateTimeImmutable( '2026-08-30 18:10:00', new DateTimeZone( 'UTC' ) );
		$ctx      = new TemplateContext( 'P', '', '1', true, $occurred );
		$phrase   = RelativeTimeFormatter::format( $occurred, $now );
		$this->assertSame( '10 minutes ago', $phrase );

		// Renderer uses "now"; use a recent occurred_at so it succeeds.
		$recent = new DateTimeImmutable( '-10 minutes', new DateTimeZone( 'UTC' ) );
		$ctx2   = new TemplateContext( 'P', '', '1', true, $recent );
		$result = $this->renderer->render( '{{product}} {{time_ago}}', $ctx2 );
		$this->assertNotNull( $result );
		$this->assertTrue( $result->used_time_ago );
		$this->assertStringContainsString( 'P ', $result->message );
	}

	public function test_no_time_token_used_time_false(): void {
		$result = $this->renderer->render( 'Someone purchased {{product}}', $this->context( 'P' ) );
		$this->assertFalse( $result->used_time_ago );
	}

	public function test_repeated_tokens(): void {
		$result = $this->renderer->render( '{{product}} and {{product}}', $this->context( 'X' ) );
		$this->assertSame( 'X and X', $result->message );
	}

	public function test_unknown_token_fails(): void {
		$this->assertNull( TemplateSettings::validate_template( 'Hi {{buyer}}' ) );
		$this->assertNull( $this->renderer->render( 'Hi {{buyer}}', $this->context( 'P' ) ) );
	}

	public function test_malformed_braces_fail(): void {
		$this->assertNull( TemplateSettings::validate_template( 'Hi {product}' ) );
		$this->assertNull( TemplateSettings::validate_template( 'Hi {{product}' ) );
		$this->assertNull( TemplateSettings::validate_template( 'Hi {{}}' ) );
	}

	public function test_empty_product_fails(): void {
		$ctx = new TemplateContext( '', '', '1', true, new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
		$this->assertNull( $this->renderer->render( 'Someone purchased {{product}}', $ctx ) );
	}

	public function test_html_looking_and_ampersand_product_remain_plain(): void {
		foreach ( array( 'A&B', '"Quoted Product"', '<em>Product</em>', 'Tirzepatide™ 10mg' ) as $name ) {
			$result = $this->renderer->render( 'Someone purchased {{product}}', $this->context( $name ) );
			$this->assertNotNull( $result );
			$this->assertSame( 'Someone purchased ' . $name, $result->message );
			$this->assertStringNotContainsString( '&amp;', $result->message );
		}
	}

	public function test_max_template_length(): void {
		$long = str_repeat( 'a', 501 );
		$this->assertNull( TemplateSettings::validate_template( $long ) );
	}

	public function test_selected_event_projection_show_relative_time(): void {
		$product = new PublicProduct( 1, 'simple', 'https://example.test/p', null, 'Demo', true, 1 );
		$event   = new SelectedEvent(
			'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
			gmdate( 'Y-m-d H:i:s', time() - 120 ),
			null,
			'1.000000',
			1,
			null,
			$product
		);
		$dto     = $event->to_public_array();
		$this->assertTrue( $dto['show_relative_time'] );
		$this->assertSame( 'Someone purchased Demo', $dto['message'] );
	}

	public function test_no_persisted_template_option_constant(): void {
		$src  = dirname( __DIR__, 2 ) . '/src/Template';
		$scan = '';
		foreach ( glob( $src . '/*.php' ) as $file ) {
			$scan .= (string) file_get_contents( $file );
		}
		$this->assertStringNotContainsString( 'OPTION_KEY', $scan );
		$this->assertStringNotContainsString( 'get_option', $scan );
		$this->assertStringNotContainsString( 'usp_notification_template_option', $scan );
	}

	/**
	 * @param string $product Product name.
	 * @param string $country Country label.
	 * @param string $qty     Stored quantity.
	 */
	private function context( string $product, string $country = '', string $qty = '1.000000' ): TemplateContext {
		return new TemplateContext(
			$product,
			$country,
			Quantity::format_display( $qty ),
			Quantity::is_positive( $qty ),
			new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) )
		);
	}
}
