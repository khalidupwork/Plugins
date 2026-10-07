<?php
/**
 * Licenses repository.
 *
 * @package TalkwynHub
 */

namespace TWH\Repository;

use TWH\Domain\ExpiryCalculator;
use TWH\Domain\KeyGenerator;
use TWH\Install\Schema;
use TWH\Support\Secrets;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom tables; only internal table names are concatenated, values always use placeholders.

/**
 * CRUD and queries for {prefix}twh_licenses.
 */
final class Licenses {

	public const STATUSES = array( 'active', 'expired', 'suspended', 'revoked' );

	/**
	 * Table name.
	 */
	public static function t(): string {
		return Schema::table( 'licenses' );
	}

	/**
	 * Create a license and return [id, plain key].
	 *
	 * @param array<string, mixed> $args product_id, plan_slug, customer_id, customer_email, order_id,
	 *                                   order_item_id, wc_product_id, subscription_id, activation_limit,
	 *                                   duration_days, features, notes, expires_at (timestamp|null, optional).
	 * @return array{id: int, key: string}
	 * @throws \RuntimeException When the row cannot be inserted.
	 */
	public static function create( array $args ): array {
		global $wpdb;

		$duration = max( 0, (int) ( $args['duration_days'] ?? 365 ) );
		$expires  = array_key_exists( 'expires_at', $args ) ? $args['expires_at'] : ExpiryCalculator::initial( time(), $duration );
		$now      = Time::now_mysql();

		// Retry on the (astronomically unlikely) hash collision.
		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$key = KeyGenerator::generate( (string) apply_filters( 'twh_license_key_prefix', KeyGenerator::PREFIX, $args ) );
			$ok  = $wpdb->insert(
				self::t(),
				array(
					'product_id'       => (int) $args['product_id'],
					'plan_slug'        => sanitize_key( (string) ( $args['plan_slug'] ?? '' ) ),
					'key_hash'         => KeyGenerator::hash( $key ),
					'key_encrypted'    => Secrets::crypto()->encrypt( $key ),
					'key_last4'        => KeyGenerator::last4( $key ),
					'customer_id'      => (int) ( $args['customer_id'] ?? 0 ),
					'customer_email'   => sanitize_email( (string) ( $args['customer_email'] ?? '' ) ),
					'order_id'         => (int) ( $args['order_id'] ?? 0 ),
					'order_item_id'    => (int) ( $args['order_item_id'] ?? 0 ),
					'wc_product_id'    => (int) ( $args['wc_product_id'] ?? 0 ),
					'subscription_id'  => empty( $args['subscription_id'] ) ? null : (int) $args['subscription_id'],
					'status'           => 'active',
					'activation_limit' => max( 0, (int) ( $args['activation_limit'] ?? 1 ) ),
					'duration_days'    => $duration,
					'features'         => self::sanitize_features( (string) ( $args['features'] ?? 'pro' ) ),
					'expires_at'       => Time::to_mysql( null === $expires ? null : (int) $expires ),
					'reminders_sent'   => '',
					'created_at'       => $now,
					'updated_at'       => $now,
					'notes'            => (string) ( $args['notes'] ?? '' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( $ok ) {
				return array(
					'id'  => (int) $wpdb->insert_id,
					'key' => $key,
				);
			}
		}
		throw new \RuntimeException( 'Could not create license: ' . $wpdb->last_error );
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
	 * Find by plain key.
	 *
	 * @param string $key License key.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_key( string $key ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE key_hash = %s', KeyGenerator::hash( $key ) ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Licenses for an order.
	 *
	 * @param int $order_id Order id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_order( int $order_id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE order_id = %d ORDER BY id ASC', $order_id ), ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Licenses for an order item.
	 *
	 * @param int $order_item_id Order item id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_order_item( int $order_item_id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE order_item_id = %d ORDER BY id ASC', $order_item_id ), ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Licenses for a subscription.
	 *
	 * @param int $subscription_id Subscription id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_subscription( int $subscription_id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE subscription_id = %d ORDER BY id ASC', $subscription_id ), ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Licenses linked to a customer account.
	 *
	 * Guest licenses (customer_id 0) are deliberately NOT matched by email: WordPress
	 * does not always verify email ownership at registration. Customers link them by
	 * entering the full key (see claim()).
	 *
	 * @param int $user_id User id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_customer( int $user_id ): array {
		global $wpdb;
		if ( $user_id <= 0 ) {
			return array();
		}
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE customer_id = %d ORDER BY id DESC', $user_id ), ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Whether a user owns a license.
	 *
	 * @param array<string, mixed> $license License.
	 * @param int                  $user_id User id.
	 */
	public static function is_owned_by( array $license, int $user_id ): bool {
		return $user_id > 0 && (int) $license['customer_id'] === $user_id;
	}

	/**
	 * Link an unowned (guest or manual) license to a user. Knowing the full key
	 * proves ownership.
	 *
	 * @param string $key     Full license key.
	 * @param int    $user_id User id.
	 * @return array<string, mixed>|null The license, or null if the key is unknown or owned by someone else.
	 */
	public static function claim( string $key, int $user_id ): ?array {
		global $wpdb;
		$license = self::find_by_key( $key );
		if ( ! $license || $user_id <= 0 ) {
			return null;
		}
		if ( (int) $license['customer_id'] === $user_id ) {
			return $license;
		}
		if ( 0 !== (int) $license['customer_id'] ) {
			return null;
		}
		$updated = $wpdb->query(
			$wpdb->prepare( 'UPDATE ' . self::t() . ' SET customer_id = %d, updated_at = %s WHERE id = %d AND customer_id = 0', $user_id, Time::now_mysql(), (int) $license['id'] )
		);
		return $updated ? self::find( (int) $license['id'] ) : null;
	}

	/**
	 * Update fields.
	 *
	 * @param int                  $id     Id.
	 * @param array<string, mixed> $fields Column => value. expires_at may be an int timestamp or null.
	 */
	public static function update( int $id, array $fields ): bool {
		global $wpdb;
		$allowed = array(
			'product_id'       => '%d',
			'plan_slug'        => '%s',
			'customer_id'      => '%d',
			'customer_email'   => '%s',
			'wc_product_id'    => '%d',
			'subscription_id'  => '%d',
			'status'           => '%s',
			'activation_limit' => '%d',
			'duration_days'    => '%d',
			'features'         => '%s',
			'expires_at'       => '%s',
			'reminders_sent'   => '%s',
			'notes'            => '%s',
		);
		$data    = array();
		$formats = array();
		foreach ( $fields as $col => $value ) {
			if ( ! isset( $allowed[ $col ] ) ) {
				continue;
			}
			if ( 'expires_at' === $col && ( is_int( $value ) || null === $value ) ) {
				$value = Time::to_mysql( $value );
			}
			if ( 'status' === $col && ! in_array( $value, self::STATUSES, true ) ) {
				continue;
			}
			if ( 'features' === $col ) {
				$value = self::sanitize_features( (string) $value );
			}
			$data[ $col ] = $value;
			$formats[]    = $allowed[ $col ];
		}
		if ( ! $data ) {
			return false;
		}
		$data['updated_at'] = Time::now_mysql();
		$formats[]          = '%s';
		return false !== $wpdb->update( self::t(), $data, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Decrypt the plain key for display to its owner/admin.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function plain_key( array $license ): ?string {
		return Secrets::crypto()->decrypt( (string) $license['key_encrypted'] );
	}

	/**
	 * Mark active licenses past their expiry as expired.
	 *
	 * @return array<int, array<string, mixed>> Licenses that were expired now.
	 */
	public static function expire_due(): array {
		global $wpdb;
		$now  = Time::now_mysql();
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= %s LIMIT 500", $now ),
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$updated = $wpdb->query(
				$wpdb->prepare( 'UPDATE ' . self::t() . " SET status = 'expired', updated_at = %s WHERE id = %d AND status = 'active'", $now, (int) $row['id'] )
			);
			if ( $updated ) {
				$row['status'] = 'expired';
				$out[]         = self::hydrate( $row );
			}
		}
		return $out;
	}

	/**
	 * Active licenses expiring within N days.
	 *
	 * @param int $days Days.
	 * @return array<int, array<string, mixed>>
	 */
	public static function expiring_within( int $days ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::t() . " WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at > %s AND expires_at <= %s ORDER BY expires_at ASC LIMIT 2000",
				Time::now_mysql(),
				gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS )
			),
			ARRAY_A
		);
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Admin search.
	 *
	 * @param array<string, mixed> $args search, status, plan, product_id, orderby, order, per_page, page.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function search( array $args ): array {
		global $wpdb;
		$l      = self::t();
		$a      = Schema::table( 'activations' );
		$where  = array( '1=1' );
		$params = array();

		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			if ( is_numeric( $search ) ) {
				$where[]  = '(l.id = %d OR l.order_id = %d)';
				$params[] = (int) $search;
				$params[] = (int) $search;
			} elseif ( preg_match( '/^[A-Z0-9-]+$/i', $search ) && strlen( KeyGenerator::normalize( $search ) ) >= 19 ) {
				$where[]  = 'l.key_hash = %s';
				$params[] = KeyGenerator::hash( $search );
			} else {
				$users    = get_users(
					array(
						'search'         => '*' . $search . '*',
						'search_columns' => array( 'user_email', 'user_login', 'display_name' ),
						'fields'         => 'ID',
						'number'         => 200,
					)
				);
				$user_sql = $users ? ' OR l.customer_id IN (' . implode( ',', array_map( 'intval', $users ) ) . ')' : '';
				$where[]  = "(l.key_last4 = %s OR l.customer_email LIKE %s OR l.id IN (SELECT license_id FROM {$a} WHERE domain_normalized LIKE %s){$user_sql})";
				$params[] = strtoupper( substr( $search, -4 ) );
				$params[] = $like;
				$params[] = $like;
			}
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'l.status = %s';
			$params[] = (string) $args['status'];
		}
		if ( ! empty( $args['plan'] ) ) {
			$where[]  = 'l.plan_slug = %s';
			$params[] = (string) $args['plan'];
		}
		if ( ! empty( $args['product_id'] ) ) {
			$where[]  = 'l.product_id = %d';
			$params[] = (int) $args['product_id'];
		}

		$orderby_map = array(
			'id'         => 'l.id',
			'expires_at' => 'l.expires_at',
			'created_at' => 'l.created_at',
			'status'     => 'l.status',
		);
		$orderby     = $orderby_map[ (string) ( $args['orderby'] ?? 'id' ) ] ?? 'l.id';
		$order       = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		$per_page    = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$offset      = max( 0, ( (int) ( $args['page'] ?? 1 ) - 1 ) * $per_page );
		$sql_where   = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$l} l WHERE {$sql_where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql = "SELECT l.*, (SELECT COUNT(*) FROM {$a} x WHERE x.license_id = l.id AND x.deactivated_at IS NULL AND x.is_dev_site = 0) AS sites_used
			FROM {$l} l WHERE {$sql_where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );

		return array(
			'rows'  => array_map( array( self::class, 'hydrate' ), $rows ),
			'total' => $total,
		);
	}

	/**
	 * Distinct plan slugs.
	 *
	 * @return string[]
	 */
	public static function plans(): array {
		global $wpdb;
		return array_map( 'strval', (array) $wpdb->get_col( 'SELECT DISTINCT plan_slug FROM ' . self::t() . " WHERE plan_slug <> '' ORDER BY plan_slug" ) );
	}

	/**
	 * Features list.
	 *
	 * @param array<string, mixed> $license License.
	 * @return string[]
	 */
	public static function features( array $license ): array {
		return array_values( array_filter( explode( ',', (string) $license['features'] ) ) );
	}

	/**
	 * Expiry timestamp or null.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function expires_ts( array $license ): ?int {
		return Time::to_ts( $license['expires_at'] ?? null );
	}

	/**
	 * Effective status: an active license past its expiry is reported as expired
	 * even before the daily cron has run.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function effective_status( array $license ): string {
		$status = (string) $license['status'];
		if ( 'active' === $status && ExpiryCalculator::is_expired( self::expires_ts( $license ), time() ) ) {
			return 'expired';
		}
		return $status;
	}

	/**
	 * Normalize a features string: comma separated sanitized keys.
	 *
	 * @param string $features Raw.
	 */
	public static function sanitize_features( string $features ): string {
		$items = array();
		foreach ( explode( ',', $features ) as $item ) {
			$items[] = sanitize_key( (string) preg_replace( '/\s+/', '_', trim( $item ) ) );
		}
		$items = array_filter( $items );
		return implode( ',', array_unique( $items ) );
	}

	/**
	 * Cast numeric columns.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ): array {
		foreach ( array( 'id', 'product_id', 'customer_id', 'order_id', 'order_item_id', 'wc_product_id', 'activation_limit', 'duration_days' ) as $col ) {
			if ( isset( $row[ $col ] ) ) {
				$row[ $col ] = (int) $row[ $col ];
			}
		}
		$row['subscription_id'] = empty( $row['subscription_id'] ) ? null : (int) $row['subscription_id'];
		if ( isset( $row['sites_used'] ) ) {
			$row['sites_used'] = (int) $row['sites_used'];
		}
		return $row;
	}
}
