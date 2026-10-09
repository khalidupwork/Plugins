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
			'reminder_days'                     => '30,7',
			'renewal_discount'                  => 20,
			'rate_limit_requests'               => 30,
			'rate_limit_window'                 => 10,
			'dev_domains'                       => implode( "\n", Domain::DEFAULT_DEV_PATTERNS ),
			'log_retention_days'                => 180,
			'delete_on_uninstall'               => 0,
			'admin_notify_email'                => '',
			'email_from_name'                   => '',
			'email_from_address'                => '',
			'email_license_subject'             => __( 'Your Talkwyn license is ready', 'talkwyn-hub' ),
			'email_license_body'                => __( "Hi {customer_name},\n\nThank you for choosing {product_name}. Your {plan} license is ready in your account.\n\nTo turn on Pro, copy your key from your account, open Talkwyn in your WordPress dashboard, go to the License tab, and paste it. Staging and local sites are free and don't use a slot.\n\nSites: {activation_limit}\nValid until: {expires_at}\n\nYou can see your sites, downloads, and invoices in your account at any time.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_reminder_subject'            => __( 'Your Talkwyn license renews in {days_left} days', 'talkwyn-hub' ),
			'email_reminder_body'               => __( "Hi {customer_name},\n\nYour {product_name} license ({plan}, key ending {key_last4}) is due for renewal on {expires_at}.\n\nRenew before then to keep updates and support running on your sites. It takes one click and your renewal discount is already applied.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_expired_subject'             => __( 'Your Talkwyn license has expired', 'talkwyn-hub' ),
			'email_expired_body'                => __( "Hi {customer_name},\n\nYour {product_name} license ({plan}, key ending {key_last4}) expired on {expires_at}. Pro features keep working on your sites, but updates and support are paused.\n\nRenew in one click and everything picks up where it left off, with the same key.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_renewed_subject'             => __( 'Your Talkwyn license was renewed', 'talkwyn-hub' ),
			'email_renewed_body'                => __( "Hi {customer_name},\n\nThanks for renewing. Your {product_name} license (key ending {key_last4}) is now valid until {expires_at}. Nothing to do on your sites, updates keep coming.\n\nThe {site_name} team", 'talkwyn-hub' ),

			// Invoices.
			'invoice_prefix'                    => 'TW-',
			'invoice_company'                   => '',
			'invoice_address'                   => '',
			'invoice_tax_id'                    => '',
			'invoice_email'                     => '',
			'invoice_note'                      => '',

			// Keys stay in the customer dashboard; emails show only the last 4 characters.
			'email_keys'                        => 0,

			// Trial.
			'trial_enabled'                     => 1,
			'trial_days'                        => 15,
			'trial_plan'                        => 'business',
			'trial_card_mode'                   => 'none',
			'trial_product_id'                  => 0,
			'trial_reminder_days'               => '5,2',
			'trial_disposable'                  => implode( "\n", \TWH\Domain\TrialPolicy::DEFAULT_DISPOSABLE ),
			'email_trial_confirm_subject'       => __( 'Confirm your email to start your Talkwyn trial', 'talkwyn-hub' ),
			'email_trial_confirm_body'          => __( "Hi {customer_name},\n\nThanks for starting a Talkwyn Pro trial for {trial_site}. Please confirm this is your email address with the button below.\n\nRight after you confirm, we create your trial license and your account and send both to you.\n\nThe link works for 48 hours. If you didn't ask for a trial, you can ignore this email and nothing will happen.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_trial_welcome_subject'       => __( 'Your {trial_days}-day Talkwyn Pro trial has started', 'talkwyn-hub' ),
			'email_trial_welcome_body'          => __( "Hi {customer_name},\n\nWelcome to Talkwyn Pro. Every Pro feature is yours until {trial_ends_at}, and your trial key is waiting in your account.\n\nGetting set up takes about five minutes:\n1. Install Talkwyn on your WordPress site.\n2. Copy your key from your account, open Talkwyn, go to the License tab and paste it.\n3. Click Scan my site, add a free AI key, and turn the chat on.\n\nThe trial covers one site. Staging and local sites are free and don't use it.\n\n{login_details}\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_trial_reminder_subject'      => __( 'Your Talkwyn trial has {days_left} days left', 'talkwyn-hub' ),
			'email_trial_reminder_body'         => __( "Hi {customer_name},\n\nYour Talkwyn Pro trial ends on {trial_ends_at}, in {days_left} days.\n\nHere's what you've used so far: {trial_usage}\n\nTo keep Pro running, pick a plan before the trial ends. You keep the same key, so nothing changes on your site. If you don't upgrade, Talkwyn moves to the free plan and keeps all your settings, chats and leads.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_trial_ended_subject'         => __( 'Your Talkwyn trial has ended', 'talkwyn-hub' ),
			'email_trial_ended_body'            => __( "Hi {customer_name},\n\nYour Talkwyn Pro trial ended on {trial_ends_at}.\n\nTalkwyn keeps working on the free plan: it still answers visitors, captures leads, and keeps your settings and chat history. Pro features like advanced search and analytics are paused.\n\nWhen you're ready, upgrade in one click and Pro switches back on with the same key.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_trial_converted_subject'     => __( 'Welcome to Talkwyn Pro', 'talkwyn-hub' ),
			'email_trial_converted_body'        => __( "Hi {customer_name},\n\nThanks for upgrading. Your trial is now a {plan} license for {activation_limit} sites, valid until {expires_at}. You keep the same key, so there's nothing to change on your site.\n\nThe {site_name} team", 'talkwyn-hub' ),

			// Partners.
			'partners_enabled'                  => 1,
			'partner_rate_new'                  => 20,
			'partner_rate_renewal'              => 0,
			'partner_cookie_days'               => 60,
			'partner_approval_days'             => 30,
			'partner_payout_threshold'          => 50,
			'partner_methods'                   => 'paypal,wise,bank,payoneer',
			'partner_pretty_links'              => 1,
			'partner_respect_consent'           => 1,
			'email_partner_approved_subject'    => __( 'You\'re a Talkwyn Partner', 'talkwyn-hub' ),
			'email_partner_approved_body'       => __( "Hi {partner_name},\n\nYour Talkwyn Partners application is approved.\n\nYour referral link: {referral_link}\n\nYou earn {commission_rate} on paid plans from people you refer, for {cookie_days} days after their click. Your dashboard shows clicks, trials, and commissions as they happen.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_partner_referral_subject'    => __( 'New referral: {amount}', 'talkwyn-hub' ),
			'email_partner_referral_body'       => __( "Hi {partner_name},\n\nSomeone you referred just bought a {plan} plan. Your commission of {commission} is pending and will be approved after the {approval_days}-day refund window.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_partner_commission_subject'  => __( 'Commission approved: {commission}', 'talkwyn-hub' ),
			'email_partner_commission_body'     => __( "Hi {partner_name},\n\nYour commission of {commission} is approved. Payouts go out once your approved balance reaches {payout_threshold}.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_partner_payout_subject'      => __( 'Payout sent: {amount}', 'talkwyn-hub' ),
			'email_partner_payout_body'         => __( "Hi {partner_name},\n\nWe sent you {amount} by {payout_method}. Reference: {reference}.\n\nThanks for sharing Talkwyn.\n\nThe {site_name} team", 'talkwyn-hub' ),
			'email_partner_application_subject' => __( 'New partner application from {partner_name}', 'talkwyn-hub' ),
			'email_partner_application_body'    => __( "{partner_name} ({partner_email}) applied to Talkwyn Partners.\n\nWebsite or channel: {website}\nHow they will promote: {promotion}\n\nReview the application in Talkwyn Hub, Partners.", 'talkwyn-hub' ),
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
	 * Whether full license keys may appear in emails (off: last 4 characters only).
	 */
	public static function email_keys(): bool {
		return (bool) self::get( 'email_keys' );
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
	 * Trial reminder thresholds (days left), descending.
	 *
	 * @return int[]
	 */
	public static function trial_reminder_days(): array {
		$days = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) self::get( 'trial_reminder_days' ) ) ) ) ) );
		rsort( $days );
		return $days;
	}

	/**
	 * Allowed partner payout methods.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function payout_methods(): array {
		$labels  = array(
			'paypal'   => __( 'PayPal', 'talkwyn-hub' ),
			'wise'     => __( 'Wise', 'talkwyn-hub' ),
			'bank'     => __( 'Bank transfer', 'talkwyn-hub' ),
			'payoneer' => __( 'Payoneer', 'talkwyn-hub' ),
		);
		$enabled = array_filter( array_map( 'trim', explode( ',', (string) self::get( 'partner_methods' ) ) ) );
		return array_intersect_key( $labels, array_flip( $enabled ) );
	}

	/**
	 * Admin notification address.
	 */
	public static function admin_email(): string {
		$email = (string) self::get( 'admin_notify_email' );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}
}
