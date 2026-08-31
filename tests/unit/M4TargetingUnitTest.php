<?php
/**
 * M4 targeting unit tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UniversalSocialProof\Product\PublicProduct;
use UniversalSocialProof\Targeting\ProductTargetingPolicy;
use UniversalSocialProof\Targeting\TargetingPolicy;

final class M4TargetingUnitTest extends TestCase {

	protected function tearDown(): void {
		remove_all_filters( ProductTargetingPolicy::FILTER );
		parent::tearDown();
	}

	public function test_default_exclusion_empty(): void {
		$this->assertSame( array(), ProductTargetingPolicy::excluded_ids() );
		$product = new PublicProduct( 9, 'simple', 'https://example.test/p', null, 'P', true, 9 );
		$this->assertFalse( ProductTargetingPolicy::is_excluded( $product ) );
	}

	public function test_filter_validation_dedupe_and_parent(): void {
		add_filter(
			ProductTargetingPolicy::FILTER,
			static function () {
				return array( 5, 5, -1, 'nope', '12', 0, 7 );
			}
		);
		$ids = ProductTargetingPolicy::excluded_ids();
		$this->assertSame(
			array(
				5  => true,
				12 => true,
				7  => true,
			),
			$ids
		);

		$simple = new PublicProduct( 5, 'simple', 'https://example.test/p', null, 'P', true, 5 );
		$this->assertTrue( ProductTargetingPolicy::is_excluded( $simple ) );

		$child = new PublicProduct( 99, 'variation', 'https://example.test/v', null, 'V', true, 12 );
		$this->assertTrue( ProductTargetingPolicy::is_excluded( $child ) );

		$other = new PublicProduct( 3, 'simple', 'https://example.test/o', null, 'O', true, 3 );
		$this->assertFalse( ProductTargetingPolicy::is_excluded( $other ) );
	}

	public function test_hard_cap(): void {
		$ids = range( 1, 250 );
		add_filter(
			ProductTargetingPolicy::FILTER,
			static function () use ( $ids ) {
				return $ids;
			}
		);
		$this->assertCount( ProductTargetingPolicy::MAX_IDS, ProductTargetingPolicy::excluded_ids() );
	}

	public function test_resolver_does_not_import_product_targeting(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Product/PublicProductResolver.php' );
		$this->assertStringNotContainsString( 'ProductTargetingPolicy', $src );
		$this->assertStringNotContainsString( 'usp_excluded_product_ids', $src );
	}

	public function test_no_persisted_exclusion_option(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Targeting/ProductTargetingPolicy.php' );
		$this->assertStringNotContainsString( 'get_option', $src );
		$this->assertStringNotContainsString( 'OPTION_KEY', $src );
	}

	public function test_targeting_policy_exists(): void {
		$this->assertTrue( method_exists( TargetingPolicy::class, 'should_load' ) );
	}
}
