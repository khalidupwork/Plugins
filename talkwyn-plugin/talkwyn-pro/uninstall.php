<?php
/**
 * Uninstall Talkwyn Pro. Data is removed only when "Delete all Talkwyn data"
 * is on (Talkwyn > Privacy). The license is always released from this site's state.
 *
 * @package TalkwynPro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'talkwyn_pro_embed', 'talkwyn_pro_crawl', 'talkwyn_license_daily_check' ) as $talkwyn_pro_hook ) {
	wp_clear_scheduled_hook( $talkwyn_pro_hook );
}

$talkwyn_pro_settings = get_option( 'talkwyn_settings', array() );
if ( empty( $talkwyn_pro_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'talkwyn_pro_vectors', 'talkwyn_pro_qa', 'talkwyn_pro_sources' ) as $talkwyn_pro_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $wpdb->prefix . $talkwyn_pro_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
}
foreach ( array( 'talkwyn_pro_db_version', 'talkwyn_pro_crawl_queue', 'talkwyn_license_key', 'talkwyn_license_state', 'talkwyn_license_instance' ) as $talkwyn_pro_option ) {
	delete_option( $talkwyn_pro_option );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_talkwyn_pro_' ) . '%', $wpdb->esc_like( '_transient_timeout_talkwyn_pro_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
