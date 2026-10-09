<?php
/**
 * Database tables, activation and daily cleanup.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schema and lifecycle.
 */
class Talkwyn_DB {

	const DB_VERSION = '2.1.0';

	/**
	 * Table names.
	 *
	 * @return array<string, string>
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'chunks' => $wpdb->prefix . 'talkwyn_chunks',
			'leads'  => $wpdb->prefix . 'talkwyn_leads',
			'logs'   => $wpdb->prefix . 'talkwyn_logs',
		);
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::install();
		if ( ! wp_next_scheduled( 'talkwyn_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'talkwyn_daily_cleanup' );
		}
		if ( ! get_option( 'talkwyn_onboarding_done' ) ) {
			add_option( 'talkwyn_do_onboarding', 1 );
		}
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'talkwyn_daily_cleanup' );
	}

	/**
	 * Install or upgrade the schema when the version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'talkwyn_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		dbDelta(
			"CREATE TABLE {$t['chunks']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_key varchar(191) NOT NULL,
			source_type varchar(50) NOT NULL,
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_url text NULL,
			source_lang varchar(20) NOT NULL DEFAULT '',
			title text NULL,
			chunk_text longtext NOT NULL,
			checksum char(32) NOT NULL,
			modified_gmt datetime NULL,
			PRIMARY KEY  (id),
			KEY source_key (source_key),
			KEY source_type (source_type),
			KEY source_id (source_id),
			KEY source_lang (source_lang)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['leads']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_gmt datetime NOT NULL,
			session_id varchar(64) NOT NULL DEFAULT '',
			name varchar(190) NULL,
			email varchar(190) NULL,
			phone varchar(80) NULL,
			message text NULL,
			page_url text NULL,
			consent tinyint(1) NOT NULL DEFAULT 0,
			source varchar(20) NOT NULL DEFAULT 'chat',
			status varchar(30) NOT NULL DEFAULT 'new',
			PRIMARY KEY  (id),
			KEY created_gmt (created_gmt),
			KEY email (email),
			KEY session_id (session_id),
			KEY status (status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['logs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_gmt datetime NOT NULL,
			session_id varchar(64) NOT NULL,
			role varchar(20) NOT NULL,
			message longtext NOT NULL,
			provider varchar(40) NULL,
			page_url text NULL,
			meta longtext NULL,
			feedback varchar(20) NULL,
			response_ms int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY created_gmt (created_gmt),
			KEY session_id (session_id),
			KEY role (role)
			) $charset;"
		);

		self::ensure_fulltext();
		update_option( 'talkwyn_db_version', self::DB_VERSION, false );
		add_option( 'talkwyn_installed_at', time(), '', false );
		if ( false === get_option( Talkwyn_Settings::OPTION ) ) {
			add_option( Talkwyn_Settings::OPTION, array(), '', false );
		}
	}

	/**
	 * Add the FULLTEXT index used for ranking. Older MySQL versions without
	 * InnoDB FULLTEXT support fall back to keyword scoring in SQL.
	 *
	 * @return void
	 */
	public static function ensure_fulltext() {
		global $wpdb;
		$t      = self::tables();
		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = %s", $t['chunks'], 'talkwyn_ft' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $exists ) {
			$suppress = $wpdb->suppress_errors( true );
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD FULLTEXT KEY talkwyn_ft (title, chunk_text)", $t['chunks'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->suppress_errors( $suppress );
			$exists = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = %s", $t['chunks'], 'talkwyn_ft' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		update_option( 'talkwyn_fulltext', $exists ? 1 : 0, false );
	}

	/**
	 * Daily cleanup: delete logs older than the retention period.
	 *
	 * @return void
	 */
	public static function cleanup() {
		global $wpdb;
		$t      = self::tables();
		$days   = max( 1, absint( Talkwyn_Settings::get( 'retention_days', 30 ) ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE created_gmt < %s", $t['logs'], $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		/**
		 * Fires after the daily cleanup.
		 *
		 * @param string $cutoff GMT date before which logs were removed.
		 */
		do_action( 'talkwyn_daily_cleanup_done', $cutoff );
	}

	/**
	 * Drop every table and option. Used by uninstall.php when the owner opts in.
	 *
	 * @return void
	 */
	public static function drop_all() {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
	}
}
