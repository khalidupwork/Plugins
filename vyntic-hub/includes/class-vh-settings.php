<?php
/**
 * Hub settings.
 *
 * @package VynticHub
 */

defined( 'ABSPATH' ) || exit;

class VH_Settings {

	const OPTION = 'vh_settings';

	public static function defaults() {
		return array(
			'base'           => 'plugins', // URL base: /plugins/{slug}/.
			'show_download'  => 1,         // Download button on public pages.
			'show_installs'  => 0,         // "Active on N sites" on public pages.
			'brand'          => 'Vyntic Studio',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function base() {
		$base = sanitize_title( (string) self::get( 'base' ) );
		return $base ? $base : 'plugins';
	}

	public static function save( array $input ) {
		$old   = self::all();
		$clean = array(
			'base'          => sanitize_title( isset( $input['base'] ) ? $input['base'] : 'plugins' ),
			'show_download' => empty( $input['show_download'] ) ? 0 : 1,
			'show_installs' => empty( $input['show_installs'] ) ? 0 : 1,
			'brand'         => sanitize_text_field( isset( $input['brand'] ) ? $input['brand'] : 'Vyntic Studio' ),
		);
		if ( '' === $clean['base'] ) {
			$clean['base'] = 'plugins';
		}
		update_option( self::OPTION, $clean );
		if ( $old['base'] !== $clean['base'] ) {
			update_option( 'vh_flush_rewrite', 1 );
		}
		VH_API::flush();
	}
}
