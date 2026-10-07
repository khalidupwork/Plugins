<?php
/**
 * Releases repository.
 *
 * @package TalkwynHub
 */

namespace TWH\Repository;

use TWH\Install\Schema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom tables; only internal table names are concatenated, values always use placeholders.

/**
 * CRUD for {prefix}twh_releases.
 */
final class Releases {

	/**
	 * Table name.
	 */
	private static function t(): string {
		return Schema::table( 'releases' );
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
	 * Releases of a product (or all), newest first.
	 *
	 * @param int $product_id Product id; 0 = all.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list( int $product_id = 0 ): array {
		global $wpdb;
		if ( $product_id > 0 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE product_id = %d ORDER BY released_at DESC, id DESC', $product_id ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( 'SELECT * FROM ' . self::t() . ' ORDER BY released_at DESC, id DESC', ARRAY_A );
		}
		return (array) $rows;
	}

	/**
	 * Latest active release for a product and channel.
	 * The beta channel receives the highest version among stable and beta.
	 *
	 * @param int    $product_id Product id.
	 * @param string $channel    stable|beta.
	 * @return array<string, mixed>|null
	 */
	public static function latest( int $product_id, string $channel = 'stable' ): ?array {
		global $wpdb;
		$channels = 'beta' === $channel ? array( 'stable', 'beta' ) : array( 'stable' );
		$in       = implode( ',', array_fill( 0, count( $channels ), '%s' ) );
		$rows     = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::t() . " WHERE product_id = %d AND is_active = 1 AND channel IN ($in)",
				array_merge( array( $product_id ), $channels )
			),
			ARRAY_A
		);
		$best     = null;
		foreach ( $rows as $row ) {
			if ( null === $best || version_compare( (string) $row['version'], (string) $best['version'], '>' ) ) {
				$best = $row;
			}
		}
		return $best;
	}

	/**
	 * Whether a version already exists for product + channel.
	 *
	 * @param int    $product_id Product id.
	 * @param string $version    Version.
	 * @param string $channel    Channel.
	 */
	public static function exists( int $product_id, string $version, string $channel ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM ' . self::t() . ' WHERE product_id = %d AND version = %s AND channel = %s', $product_id, $version, $channel )
		);
	}

	/**
	 * Insert.
	 *
	 * @param array<string, mixed> $data Columns.
	 */
	public static function create( array $data ): int {
		global $wpdb;
		$ok = $wpdb->insert(
			self::t(),
			array(
				'product_id'   => (int) $data['product_id'],
				'version'      => (string) $data['version'],
				'channel'      => 'beta' === $data['channel'] ? 'beta' : 'stable',
				'zip_path'     => (string) $data['zip_path'],
				'file_size'    => (int) ( $data['file_size'] ?? 0 ),
				'checksum'     => (string) ( $data['checksum'] ?? '' ),
				'changelog'    => (string) ( $data['changelog'] ?? '' ),
				'requires_wp'  => (string) ( $data['requires_wp'] ?? '' ),
				'requires_php' => (string) ( $data['requires_php'] ?? '' ),
				'tested_wp'    => (string) ( $data['tested_wp'] ?? '' ),
				'released_at'  => (string) $data['released_at'],
				'is_active'    => empty( $data['is_active'] ) ? 0 : 1,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		$id = $ok ? (int) $wpdb->insert_id : 0;
		if ( $id ) {
			self::refresh_latest( (int) $data['product_id'] );
		}
		return $id;
	}

	/**
	 * Update editable fields.
	 *
	 * @param int                  $id   Id.
	 * @param array<string, mixed> $data Columns.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$release = self::find( $id );
		if ( ! $release ) {
			return false;
		}
		$allowed = array_intersect_key( $data, array_flip( array( 'changelog', 'requires_wp', 'requires_php', 'tested_wp', 'is_active', 'channel' ) ) );
		if ( ! $allowed ) {
			return false;
		}
		$ok = false !== $wpdb->update( self::t(), $allowed, array( 'id' => $id ), null, array( '%d' ) );
		self::refresh_latest( (int) $release['product_id'] );
		return $ok;
	}

	/**
	 * Delete a release row (file removal is the caller's job).
	 *
	 * @param int $id Id.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$release = self::find( $id );
		if ( ! $release ) {
			return false;
		}
		$ok = (bool) $wpdb->delete( self::t(), array( 'id' => $id ), array( '%d' ) );
		self::refresh_latest( (int) $release['product_id'] );
		return $ok;
	}

	/**
	 * Store the newest active stable version on the product.
	 *
	 * @param int $product_id Product id.
	 */
	public static function refresh_latest( int $product_id ): void {
		$latest = self::latest( $product_id, 'stable' );
		Products::update( $product_id, array( 'latest_version' => $latest ? (string) $latest['version'] : '' ) );
	}
}
