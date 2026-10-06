<?php
/**
 * Vyntic Client loader.
 *
 * Every Vyntic plugin ships a copy of this library. Each copy announces its
 * version here; on plugins_loaded the newest copy is loaded exactly once, so
 * plugins with older copies never conflict.
 *
 * Usage in a plugin's main file:
 *
 *     require_once __DIR__ . '/vendor/vyntic-client/loader.php';
 *     vyntic_client_register( array(
 *         'file' => __FILE__,                    // Main plugin file.
 *         'slug' => 'vyntic-speed-optimizer',    // Same slug as on the hub.
 *         'name' => 'Vyntic Speed Optimizer',
 *         'page' => 'vyntic-speed',              // Admin page slug (optional).
 *     ) );
 *
 * @package VynticClient
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'vyntic_client_candidate' ) ) {

	/**
	 * Records a bundled copy of the library.
	 */
	function vyntic_client_candidate( $version, $file ) {
		$GLOBALS['vyntic_client_candidates'][ $version ] = $file;
	}

	/**
	 * Registers a plugin with the shared Vyntic menu and the update hub.
	 */
	function vyntic_client_register( array $plugin ) {
		$GLOBALS['vyntic_client_plugins'][ $plugin['slug'] ] = $plugin;
	}

	add_action(
		'plugins_loaded',
		static function () {
			$candidates = isset( $GLOBALS['vyntic_client_candidates'] ) ? $GLOBALS['vyntic_client_candidates'] : array();
			if ( ! $candidates || class_exists( 'Vyntic_Client' ) ) {
				return;
			}
			uksort( $candidates, 'version_compare' );
			require_once end( $candidates );
			Vyntic_Client::boot( isset( $GLOBALS['vyntic_client_plugins'] ) ? $GLOBALS['vyntic_client_plugins'] : array() );
		},
		1
	);
}

vyntic_client_candidate( '1.0.0', __DIR__ . '/class-vyntic-client.php' );
