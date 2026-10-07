<?php
/**
 * Fixed-window rate limiter.
 *
 * @package TalkwynHub
 */

namespace TWH\Api;

use TWH\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Counts requests per scope (hashed IP, hashed license key) in a fixed window.
 * Uses the object cache when persistent, transients otherwise.
 */
final class RateLimiter {

	/**
	 * Record a hit and report whether it is allowed.
	 *
	 * @param string $scope Scope label (ip|key|download).
	 * @param string $id    Already-hashed identifier.
	 */
	public static function hit( string $scope, string $id ): bool {
		if ( '' === $id ) {
			return true;
		}
		$max    = max( 1, (int) Settings::get( 'rate_limit_requests' ) );
		$window = max( 1, (int) Settings::get( 'rate_limit_window' ) ) * MINUTE_IN_SECONDS;

		/**
		 * Filter the limit for a scope.
		 *
		 * @param int    $max   Max requests per window.
		 * @param string $scope Scope.
		 */
		$max = (int) apply_filters( 'twh_rate_limit_max', $max, $scope );

		$key   = 'twh_rl_' . substr( md5( $scope . '|' . $id ), 0, 24 );
		$now   = time();
		$state = get_transient( $key );
		if ( ! is_array( $state ) || ( $now - (int) $state['t'] ) >= $window ) {
			$state = array(
				'c' => 0,
				't' => $now,
			);
		}
		++$state['c'];
		set_transient( $key, $state, max( 1, $window - ( $now - (int) $state['t'] ) ) );

		return $state['c'] <= $max;
	}
}
