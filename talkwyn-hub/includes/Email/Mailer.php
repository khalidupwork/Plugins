<?php
/**
 * Branded transactional emails.
 *
 * @package TalkwynHub
 */

namespace TWH\Email;

use TWH\Domain\ExpiryCalculator;
use TWH\Domain\KeyGenerator;
use TWH\LicenseService;
use TWH\Repository\Licenses;
use TWH\Support\Settings;
use TWH\Support\Time;
use TWH\Woo\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Sends emails using the WooCommerce email template (header, footer, colors)
 * when available, so they match the store's branding.
 */
final class Mailer {

	/**
	 * Send the "Your license" email for every key issued in an order.
	 *
	 * @param \WC_Order                               $order   Order.
	 * @param array<int, array{id: int, key: string}> $created Created licenses.
	 */
	public static function send_order_licenses( \WC_Order $order, array $created ): void {
		if ( ! $created ) {
			return;
		}
		$first = Licenses::find( (int) $created[0]['id'] );
		if ( ! $first ) {
			return;
		}
		$keys = array();
		foreach ( $created as $c ) {
			$keys[] = $c['key'];
		}
		$vars                    = self::vars( $first );
		$vars['{license_key}']   = implode( "\n", $keys );
		$vars['{order_number}']  = $order->get_order_number();
		$vars['{customer_name}'] = $order->get_billing_first_name() ? $order->get_billing_first_name() : $vars['{customer_name}'];

		self::send( $order->get_billing_email(), 'license', $vars, $keys );
	}

	/**
	 * Send (or resend) the license email for a single key.
	 *
	 * @param array<string, mixed> $license License.
	 * @param string               $key     Plain key.
	 */
	public static function send_license( array $license, string $key ): bool {
		$vars                  = self::vars( $license );
		$vars['{license_key}'] = $key;
		return self::send( self::recipient( $license ), 'license', $vars, array( $key ) );
	}

	/**
	 * Send a templated email: reminder, expired, renewed.
	 *
	 * @param string                $type    Template type.
	 * @param array<string, mixed>  $license License.
	 * @param array<string, string> $extra  Extra placeholders.
	 */
	public static function send_template( string $type, array $license, array $extra = array() ): bool {
		return self::send( self::recipient( $license ), $type, array_merge( self::vars( $license ), $extra ) );
	}

