<?php
/**
 * Date helpers. All stored dates are UTC MySQL DATETIME strings.
 *
 * @package TalkwynHub
 */

namespace TWH\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Conversions between unix timestamps, MySQL UTC dates and ISO 8601.
 */
final class Time {

	/**
	 * Current time as MySQL UTC.
	 */
	public static function now_mysql(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Timestamp to MySQL UTC (null passes through).
	 *
	 * @param int|null $ts Timestamp.
	 */
	public static function to_mysql( ?int $ts ): ?string {
		return null === $ts ? null : gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * MySQL UTC to timestamp (null/empty passes through as null).
	 *
	 * @param string|null $date Date.
	 */
	public static function to_ts( ?string $date ): ?int {
		if ( null === $date || '' === $date || '0000-00-00 00:00:00' === $date ) {
			return null;
		}
		$ts = strtotime( $date . ' UTC' );
		return false === $ts ? null : $ts;
	}

	/**
	 * MySQL UTC to ISO 8601 (Z), or null.
	 *
	 * @param string|null $date Date.
	 */
	public static function to_iso( ?string $date ): ?string {
		$ts = self::to_ts( $date );
		return null === $ts ? null : gmdate( 'Y-m-d\TH:i:s\Z', $ts );
	}

	/**
	 * Localized human date for UI and emails.
	 *
	 * @param string|null $date     Date.
	 * @param string      $lifetime Text for null.
	 */
	public static function human( ?string $date, string $lifetime = '' ): string {
		$ts = self::to_ts( $date );
		if ( null === $ts ) {
			return '' !== $lifetime ? $lifetime : __( 'Never (lifetime)', 'talkwyn-hub' );
		}
		return wp_date( (string) get_option( 'date_format', 'F j, Y' ), $ts );
	}
}
