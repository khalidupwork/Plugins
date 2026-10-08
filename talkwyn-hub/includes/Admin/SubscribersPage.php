<?php
/**
 * Subscribers from the plugin setup wizard opt-in.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

use TWH\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Subscribers list.
 */
final class SubscribersPage {

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( Admin::cap() ) ) {
			return;
		}
		global $wpdb;
		$table = Schema::table( 'subscribers' );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
		$rows  = (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200", ARRAY_A ); // phpcs:ignore WordPress.DB
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Subscribers', 'talkwyn-hub' ) . '</h1> <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=twh_export_subscribers' ), 'twh_export_subscribers' ) ) . '">' . esc_html__( 'Export CSV', 'talkwyn-hub' ) . '</a><hr class="wp-header-end">';
		echo '<p>' . esc_html( sprintf( /* translators: %d: count */ __( '%d site owners asked for product news from the Talkwyn plugin setup wizard. Showing the latest 200.', 'talkwyn-hub' ), $total ) ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Email', 'talkwyn-hub' ) . '</th><th>' . esc_html__( 'Site', 'talkwyn-hub' ) . '</th><th>' . esc_html__( 'Source', 'talkwyn-hub' ) . '</th><th>' . esc_html__( 'Date', 'talkwyn-hub' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No subscribers yet.', 'talkwyn-hub' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r['email'] ) . '</td><td>' . esc_html( $r['site_url'] ) . '</td><td>' . esc_html( $r['source'] ) . '</td><td>' . esc_html( $r['created_at'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
