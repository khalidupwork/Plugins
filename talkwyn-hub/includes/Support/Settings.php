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
			'email_license_subject'  => __( 'Your Talkwyn license is ready', 'talkwyn-hub' ),
			'email_license_body'     => __( "Hi {customer_name},\n\nThank you for choosing {product_name}. Your {plan} license is ready, and your key is below.\n\nTo turn on Pro, open Talkwyn in your WordPress dashboard, go to the License tab, and paste the key. Staging and local sites are free and don't use a slot.\n\nSites: {activation_limit}\nValid until: {expires_at}\n\nYou can see your sites, downloads, and invoices in your account at any time.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_reminder_subject' => __( 'Your Talkwyn license renews in {days_left} days', 'talkwyn-hub' ),
			'email_reminder_body'    => __( "Hi {customer_name},\n\nYour {product_name} license ({plan}, key ending {key_last4}) is due for renewal on {expires_at}.\n\nRenew before then to keep Pro features and updates running on your sites. It takes one click and your renewal discount is already applied.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_expired_subject'  => __( 'Your Talkwyn license has expired', 'talkwyn-hub' ),
			'email_expired_body'     => __( "Hi {customer_name},\n\nYour {product_name} license ({plan}, key ending {key_last4}) expired on {expires_at}. Talkwyn keeps answering with the free features, but Pro features and updates are paused.\n\nRenew in one click and everything picks up where it left off, with the same key.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_renewed_subject'  => __( 'Your Talkwyn license was renewed', 'talkwyn-hub' ),
			'email_renewed_body'     => __( "Hi {customer_name},\n\nThanks for renewing. Your {product_name} license (key ending {key_last4}) is now valid until {expires_at}. Nothing to do on your sites, updates keep coming.\n\nThe {site_name} team", 'talkwyn-hub' ),
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
