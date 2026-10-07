<?php
/**
 * License key generation.
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

/**
 * Generates and normalizes keys in the format TALK-XXXX-XXXX-XXXX-XXXX.
 *
 * The alphabet has 32 unambiguous characters (no 0/O/1/I), so masking a random
 * byte to 5 bits gives a uniform distribution without modulo bias.
 * 16 characters x 5 bits = 80 bits of entropy.
 */
final class KeyGenerator {

	public const ALPHABET  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	public const PREFIX    = 'TALK';
	public const GROUPS    = 4;
	public const GROUP_LEN = 4;

	/**
	 * Generate a new random key.
	 *
	 * @param string $prefix Key prefix.
	 */
	public static function generate( string $prefix = self::PREFIX ): string {
		$length = self::GROUPS * self::GROUP_LEN;
		$bytes  = random_bytes( $length );
		$chars  = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$chars .= self::ALPHABET[ ord( $bytes[ $i ] ) & 31 ];
		}
		return $prefix . '-' . implode( '-', str_split( $chars, self::GROUP_LEN ) );
	}

	/**
	 * Normalize user input: trim, uppercase, strip whitespace.
	 *
	 * @param string $key Raw key.
	 */
	public static function normalize( string $key ): string {
		return strtoupper( (string) preg_replace( '/\s+/', '', $key ) );
	}

	/**
	 * Whether a (normalized) key has the expected format.
	 *
	 * @param string $key    Key.
	 * @param string $prefix Expected prefix.
	 */
	public static function is_valid_format( string $key, string $prefix = self::PREFIX ): bool {
		$group = '[' . self::ALPHABET . ']{' . self::GROUP_LEN . '}';
		$regex = '/^' . preg_quote( $prefix, '/' ) . '(?:-' . $group . '){' . self::GROUPS . '}$/';
		return 1 === preg_match( $regex, $key );
	}

	/**
	 * Lookup hash for a key.
	 *
	 * @param string $key Key (normalized internally).
	 */
	public static function hash( string $key ): string {
		return hash( 'sha256', self::normalize( $key ) );
	}

	/**
	 * Last four characters, used for display and search.
	 *
	 * @param string $key Key.
	 */
	public static function last4( string $key ): string {
		return substr( self::normalize( $key ), -4 );
	}

	/**
	 * Masked representation, e.g. TALK-****-****-****-AB12.
	 *
	 * @param string $last4  Last four characters.
	 * @param string $prefix Prefix.
	 */
	public static function mask( string $last4, string $prefix = self::PREFIX ): string {
		return $prefix . '-****-****-****-' . $last4;
	}
}
