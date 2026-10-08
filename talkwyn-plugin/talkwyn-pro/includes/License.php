<?php
/**
 * License client wrapper (Talkwyn Hub SDK).
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * License access.
 */
final class License {

	/**
	 * Client instance.
	 *
	 * @var \Talkwyn_License_Client|null
	 */
	private static ?\Talkwyn_License_Client $client = null;

	/**
	 * Trusted Hub public keys (base64 Ed25519). Copy the active key from
	 * Talkwyn Hub > Settings > Signing keys before building a release, or define
	 * TALKWYN_PRO_PUBLIC_KEYS (comma separated) in wp-config.php.
	 *
	 * @return string[]
	 */
	public static function public_keys(): array {
		$keys = array();
		if ( defined( 'TALKWYN_PRO_PUBLIC_KEYS' ) ) {
			$keys = array_filter( array_map( 'trim', explode( ',', (string) TALKWYN_PRO_PUBLIC_KEYS ) ) );
		}
		$file = TALKWYN_PRO_DIR . 'includes/hub-keys.php';
		if ( is_readable( $file ) ) {
			$keys = array_merge( $keys, (array) include $file );
		}
		return array_values( array_unique( array_filter( (array) apply_filters( 'talkwyn_pro_public_keys', $keys ) ) ) );
	}

	/**
	 * Client.
	 */
	public static function client(): \Talkwyn_License_Client {
		if ( null === self::$client ) {
			self::$client = new \Talkwyn_License_Client(
				array(
					'api_url'     => (string) apply_filters( 'talkwyn_pro_hub_url', 'https://talkwyn.com/wp-json/talkwyn-hub/v1/' ),
					'product'     => 'talkwyn-pro',
					'plugin_file' => TALKWYN_PRO_FILE,
					'version'     => TALKWYN_PRO_VERSION,
					'public_keys' => self::public_keys(),
					'prefix'      => 'talkwyn_license',
					'manage_url'  => 'https://talkwyn.com/my-account/licenses/',
					'upgrade_url' => 'https://talkwyn.com/pricing/',
				)
			);
		}
		return self::$client;
	}

	/**
	 * Whether Pro features run (paid license, running trial or grace period).
	 */
	public static function active(): bool {
		return (bool) apply_filters( 'talkwyn_pro_license_active', self::client()->is_pro() );
	}
}
