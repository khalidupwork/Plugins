<?php
/**
 * API error exception.
 *
 * @package TalkwynHub
 */

namespace TWH\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown inside handlers, converted to a signed error response.
 */
final class ApiError extends \Exception {

	/**
	 * Error code.
	 *
	 * @var string
	 */
	public string $error_code;

	/**
	 * Extra signed data.
	 *
	 * @var array<string, mixed>
	 */
	public array $extra;

	/**
	 * Constructor.
	 *
	 * @param string               $error_code Code.
	 * @param string               $message    Message.
	 * @param array<string, mixed> $extra      Extra data.
	 */
	public function __construct( string $error_code, string $message, array $extra = array() ) {
		parent::__construct( $message );
		$this->error_code = $error_code;
		$this->extra      = $extra;
	}
}
