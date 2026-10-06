<?php
/**
 * Activation / DB schema.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates the audits table.
 */
class CMA_Install {

	/**
	 * Table name with prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cma_audits';
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::create_table();
		if ( false === get_option( CMA_Settings::OPTION ) ) {
			add_option( CMA_Settings::OPTION, CMA_Settings::defaults() );
		}
	}

	/**
	 * Re-run schema when the DB version changes (covers updates without re-activation).
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'cma_db_version' ) !== CMA_DB_VERSION ) {
			self::create_table();
		}
	}

	/**
	 * Create / update the table using dbDelta.
	 */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token varchar(64) NOT NULL,
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL,
			url varchar(500) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			overall_score tinyint(3) unsigned DEFAULT NULL,
			report longtext NULL,
			ip varchar(64) NOT NULL DEFAULT '',
			email_sent tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'cma_db_version', CMA_DB_VERSION );
	}
}