	/**
	 * Send any configured email type to an address (partner and admin emails).
	 *
	 * @param string                $to   Recipient.
	 * @param string                $type Email type (settings keys email_{type}_subject/body).
	 * @param array<string, string> $vars Placeholders. {site_name} and {account_url} are added.
	 */
	public static function send_type( string $to, string $type, array $vars ): bool {
		$vars = array_merge(
			array(
				'{site_name}'   => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
				'{account_url}' => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'licenses' ) : home_url( '/' ),
				'{renew_url}'   => '',
			),
			$vars
		);
		return self::send( $to, $type, $vars );
	}

	/**
	 * Send the trial welcome email with the key.
	 *
	 * @param array<string, mixed> $license License.
	 * @param string               $key     Plain key.
	 * @param string               $name    First name entered on the form.
	 */
	public static function send_trial_welcome( array $license, string $key, string $name = '', string $login_details = '' ): bool {
		$vars                    = self::vars( $license );
		$vars['{license_key}']   = $key;
		$vars['{login_details}'] = $login_details;
		if ( '' !== $name ) {
			$vars['{customer_name}'] = $name;
		}
		return self::send( self::recipient( $license ), 'trial_welcome', $vars, array( $key ) );
	}

	/**
	 * First trial email: confirm the address before any license or account is created.
	 *
	 * @param string $to          Email entered on the form.
	 * @param string $name        First name entered on the form.
	 * @param string $site        Website entered on the form.
	 * @param string $confirm_url One-time confirmation link.
	 */
	public static function send_trial_confirm( string $to, string $name, string $site, string $confirm_url ): bool {
		return self::send_type(
			$to,
			'trial_confirm',
			array(
				'{customer_name}' => '' !== $name ? $name : __( 'there', 'talkwyn-hub' ),
				'{trial_site}'    => $site,
				'{confirm_url}'   => $confirm_url,
				'{trial_days}'    => (string) (int) Settings::get( 'trial_days' ),
			)
		);
	}

	/**
	 * Notify the admin that a license was revoked.
	 *
	 * @param array<string, mixed> $license License.
	 * @param string               $reason  Reason.
	 */
	public static function notify_admin_revoked( array $license, string $reason ): void {
		self::notify_admin(
			/* translators: 1: masked key, 2: reason */
			sprintf( __( 'License …%1$s revoked (%2$s)', 'talkwyn-hub' ), $license['key_last4'], $reason ),
			/* translators: 1: license id, 2: order id, 3: reason */
			sprintf( __( 'License #%1$d from order #%2$d was revoked automatically. Reason: %3$s.', 'talkwyn-hub' ), (int) $license['id'], (int) $license['order_id'], $reason ),
			$license
		);
	}

	/**
	 * Plain notification to the admin.
	 *
	 * @param string                    $subject Subject.
	 * @param string                    $message Message.
	 * @param array<string, mixed>|null $license License for a link.
	 */
	public static function notify_admin( string $subject, string $message, ?array $license = null ): void {
		$body = '<p>' . esc_html( $message ) . '</p>';
		if ( $license ) {
			$url   = admin_url( 'admin.php?page=twh-licenses&action=edit&license=' . (int) $license['id'] );
			$body .= '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'View license', 'talkwyn-hub' ) . '</a></p>';
		}
		self::deliver(
			Settings::admin_email(),
			'[Talkwyn Hub] ' . $subject,
			self::render(
				array(
					'heading' => $subject,
					'body'    => $body,
				)
			),
			$subject . "\n\n" . $message . ( $license ? "\n\n" . admin_url( 'admin.php?page=twh-licenses&action=edit&license=' . (int) $license['id'] ) : '' )
		);
	}

	/**
	 * Placeholder values for a license.
	 *
	 * @param array<string, mixed> $license License.
	 * @return array<string, string>
	 */
	public static function vars( array $license ): array {
		$name = '';
		if ( (int) $license['customer_id'] ) {
			$user = get_userdata( (int) $license['customer_id'] );
			if ( $user ) {
				$name = $user->first_name ? $user->first_name : $user->display_name;
			}
		}
		$days = ExpiryCalculator::days_left( Licenses::expires_ts( $license ), time() );
		return array(
			'{customer_name}'    => '' !== $name ? $name : __( 'there', 'talkwyn-hub' ),
			'{license_key}'      => '',
			'{key_last4}'        => (string) $license['key_last4'],
			'{product_name}'     => LicenseService::product_name( $license ),
			'{plan}'             => LicenseService::plan_label( (string) $license['plan_slug'] ),
			'{expires_at}'       => Time::human( $license['expires_at'] ),
			'{days_left}'        => null === $days ? '' : (string) $days,
			'{activation_limit}' => LicenseService::limit_label( (int) $license['activation_limit'] ),
			'{renew_url}'        => Cart::can_renew( $license ) ? Cart::renew_url( $license ) : '',
			'{account_url}'      => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'licenses' ) : home_url( '/' ),
			'{site_name}'        => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'{order_number}'     => (string) $license['order_id'],
			'{trial_ends_at}'    => ! empty( $license['trial_ends_at'] ) ? Time::human( $license['trial_ends_at'] ) : '',
			'{trial_days}'       => (string) (int) Settings::get( 'trial_days' ),
			'{upgrade_url}'      => ! empty( $license['is_trial'] ) ? \TWH\Trial\Trial::upgrade_url( $license ) : '',
		);
	}

	/**
	 * Documented placeholders.
	 *
	 * @return string[]
	 */
	public static function placeholders(): array {
		return array( '{customer_name}', '{license_key}', '{key_last4}', '{product_name}', '{plan}', '{expires_at}', '{days_left}', '{activation_limit}', '{renew_url}', '{account_url}', '{site_name}', '{order_number}', '{trial_ends_at}', '{trial_days}', '{trial_usage}', '{upgrade_url}', '{confirm_url}', '{trial_site}', '{login_details}' );
	}

	/**
	 * Recipient for a license.
	 *
	 * @param array<string, mixed> $license License.
	 */
	private static function recipient( array $license ): string {
		if ( (int) $license['customer_id'] ) {
			$user = get_userdata( (int) $license['customer_id'] );
			if ( $user && is_email( $user->user_email ) ) {
				return (string) $user->user_email;
			}
		}
		return (string) $license['customer_email'];
	}

	/**
	 * Heading and call-to-action per email type.
	 *
	 * @param string                $type Type.
	 * @param array<string, string> $vars Placeholders.
	 * @return array{heading: string, button: string, url: string}
	 */
	private static function layout( string $type, array $vars ): array {
		$renew   = '' !== $vars['{renew_url}'] ? $vars['{renew_url}'] : $vars['{account_url}'];
		$layouts = array(
			'license'  => array( __( 'You\'re in. Here is your license.', 'talkwyn-hub' ), __( 'Manage your license', 'talkwyn-hub' ), $vars['{account_url}'] ),
			'reminder' => array( __( 'Your license renews soon', 'talkwyn-hub' ), __( 'Renew now', 'talkwyn-hub' ), $renew ),
			'expired'  => array( __( 'Your license has expired', 'talkwyn-hub' ), __( 'Renew in one click', 'talkwyn-hub' ), $renew ),
			'renewed'  => array( __( 'Thanks for renewing', 'talkwyn-hub' ), __( 'View your license', 'talkwyn-hub' ), $vars['{account_url}'] ),
		);
		$upgrade = $vars['{upgrade_url}'] ?? $vars['{account_url}'];
		$dash    = $vars['{dashboard_url}'] ?? $vars['{account_url}'];
		$layouts = array_merge(
			$layouts,
			array(
				'trial_confirm'       => array( __( 'Confirm your email', 'talkwyn-hub' ), __( 'Confirm and start my trial', 'talkwyn-hub' ), $vars['{confirm_url}'] ?? '' ),
				'trial_welcome'       => array( __( 'Your Pro trial has started', 'talkwyn-hub' ), __( 'Read the setup guide', 'talkwyn-hub' ), (string) apply_filters( 'twh_setup_guide_url', home_url( '/docs/getting-started/' ) ) ),
				'trial_reminder'      => array( __( 'Your trial ends soon', 'talkwyn-hub' ), __( 'Choose a plan', 'talkwyn-hub' ), $upgrade ),
				'trial_ended'         => array( __( 'Your trial has ended', 'talkwyn-hub' ), __( 'Upgrade in one click', 'talkwyn-hub' ), $upgrade ),
				'trial_converted'     => array( __( 'Welcome to Talkwyn Pro', 'talkwyn-hub' ), __( 'View your license', 'talkwyn-hub' ), $vars['{account_url}'] ),
				'partner_approved'    => array( __( 'You\'re a Talkwyn Partner', 'talkwyn-hub' ), __( 'Open your dashboard', 'talkwyn-hub' ), $dash ),
				'partner_referral'    => array( __( 'You have a new referral', 'talkwyn-hub' ), __( 'Open your dashboard', 'talkwyn-hub' ), $dash ),
				'partner_commission'  => array( __( 'Commission approved', 'talkwyn-hub' ), __( 'Open your dashboard', 'talkwyn-hub' ), $dash ),
				'partner_payout'      => array( __( 'Payout sent', 'talkwyn-hub' ), __( 'See your payouts', 'talkwyn-hub' ), $dash ),
				'partner_application' => array( __( 'New partner application', 'talkwyn-hub' ), __( 'Review applications', 'talkwyn-hub' ), admin_url( 'admin.php?page=twh-partners' ) ),
			)
		);
		$l       = $layouts[ $type ] ?? array( '', '', '' );
		return array(
			'heading' => $l[0],
			'button'  => $l[1],
			'url'     => $l[2],
		);
	}

	/**
	 * Render and send a template.
	 *
	 * @param string                $to    Recipient.
	 * @param string                $type  license|reminder|expired|renewed.
	 * @param array<string, string> $vars  Placeholders.
	 * @param string[]              $keys  Plain keys shown in the key panel.
	 */
	private static function send( string $to, string $type, array $vars, array $keys = array() ): bool {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$masked = $keys && ! Settings::email_keys();
		if ( $masked ) {
			// Full keys live only in the customer's account; the email shows the last 4 characters.
			$keys                  = array_map(
				static function ( $key ) {
					return KeyGenerator::mask( substr( (string) $key, -4 ) );
				},
				$keys
			);
			$vars['{license_key}'] = implode( "\n", $keys );
		}
		$subject = strtr( (string) Settings::get( 'email_' . $type . '_subject' ), $vars );
		$body    = (string) Settings::get( 'email_' . $type . '_body' );
		$text    = trim( strtr( $body, $vars ) );
		if ( ! empty( $vars['{login_details}'] ) && false === strpos( $body, '{login_details}' ) ) {
			// Older saved templates have no {login_details}: still tell the customer how to log in.
			$text .= "\n\n" . $vars['{login_details}'];
		}
		$layout  = self::layout( $type, $vars );
		if ( $masked ) {
			$text             .= "\n\n" . sprintf(
				/* translators: %s: account URL */
				__( 'For your security, the full license key is only shown in your account. Log in to copy it: %s', 'talkwyn-hub' ),
				$vars['{account_url}']
			);
			$layout['button'] = __( 'Open your dashboard', 'talkwyn-hub' );
			$layout['url']    = $vars['{account_url}'];
		}

		$html = self::render(
			array(
				'heading' => $layout['heading'],
				'body'    => make_clickable( wpautop( esc_html( $text ) ) ),
				'keys'    => $keys,
				'button'  => $layout['button'],
				'url'     => $layout['url'],
				'footer'  => $vars['{site_name}'],
			)
		);

		$plain = $layout['heading'] . "\n\n" . $text . "\n";
		if ( $keys ) {
			$plain .= "\n" . _n( 'Your license key:', 'Your license keys:', count( $keys ), 'talkwyn-hub' ) . "\n" . implode( "\n", $keys ) . "\n";
		}
		if ( '' !== $layout['url'] ) {
			$plain .= "\n" . $layout['button'] . ': ' . $layout['url'] . "\n";
		}

		/**
		 * Filter the email before sending.
		 *
		 * @param array{subject: string, html: string, text: string, to: string} $email Email.
		 * @param string $type Type.
		 * @param array<string, string> $vars Placeholders.
		 */
		$email = apply_filters(
			'twh_email',
			array(
				'subject' => $subject,
				'html'    => $html,
				'text'    => $plain,
				'to'      => $to,
			),
			$type,
			$vars
		);

		return self::deliver( (string) $email['to'], (string) $email['subject'], (string) $email['html'], (string) $email['text'] );
	}

	/**
	 * Branded HTML email (templates/emails/branded.php, overridable from the theme
	 * as talkwyn-hub/emails/branded.php).
	 *
	 * @param array<string, mixed> $args heading, body (HTML), keys, button, url, footer.
	 */
	public static function render( array $args ): string {
		$args     = array_merge(
			array(
				'heading' => '',
				'body'    => '',
				'keys'    => array(),
				'button'  => '',
				'url'     => '',
				'footer'  => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
				'logo'    => TWH_URL . 'assets/img/email-logo.png',
			),
			$args
		);
		$template = locate_template( 'talkwyn-hub/emails/branded.php' );
		if ( '' === $template ) {
			$template = TWH_DIR . 'templates/emails/branded.php';
		}
		ob_start();
		include $template;
		return (string) ob_get_clean();
	}

	/**
	 * Send HTML with a plain-text alternative.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $html    Full HTML document.
	 * @param string $text    Plain-text version.
	 */
	private static function deliver( string $to, string $subject, string $html, string $text = '' ): bool {
		$from_name = (string) Settings::get( 'email_from_name' );
		$name_cb   = static function ( $name ) use ( $from_name ) {
			return '' !== $from_name ? $from_name : $name;
		};
		$alt_cb    = static function ( $phpmailer ) use ( $text ) {
			if ( '' !== $text ) {
				$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer API.
			}
		};
		add_filter( 'wp_mail_from_name', $name_cb, 99 );
		add_action( 'phpmailer_init', $alt_cb );
		$sent = wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		remove_action( 'phpmailer_init', $alt_cb );
		remove_filter( 'wp_mail_from_name', $name_cb, 99 );
		return (bool) $sent;
	}
}
