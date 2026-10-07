<?php
/**
 * Master secret access.
 *
 * @package TalkwynHub
 */

namespace TWH\Support;

use TWH\Domain\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Provides the Crypto service, keyed from TWH_SECRET_KEY (wp-config.php)
 * or, as a fallback, from the WordPress auth salt.
 */
final class Secrets {

	/**
	 * Shared instance.
	 *
	 * @var Crypto|null
	 */
	private static ?Crypto $crypto = null;

	/**
	 * Whether the dedicated constant is defined and strong enough.
	 */
	public static function has_constant(): bool {
		return defined( 'TWH_SECRET_KEY' ) && is_string( TWH_SECRET_KEY ) && strlen( TWH_SECRET_KEY ) >= 32;
	}

	/**
	 * Crypto service.
	 */
	public static function crypto(): Crypto {
		if ( null === self::$crypto ) {
			$master       = self::has_constant() ? (string) TWH_SECRET_KEY : wp_salt( 'auth' ) . '|twh-fallback';
			self::$crypto = new Crypto( $master );
		}
		return self::$crypto;
	}

	/**
	 * Fallback crypto (WordPress auth salt), used for one-time migration.
	 */
	private static function fallback_crypto(): Crypto {
		return new Crypto( wp_salt( 'auth' ) . '|twh-fallback' );
	}

	/**
	 * Short fingerprint identifying a master secret.
	 *
	 * @param Crypto $crypto Crypto.
	 */
	private static function fingerprint( Crypto $crypto ): string {
		return substr( $crypto->hmac( 'fingerprint', 'fingerprint' ), 0, 16 );
	}

	/**
	 * Detect a change of master secret. When TWH_SECRET_KEY is added after data
	 * was encrypted with the salt fallback, re-encrypt everything with the new key.
	 *
	 * @return bool False when stored data cannot be decrypted with the current secret.
	 */
	public static function maybe_migrate(): bool {
		$stored  = (string) get_option( 'twh_secret_fp', '' );
		$current = self::fingerprint( self::crypto() );
		if ( '' === $stored ) {
			update_option( 'twh_secret_fp', $current, false );
			return true;
		}
		if ( hash_equals( $stored, $current ) ) {
			return true;
		}
		$old = self::fallback_crypto();
		if ( ! self::has_constant() || ! hash_equals( $stored, self::fingerprint( $old ) ) ) {
			return false;
		}

		global $wpdb;
		$table = \TWH\Install\Schema::table( 'licenses' );
		$last  = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, key_encrypted FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT 500", $last ), ARRAY_A );
			foreach ( $rows as $row ) {
				$last  = (int) $row['id'];
				$plain = $old->decrypt( (string) $row['key_encrypted'] );
				if ( null !== $plain ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->update( $table, array( 'key_encrypted' => self::crypto()->encrypt( $plain ) ), array( 'id' => $last ), array( '%s' ), array( '%d' ) );
				}
			}
			$more = count( $rows ) === 500;
		} while ( $more );

		$keys = get_option( SigningKeys::OPTION, array() );
		if ( is_array( $keys ) ) {
			foreach ( $keys as &$key ) {
				$plain = $old->decrypt( (string) ( $key['secret_enc'] ?? '' ) );
				if ( null !== $plain ) {
					$key['secret_enc'] = self::crypto()->encrypt( $plain );
				}
			}
			unset( $key );
			update_option( SigningKeys::OPTION, $keys, false );
		}
		update_option( 'twh_secret_fp', $current, false );
		return true;
	}

	/**
	 * HMAC secret for download tokens.
	 */
	public static function token_secret(): string {
		return self::crypto()->subkey( 'download-token' );
	}

	/**
	 * Privacy-preserving hash of an IP address.
	 *
	 * @param string $ip IP address.
	 */
	public static function hash_ip( string $ip ): string {
		return '' === $ip ? '' : self::crypto()->hmac( $ip, 'ip' );
	}

	/**
	 * Signed token for one-click renewal links (no login needed; paying for a
	 * renewal is harmless, the token only prevents guessing license ids).
	 *
	 * @param int $license_id License id.
	 */
	public static function renew_token( int $license_id ): string {
		return substr( self::crypto()->hmac( 'renew|' . $license_id, 'renew' ), 0, 24 );
	}
}
