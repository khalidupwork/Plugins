<?php
/**
 * PHPUnit bootstrap: loads the pure domain classes without WordPress.
 *
 * @package TalkwynHub
 */

define( 'TWH_TESTS', true );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/includes/Autoloader.php';
\TWH\Autoloader::register( 'TWH\\', dirname( __DIR__ ) . '/includes/' );

// Minimal WordPress shims used by the client SDK's static helpers.
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) { // phpcs:ignore
		return json_encode( $data, $options, $depth );
	}
}
require_once dirname( __DIR__, 2 ) . '/client-sdk/class-talkwyn-license-client.php';
