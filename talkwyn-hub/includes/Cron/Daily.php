<?php
/**
 * Daily maintenance: expiry, reminders, log pruning.
 *
 * @package TalkwynHub
 */

namespace TWH\Cron;

use TWH\Domain\ExpiryCalculator;
use TWH\Email\Mailer;
use TWH\Repository\Events;
use TWH\Repository\Licenses;
use TWH\Support\Settings;
use TWH\Woo\Subscriptions;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on the `twh_daily` WP-Cron event.
 */
final class Daily {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'twh_daily', array( self::class, 'run' ) );
		// Self-heal if the event was lost (e.g. after a migration).
		add_action(
			'init',
			static function () {
				if ( ! wp_next_scheduled( 'twh_daily' ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'twh_daily' );
				}
			}
		);
	}

	/**
	 * Run all tasks.
	 */
	public static function run(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		self::expire();
		self::reminders();
		\TWH\Trial\Trial::cron();
		/**
		 * Daily maintenance hook for add-ons (Partners approval runs here).
		 */
		do_action( 'twh_daily_tasks' );
		Events::prune( (int) Settings::get( 'log_retention_days' ) );
		delete_transient( 'twh_dashboard_stats' );
	}

	/**
	 * Mark due licenses as expired and send the "expired" email.
	 */
	public static function expire(): int {
		$count = 0;
		// Batches of 500 until done.
		do {
			$batch = Licenses::expire_due();
			foreach ( $batch as $license ) {
				$is_trial = ! empty( $license['is_trial'] );
				Events::log(
					'expire',
					(int) $license['id'],
					array(
						'expires_at' => $license['expires_at'],
						'reason'     => $is_trial ? 'trial_ended' : 'term_ended',
					),
					''
				);
				// A subscription renewal may still be in progress; don't nag those customers.
				if ( ! Subscriptions::auto_renews( $license ) ) {
					Mailer::send_template( $is_trial ? 'trial_ended' : 'expired', $license );
				}
				++$count;
			}
			$more = count( $batch ) >= 500;
		} while ( $more );
		return $count;
	}

	/**
	 * Send "expires in N days" reminders once per threshold per expiry date.
	 */
	public static function reminders(): int {
		$days = Settings::reminder_days();
		if ( ! $days ) {
			return 0;
		}
		$sent = 0;
		$now  = time();
		foreach ( Licenses::expiring_within( max( $days ) ) as $license ) {
			// Trials get their own reminders (Trial::cron).
			if ( ! empty( $license['is_trial'] ) || Subscriptions::auto_renews( $license ) ) {
				continue;
			}
			$already = array_filter( array_map( 'intval', explode( ',', (string) $license['reminders_sent'] ) ) );
			$due     = ExpiryCalculator::due_reminder( Licenses::expires_ts( $license ), $now, $days, $already );
			if ( null === $due ) {
				continue;
			}
			// Mark every threshold at or above the one sent, so a skipped 30-day reminder is not sent after the 7-day one.
			$mark = $already;
			foreach ( $days as $d ) {
				if ( $d >= $due ) {
					$mark[] = $d;
				}
			}
			Licenses::update( (int) $license['id'], array( 'reminders_sent' => implode( ',', array_unique( $mark ) ) ) );
			Mailer::send_template( 'reminder', $license );
			Events::log( 'reminder', (int) $license['id'], array( 'days' => $due ), '' );
			++$sent;
		}
		return $sent;
	}
}
