<?php
/**
 * Sites that use your plugins: one row per site + plugin, updated on every
 * update check (about twice a day). Only URL and versions are stored.
 *
 * @package VynticHub
 */

defined( 'ABSPATH' ) || exit;

class VH_Sites {

	const ACTIVE_DAYS = 14;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'vyntic_hub_sites';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				site_hash char(32) NOT NULL,
				site_url varchar(255) NOT NULL,
				slug varchar(100) NOT NULL,
				version varchar(30) NOT NULL DEFAULT '',
				wp varchar(20) NOT NULL DEFAULT '',
				php varchar(20) NOT NULL DEFAULT '',
				first_seen datetime NOT NULL,
				last_seen datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY site_slug (site_hash, slug),
				KEY slug_seen (slug, last_seen)
			) {$charset};"
		);
	}

	public static function record( $site, $slug, $version, $wp, $php ) {
		global $wpdb;
		$site = esc_url_raw( $site );
		$host = wp_parse_url( $site, PHP_URL_HOST );
		if ( ! $host || ! $slug ) {
			return;
		}
		$site = untrailingslashit( preg_replace( '#^http://#i', 'https://', $site ) );
		$now  = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . self::table() . ' (site_hash, site_url, slug, version, wp, php, first_seen, last_seen) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE site_url = VALUES(site_url), version = VALUES(version), wp = VALUES(wp), php = VALUES(php), last_seen = VALUES(last_seen)',
				md5( strtolower( $site ) ),
				substr( $site, 0, 255 ),
				substr( $slug, 0, 100 ),
				substr( $version, 0, 30 ),
				substr( $wp, 0, 20 ),
				substr( $php, 0, 20 ),
				$now,
				$now
			)
		);
	}

	private static function since() {
		return gmdate( 'Y-m-d H:i:s', time() - self::ACTIVE_DAYS * DAY_IN_SECONDS );
	}

	public static function active_count( $slug = '' ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( $slug ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE slug = %s AND last_seen >= %s', $slug, self::since() ) );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT site_hash) FROM ' . self::table() . ' WHERE last_seen >= %s', self::since() ) );
		// phpcs:enable
	}

	/**
	 * [ version => count ] for one plugin's active sites.
	 */
	public static function versions( $slug ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT version, COUNT(*) AS n FROM ' . self::table() . ' WHERE slug = %s AND last_seen >= %s GROUP BY version', $slug, self::since() ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['version'] ] = (int) $row['n'];
		}
		uksort( $out, static function ( $a, $b ) {
			return version_compare( $b, $a );
		} );
		return $out;
	}

	public static function query( $slug, $search, $page, $per_page ) {
		global $wpdb;
		$where = array( '1=1' );
		$args  = array();
		if ( $slug ) {
			$where[] = 'slug = %s';
			$args[]  = $slug;
		}
		if ( $search ) {
			$where[] = 'site_url LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$sql_where = implode( ' AND ', $where );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE {$sql_where}", $args ) : 'SELECT COUNT(*) FROM ' . self::table() );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE {$sql_where} ORDER BY last_seen DESC LIMIT %d OFFSET %d", array_merge( $args, array( $per_page, ( $page - 1 ) * $per_page ) ) ),
			ARRAY_A
		);
		// phpcs:enable
		return array( $total, (array) $rows );
	}

	public static function is_active( $last_seen ) {
		return strtotime( $last_seen . ' UTC' ) >= time() - self::ACTIVE_DAYS * DAY_IN_SECONDS;
	}
}
