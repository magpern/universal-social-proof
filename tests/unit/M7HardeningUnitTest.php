<?php
/**
 * M7 hardening unit tests.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use UniversalSocialProof\Frontend\ShellRenderer;
use UniversalSocialProof\Logger;
use UniversalSocialProof\Selection\CandidateQuery;
use UniversalSocialProof\Selection\ProductResolutionBudget;

final class M7HardeningUnitTest extends TestCase {

	public function test_logger_context_allowlist_drops_pii_keys(): void {
		$method = new ReflectionMethod( Logger::class, 'sanitize_context' );
		$safe   = $method->invoke(
			null,
			array(
				'order_id' => 42,
				'item_id'  => 7,
				'error'    => 'boom',
				'email'    => 'buyer@example.com',
				'ip'       => '1.2.3.4',
				'name'     => 'Alice',
			)
		);
		$this->assertSame(
			array(
				'order_id' => 42,
				'item_id'  => 7,
				'error'    => 'boom',
			),
			$safe
		);
	}

	public function test_shell_renderer_includes_dismiss_aria_label(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Frontend/ShellRenderer.php' );
		$this->assertStringContainsString( 'usp-toaster__dismiss', $src );
		$this->assertStringContainsString( 'aria-label', $src );
		$this->assertStringContainsString( 'Dismiss notification', $src );
		unset( $src );
		$this->assertTrue( class_exists( ShellRenderer::class ) );
	}

	public function test_frozen_performance_budgets(): void {
		$this->assertSame( 20, ProductResolutionBudget::MAX );
		$this->assertSame( 5, ProductResolutionBudget::PDP_SEARCH_CAP );
		$this->assertSame( 80, CandidateQuery::GLOBAL_LIMIT );
		$this->assertSame( 20, CandidateQuery::PREFERRED_LIMIT );
		$this->assertSame( 20, CandidateQuery::EXCLUDE_MAX );
		$this->assertSame( 10, \UniversalSocialProof\Selection\SelectionRequest::LIMIT_MAX );
	}

	public function test_no_sql_rand_in_selection(): void {
		$files = glob( dirname( __DIR__, 2 ) . '/src/Selection/*.php' );
		$this->assertNotFalse( $files );
		foreach ( $files as $file ) {
			$src = (string) file_get_contents( $file );
			$this->assertDoesNotMatchRegularExpression( '/ORDER\s+BY\s+RAND\s*\(/i', $src, basename( $file ) );
			$this->assertDoesNotMatchRegularExpression( '/SELECT\s+RAND\s*\(/i', $src, basename( $file ) );
		}
	}
}
