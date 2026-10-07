<?php
/**
 * Short-lived, HMAC-signed download tokens.
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

/**
 * Token = base64url(payload JSON) "." base64url(HMAC-SHA256(payload)).
 * Payload: p (purpose), l (license id), r (release id), e (expiry), n (random nonce).
 */
final class DownloadToken {

	public const PURPOSE = 'dl';
	public const TTL     = 600;

	/**
	 * Create a token.
	 *
	 * @param int    $license_id License id.
	 * @param int    $release_id Release id.
	 * @param string $secret     HMAC secret (raw bytes).
	 * @param int    $now        Current time.
	 * @param int    $ttl        Lifetime in seconds.
	 */
	public static function create( int $license_id, int $release_id, string $secret, int $now, int $ttl = self::TTL ): string {
		$payload = (string) json_encode(
			array(
				'p' => self::PURPOSE,
				'l' => $license_id,
				'r' => $release_id,
				'e' => $now + $ttl,
				'n' => bin2hex( random_bytes( 8 ) ),
			)
		);
		$body    = self::b64url_encode( $payload );
		return $body . '.' . self::b64url_encode( hash_hmac( 'sha256', $body, $secret, true ) );
	}

	/**
	 * Validate a token.
	 *
	 * @param string $token  Token.
	 * @param string $secret HMAC secret.
	 * @param int    $now    Current time.
	 * @return array{license_id: int, release_id: int, expires: int}|null Null when invalid or expired.
	 */
	public static function validate( string $token, string $secret, int $now ): ?array {
		if ( strlen( $token ) > 512 || 1 !== substr_count( $token, '.' ) ) {
			return null;
		}
		list( $body, $mac ) = explode( '.', $token );
		$expected           = self::b64url_encode( hash_hmac( 'sha256', $body, $secret, true ) );
		if ( ! hash_equals( $expected, $mac ) ) {
			return null;
		}
		$payload = json_decode( (string) self::b64url_decode( $body ), true );
		if ( ! is_array( $payload )
			|| ( $payload['p'] ?? '' ) !== self::PURPOSE
			|| ! isset( $payload['l'], $payload['r'], $payload['e'] )
			|| ! is_int( $payload['l'] ) || ! is_int( $payload['r'] ) || ! is_int( $payload['e'] ) ) {
			return null;
		}
		if ( $payload['e'] < $now ) {
			return null;
		}
		return array(
			'license_id' => $payload['l'],
			'release_id' => $payload['r'],
			'expires'    => $payload['e'],
		);
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $data Data.
	 */
	public static function b64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url decode.
	 *
	 * @param string $data Data.
	 * @return string|false
	 */
	public static function b64url_decode( string $data ) {
		return base64_decode( strtr( $data, '-_', '+/' ), true );
	}
}
