<?php
/**
 * Successful template render result.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text message plus time-token consumption metadata.
 */
final class RenderResult {

	/**
	 * Constructor.
	 *
	 * @param string $message       Non-empty plain-text message.
	 * @param bool   $used_time_ago Whether {{time_ago}} was substituted.
	 */
	public function __construct(
		public readonly string $message,
		public readonly bool $used_time_ago
	) {}
}
