<?php
/**
 * Event status and suppress-reason constants.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Controlled vocabulary for usp_events.status / suppress_reason.
 */
final class EventStatus {

	public const ACTIVE     = 'active';
	public const SUPPRESSED = 'suppressed';

	public const REASON_CANCELLED     = 'cancelled';
	public const REASON_FAILED        = 'failed';
	public const REASON_REFUND_FULL   = 'refund_full';
	public const REASON_LINE_REMOVED  = 'line_removed';
	public const REASON_ORDER_DELETED = 'order_deleted';
}
