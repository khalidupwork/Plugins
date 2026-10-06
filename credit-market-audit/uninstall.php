<?php
/**
 * Uninstall: only removes data when the "Delete all data on uninstall" setting is enabled.
 *
 * @package CreditMarketAudit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$cma_settings = get_option( 'cma_settings', array() );

if ( ! empty( $cma_settings['delete_on_uninstall'] ) ) {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cma_audits" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	delete_option( 'cma_settings' );
	delete_option( 'cma_db_version' );
}
