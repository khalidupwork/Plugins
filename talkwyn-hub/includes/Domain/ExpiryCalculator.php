<?php
/**
 * Expiry and renewal date math.
 *
 * @package TalkwynHub
 */

namespace TWH\Domain;

/**
 * All timestamps are unix seconds in UTC. A null expiry means lifetime.
 */
final class ExpiryCalculator {

	public const DAY = 86400;

	/**
	 * Initial expiry for a new license.
	 *
	 * @param int $now           Current time.
	 * @param int $duration_days Duration; 0 = lifetime.
	 */
	public static function initial( int $now, int $duration_days ): ?int {
		if ( $duration_days <= 0 ) {
			return null;
		}
		return $now + $duration_days * self::DAY;
	}

	/**
	 * New expiry after a renewal: from the later of (now, current expiry).
	 *
	 * @param int      $now            Current time.
	 * @param int|null $current_expiry Current expiry; null = lifetime.
	 * @param int      $duration_days  Plan duration; 0 = lifetime.
	 */
	public static function renew( int $now, ?int $current_expiry, int $duration_days ): ?int {
		if ( null === $current_expiry || $duration_days <= 0 ) {
			return null;
		}
		return max( $now, $current_expiry ) + $duration_days * self::DAY;
	}

	/**
	 * Whether a license with this expiry is expired at $now.
	 *
	 * @param int|null $expires_at Expiry.
	 * @param int      $now        Current time.
	 */
	public static function is_expired( ?int $expires_at, int $now ): bool {
		return null !== $expires_at && $expires_at <= $now;
	}

	/**
	 * Whole days until expiry (rounded up, never negative). Null for lifetime.
	 *
	 * @param int|null $expires_at Expiry.
	 * @param int      $now        Current time.
	 */
	public static function days_left( ?int $expires_at, int $now ): ?int {
		if ( null === $expires_at ) {
			return null;
		}
		return (int) max( 0, ceil( ( $expires_at - $now ) / self::DAY ) );
	}

	/**
	 * Prorated upgrade price: price difference scaled by the remaining share of the term.
	 *
	 * @param float    $current_price Price of the current plan.
	 * @param float    $target_price  Price of the target plan.
	 * @param int|null $expires_at    Current expiry; null = lifetime (full difference).
	 * @param int      $duration_days Term length in days.
	 * @param int      $now           Current time.
	 */
	public static function upgrade_price( float $current_price, float $target_price, ?int $expires_at, int $duration_days, int $now ): float {
		$diff = max( 0.0, $target_price - $current_price );
		if ( null === $expires_at || $duration_days <= 0 ) {
			return round( $diff, 2 );
		}
		$remaining = max( 0, $expires_at - $now );
		$ratio     = min( 1.0, $remaining / ( $duration_days * self::DAY ) );
		return round( $diff * $ratio, 2 );
	}

	/**
	 * Which reminder (days-before value) is due now, if any.
	 *
	 * Picks the smallest configured threshold that the license has crossed and that
	 * has not been sent yet, so a late cron run never sends a stale 30-day reminder
	 * after the 7-day one.
	 *
	 * @param int|null $expires_at    Expiry.
	 * @param int      $now           Current time.
	 * @param int[]    $reminder_days Configured days, e.g. [30, 7].
	 * @param int[]    $already_sent  Days already sent for this expiry.
	 */
	public static function due_reminder( ?int $expires_at, int $now, array $reminder_days, array $already_sent ): ?int {
		if ( null === $expires_at || $expires_at <= $now ) {
			return null;
		}
		$seconds_left = $expires_at - $now;
		$due          = null;
		foreach ( $reminder_days as $days ) {
			$days = (int) $days;
			if ( $days > 0 && $seconds_left <= $days * self::DAY ) {
				$due = null === $due ? $days : min( $due, $days );
			}
		}
		if ( null === $due || in_array( $due, array_map( 'intval', $already_sent ), true ) ) {
			return null;
		}
		return $due;
	}
}
