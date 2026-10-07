<?php
/**
 * Partners repository.
 *
 * @package TalkwynHub
 */

namespace TWH\Repository;

use TWH\Domain\ReferralPolicy;
use TWH\Install\Schema;
use TWH\Support\Secrets;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom tables; only internal table names are concatenated, values always use placeholders.

/**
 * CRUD for {prefix}twh_partners.
 */
final class Partners {

	public const STATUSES = array( 'pending', 'approved', 'suspended' );

	/**
	 * Table name.
	 */
	public static function t(): string {
		return Schema::table( 'partners' );
	}

	/**
	 * Create a partner application.
	 *
	 * @param array<string, mixed> $args user_id, website, promotion, payout_method, payout_details, status.
	 * @return int Partner id (0 on failure).
	 */
	public static function create( array $args ): int {
		global $wpdb;
		$user = get_userdata( (int) $args['user_id'] );
		$base = ReferralPolicy::sanitize_code( $user ? (string) $user->user_login : '' );
		$code = self::unique_code( '' !== $base ? $base : 'partner' );
		$now  = Time::now_mysql();
		$ok   = $wpdb->insert(
			self::t(),
			array(
				'user_id'        => (int) $args['user_id'],
				'status'         => in_array( $args['status'] ?? 'pending', self::STATUSES, true ) ? (string) ( $args['status'] ?? 'pending' ) : 'pending',
				'referral_code'  => $code,
				'payout_method'  => sanitize_key( (string) ( $args['payout_method'] ?? '' ) ),
				'payout_details' => '' !== (string) ( $args['payout_details'] ?? '' ) ? Secrets::crypto()->encrypt( (string) $args['payout_details'] ) : '',
				'website'        => esc_url_raw( (string) ( $args['website'] ?? '' ) ),
				'promotion'      => sanitize_textarea_field( (string) ( $args['promotion'] ?? '' ) ),
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Find a free code, adding a number when taken.
	 *
	 * @param string $base Base code.
	 */
	public static function unique_code( string $base ): string {
		$code = $base;
		for ( $i = 2; $i < 1000 && self::find_by_code( $code ); $i++ ) {
			$code = substr( $base, 0, 28 ) . '-' . $i;
		}
		return $code;
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
	 * Find by WordPress user.
	 *
	 * @param int $user_id User id.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_user( int $user_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE user_id = %d', $user_id ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Find by referral code (case-insensitive).
	 *
	 * @param string $code Code.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_code( string $code ): ?array {
		global $wpdb;
		$code = strtolower( trim( $code ) );
		if ( '' === $code ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE referral_code = %s', $code ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Find by coupon code (case-insensitive).
	 *
	 * @param string $coupon Coupon code.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_coupon( string $coupon ): ?array {
		global $wpdb;
		$coupon = strtolower( trim( $coupon ) );
		if ( '' === $coupon ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE LOWER(coupon_code) = %s', $coupon ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Update fields.
	 *
	 * @param int                  $id     Id.
	 * @param array<string, mixed> $fields Fields.
	 */
	public static function update( int $id, array $fields ): bool {
		global $wpdb;
		$allowed = array(
			'status'          => '%s',
			'referral_code'   => '%s',
			'code_changed'    => '%d',
			'commission_rate' => '%s',
			'payout_method'   => '%s',
			'payout_details'  => '%s',
			'website'         => '%s',
			'promotion'       => '%s',
			'coupon_code'     => '%s',
		);
		$data    = array();
		$formats = array();
		foreach ( $fields as $col => $value ) {
			if ( ! isset( $allowed[ $col ] ) ) {
				continue;
			}
			if ( 'status' === $col && ! in_array( $value, self::STATUSES, true ) ) {
				continue;
			}
			if ( 'payout_details' === $col ) {
				$value = '' !== (string) $value ? Secrets::crypto()->encrypt( (string) $value ) : '';
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
	 * Decrypted payout details.
	 *
	 * @param array<string, mixed> $partner Partner.
	 */
	public static function payout_details( array $partner ): string {
		if ( '' === (string) $partner['payout_details'] ) {
			return '';
		}
		return (string) Secrets::crypto()->decrypt( (string) $partner['payout_details'] );
	}

	/**
	 * List partners with totals for the admin.
	 *
	 * @param string $status Status filter ('' for all).
	 * @return array<int, array<string, mixed>>
	 */
	public static function all( string $status = '' ): array {
		global $wpdb;
		$p     = self::t();
		$r     = Schema::table( 'referrals' );
		$v     = Schema::table( 'referral_visits' );
		$where = '' !== $status ? $wpdb->prepare( 'WHERE p.status = %s', $status ) : '';
		$rows  = (array) $wpdb->get_results(
			"SELECT p.*,
				(SELECT COUNT(*) FROM {$v} WHERE partner_id = p.id) AS clicks,
				(SELECT COUNT(*) FROM {$r} WHERE partner_id = p.id AND status <> 'rejected') AS referrals,
				(SELECT COUNT(*) FROM {$r} WHERE partner_id = p.id AND status = 'rejected') AS rejected,
				(SELECT COALESCE(SUM(commission),0) FROM {$r} WHERE partner_id = p.id AND status = 'approved') AS approved_unpaid,
				(SELECT COALESCE(SUM(commission),0) FROM {$r} WHERE partner_id = p.id AND status = 'pending') AS pending,
				(SELECT COALESCE(SUM(commission),0) FROM {$r} WHERE partner_id = p.id AND status = 'paid') AS paid
			FROM {$p} p {$where} ORDER BY p.status = 'pending' DESC, p.id DESC LIMIT 500",
			ARRAY_A
		);
		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Cast numeric columns.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ): array {
		foreach ( array( 'id', 'user_id', 'code_changed', 'clicks', 'referrals', 'rejected' ) as $col ) {
			if ( isset( $row[ $col ] ) ) {
				$row[ $col ] = (int) $row[ $col ];
			}
		}
		foreach ( array( 'approved_unpaid', 'pending', 'paid' ) as $col ) {
			if ( isset( $row[ $col ] ) ) {
				$row[ $col ] = (float) $row[ $col ];
			}
		}
		$row['commission_rate'] = null === $row['commission_rate'] || '' === $row['commission_rate'] ? null : (float) $row['commission_rate'];
		return $row;
	}
}
