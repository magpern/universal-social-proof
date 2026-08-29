<?php
/**
 * Unit tests for Quantity and CountryExtractor.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UniversalSocialProof\Capture\CountryExtractor;
use UniversalSocialProof\Storage\Quantity;

final class QuantityAndCountryUnitTest extends TestCase {

	public function test_quantity_format_preserves_fraction(): void {
		$this->assertSame( '0.100000', Quantity::format( 0.1 ) );
		$this->assertSame( '0.500000', Quantity::format( 0.5 ) );
		$this->assertSame( '3.000000', Quantity::format( 3 ) );
	}

	public function test_quantity_full_refund_float_safe(): void {
		$this->assertTrue( Quantity::is_fully_refunded( 0.3, 0.1 + 0.2 ) );
		$this->assertFalse( Quantity::is_fully_refunded( 3, 1 ) );
		$this->assertTrue( Quantity::is_fully_refunded( 3, 3 ) );
		$this->assertTrue( Quantity::is_fully_refunded( '0.100000', 0.1 ) );
	}

	public function test_quantity_positive(): void {
		$this->assertTrue( Quantity::is_positive( 0.1 ) );
		$this->assertFalse( Quantity::is_positive( 0 ) );
		$this->assertFalse( Quantity::is_positive( -1 ) );
	}

	public function test_country_normalize(): void {
		$this->assertSame( 'SE', CountryExtractor::normalize( ' se ' ) );
		$this->assertNull( CountryExtractor::normalize( '' ) );
		$this->assertNull( CountryExtractor::normalize( 'SWE' ) );
		$this->assertNull( CountryExtractor::normalize( '12' ) );
	}
}
