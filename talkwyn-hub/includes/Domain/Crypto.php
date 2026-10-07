<?php
/**
 * Symmetric encryption and key derivation.
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

/**
 * XSalsa20-Poly1305 (sodium secretbox) with subkeys derived from one master secret.
 */
final class Crypto {

	/**
	 * Master secret (raw).
	 *
	 * @var string
	 */
	private string $master;

	/**
	 * Constructor.
	 *
	 * @param string $master Master secret; any length, at least 16 bytes recommended.
	 * @throws \InvalidArgumentException On an empty secret.
	 */
	public function __construct( string $master ) {
		if ( '' === $master ) {
			throw new \InvalidArgumentException( 'Empty master secret.' );
		}
		$this->master = $master;
	}

	/**
	 * Derive a 32-byte subkey for a context.
	 *
	 * @param string $context Context label.
	 */
	public function subkey( string $context ): string {
		return sodium_crypto_generichash( 'talkwyn-hub|' . $context, sodium_crypto_generichash( $this->master, '', 32 ), 32 );
	}

	/**
	 * Encrypt.
	 *
	 * @param string $plaintext Plain text.
	 * @return string Base64(nonce || ciphertext).
	 */
	public function encrypt( string $plaintext ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $nonce . sodium_crypto_secretbox( $plaintext, $nonce, $this->subkey( 'enc' ) ) );
	}

	/**
	 * Decrypt.
	 *
	 * @param string $encoded Base64(nonce || ciphertext).
	 * @return string|null Null when it cannot be decrypted (wrong secret or tampered).
	 */
	public function decrypt( string $encoded ): ?string {
		$raw = base64_decode( $encoded, true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $this->subkey( 'enc' ) );
		return false === $plain ? null : $plain;
	}

	/**
	 * Keyed hash for privacy-preserving identifiers (e.g. IPs).
	 *
	 * @param string $value   Value.
	 * @param string $context Context label.
	 */
	public function hmac( string $value, string $context = 'hmac' ): string {
		return hash_hmac( 'sha256', $value, $this->subkey( $context ) );
	}
}
