<?php
/**
 * Ed25519 response signing.
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

/**
 * Canonical JSON + detached Ed25519 signatures (libsodium).
 *
 * Canonical JSON rules (the client must apply the same):
 * 1. Decode the JSON into associative arrays (empty objects become empty arrays).
 * 2. Recursively sort object keys by byte order (ksort, SORT_STRING); lists keep their order.
 * 3. Encode with JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, no whitespace.
 * Floats are not used in signed payloads.
 */
final class Signer {

	/**
	 * Canonical JSON of a value.
	 *
	 * @param mixed $data Data.
	 */
	public static function canonical_json( $data ): string {
		// Round-trip normalizes objects to arrays and drops non-JSON types.
		$normalized = json_decode( (string) json_encode( $data ), true );
		$normalized = self::sort_keys( $normalized );
		return (string) json_encode( $normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Recursively sort keys of associative arrays.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function sort_keys( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( ! self::is_list( $value ) ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $k => $v ) {
			$value[ $k ] = self::sort_keys( $v );
		}
		return $value;
	}

	/**
	 * PHP 8.0-compatible array_is_list().
	 *
	 * @param array<mixed> $value Array.
	 */
	private static function is_list( array $value ): bool {
		$i = 0;
		foreach ( $value as $k => $unused ) {
			if ( $k !== $i++ ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Generate a keypair.
	 *
	 * @return array{public: string, secret: string} Base64-encoded keys.
	 */
	public static function generate_keypair(): array {
		$pair = sodium_crypto_sign_keypair();
		return array(
			'public' => base64_encode( sodium_crypto_sign_publickey( $pair ) ),
			'secret' => base64_encode( sodium_crypto_sign_secretkey( $pair ) ),
		);
	}

	/**
	 * Sign the canonical JSON of $data.
	 *
	 * @param mixed  $data       Data.
	 * @param string $secret_b64 Base64 secret key (64 bytes).
	 * @return string Base64 signature.
	 * @throws \InvalidArgumentException On an invalid key.
	 */
	public static function sign( $data, string $secret_b64 ): string {
		$secret = base64_decode( $secret_b64, true );
		if ( false === $secret || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $secret ) ) {
			throw new \InvalidArgumentException( 'Invalid signing key.' );
		}
		return base64_encode( sodium_crypto_sign_detached( self::canonical_json( $data ), $secret ) );
	}

	/**
	 * Verify a signature.
	 *
	 * @param mixed  $data          Data.
	 * @param string $signature_b64 Base64 signature.
	 * @param string $public_b64    Base64 public key (32 bytes).
	 */
	public static function verify( $data, string $signature_b64, string $public_b64 ): bool {
		$sig    = base64_decode( $signature_b64, true );
		$public = base64_decode( $public_b64, true );
		if ( false === $sig || false === $public
			|| SODIUM_CRYPTO_SIGN_BYTES !== strlen( $sig )
			|| SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $public ) ) {
			return false;
		}
		return sodium_crypto_sign_verify_detached( $sig, self::canonical_json( $data ), $public );
	}

	/**
	 * Derive the public key from a secret key.
	 *
	 * @param string $secret_b64 Base64 secret key.
	 */
	public static function public_from_secret( string $secret_b64 ): string {
		$secret = (string) base64_decode( $secret_b64, true );
		return base64_encode( sodium_crypto_sign_publickey_from_secretkey( $secret ) );
	}
}
