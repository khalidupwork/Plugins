<?php
/**
 * Request helpers.
 *
 * @package TalkwynHub
 */

namespace TWH\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Client IP detection.
 */
final class Request {

	/**
	 * Client IP. Uses REMOTE_ADDR only; behind a proxy/CDN, use the
	 * `twh_client_ip` filter to read a trusted header (e.g. CF-Connecting-IP).
	 */
	public static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * Filter the detected client IP.
		 *
		 * @param string $ip IP from REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'twh_client_ip', $ip );
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
