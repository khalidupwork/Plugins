<?php
/**
 * Uninstall: removes data only when "Delete all data on uninstall" is enabled.
 *
 * @package TalkwynHub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$twh_settings = get_option( 'twh_settings', array() );
if ( empty( $twh_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Release ZIPs.
$twh_dir = defined( 'TWH_RELEASES_DIR' ) && TWH_RELEASES_DIR ? rtrim( (string) TWH_RELEASES_DIR, '/\\' ) . '/' : '';
if ( '' === $twh_dir ) {
	$twh_uploads = wp_upload_dir( null, false );
	$twh_dir     = rtrim( $twh_uploads['basedir'], '/\\' ) . '/talkwyn-hub-releases/';
}
if ( is_dir( $twh_dir ) ) {
	foreach ( (array) glob( $twh_dir . '*.zip' ) as $twh_file ) {
		if ( is_string( $twh_file ) && is_file( $twh_file ) ) {
			wp_delete_file( $twh_file );
		}
	}
	foreach ( array( '.htaccess', 'index.php', 'web.config' ) as $twh_file ) {
		if ( is_file( $twh_dir . $twh_file ) ) {
			wp_delete_file( $twh_dir . $twh_file );
		}
	}
	// Only remove the folder we created inside uploads; never a custom directory.
	if ( ! defined( 'TWH_RELEASES_DIR' ) ) {
		@rmdir( $twh_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}

// Tables.
foreach ( array( 'products', 'releases', 'licenses', 'activations', 'events' ) as $twh_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}twh_{$twh_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Options and transients.
foreach ( array( 'twh_settings', 'twh_signing_keys', 'twh_db_version', 'twh_secret_fp' ) as $twh_option ) {
	delete_option( $twh_option );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_twh\\_%' OR option_name LIKE '\\_transient\\_timeout\\_twh\\_%' OR option_name LIKE 'twh\\_lock\\_order\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Product mapping meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_twh\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery

wp_clear_scheduled_hook( 'twh_daily' );
