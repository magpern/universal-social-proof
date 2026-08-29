<?php
/**
 * Minimal WooCommerce logger wrapper.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof;

defined( 'ABSPATH' ) || exit;

/**
 * Operator logs without PII.
 */
final class Logger {

	public const SOURCE = 'universal-social-proof';

	/**
	 * Log a warning without PII.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Non-PII context.
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Log an error without PII.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Non-PII context.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Write to the WooCommerce logger.
	 *
	 * @param string               $level   Log level.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Non-PII context.
	 */
	private static function log( string $level, string $message, array $context ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		$logger = wc_get_logger();
		$extra  = $context ? ' ' . wp_json_encode( $context ) : '';
		$logger->log( $level, $message . $extra, array( 'source' => self::SOURCE ) );
	}
}
