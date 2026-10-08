<?php
/**
 * Uninstall: removes data only when the owner opted in (Talkwyn > Privacy).
 *
 * @package Talkwyn
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'talkwyn_daily_cleanup' );

$talkwyn_settings = get_option( 'talkwyn_settings', array() );
if ( empty( $talkwyn_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'talkwyn_chunks', 'talkwyn_leads', 'talkwyn_logs' ) as $talkwyn_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $wpdb->prefix . $talkwyn_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
}
foreach ( array( 'talkwyn_settings', 'talkwyn_db_version', 'talkwyn_fulltext', 'talkwyn_migrated_from_nabia', 'talkwyn_nabia_notice', 'talkwyn_onboarding_done', 'talkwyn_do_onboarding' ) as $talkwyn_option ) {
	delete_option( $talkwyn_option );
}
// Conversation history, rate limits and model lists are transients.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_talkwyn_' ) . '%', $wpdb->esc_like( '_transient_timeout_talkwyn_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
