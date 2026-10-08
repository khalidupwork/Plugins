<?php
/**
 * Pro tables: vectors (smart search), custom answers, extra knowledge sources.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Schema.
 */
final class Installer {

	const DB_VERSION = '1.0.0';

	/**
	 * Table names.
	 *
	 * @return array<string, string>
	 */
	public static function tables(): array {
		global $wpdb;
		return array(
			'vectors' => $wpdb->prefix . 'talkwyn_pro_vectors',
			'qa'      => $wpdb->prefix . 'talkwyn_pro_qa',
			'sources' => $wpdb->prefix . 'talkwyn_pro_sources',
		);
	}

	/**
	 * Activation.
	 */
	public static function activate(): void {
		self::install();
	}

	/**
	 * Install when the version changes.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'talkwyn_pro_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create tables.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = self::tables();
		dbDelta(
			"CREATE TABLE {$t['vectors']} (
			chunk_id bigint(20) unsigned NOT NULL,
			model varchar(120) NOT NULL DEFAULT '',
			dims smallint(5) unsigned NOT NULL DEFAULT 0,
			vec longblob NOT NULL,
			updated_gmt datetime NOT NULL,
			PRIMARY KEY  (chunk_id),
			KEY model (model)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t['qa']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			question text NOT NULL,
			answer longtext NOT NULL,
			keywords text NULL,
			hits int(10) unsigned NOT NULL DEFAULT 0,
			created_gmt datetime NOT NULL,
			updated_gmt datetime NOT NULL,
			PRIMARY KEY  (id)
			) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t['sources']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL,
			ref text NOT NULL,
			title text NULL,
			chunks int(10) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'ok',
			message text NULL,
			updated_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY type (type)
			) $c;"
		);
		update_option( 'talkwyn_pro_db_version', self::DB_VERSION, false );
	}
}
