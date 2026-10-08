<?php
/**
 * PHPUnit bootstrap for Talkwyn Pro: reuses the free plugin shims.
 *
 * @package TalkwynPro
 */

require dirname( __DIR__, 2 ) . '/talkwyn/tests/bootstrap.php';

define( 'TALKWYN_PRO_DIR', dirname( __DIR__ ) . '/' );
function wp_json_encode( $d, $o = 0 ) {
	return json_encode( $d, $o );
}
function plugin_basename( $f ) {
	return basename( dirname( $f ) ) . '/' . basename( $f );
}
function trailingslashit( $s ) {
	return rtrim( $s, '/' ) . '/';
}
require_once TALKWYN_PRO_DIR . 'includes/class-talkwyn-license-client.php';
spl_autoload_register(
	static function ( $class ) {
		if ( 0 === strpos( $class, 'TalkwynPro\\' ) ) {
			require_once TALKWYN_PRO_DIR . 'includes/' . substr( $class, 11 ) . '.php';
		}
	}
);
