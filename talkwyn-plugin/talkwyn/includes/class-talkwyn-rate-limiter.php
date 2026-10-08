<?php
/**
 * Rate limiting by session plus IP.
 *
 * Proxy headers (Cloudflare, X-Forwarded-For) are read only when the site owner
 * turns on "Behind a proxy or Cloudflare", because visitors can fake them otherwise.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fixed-window rate limiter.
 */
class Talkwyn_Rate_Limiter {

	/**
	 * Count a hit and report whether it is allowed.
	 *
	 * @param string $bucket   Bucket name (chat, lead).
	 * @param string $identity Who is counted.
	 * @param int    $limit    Hits per window.
	 * @param int    $window   Window in seconds.
	 * @return bool
	 */
	public static function hit( $bucket, $identity, $limit, $window = 3600 ) {
		$key  = 'talkwyn_rl_' . md5( $bucket . '|' . $identity . '|' . self::salt() );
		$data = get_transient( $key );
		$now  = time();
		if ( ! is_array( $data ) || ( $now - (int) ( $data['start'] ?? 0 ) ) >= $window ) {
			$data = array(
				'start' => $now,
				'count' => 0,
			);
		}
		if ( (int) $data['count'] >= max( 1, (int) $limit ) ) {
			return false;
		}
		++$data['count'];
		set_transient( $key, $data, max( 1, $window - ( $now - (int) $data['start'] ) ) );
		return true;
	}

	/**
	 * Chat limit: per session and IP, plus a wider per-IP cap so a visitor
	 * cannot dodge the limit by starting new sessions.
	 *
	 * @param string $session Session ID.
	 * @param int    $limit   Messages per hour.
	 * @param bool   $trusted Read proxy headers.
	 * @param array  $server  $_SERVER (for tests).
	 * @return bool
	 */
	public static function allow_chat( $session, $limit, $trusted = false, $server = null ) {
		$ip = self::client_ip( $trusted, $server );
		if ( ! self::hit( 'chat', $session . '@' . $ip, $limit ) ) {
			return false;
		}
		return self::hit( 'chat-ip', $ip, max( $limit, $limit * 3 ) );
	}

	/**
	 * Visitor IP.
	 *
	 * @param bool       $trusted Read proxy headers.
	 * @param array|null $server  Server vars (for tests).
	 * @return string
	 */
	public static function client_ip( $trusted = false, $server = null ) {
		$server = null === $server ? $_SERVER : $server; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$ip     = self::valid_ip( $server['REMOTE_ADDR'] ?? '' );
		if ( $trusted ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ) as $header ) {
				if ( empty( $server[ $header ] ) ) {
					continue;
				}
				$first     = trim( explode( ',', (string) $server[ $header ] )[0] );
				$candidate = self::valid_ip( $first );
				if ( '' !== $candidate ) {
					$ip = $candidate;
					break;
				}
			}
		}
		return '' !== $ip ? $ip : 'unknown';
	}

	/**
	 * Validate an IP.
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	private static function valid_ip( $ip ) {
		$ip = trim( (string) $ip );
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Salt so stored keys do not reveal IPs.
	 *
	 * @return string
	 */
	private static function salt() {
		return function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : 'talkwyn';
	}
}
