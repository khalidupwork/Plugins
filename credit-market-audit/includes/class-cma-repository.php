<?php
/**
 * Persistence for audits / leads.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD helpers around the cma_audits table. The `report` column holds JSON.
 */
class CMA_Repository {

	/**
	 * Insert a new audit row.
	 *
	 * @param array $data name, email, url, ip.
	 * @return array|false The created row.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$now   = current_time( 'mysql', true );
		$token = bin2hex( random_bytes( 16 ) );

		$ok = $wpdb->insert(
			CMA_Install::table(),
			array(
				'token'      => $token,
				'name'       => isset( $data['name'] ) ? $data['name'] : '',
				'email'      => $data['email'],
				'url'        => $data['url'],
				'status'     => 'pending',
				'report'     => wp_json_encode( array() ),
				'ip'         => isset( $data['ip'] ) ? $data['ip'] : '',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? self::get_by_token( $token ) : false;
	}

	/**
	 * Fetch by public token.
	 *
	 * @param string $token Token.
	 * @return array|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}
		$table = CMA_Install::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Fetch by id.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = CMA_Install::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Update columns. `report` may be passed as an array.
	 *
	 * @param int   $id     Row id.
	 * @param array $fields Columns.
	 * @return bool
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;
		if ( isset( $fields['report'] ) && is_array( $fields['report'] ) ) {
			$fields['report'] = wp_json_encode( $fields['report'] );
		}
		$fields['updated_at'] = current_time( 'mysql', true );
		return false !== $wpdb->update( CMA_Install::table(), $fields, array( 'id' => (int) $id ) );
	}

	/**
	 * Delete rows.
	 *
	 * @param int[] $ids IDs.
	 */
	public static function delete( array $ids ) {
		global $wpdb;
		foreach ( array_map( 'absint', $ids ) as $id ) {
			$wpdb->delete( CMA_Install::table(), array( 'id' => $id ), array( '%d' ) );
		}
	}

	/**
	 * Paged listing for the admin.
	 *
	 * @param array $args per_page, page, search, orderby, order.
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'per_page' => 20,
				'page'     => 1,
				'search'   => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		$table   = CMA_Install::table();
		$where   = '1=1';
		$params  = array();
		$allowed = array( 'created_at', 'email', 'url', 'overall_score', 'status' );
		$orderby = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (email LIKE %s OR url LIKE %s OR name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$limit    = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $limit );
		$list_sql = "SELECT id, token, name, email, url, status, overall_score, email_sent, created_at FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $limit, $offset ) ) ), ARRAY_A );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * All rows for CSV export (without the heavy report column).
	 *
	 * @return array
	 */
	public static function export_rows() {
		global $wpdb;
		$table = CMA_Install::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT name, email, url, status, overall_score, email_sent, ip, created_at FROM {$table} ORDER BY created_at DESC", ARRAY_A );
	}

	/**
	 * Count audits from an IP in the last hour (rate limiting).
	 *
	 * @param string $ip IP.
	 * @return int
	 */
	public static function count_recent_by_ip( $ip ) {
		global $wpdb;
		$table = CMA_Install::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ip = %s AND created_at >= %s", $ip, $since ) );
	}

	/**
	 * Decode the JSON report column.
	 *
	 * @param array $row DB row.
	 * @return array
	 */
	private static function hydrate( array $row ) {
		$report        = json_decode( (string) $row['report'], true );
		$row['report'] = is_array( $report ) ? $report : array();
		return $row;
	}
}
