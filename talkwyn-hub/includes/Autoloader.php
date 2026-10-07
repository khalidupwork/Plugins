<?php
/**
 * PSR-4 style autoloader.
 *
 * @package TalkwynHub
 */

namespace TWH;

defined( 'ABSPATH' ) || defined( 'TWH_TESTS' ) || exit;

/**
 * Maps the TWH\ namespace to the includes/ directory.
 */
final class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @param string $prefix   Namespace prefix, with trailing backslash.
	 * @param string $base_dir Base directory, with trailing slash.
	 */
	public static function register( string $prefix, string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class_name ) use ( $prefix, $base_dir ): void {
				if ( 0 !== strncmp( $prefix, $class_name, strlen( $prefix ) ) ) {
					return;
				}
				$relative = substr( $class_name, strlen( $prefix ) );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
