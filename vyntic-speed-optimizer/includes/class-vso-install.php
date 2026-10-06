<?php
/**
 * Activation, deactivation, upgrades and keeping server files in sync.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Install {

	public static function activate() {
		if ( false === get_option( VSO_Settings::OPTION, false ) ) {
			// First install: start on the recommended preset.
			add_option( VSO_Settings::OPTION, VSO_Settings::sanitize( array_merge( VSO_Settings::defaults(), VSO_Settings::presets()['balanced'], array( 'level' => 'balanced' ) ) ) );
		}
		VSO_Utils::ensure_dir( VSO_CACHE_DIR );
		self::sync_server_files();
		self::schedule_events();
		update_option( 'vso_version', VSO_VERSION );
		set_transient( 'vso_activated', 1, 60 );
	}

	public static function deactivate() {
		VSO_Page_Cache::remove_dropin();
		VSO_Page_Cache::set_wp_cache( false );
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$htaccess = get_home_path() . '.htaccess';
		if ( file_exists( $htaccess ) && wp_is_writable( $htaccess ) ) {
			insert_with_markers( $htaccess, 'Vyntic Speed Optimizer', array() );
		}
		foreach ( array( 'vso_cache_gc', 'vso_db_cleanup', VSO_Preload::HOOK ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		VSO_Utils::rrmdir( VSO_CACHE_DIR );
	}

	public static function maybe_upgrade() {
		add_action( 'vso_settings_updated', array( __CLASS__, 'sync_server_files' ) );
		if ( get_option( 'vso_version' ) !== VSO_VERSION ) {
			self::sync_server_files();
			self::schedule_events();
			update_option( 'vso_version', VSO_VERSION );
		}
	}

	public static function sync_server_files() {
		VSO_Page_Cache::sync();
		VSO_Tweaks::sync_htaccess();
	}

	public static function schedule_events() {
		if ( ! wp_next_scheduled( 'vso_cache_gc' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'vso_cache_gc' );
		}
		if ( ! wp_next_scheduled( 'vso_db_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'vso_db_cleanup' );
		}
	}
}
