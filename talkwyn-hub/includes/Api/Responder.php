<?php
/**
 * Signed API responses.
 *
 * @package TalkwynHub
 */

namespace TWH\Api;

use TWH\Domain\Signer;
use TWH\Support\SigningKeys;

defined( 'ABSPATH' ) || exit;

/**
 * Every response carries `data` (with server_time and the echoed nonce) and an
 * Ed25519 signature over the canonical JSON of `data`.
 */
final class Responder {

	/**
	 * HTTP status per error code.
	 */
	public const HTTP = array(
		'bad_request'   => 400,
		'invalid_key'   => 404,
		'wrong_product' => 400,
		'expired'       => 403,
		'revoked'       => 403,
		'suspended'     => 403,
		'limit_reached' => 403,
		'rate_limited'  => 429,
		'not_found'     => 404,
		'server_error'  => 500,
	);

	/**
	 * Success response.
	 *
	 * @param array<string, mixed> $data  Data.
	 * @param string               $nonce Client nonce.
	 */
	public static function success( array $data, string $nonce ): \WP_REST_Response {
		$data['server_time'] = time();
		$data['nonce']       = $nonce;
		return self::signed(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Error response. `data` holds the code, server_time and nonce so clients can
	 * verify that a "revoked"/"expired" answer really came from the hub.
	 *
	 * @param string               $code    Error code.
	 * @param string               $message Human readable message.
	 * @param string               $nonce   Client nonce ('' if unknown).
	 * @param array<string, mixed> $extra   Extra signed data (e.g. renew_url).
	 */
	public static function error( string $code, string $message, string $nonce = '', array $extra = array() ): \WP_REST_Response {
		$data = array_merge(
			$extra,
			array(
				'error_code'  => $code,
				'server_time' => time(),
				'nonce'       => $nonce,
			)
		);
		return self::signed(
			array(
				'success' => false,
				'error'   => array(
					'code'    => $code,
					'message' => $message,
				),
				'data'    => $data,
			),
			self::HTTP[ $code ] ?? 400
		);
	}

	/**
	 * Attach the signature and no-cache headers.
	 *
	 * @param array<string, mixed> $body   Body with a `data` key.
	 * @param int                  $status HTTP status.
	 */
	private static function signed( array $body, int $status ): \WP_REST_Response {
		try {
			$key               = SigningKeys::active_secret();
			$body['signature'] = Signer::sign( $body['data'], $key['secret'] );
			$body['key_id']    = $key['kid'];
		} catch ( \Throwable $e ) {
			$body   = array(
				'success' => false,
				'error'   => array(
					'code'    => 'server_error',
					'message' => 'Signing unavailable.',
				),
			);
			$status = 500;
		}
		$response = new \WP_REST_Response( $body, $status );
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}
}
