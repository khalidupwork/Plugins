<?php
/**
 * Referral link tracking.
 *
 * @package TalkwynHub
 */

namespace TWH\Partners;

use TWH\Domain\ReferralPolicy;
use TWH\Repository\Partners;
use TWH\Repository\Referrals;
use TWH\Support\Request;
use TWH\Support\Secrets;
use TWH\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Captures ?ref=CODE and /r/CODE, keeps the partner in a signed first-party cookie
 * (and the WooCommerce session), then stamps the order at checkout.
 */
final class Tracking {

	public const COOKIE    = 'twh_ref';
	public const QUERY_VAR = 'twh_ref_code';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'rewrite' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'template_redirect', array( self::class, 'capture' ), 1 );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'stamp_order' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( self::class, 'stamp_order' ), 20 );
	}

	/**
	 * Pretty link rule: /r/CODE.
	 */
	public static function rewrite(): void {
		if ( Settings::get( 'partner_pretty_links' ) ) {
			add_rewrite_rule( '^r/([a-z0-9-]{3,40})/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
		}
	}

	/**
	 * Register the query var.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Read a referral code from the request and remember it.
	 */
	public static function capture(): void {
		if ( ! Settings::get( 'partners_enabled' ) || is_admin() ) {
			return;
		}
		$pretty = (string) get_query_var( self::QUERY_VAR );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public tracking parameter.
		$code = '' !== $pretty ? $pretty : ( isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '' );
		if ( '' === $code ) {
			return;
		}
		$partner = Partners::find_by_code( ReferralPolicy::sanitize_code( $code ) );
		if ( $partner && 'approved' === $partner['status'] ) {
			$landing = home_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );
			$ref     = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
			Referrals::add_visit( (int) $partner['id'], $landing, $ref, Secrets::hash_ip( Request::ip() ) );
			self::remember( (int) $partner['id'] );
		}
		if ( '' !== $pretty ) {
			// /r/CODE?to=/pricing/ lands on any page of this site; default is the homepage.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public redirect target, validated below.
			$to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '/';
			wp_safe_redirect( wp_validate_redirect( home_url( '/' . ltrim( $to, '/' ) ), home_url( '/' ) ), 302 );
			exit;
		}
	}

	/**
	 * Store the partner for the cookie window. Last click wins.
	 *
	 * @param int $partner_id Partner id.
	 */
	public static function remember( int $partner_id ): void {
		$now   = time();
		$value = $partner_id . '.' . $now . '.' . self::sign( $partner_id, $now );
		if ( function_exists( 'WC' ) && WC()->session ) {
			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
			WC()->session->set( self::COOKIE, $value );
		}
		if ( ! self::consent_allows() || headers_sent() ) {
			return;
		}
		$days = max( 1, (int) Settings::get( 'partner_cookie_days' ) );
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $now + $days * DAY_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/**
	 * Whether a marketing cookie may be set (WP Consent API, when a consent plugin is active).
	 */
	public static function consent_allows(): bool {
		if ( ! Settings::get( 'partner_respect_consent' ) || ! function_exists( 'wp_has_consent' ) ) {
			return true;
		}
		$allowed = wp_has_consent( 'marketing' );
		return (bool) apply_filters( 'twh_referral_cookie_consent', $allowed );
	}

	/**
	 * Current tracked click.
	 *
	 * @return array{partner_id: int, at: int}|null
	 */
	public static function current_click(): ?array {
		$raw = '';
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		} elseif ( function_exists( 'WC' ) && WC()->session ) {
			$raw = (string) WC()->session->get( self::COOKIE );
		}
		$parts = explode( '.', $raw );
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		$partner_id = (int) $parts[0];
		$at         = (int) $parts[1];
		if ( ! hash_equals( self::sign( $partner_id, $at ), (string) $parts[2] ) ) {
			return null;
		}
		if ( ! ReferralPolicy::click_valid( $at, time(), max( 1, (int) Settings::get( 'partner_cookie_days' ) ) ) ) {
			return null;
		}
		return array(
			'partner_id' => $partner_id,
			'at'         => $at,
		);
	}

	/**
	 * Partner id of the current visitor (0 if none or not approved).
	 */
	public static function current_partner_id(): int {
		if ( ! Settings::get( 'partners_enabled' ) ) {
			return 0;
		}
		$click = self::current_click();
		if ( ! $click ) {
			return 0;
		}
		$partner = Partners::find( $click['partner_id'] );
		return $partner && 'approved' === $partner['status'] ? (int) $partner['id'] : 0;
	}

	/**
	 * Stamp the order with the credited partner (click or coupon, last one wins).
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function stamp_order( $order ): void {
		if ( ! $order instanceof \WC_Order || ! Settings::get( 'partners_enabled' ) ) {
			return;
		}
		$coupon = null;
		foreach ( $order->get_coupon_codes() as $code ) {
			$partner = Partners::find_by_coupon( (string) $code );
			if ( $partner && 'approved' === $partner['status'] ) {
				$coupon = array(
					'partner_id' => (int) $partner['id'],
					'at'         => time(),
				);
				break;
			}
		}
		$click = self::current_click();
		if ( $click ) {
			$partner = Partners::find( $click['partner_id'] );
			if ( ! $partner || 'approved' !== $partner['status'] ) {
				$click = null;
			}
		}
		$winner = ReferralPolicy::attribute( $click, $coupon );
		if ( ! $winner ) {
			return;
		}
		$order->update_meta_data( '_twh_partner_id', $winner['partner_id'] );
		$order->update_meta_data( '_twh_ref_source', $winner['source'] );
		if ( 'cookie' === $winner['source'] && $click ) {
			$order->update_meta_data( '_twh_ref_clicked_at', $click['at'] );
		}
		$order->update_meta_data( '_twh_ref_ip', Secrets::hash_ip( Request::ip() ) );
	}

	/**
	 * HMAC of a cookie value.
	 *
	 * @param int $partner_id Partner id.
	 * @param int $at         Click time.
	 */
	private static function sign( int $partner_id, int $at ): string {
		return substr( Secrets::crypto()->hmac( 'ref|' . $partner_id . '|' . $at, 'ref' ), 0, 16 );
	}
}
