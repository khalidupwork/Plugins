<?php
/**
 * Protected storage for release ZIPs.
 *
 * @package TalkwynHub
 */

namespace TWH\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Uses TWH_RELEASES_DIR from wp-config.php when defined (ideally outside the
 * web root); otherwise wp-content/uploads/talkwyn-hub-releases, protected by
 * .htaccess deny rules, an index.php and random file names.
 */
final class Storage {

	/**
	 * Absolute directory path with trailing slash.
	 */
	public static function dir(): string {
		if ( defined( 'TWH_RELEASES_DIR' ) && is_string( TWH_RELEASES_DIR ) && '' !== TWH_RELEASES_DIR ) {
			return trailingslashit( TWH_RELEASES_DIR );
		}
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . 'talkwyn-hub-releases/';
	}

	/**
	 * Whether the configured directory is outside the uploads folder.
	 */
	public static function is_custom(): bool {
		return defined( 'TWH_RELEASES_DIR' ) && is_string( TWH_RELEASES_DIR ) && '' !== TWH_RELEASES_DIR;
	}

	/**
	 * Create the directory and protection files.
	 */
	public static function ensure_dir(): bool {
		$dir = self::dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$files = array(
			'.htaccess'  => "# Talkwyn Hub: deny direct access.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $content ) {
			if ( ! file_exists( $dir . $name ) ) {
				file_put_contents( $dir . $name, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		return is_writable( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
	}

	/**
	 * Absolute path of a stored file name, or null if it escapes the directory.
	 *
	 * @param string $name Stored file name.
	 */
	public static function path( string $name ): ?string {
		$name = basename( $name );
		if ( '' === $name || ! preg_match( '/^[a-z0-9._-]+\.zip$/i', $name ) ) {
			return null;
		}
		return self::dir() . $name;
	}

	/**
	 * Random, unguessable file name for a release.
	 *
	 * @param string $slug    Product slug.
	 * @param string $version Version.
	 */
	public static function random_name( string $slug, string $version ): string {
		$safe = static function ( string $s ): string {
			return (string) preg_replace( '/[^a-z0-9.-]/i', '', $s );
		};
		return $safe( $slug ) . '-' . $safe( $version ) . '-' . bin2hex( random_bytes( 16 ) ) . '.zip';
	}
}
