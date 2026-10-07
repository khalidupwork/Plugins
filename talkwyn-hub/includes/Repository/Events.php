<?php
/**
 * Event log repository.
 *
 * @package TalkwynHub
 */

namespace TWH\Repository;

use TWH\Install\Schema;
use TWH\Support\Request;
use TWH\Support\Secrets;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom tables; only internal table names are concatenated, values always use placeholders.

/**
 * Append-only log. Never store full license keys or secrets in meta.
 */
final class Events {

	public const TYPES = array(
		'activate',
		'deactivate',
		'check',
		'update_check',
		'update_download',
		'purchase',
		'renewal',
		'upgrade',
		'revoke',
		'refund',
		'chargeback',
		'expire',
		'reminder',
		'admin_edit',
		'rate_limited',
		'invalid_key',
	);

	/**
	 * Table name.
	 */
	private static function t(): string {
		return Schema::table( 'events' );
	}

	/**
	 * Log an event.
	 *
	 * @param string               $type       Type.
	 * @param int|null             $license_id License id.
	 * @param array<string, mixed> $meta       Meta.
	 * @param string|null          $ip         Client IP (hashed before storing); null = current request.
	 */
	public static function log( string $type, ?int $license_id, array $meta = array(), ?string $ip = null ): void {
		global $wpdb;
		if ( null === $ip ) {
			$ip = Request::ip();
		}
		$wpdb->insert(
			self::t(),
			array(
				'license_id' => $license_id,
				'type'       => sanitize_key( $type ),
				'ip_hash'    => Secrets::hash_ip( $ip ),
				'meta'       => $meta ? wp_json_encode( $meta ) : null,
				'created_at' => Time::now_mysql(),
			),
			array( null === $license_id ? null : '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Query events.
	 *
	 * @param array<string, mixed> $args type, license_id, from, to, per_page, page.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$params[] = (string) $args['type'];
		}
		if ( ! empty( $args['license_id'] ) ) {
			$where[]  = 'license_id = %d';
			$params[] = (int) $args['license_id'];
		}
		if ( ! empty( $args['from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = (string) $args['from'];
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = (string) $args['to'];
		}
		$per_page  = max( 1, (int) ( $args['per_page'] ?? 50 ) );
		$offset    = max( 0, ( (int) ( $args['page'] ?? 1 ) - 1 ) * $per_page );
		$sql_where = implode( ' AND ', $where );

		$count_sql = 'SELECT COUNT(*) FROM ' . self::t() . " WHERE {$sql_where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql = 'SELECT * FROM ' . self::t() . " WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Delete events older than N days.
	 *
	 * @param int $days Retention in days.
	 * @return int Rows deleted.
	 */
	public static function prune( int $days ): int {
		global $wpdb;
		if ( $days <= 0 ) {
			return 0;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t() . ' WHERE created_at < %s', $cutoff ) );
	}

	/**
	 * Sum of meta.amount for event types since a date, grouped by month (Y-m).
	 *
	 * @param string[] $types Types.
	 * @param string   $since MySQL date.
	 * @return array<string, float>
	 */
	public static function amounts_by_month( array $types, string $since ): array {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$rows         = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT type, meta, created_at FROM ' . self::t() . " WHERE type IN ($placeholders) AND created_at >= %s",
				array_merge( $types, array( $since ) )
			),
			ARRAY_A
		);
		$out          = array();
		foreach ( $rows as $row ) {
			$meta   = json_decode( (string) $row['meta'], true );
			$amount = is_array( $meta ) && isset( $meta['amount'] ) ? (float) $meta['amount'] : 0.0;
			if ( 'refund' === $row['type'] || 'chargeback' === $row['type'] ) {
				$amount = -abs( $amount );
			}
			$month         = substr( (string) $row['created_at'], 0, 7 );
			$out[ $month ] = ( $out[ $month ] ?? 0.0 ) + $amount;
		}
		return $out;
	}
}
