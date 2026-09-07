<?php
/**
 * Event type constants for usp_events.event_type.
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

namespace UniversalSocialProof\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Controlled vocabulary for purchase vs add-to-cart events (ADR-0018).
 */
final class EventType {

	public const PURCHASE    = 'purchase';
	public const ADD_TO_CART = 'add_to_cart';

	/**
	 * Whether a value is a known event type.
	 *
	 * @param string $type Candidate type.
	 */
	public static function is_valid( string $type ): bool {
		return self::PURCHASE === $type || self::ADD_TO_CART === $type;
	}
}
