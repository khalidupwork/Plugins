<?php
/**
 * Software products repository.
 *
 * @package TalkwynHub
 */

namespace TWH\Repository;

use TWH\Install\Schema;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom tables; only internal table names are concatenated, values always use placeholders.

/**
 * CRUD for {prefix}twh_products.
 */
final class Products {

	/**
	 * Table name.
	 */
	private static function t(): string {
		return Schema::table( 'products' );
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
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Find by slug.
	 *
	 * @param string $slug Slug.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_slug( string $slug ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE slug = %s', $slug ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * All products.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . self::t() . ' ORDER BY name ASC', ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Create.
	 *
	 * @param string               $slug     Slug.
	 * @param string               $name     Name.
	 * @param string               $homepage Homepage URL.
	 * @param array<string, mixed> $meta     Meta (icons, banners, description).
	 * @return int New id, 0 on failure.
	 */
	public static function create( string $slug, string $name, string $homepage = '', array $meta = array() ): int {
		global $wpdb;
		$ok = $wpdb->insert(
			self::t(),
			array(
				'slug'           => sanitize_title( $slug ),
				'name'           => $name,
				'latest_version' => '',
				'homepage'       => $homepage,
				'meta'           => wp_json_encode( $meta ),
				'created_at'     => Time::now_mysql(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update.
	 *
	 * @param int                  $id     Id.
	 * @param array<string, mixed> $fields Fields (name, homepage, meta array, latest_version).
	 */
	public static function update( int $id, array $fields ): bool {
		global $wpdb;
		$allowed = array_intersect_key( $fields, array_flip( array( 'name', 'homepage', 'meta', 'latest_version' ) ) );
		if ( isset( $allowed['meta'] ) && is_array( $allowed['meta'] ) ) {
			$allowed['meta'] = wp_json_encode( $allowed['meta'] );
		}
		if ( ! $allowed ) {
			return false;
		}
		return false !== $wpdb->update( self::t(), $allowed, array( 'id' => $id ), null, array( '%d' ) );
	}

	/**
	 * Delete (only when nothing references it).
	 *
	 * @param int $id Id.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$licenses = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'licenses' ) . ' WHERE product_id = %d', $id ) );
		$releases = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'releases' ) . ' WHERE product_id = %d', $id ) );
		if ( $licenses > 0 || $releases > 0 ) {
			return false;
		}
		return (bool) $wpdb->delete( self::t(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Decode meta JSON.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ): array {
		$meta        = json_decode( (string) ( $row['meta'] ?? '' ), true );
		$row['meta'] = is_array( $meta ) ? $meta : array();
		$row['id']   = (int) $row['id'];
		return $row;
	}
}
