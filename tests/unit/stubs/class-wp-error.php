<?php
/**
 * Minimal WP_Error stub for unit tests (WordPress not loaded).
 *
 * @package UniversalSocialProof
 */

declare( strict_types=1 );

/**
 * Minimal WP_Error stub.
 */
class WP_Error {

	/**
	 * Error code.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * @param string $code    Code.
	 * @param string $message Message.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * Error message accessor.
	 */
	public function get_error_message() {
		return $this->message;
	}
}
