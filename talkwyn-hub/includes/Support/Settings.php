<?php
/**
 * Plugin settings with defaults.
 *
 * @package TalkwynHub
 */

namespace TWH\Support;

use TWH\Domain\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the single `twh_settings` option.
 */
final class Settings {

	public const OPTION = 'twh_settings';

	/**
	 * Cached values.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Default values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'reminder_days'          => '30,7',
			'renewal_discount'       => 20,
			'rate_limit_requests'    => 30,
			'rate_limit_window'      => 10,
			'dev_domains'            => implode( "\n", Domain::DEFAULT_DEV_PATTERNS ),
			'log_retention_days'     => 180,
			'delete_on_uninstall'    => 0,
			'admin_notify_email'     => '',
			'email_from_name'        => '',
			'email_license_subject'  => __( 'Your {product_name} license key', 'talkwyn-hub' ),
			'email_license_body'     => __( "Hi {customer_name},\n\nThank you for your purchase! Here is your license key for {product_name} ({plan}):\n\n{license_key}\n\nSites: {activation_limit}\nValid until: {expires_at}\n\nPaste the key into Talkwyn → Settings → License on your website to unlock Pro features and automatic updates.\n\nManage your licenses, sites and downloads: {account_url}\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_reminder_subject' => __( 'Your {product_name} license expires in {days_left} days', 'talkwyn-hub' ),
			'email_reminder_body'    => __( "Hi {customer_name},\n\nYour {product_name} license ({plan}, key ending {key_last4}) expires on {expires_at}.\n\nRenew now to keep Pro features and updates on your sites:\n{renew_url}\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_expired_subject'  => __( 'Your {product_name} license has expired', 'talkwyn-hub' ),
			'email_expired_body'     => __( "Hi {customer_name},\n\nYour {product_name} license ({plan}, key ending {key_last4}) expired on {expires_at}. Pro features and updates are now paused on your sites.\n\nRenew in one click:\n{renew_url}\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_renewed_subject'  => __( 'Your {product_name} license has been renewed', 'talkwyn-hub' ),
			'email_renewed_body'     => __( "Hi {customer_name},\n\nThanks for renewing! Your {product_name} license (key ending {key_last4}) is now valid until {expires_at}.\n\nManage your license: {account_url}\n\nThe {site_name} team", 'talkwyn-hub' ),
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Save settings (already sanitized).
	 *
	 * @param array<string, mixed> $values Values.
	 */
	public static function save( array $values ): void {
		update_option( self::OPTION, $values, false );
		self::$cache = null;
	}

	/**
	 * Reset the in-memory cache.
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Reminder thresholds in days, descending.
	 *
	 * @return int[]
	 */
	public static function reminder_days(): array {
		$days = array_filter( array_map( 'absint', explode( ',', (string) self::get( 'reminder_days' ) ) ) );
		$days = array_values( array_unique( $days ) );
		rsort( $days );
		return $days;
	}

	/**
	 * Dev host patterns from settings.
	 *
	 * @return string[]
	 */
	public static function dev_patterns(): array {
		return Domain::parse_patterns( (string) self::get( 'dev_domains' ) );
	}

	/**
	 * Admin notification address.
	 */
	public static function admin_email(): string {
		$email = (string) self::get( 'admin_notify_email' );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}
}
