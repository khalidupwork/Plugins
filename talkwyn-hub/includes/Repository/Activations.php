<?php
/**
 * Activations repository.
 *
 * @package TalkwynHub
 */

namespace TWH\Repository;

use TWH\Install\Schema;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom tables; only internal table names are concatenated, values always use placeholders.

/**
 * CRUD for {prefix}twh_activations.
 */
final class Activations {

	/**
	 * Table name.
	 */
	public static function t(): string {
		return Schema::table( 'activations' );
	}

	/**
	 * Active activations for a license.
	 *
	 * @param int $license_id License id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function active_for( int $license_id ): array {
		global $wpdb;
		return (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE license_id = %d AND deactivated_at IS NULL ORDER BY id ASC', $license_id ),
			ARRAY_A
		);
	}

	/**
	 * All activations (including deactivated) for a license.
	 *
	 * @param int $license_id License id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function all_for( int $license_id ): array {
		global $wpdb;
		return (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE license_id = %d ORDER BY deactivated_at IS NULL DESC, id DESC', $license_id ),
			ARRAY_A
		);
	}

	/**
	 * Find by id.
	 *
	 * @param int $id Id.
	 * @return array<string, mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Active activation for a license + instance.
	 *
	 * @param int    $license_id  License id.
	 * @param string $instance_id Instance id.
	 * @return array<string, mixed>|null
	 */
	public static function find_active_instance( int $license_id, string $instance_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE license_id = %d AND instance_id = %s AND deactivated_at IS NULL ORDER BY id DESC LIMIT 1', $license_id, $instance_id ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Count active non-dev activations.
	 *
	 * @param int $license_id License id.
	 */
	public static function count_used( int $license_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE license_id = %d AND deactivated_at IS NULL AND is_dev_site = 0', $license_id )
		);
	}

	/**
	 * Insert.
	 *
	 * @param array<string, mixed> $data Columns.
	 */
	public static function create( array $data ): int {
		global $wpdb;
		$now = Time::now_mysql();
		$wpdb->insert(
			self::t(),
			array(
				'license_id'        => (int) $data['license_id'],
				'instance_id'       => (string) $data['instance_id'],
				'site_url'          => (string) $data['site_url'],
				'domain_normalized' => (string) $data['domain_normalized'],
				'is_dev_site'       => empty( $data['is_dev_site'] ) ? 0 : 1,
				'wp_version'        => (string) ( $data['wp_version'] ?? '' ),
				'php_version'       => (string) ( $data['php_version'] ?? '' ),
				'plugin_version'    => (string) ( $data['plugin_version'] ?? '' ),
				'last_check_at'     => $now,
				'activated_at'      => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a row.
	 *
	 * @param int                  $id   Id.
	 * @param array<string, mixed> $data Columns.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$allowed = array( 'instance_id', 'site_url', 'domain_normalized', 'is_dev_site', 'wp_version', 'php_version', 'plugin_version', 'last_check_at' );
		$data    = array_intersect_key( $data, array_flip( $allowed ) );
		if ( ! $data ) {
			return false;
		}
		return false !== $wpdb->update( self::t(), $data, array( 'id' => $id ), null, array( '%d' ) );
	}

	/**
	 * Deactivate a row.
	 *
	 * @param int $id Id.
	 */
	public static function deactivate( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->query(
			$wpdb->prepare( 'UPDATE ' . self::t() . ' SET deactivated_at = %s WHERE id = %d AND deactivated_at IS NULL', Time::now_mysql(), $id )
		);
	}

	/**
	 * Deactivate all activations of a license.
	 *
	 * @param int $license_id License id.
	 */
	public static function deactivate_all( int $license_id ): int {
		global $wpdb;
		return (int) $wpdb->query(
			$wpdb->prepare( 'UPDATE ' . self::t() . ' SET deactivated_at = %s WHERE license_id = %d AND deactivated_at IS NULL', Time::now_mysql(), $license_id )
		);
	}

	/**
	 * Plugin version distribution across active production sites.
	 *
	 * @return array<string, int>
	 */
	public static function version_distribution(): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results(
			'SELECT plugin_version, COUNT(*) AS c FROM ' . self::t() . ' WHERE deactivated_at IS NULL GROUP BY plugin_version ORDER BY c DESC LIMIT 12',
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ '' === $row['plugin_version'] ? '?' : (string) $row['plugin_version'] ] = (int) $row['c'];
		}
		return $out;
	}

	/**
	 * Count of active sites (excluding dev).
	 */
	public static function count_active_sites(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE deactivated_at IS NULL AND is_dev_site = 0' );
	}
}
