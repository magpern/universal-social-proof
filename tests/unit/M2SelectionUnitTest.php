<?php
/**
 * M2 unit tests — selection constants, budget, DTO, no M3+ surface.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UniversalSocialProof\Product\PublicProduct;
use UniversalSocialProof\Product\PublicProductResolver;
use UniversalSocialProof\Selection\Candidate;
use UniversalSocialProof\Selection\CandidateQuery;
use UniversalSocialProof\Selection\ProductResolutionBudget;
use UniversalSocialProof\Selection\SelectedEvent;
use UniversalSocialProof\Selection\SelectionRequest;

final class M2SelectionUnitTest extends TestCase {

	public function test_candidate_query_caps(): void {
		$global = CandidateQuery::global( '2026-01-01 00:00:00', array() );
		$this->assertSame( 80, $global->limit() );
		$this->assertFalse( $global->is_preferred() );

		$preferred = CandidateQuery::preferred( '2026-01-01 00:00:00', array(), 42 );
		$this->assertSame( 20, $preferred->limit() );
		$this->assertTrue( $preferred->is_preferred() );
		$this->assertSame( 42, $preferred->product_id() );
	}

	public function test_candidate_query_exclude_dedupes_and_caps(): void {
		$ids = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$ids[] = 'id-' . $i;
		}
		$ids[] = 'id-0';
		$query = CandidateQuery::global( '2026-01-01 00:00:00', $ids );
		$this->assertCount( 20, $query->exclude_public_ids() );
	}

	public function test_candidate_from_row_skips_malformed(): void {
		$this->assertNull( Candidate::from_row( array() ) );
		$this->assertNull( Candidate::from_row( array( 'public_id' => 'x' ) ) );
		$ok = Candidate::from_row(
			array(
				'public_id'    => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
				'product_id'   => 9,
				'variation_id' => null,
				'quantity'     => '2.000000',
				'country_code' => 'SE',
				'occurred_at'  => '2026-08-30 18:42:11',
			)
		);
		$this->assertInstanceOf( Candidate::class, $ok );
		$this->assertSame( 9, $ok->product_id );
		$this->assertNull( $ok->variation_id );
	}

	public function test_limit_clamp_and_page_context(): void {
		$this->assertSame( 1, SelectionRequest::clamp_limit( 0 ) );
		$this->assertSame( 1, SelectionRequest::clamp_limit( -10 ) );
		$this->assertSame( 10, SelectionRequest::clamp_limit( 11 ) );
		$this->assertSame( 10, SelectionRequest::clamp_limit( 999 ) );
		$this->assertSame( 5, SelectionRequest::clamp_limit( 5 ) );
		$this->assertSame( SelectionRequest::CONTEXT_UNKNOWN, SelectionRequest::normalize_page_context( 'checkout' ) );
		$this->assertSame( SelectionRequest::CONTEXT_PRODUCT, SelectionRequest::normalize_page_context( 'product' ) );
		$this->assertSame( SelectionRequest::CONTEXT_UNKNOWN, SelectionRequest::normalize_page_context( array( 'product' ) ) );
	}

	public function test_pdp_request_requires_product_context_and_id(): void {
		$pdp = new SelectionRequest( 5, 12, SelectionRequest::CONTEXT_PRODUCT, array() );
		$this->assertTrue( $pdp->is_pdp() );
		$unknown = new SelectionRequest( 5, 12, SelectionRequest::CONTEXT_UNKNOWN, array() );
		$this->assertFalse( $unknown->is_pdp() );
		$no_id = new SelectionRequest( 5, null, SelectionRequest::CONTEXT_PRODUCT, array() );
		$this->assertFalse( $no_id->is_pdp() );
	}

	public function test_product_resolution_budget_hard_cap(): void {
		$budget   = new ProductResolutionBudget();
		$calls    = 0;
		$resolver = new PublicProductResolver(
			$budget,
			static function ( int $id ) use ( &$calls ) {
				++$calls;
				unset( $id );
				return false;
			}
		);

		for ( $i = 1; $i <= 25; $i++ ) {
			$resolver->get_product( $i );
		}
		$this->assertSame( ProductResolutionBudget::MAX, $budget->used() );
		$this->assertSame( ProductResolutionBudget::MAX, $calls );
		$this->assertFalse( $budget->can_consume() );

		$resolver->get_product( 1 );
		$this->assertSame( ProductResolutionBudget::MAX, $calls );
	}

	public function test_memoized_repeat_id_does_not_consume_budget(): void {
		$budget   = new ProductResolutionBudget();
		$calls    = 0;
		$resolver = new PublicProductResolver(
			$budget,
			static function ( int $id ) use ( &$calls ) {
				++$calls;
				unset( $id );
				return false;
			}
		);
		$resolver->get_product( 7 );
		$resolver->get_product( 7 );
		$resolver->get_product( 7 );
		$this->assertSame( 1, $budget->used() );
		$this->assertSame( 1, $calls );
	}

	public function test_selected_event_dto_is_utc_z_allowlist_without_message(): void {
		$product = new PublicProduct( 3, 'simple', 'https://example.test/p', null, 'Name', true, 3 );
		$event   = new SelectedEvent(
			'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
			'2026-08-30 18:42:11',
			'SE',
			'1.000000',
			3,
			null,
			$product
		);
		$dto     = $event->to_public_array();
		$this->assertIsArray( $dto );
		$this->assertSame(
			array( 'public_id', 'product_url', 'thumbnail_url', 'occurred_at' ),
			array_keys( $dto )
		);
		$this->assertArrayNotHasKey( 'message', $dto );
		$this->assertSame( '2026-08-30T18:42:11Z', $dto['occurred_at'] );
		$this->assertNull( $dto['thumbnail_url'] );
	}

	public function test_malformed_occurred_at_is_not_serialized(): void {
		$product = new PublicProduct( 3, 'simple', 'https://example.test/p', null, 'Name', true, 3 );
		$event   = new SelectedEvent( 'id', 'not-a-datetime', null, '1', 3, null, $product );
		$this->assertNull( $event->to_public_array() );
	}

	public function test_m3_plus_packages_are_absent(): void {
		$src = dirname( __DIR__, 2 ) . '/src';
		foreach ( array( 'Frontend', 'Template', 'Geo', 'Admin' ) as $dir ) {
			$this->assertDirectoryDoesNotExist( $src . '/' . $dir );
		}
		$scan = '';
		foreach ( $this->php_files( $src ) as $file ) {
			$scan .= (string) file_get_contents( $file );
		}
		$scan .= (string) file_get_contents( dirname( __DIR__, 2 ) . '/universal-social-proof.php' );
		$this->assertStringNotContainsString( '{{product}}', $scan );
		$this->assertStringNotContainsString( '{{country}}', $scan );
		$this->assertStringNotContainsString( '{{time_ago}}', $scan );
		$this->assertStringNotContainsString( '{{quantity}}', $scan );
		$this->assertStringNotContainsString( 'sessionStorage', $scan );
		$this->assertStringNotContainsString( 'GeoContextAdapter', $scan );
		$this->assertStringNotContainsString( 'wp_enqueue_script', $scan );
	}

	/**
	 * @return list<string>
	 */
	private function php_files( string $dir ): array {
		$out   = array();
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir ) );
		foreach ( $files as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$out[] = $file->getPathname();
			}
		}
		return $out;
	}
}
