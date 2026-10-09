<?php
/**
 * PHPUnit bootstrap: loads Talkwyn classes with small WordPress shims (no database).
 *
 * @package Talkwyn
 */

define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['tw_options']    = array();
$GLOBALS['tw_transients'] = array();
$GLOBALS['tw_filters']    = array();

function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['tw_filters'][ $hook ] ?? array() as $cb ) {
		$value = $cb( $value, ...$args );
	}
	return $value;
}
function add_filter( $hook, $cb ) {
	$GLOBALS['tw_filters'][ $hook ][] = $cb;
	return true;
}
function do_action( ...$args ) {}
function add_action( ...$args ) {}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['tw_options'] ) ? $GLOBALS['tw_options'][ $k ] : $d;
}
function update_option( $k, $v ) {
	$GLOBALS['tw_options'][ $k ] = $v;
	return true;
}
function get_transient( $k ) {
	return $GLOBALS['tw_transients'][ $k ] ?? false;
}
function set_transient( $k, $v, $ttl = 0 ) {
	$GLOBALS['tw_transients'][ $k ] = $v;
	return true;
}
function delete_transient( $k ) {
	unset( $GLOBALS['tw_transients'][ $k ] );
	return true;
}
function wp_parse_args( $a, $d ) {
	return array_merge( $d, (array) $a );
}
function sanitize_key( $k ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}
function sanitize_text_field( $s ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function sanitize_textarea_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function sanitize_email( $s ) {
	return trim( (string) $s );
}
function sanitize_hex_color( $c ) {
	return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $c ) ? $c : null;
}
function esc_url_raw( $u ) {
	return (string) $u;
}
function absint( $n ) {
	return abs( (int) $n );
}
function is_email( $e ) {
	return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL );
}
function __( $s ) {
	return $s;
}
function wp_salt() {
	return 'salt';
}
function wp_generate_uuid4() {
	return sprintf( '%04x%04x-%04x-4%03x-a%03x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xfff ), mt_rand( 0, 0xfff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
}
function get_post_types() {
	return array( 'post' => 'post', 'page' => 'page', 'product' => 'product', 'attachment' => 'attachment' );
}
function post_type_exists( $t ) {
	return true;
}
function wp_strip_all_tags( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false );
}
function wp_specialchars_decode( $s, $q = ENT_NOQUOTES ) {
	return htmlspecialchars_decode( (string) $s, $q );
}

class WP_Post {
	public $ID = 1;
	public $post_status = 'publish';
	public $post_password = '';
	public $post_type = 'post';
	public function __construct( array $props = array() ) {
		foreach ( $props as $k => $v ) {
			$this->$k = $v;
		}
	}
}

$dir = dirname( __DIR__ ) . '/includes/';
foreach ( array( 'text', 'db', 'settings', 'contrast', 'markdown', 'retriever', 'providers', 'history', 'rate-limiter', 'conversation', 'leads', 'indexer', 'i18n' ) as $f ) {
	require_once $dir . 'class-talkwyn-' . $f . '.php';
}

/**
 * Records deletes so tests can check what was removed.
 */
class Talkwyn_Test_WPDB {
	public $prefix  = 'wp_';
	public $deleted = array();
	public function delete( $table, $where ) {
		$this->deleted[] = array( $table, $where );
		return 1;
	}
}
$GLOBALS['wpdb'] = new Talkwyn_Test_WPDB();
