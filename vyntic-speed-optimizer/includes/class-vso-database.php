<?php
/**
 * Database cleanup: revisions, drafts, trash, spam, expired transients, table optimize.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Database {

	public static function init() {
		add_action( 'vso_db_cleanup', array( __CLASS__, 'scheduled_cleanup' ) );
	}

	public static function items() {
		return array(
			'revisions'  => __( 'Post revisions', 'vyntic-speed-optimizer' ),
			'autodrafts' => __( 'Auto drafts', 'vyntic-speed-optimizer' ),
			'trash'      => __( 'Trashed posts', 'vyntic-speed-optimizer' ),
			'spam'       => __( 'Spam comments', 'vyntic-speed-optimizer' ),
			'trash_comm' => __( 'Trashed comments', 'vyntic-speed-optimizer' ),
			'transients' => __( 'Expired transients', 'vyntic-speed-optimizer' ),
			'orphanmeta' => __( 'Orphaned post meta', 'vyntic-speed-optimizer' ),
			'optimize'   => __( 'Optimize tables', 'vyntic-speed-optimizer' ),
		);
	}

	public static function counts() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return array(
			'revisions'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ),
			'autodrafts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ),
			'trash'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ),
			'spam'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ),
			'trash_comm' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ),
			'transients' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) ),
			'orphanmeta' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ),
			'optimize'   => 0,
		);
		// phpcs:enable
	}

	/**
	 * Runs one cleanup task. Returns the number of removed rows.
	 */
	public static function clean( $item ) {
		global $wpdb;
		$removed = 0;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		switch ( $item ) {
			case 'revisions':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT 5000" );
				foreach ( $ids as $id ) {
					$removed += wp_delete_post_revision( (int) $id ) ? 1 : 0;
				}
				break;
			case 'autodrafts':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' LIMIT 5000" );
				foreach ( $ids as $id ) {
					$removed += wp_delete_post( (int) $id, true ) ? 1 : 0;
				}
				break;
			case 'trash':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' LIMIT 5000" );
				foreach ( $ids as $id ) {
					$removed += wp_delete_post( (int) $id, true ) ? 1 : 0;
				}
				break;
			case 'spam':
			case 'trash_comm':
				$status = 'spam' === $item ? 'spam' : 'trash';
				$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s LIMIT 5000", $status ) );
				foreach ( $ids as $id ) {
					$removed += wp_delete_comment( (int) $id, true ) ? 1 : 0;
				}
				break;
			case 'transients':
				$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT 5000", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
				foreach ( $names as $name ) {
					$removed += delete_transient( substr( $name, strlen( '_transient_timeout_' ) ) ) ? 1 : 0;
				}
				break;
			case 'orphanmeta':
				$removed = (int) $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
				break;
			case 'optimize':
				$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
				foreach ( $tables as $table ) {
					$wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $table ) . '`' );
					++$removed;
				}
				break;
		}
		// phpcs:enable
		return $removed;
	}

	public static function scheduled_cleanup() {
		if ( ! VSO_Settings::enabled( 'db_auto_clean' ) ) {
			return;
		}
		foreach ( array( 'revisions', 'autodrafts', 'spam', 'trash_comm', 'transients' ) as $item ) {
			self::clean( $item );
		}
	}
}
