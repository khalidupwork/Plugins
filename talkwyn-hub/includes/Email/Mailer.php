<?php
/**
 * Branded transactional emails.
 *
 * @package TalkwynHub
 */

namespace TWH\Email;

use TWH\Domain\ExpiryCalculator;
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
		self::deliver( Settings::admin_email(), '[Talkwyn Hub] ' . $subject, $subject, $body );
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
		);
	}

	/**
	 * Documented placeholders.
	 *
	 * @return string[]
	 */
	public static function placeholders(): array {
		return array( '{customer_name}', '{license_key}', '{key_last4}', '{product_name}', '{plan}', '{expires_at}', '{days_left}', '{activation_limit}', '{renew_url}', '{account_url}', '{site_name}', '{order_number}' );
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
	 * Render and send a template.
	 *
	 * @param string                $to    Recipient.
	 * @param string                $type  license|reminder|expired|renewed.
	 * @param array<string, string> $vars  Placeholders.
	 * @param string[]              $keys  Plain keys to highlight.
	 */
	private static function send( string $to, string $type, array $vars, array $keys = array() ): bool {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$subject = strtr( (string) Settings::get( 'email_' . $type . '_subject' ), $vars );
		$text    = strtr( (string) Settings::get( 'email_' . $type . '_body' ), $vars );

		$html = wpautop( esc_html( $text ) );
		foreach ( $keys as $key ) {
			$html = str_replace(
				esc_html( $key ),
				'<code style="display:inline-block;font-family:Menlo,Consolas,monospace;font-size:16px;letter-spacing:1px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;padding:8px 12px;user-select:all;">' . esc_html( $key ) . '</code>',
				$html
			);
		}
		$html = make_clickable( $html );

		/**
		 * Filter the email before sending.
		 *
		 * @param array{subject: string, html: string, to: string} $email Email.
		 * @param string $type Type.
		 * @param array<string, string> $vars Placeholders.
		 */
		$email = apply_filters(
			'twh_email',
			array(
				'subject' => $subject,
				'html'    => $html,
				'to'      => $to,
			),
			$type,
			$vars
		);

		return self::deliver( (string) $email['to'], (string) $email['subject'], (string) $email['subject'], (string) $email['html'] );
	}

	/**
	 * Wrap in the WooCommerce template and send.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $heading Heading.
	 * @param string $html    Body HTML.
	 */
	private static function deliver( string $to, string $subject, string $heading, string $html ): bool {
		$from_name = (string) Settings::get( 'email_from_name' );
		$filter    = static function () use ( $from_name ) {
			return $from_name;
		};
		if ( '' !== $from_name ) {
			add_filter( 'wp_mail_from_name', $filter, 99 );
			add_filter( 'woocommerce_email_from_name', $filter, 99 );
		}

		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$mailer = WC()->mailer();
			$body   = $mailer->wrap_message( $heading, $html );
			$sent   = (bool) $mailer->send( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		} else {
			$sent = wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		}

		if ( '' !== $from_name ) {
			remove_filter( 'wp_mail_from_name', $filter, 99 );
			remove_filter( 'woocommerce_email_from_name', $filter, 99 );
		}
		return $sent;
	}
}
