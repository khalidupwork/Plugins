<?php
/**
 * Referrals (commissions), visits and payouts repository.
 *
 * @package TalkwynHub
 */

namespace TWH\Repository;

use TWH\Install\Schema;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom tables; only internal table names are concatenated, values always use placeholders.

/**
 * {prefix}twh_referrals, twh_referral_visits and twh_payouts.
 */
final class Referrals {

	public const STATUSES = array( 'pending', 'approved', 'paid', 'rejected' );

	/**
	 * Referrals table.
	 */
	public static function t(): string {
		return Schema::table( 'referrals' );
	}

	/**
	 * Visits table.
	 */
	public static function tv(): string {
		return Schema::table( 'referral_visits' );
	}

	/**
	 * Payouts table.
	 */
	public static function tp(): string {
		return Schema::table( 'payouts' );
	}

	/**
	 * Record a visit (one per partner, IP and day).
	 *
	 * @param int    $partner_id Partner id.
	 * @param string $landing    Landing URL.
	 * @param string $referrer   HTTP referrer.
	 * @param string $ip_hash    Hashed IP.
	 */
	public static function add_visit( int $partner_id, string $landing, string $referrer, string $ip_hash ): void {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$seen  = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::tv() . ' WHERE partner_id = %d AND ip_hash = %s AND created_at >= %s LIMIT 1', $partner_id, $ip_hash, $since ) );
		if ( $seen ) {
			return;
		}
		$wpdb->insert(
			self::tv(),
			array(
				'partner_id'  => $partner_id,
				'landing_url' => substr( esc_url_raw( $landing ), 0, 255 ),
				'referrer'    => substr( esc_url_raw( $referrer ), 0, 255 ),
				'ip_hash'     => $ip_hash,
				'created_at'  => Time::now_mysql(),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Create a referral row; duplicates (same order, partner, license, type) are ignored.
	 *
	 * @param array<string, mixed> $r Fields.
	 * @return int Id, or 0 when it already existed.
	 */
	public static function create( array $r ): int {
		global $wpdb;
		$ok = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::t() . ' (partner_id, order_id, customer_id, license_id, type, plan_slug, amount, commission, currency, status, reason, source, click_to_buy, ip_hash, created_at)
				VALUES (%d, %d, %d, %d, %s, %s, %f, %f, %s, %s, %s, %s, %d, %s, %s)',
				(int) $r['partner_id'],
				(int) ( $r['order_id'] ?? 0 ),
				(int) ( $r['customer_id'] ?? 0 ),
				(int) ( $r['license_id'] ?? 0 ),
				(string) ( $r['type'] ?? 'new' ),
				(string) ( $r['plan_slug'] ?? '' ),
				(float) ( $r['amount'] ?? 0 ),
				(float) ( $r['commission'] ?? 0 ),
				(string) ( $r['currency'] ?? '' ),
				(string) ( $r['status'] ?? 'pending' ),
				(string) ( $r['reason'] ?? '' ),
				(string) ( $r['source'] ?? 'cookie' ),
				(int) ( $r['click_to_buy'] ?? 0 ),
				(string) ( $r['ip_hash'] ?? '' ),
				Time::now_mysql()
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
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
	 * Referrals for an order.
	 *
	 * @param int $order_id Order id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_order( int $order_id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE order_id = %d', $order_id ), ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Referrals of a partner (newest first).
	 *
	 * @param int    $partner_id Partner id.
	 * @param string $status     Status filter.
	 * @param int    $limit      Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_partner( int $partner_id, string $status = '', int $limit = 200 ): array {
		global $wpdb;
		$sql  = 'SELECT * FROM ' . self::t() . ' WHERE partner_id = %d';
		$args = array( $partner_id );
		if ( '' !== $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}
		$sql   .= ' ORDER BY id DESC LIMIT %d';
		$args[] = $limit;
		$rows   = (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * All referrals for the admin list.
	 *
	 * @param string $status Status filter.
	 * @return array<int, array<string, mixed>>
	 */
	public static function all( string $status = '' ): array {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::t();
		if ( '' !== $status ) {
			$sql = $wpdb->prepare( $sql . ' WHERE status = %s', $status );
		}
		$rows = (array) $wpdb->get_results( $sql . ' ORDER BY id DESC LIMIT 500', ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Change status.
	 *
	 * @param int    $id     Id.
	 * @param string $status New status.
	 * @param string $reason Reason (required for rejections by the admin).
	 */
	public static function set_status( int $id, string $status, string $reason = '' ): bool {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		$data = array(
			'status' => $status,
			'reason' => substr( $reason, 0, 255 ),
		);
		if ( 'approved' === $status ) {
			$data['approved_at'] = Time::now_mysql();
		}
		if ( 'paid' === $status ) {
			$data['paid_at'] = Time::now_mysql();
		}
		return false !== $wpdb->update( self::t(), $data, array( 'id' => $id ) );
	}

	/**
	 * Update the amounts of a referral (partial refunds).
	 *
	 * @param int   $id         Id.
	 * @param float $amount     New base amount.
	 * @param float $commission New commission.
	 */
	public static function set_amounts( int $id, float $amount, float $commission ): bool {
		global $wpdb;
		return false !== $wpdb->update(
			self::t(),
			array(
				'amount'     => $amount,
				'commission' => $commission,
			),
			array( 'id' => $id ),
			array( '%f', '%f' ),
			array( '%d' )
		);
	}

	/**
	 * Pending referrals created before a date.
	 *
	 * @param string $before MySQL date.
	 * @return array<int, array<string, mixed>>
	 */
	public static function pending_before( string $before ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE status = 'pending' AND created_at <= %s ORDER BY id ASC LIMIT 1000", $before ), ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Totals per status for a partner.
	 *
	 * @param int $partner_id Partner id.
	 * @return array{pending: float, approved: float, paid: float, rejected: float}
	 */
	public static function totals( int $partner_id ): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, SUM(commission) AS total FROM ' . self::t() . ' WHERE partner_id = %d GROUP BY status', $partner_id ), ARRAY_A );
		$out  = array(
			'pending'  => 0.0,
			'approved' => 0.0,
			'paid'     => 0.0,
			'rejected' => 0.0,
		);
		foreach ( $rows as $row ) {
			$out[ (string) $row['status'] ] = round( (float) $row['total'], 2 );
		}
		return $out;
	}

	/**
	 * Count paid conversions (non-rejected referrals) for a partner since a date.
	 *
	 * @param int    $partner_id Partner id.
	 * @param string $since      MySQL date.
	 */
	public static function count_since( int $partner_id, string $since ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . " WHERE partner_id = %d AND status <> 'rejected' AND created_at >= %s", $partner_id, $since ) );
	}

	/**
	 * Clicks since a date.
	 *
	 * @param int    $partner_id Partner id.
	 * @param string $since      MySQL date.
	 */
	public static function clicks_since( int $partner_id, string $since ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::tv() . ' WHERE partner_id = %d AND created_at >= %s', $partner_id, $since ) );
	}

	/**
	 * Daily clicks and conversions for a chart.
	 *
	 * @param int $partner_id Partner id.
	 * @param int $days       Range in days.
	 * @return array<string, array{clicks: int, conversions: int}> Keyed Y-m-d (or Y-m for ranges over 90 days).
	 */
	public static function series( int $partner_id, int $days ): array {
		global $wpdb;
		$monthly = $days > 90;
		$fmt     = $monthly ? '%Y-%m' : '%Y-%m-%d';
		$since   = gmdate( 'Y-m-d 00:00:00', time() - $days * DAY_IN_SECONDS );
		$out     = array();
		if ( $monthly ) {
			for ( $i = 11; $i >= 0; $i-- ) {
				$out[ gmdate( 'Y-m', strtotime( "-{$i} months" ) ) ] = array(
					'clicks'      => 0,
					'conversions' => 0,
				);
			}
		} else {
			for ( $i = $days - 1; $i >= 0; $i-- ) {
				$out[ gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) ] = array(
					'clicks'      => 0,
					'conversions' => 0,
				);
			}
		}
		$clicks = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT DATE_FORMAT(created_at, %s) AS d, COUNT(*) AS n FROM ' . self::tv() . ' WHERE partner_id = %d AND created_at >= %s GROUP BY d', $fmt, $partner_id, $since ), ARRAY_A );
		$conv   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT DATE_FORMAT(created_at, %s) AS d, COUNT(*) AS n FROM ' . self::t() . " WHERE partner_id = %d AND status <> 'rejected' AND created_at >= %s GROUP BY d", $fmt, $partner_id, $since ), ARRAY_A );
		foreach ( $clicks as $row ) {
			if ( isset( $out[ $row['d'] ] ) ) {
				$out[ $row['d'] ]['clicks'] = (int) $row['n'];
			}
		}
		foreach ( $conv as $row ) {
			if ( isset( $out[ $row['d'] ] ) ) {
				$out[ $row['d'] ]['conversions'] = (int) $row['n'];
			}
		}
		return $out;
	}

	/**
	 * Fraud signals for a partner.
	 *
	 * @param int $partner_id Partner id.
	 * @return array{top_ip_share: float, fast_buys: int, refund_rate: float}
	 */
	public static function signals( int $partner_id ): array {
		global $wpdb;
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE partner_id = %d', $partner_id ) );
		if ( 0 === $total ) {
			return array(
				'top_ip_share' => 0.0,
				'fast_buys'    => 0,
				'refund_rate'  => 0.0,
			);
		}
		$top      = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) AS n FROM ' . self::t() . " WHERE partner_id = %d AND ip_hash <> '' GROUP BY ip_hash ORDER BY n DESC LIMIT 1", $partner_id ) );
		$fast     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE partner_id = %d AND click_to_buy IS NOT NULL AND click_to_buy > 0 AND click_to_buy < 60', $partner_id ) );
		$rejected = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . " WHERE partner_id = %d AND status = 'rejected' AND reason IN ('refund','chargeback','cancelled')", $partner_id ) );
		return array(
			'top_ip_share' => round( 100 * $top / $total, 1 ),
			'fast_buys'    => $fast,
			'refund_rate'  => round( 100 * $rejected / $total, 1 ),
		);
	}

	/**
	 * Record a payout and mark the partner's approved referrals as paid.
	 *
	 * @param int    $partner_id Partner id.
	 * @param string $method     Method.
	 * @param string $reference  Reference.
	 * @return array{id: int, amount: float}|null
	 */
	public static function pay( int $partner_id, string $method, string $reference ): ?array {
		global $wpdb;
		$rows = self::for_partner( $partner_id, 'approved', 5000 );
		if ( ! $rows ) {
			return null;
		}
		$amount = 0.0;
		foreach ( $rows as $row ) {
			$amount += (float) $row['commission'];
		}
		$amount = round( $amount, 2 );
		$wpdb->insert(
			self::tp(),
			array(
				'partner_id' => $partner_id,
				'amount'     => $amount,
				'currency'   => (string) $rows[0]['currency'],
				'method'     => sanitize_key( $method ),
				'reference'  => substr( sanitize_text_field( $reference ), 0, 190 ),
				'status'     => 'paid',
				'created_at' => Time::now_mysql(),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s', '%s' )
		);
		$payout_id = (int) $wpdb->insert_id;
		$ids       = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::t() . " SET status = 'paid', paid_at = %s, payout_id = %d WHERE status = 'approved' AND id IN (" . implode( ',', $ids ) . ')',
				Time::now_mysql(),
				$payout_id
			)
		);
		return array(
			'id'     => $payout_id,
			'amount' => $amount,
		);
	}

	/**
	 * Payouts of a partner.
	 *
	 * @param int $partner_id Partner id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function payouts( int $partner_id ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::tp() . ' WHERE partner_id = %d ORDER BY id DESC LIMIT 200', $partner_id ), ARRAY_A );
	}

	/**
	 * Cast numeric columns.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ): array {
		foreach ( array( 'id', 'partner_id', 'order_id', 'customer_id', 'license_id', 'payout_id' ) as $col ) {
			if ( isset( $row[ $col ] ) ) {
				$row[ $col ] = (int) $row[ $col ];
			}
		}
		$row['amount']       = (float) $row['amount'];
		$row['commission']   = (float) $row['commission'];
		$row['click_to_buy'] = null === $row['click_to_buy'] ? null : (int) $row['click_to_buy'];
		return $row;
	}
}
