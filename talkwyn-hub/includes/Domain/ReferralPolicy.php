<?php
/**
 * Referral and commission rules (pure, no WordPress).
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Attribution, self-referral blocking, commission maths, approval and payout rules.
 */
final class ReferralPolicy {

	/**
	 * Pick the partner credited for a sale. Last click wins: the most recent of a
	 * tracked click (cookie) and a partner coupon used at checkout.
	 *
	 * @param array{partner_id: int, at: int}|null $click  Cookie attribution.
	 * @param array{partner_id: int, at: int}|null $coupon Coupon attribution (at = checkout time).
	 * @return array{partner_id: int, source: string}|null
	 */
	public static function attribute( ?array $click, ?array $coupon ): ?array {
		$candidates = array();
		if ( $click && (int) $click['partner_id'] > 0 ) {
			$candidates[] = array(
				'partner_id' => (int) $click['partner_id'],
				'at'         => (int) $click['at'],
				'source'     => 'cookie',
			);
		}
		if ( $coupon && (int) $coupon['partner_id'] > 0 ) {
			$candidates[] = array(
				'partner_id' => (int) $coupon['partner_id'],
				'at'         => (int) $coupon['at'],
				'source'     => 'coupon',
			);
		}
		if ( ! $candidates ) {
			return null;
		}
		usort(
			$candidates,
			static function ( $a, $b ) {
				return $b['at'] <=> $a['at'];
			}
		);
		return array(
			'partner_id' => $candidates[0]['partner_id'],
			'source'     => $candidates[0]['source'],
		);
	}

	/**
	 * Whether a tracked click is still inside the cookie window.
	 *
	 * @param int $clicked_at Click time.
	 * @param int $now        Now.
	 * @param int $days       Cookie days.
	 */
	public static function click_valid( int $clicked_at, int $now, int $days ): bool {
		return $clicked_at > 0 && $clicked_at <= $now && ( $now - $clicked_at ) <= $days * 86400;
	}

	/**
	 * Self-referral check: same user, same email, or same payment fingerprint.
	 *
	 * @param array{user_id: int, email: string, fingerprint: string} $partner Partner identity.
	 * @param array{user_id: int, email: string, fingerprint: string} $buyer   Buyer identity.
	 */
	public static function is_self_referral( array $partner, array $buyer ): bool {
		if ( (int) $partner['user_id'] > 0 && (int) $partner['user_id'] === (int) $buyer['user_id'] ) {
			return true;
		}
		$pe = TrialPolicy::canonical_email( (string) $partner['email'] );
		$be = TrialPolicy::canonical_email( (string) $buyer['email'] );
		if ( '' !== $pe && $pe === $be ) {
			return true;
		}
		$pf = (string) ( $partner['fingerprint'] ?? '' );
		return '' !== $pf && hash_equals( $pf, (string) ( $buyer['fingerprint'] ?? '' ) );
	}

	/**
	 * Commission rate for a sale type.
	 *
	 * @param string     $type          new|renewal.
	 * @param float      $new_rate      Default percent for new sales.
	 * @param float      $renewal_rate  Percent for renewals (0 = first payment only).
	 * @param float|null $override      Per-partner override (applies to new sales and, when renewals pay, to renewals).
	 */
	public static function rate( string $type, float $new_rate, float $renewal_rate, ?float $override ): float {
		if ( 'renewal' === $type ) {
			if ( $renewal_rate <= 0 ) {
				return 0.0;
			}
			return null !== $override ? min( $override, 100.0 ) : $renewal_rate;
		}
		return max( 0.0, min( 100.0, null !== $override ? $override : $new_rate ) );
	}

	/**
	 * Commission amount, rounded to cents. Never negative.
	 *
	 * @param float $amount Net order amount for the item(s).
	 * @param float $rate   Percent.
	 */
	public static function commission( float $amount, float $rate ): float {
		return max( 0.0, round( $amount * $rate / 100, 2 ) );
	}

	/**
	 * Whether a pending commission is old enough to approve.
	 *
	 * @param int $created_at Created timestamp.
	 * @param int $now        Now.
	 * @param int $days       Approval delay in days (refund window).
	 */
	public static function approvable( int $created_at, int $now, int $days ): bool {
		return ( $now - $created_at ) >= max( 0, $days ) * 86400;
	}

	/**
	 * New status of a commission after a refund on its order.
	 *
	 * @param string $status      Current status.
	 * @param float  $order_total Order total.
	 * @param float  $refunded    Total refunded so far.
	 * @return string The new status ('rejected' for full refunds of unpaid commissions).
	 */
	public static function after_refund( string $status, float $order_total, float $refunded ): string {
		$full = $order_total <= 0 || $refunded + 0.009 >= $order_total;
		if ( $full && in_array( $status, array( 'pending', 'approved' ), true ) ) {
			return 'rejected';
		}
		return $status;
	}

	/**
	 * Whether a balance can be paid out.
	 *
	 * @param float $approved_unpaid Approved, unpaid commission.
	 * @param float $threshold       Minimum payout.
	 */
	public static function can_pay( float $approved_unpaid, float $threshold ): bool {
		return $approved_unpaid > 0 && $approved_unpaid + 0.0001 >= $threshold;
	}

	/**
	 * Clean a referral code: 3 to 32 chars, lowercase letters, digits, dashes.
	 *
	 * @param string $code Raw code.
	 */
	public static function sanitize_code( string $code ): string {
		$code = strtolower( trim( $code ) );
		$code = (string) preg_replace( '/[^a-z0-9-]+/', '-', $code );
		$code = trim( (string) preg_replace( '/-+/', '-', $code ), '-' );
		return strlen( $code ) >= 3 ? substr( $code, 0, 32 ) : '';
	}
}
