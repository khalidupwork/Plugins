<?php
/**
 * Ed25519 signing key storage and rotation.
 *
 * @package TalkwynHub
 */

namespace TWH\Support;

use TWH\Domain\Signer;

defined( 'ABSPATH' ) || exit;

/**
 * Keys live in the `twh_signing_keys` option. Secret keys are encrypted.
 *
 * Rotation is two-step so existing installs never break:
 * 1. "Generate next key": creates a `next` key. Embed its public key in the next
 *    client release, next to the current one.
 * 2. "Promote next key": once most sites run that release, `next` becomes `active`
 *    and the previous active key becomes `retired`.
 */
final class SigningKeys {

	public const OPTION = 'twh_signing_keys';

	/**
	 * All keys.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$keys = get_option( self::OPTION, array() );
		return is_array( $keys ) ? $keys : array();
	}

	/**
	 * Ensure an active key exists.
	 */
	public static function ensure(): void {
		if ( null === self::find( 'active' ) ) {
			$keys   = self::all();
			$keys[] = self::make( 'active' );
			update_option( self::OPTION, $keys, false );
		}
	}

	/**
	 * Find the first key with a status.
	 *
	 * @param string $status Status.
	 * @return array<string, mixed>|null
	 */
	public static function find( string $status ): ?array {
		foreach ( self::all() as $key ) {
			if ( ( $key['status'] ?? '' ) === $status ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Active key id and decrypted secret.
	 *
	 * @return array{kid: string, secret: string}
	 * @throws \RuntimeException When the key cannot be decrypted.
	 */
	public static function active_secret(): array {
		self::ensure();
		$key    = (array) self::find( 'active' );
		$secret = Secrets::crypto()->decrypt( (string) $key['secret_enc'] );
		if ( null === $secret ) {
			throw new \RuntimeException( 'Signing key cannot be decrypted. Was TWH_SECRET_KEY changed?' );
		}
		return array(
			'kid'    => (string) $key['kid'],
			'secret' => $secret,
		);
	}

	/**
	 * Create a `next` key (replaces an existing unpromoted one).
	 */
	public static function generate_next(): void {
		$keys   = array_values(
			array_filter(
				self::all(),
				static function ( $k ) {
					return ( $k['status'] ?? '' ) !== 'next';
				}
			)
		);
		$keys[] = self::make( 'next' );
		update_option( self::OPTION, $keys, false );
	}

	/**
	 * Promote `next` to `active` and retire the current active key.
	 */
	public static function promote_next(): bool {
		$keys = self::all();
		if ( null === self::find( 'next' ) ) {
			return false;
		}
		foreach ( $keys as &$key ) {
			if ( 'active' === $key['status'] ) {
				$key['status']     = 'retired';
				$key['retired_at'] = time();
			} elseif ( 'next' === $key['status'] ) {
				$key['status']      = 'active';
				$key['promoted_at'] = time();
			}
		}
		unset( $key );
		update_option( self::OPTION, $keys, false );
		return true;
	}

	/**
	 * Build a new key record.
	 *
	 * @param string $status Status.
	 * @return array<string, mixed>
	 */
	private static function make( string $status ): array {
		$pair = Signer::generate_keypair();
		return array(
			'kid'        => substr( bin2hex( random_bytes( 4 ) ), 0, 8 ),
			'public'     => $pair['public'],
			'secret_enc' => Secrets::crypto()->encrypt( $pair['secret'] ),
			'status'     => $status,
			'created_at' => time(),
		);
	}
}
