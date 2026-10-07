<?php
/**
 * Talkwyn Partners program: applications, approval, terms, payouts.
 *
 * @package TalkwynHub
 */

namespace TWH\Partners;

use TWH\Domain\ReferralPolicy;
use TWH\Email\Mailer;
use TWH\Repository\Events;
use TWH\Repository\Partners;
use TWH\Repository\Referrals;
use TWH\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Program-level operations shared by My Account, the admin and the REST API.
 */
final class Program {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		Tracking::init();
		Commissions::init();
		PartnerAccount::init();
	}

	/**
	 * GET talkwyn-hub/v1/partners/terms (public): what the website shows on /partners/.
	 */
	public static function routes(): void {
		register_rest_route(
			'talkwyn-hub/v1',
			'/partners/terms',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$response = rest_ensure_response( self::terms() );
					$response->header( 'Cache-Control', 'public, max-age=600' );
					return $response;
				},
			)
		);
	}

	/**
	 * Public program terms.
	 *
	 * @return array<string, mixed>
	 */
	public static function terms(): array {
		return array(
			'enabled'          => (bool) Settings::get( 'partners_enabled' ),
			'commission_rate'  => (float) Settings::get( 'partner_rate_new' ),
			'renewal_rate'     => (float) Settings::get( 'partner_rate_renewal' ),
			'cookie_days'      => (int) Settings::get( 'partner_cookie_days' ),
			'approval_days'    => (int) Settings::get( 'partner_approval_days' ),
			'payout_threshold' => (float) Settings::get( 'partner_payout_threshold' ),
			'currency'         => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
			'payout_methods'   => array_values( Settings::payout_methods() ),
			'attribution'      => 'last_click',
			'trial_days'       => (int) Settings::get( 'trial_days' ),
		);
	}

	/**
	 * Apply to the program.
	 *
	 * @param int                   $user_id User id.
	 * @param array<string, string> $data    website, promotion, payout_method, payout_details.
	 * @return int|\WP_Error Partner id.
	 */
	public static function apply( int $user_id, array $data ) {
		if ( ! Settings::get( 'partners_enabled' ) ) {
			return new \WP_Error( 'disabled', __( 'The partner program is closed right now.', 'talkwyn-hub' ) );
		}
		if ( Partners::find_by_user( $user_id ) ) {
			return new \WP_Error( 'exists', __( 'You have already applied.', 'talkwyn-hub' ) );
		}
		$id = Partners::create(
			array(
				'user_id'        => $user_id,
				'website'        => $data['website'],
				'promotion'      => $data['promotion'],
				'payout_method'  => $data['payout_method'],
				'payout_details' => $data['payout_details'],
			)
		);
		if ( ! $id ) {
			return new \WP_Error( 'failed', __( 'Your application could not be saved. Please try again.', 'talkwyn-hub' ) );
		}
		$partner = Partners::find( $id );
		$user    = get_userdata( $user_id );
		if ( $partner && $user ) {
			Mailer::send_type(
				Settings::admin_email(),
				'partner_application',
				array_merge(
					self::vars( $partner ),
					array(
						'{partner_email}' => (string) $user->user_email,
						'{website}'       => (string) $partner['website'],
						'{promotion}'     => (string) $partner['promotion'],
					)
				)
			);
		}
		Events::log( 'partner', null, array( 'applied' => $id ) );
		do_action( 'twh_partner_applied', $id );
		return $id;
	}

	/**
	 * Approve or suspend.
	 *
	 * @param int    $partner_id Partner id.
	 * @param string $status     approved|suspended|pending.
	 */
	public static function set_status( int $partner_id, string $status ): bool {
		$partner = Partners::find( $partner_id );
		if ( ! $partner || $partner['status'] === $status || ! Partners::update( $partner_id, array( 'status' => $status ) ) ) {
			return false;
		}
		Events::log(
			'partner',
			null,
			array(
				'partner' => $partner_id,
				'status'  => $status,
				'by'      => get_current_user_id(),
			)
		);
		if ( 'approved' === $status ) {
			$user = get_userdata( (int) $partner['user_id'] );
			if ( $user ) {
				Mailer::send_type( (string) $user->user_email, 'partner_approved', self::vars( array_merge( $partner, array( 'status' => 'approved' ) ) ) );
			}
		}
		return true;
	}

	/**
	 * Change the referral code (partners may do this once).
	 *
	 * @param array<string, mixed> $partner Partner.
	 * @param string               $code    New code.
	 * @param bool                 $admin   Admin override (no once-only limit).
	 * @return true|\WP_Error
	 */
	public static function change_code( array $partner, string $code, bool $admin = false ) {
		$code = ReferralPolicy::sanitize_code( $code );
		if ( '' === $code ) {
			return new \WP_Error( 'invalid', __( 'Use 3 to 32 letters, numbers or dashes.', 'talkwyn-hub' ) );
		}
		if ( ! $admin && (int) $partner['code_changed'] ) {
			return new \WP_Error( 'once', __( 'You can change your link once. Contact us if you need another change.', 'talkwyn-hub' ) );
		}
		$taken = Partners::find_by_code( $code );
		if ( $taken && (int) $taken['id'] !== (int) $partner['id'] ) {
			return new \WP_Error( 'taken', __( 'That link is taken. Try another one.', 'talkwyn-hub' ) );
		}
		Partners::update(
			(int) $partner['id'],
			array(
				'referral_code' => $code,
				'code_changed'  => $admin ? (int) $partner['code_changed'] : 1,
			)
		);
		return true;
	}

	/**
	 * Mark a partner's approved balance as paid.
	 *
	 * @param int    $partner_id Partner id.
	 * @param string $reference  Payment reference.
	 * @return array{id: int, amount: float}|\WP_Error
	 */
	public static function pay( int $partner_id, string $reference ) {
		$partner = Partners::find( $partner_id );
		if ( ! $partner ) {
			return new \WP_Error( 'missing', __( 'Partner not found.', 'talkwyn-hub' ) );
		}
		$totals = Referrals::totals( $partner_id );
		if ( ! ReferralPolicy::can_pay( $totals['approved'], (float) Settings::get( 'partner_payout_threshold' ) ) ) {
			return new \WP_Error( 'threshold', __( 'This partner is below the payout threshold.', 'talkwyn-hub' ) );
		}
		$paid = Referrals::pay( $partner_id, (string) $partner['payout_method'], $reference );
		if ( ! $paid ) {
			return new \WP_Error( 'nothing', __( 'Nothing to pay.', 'talkwyn-hub' ) );
		}
		$user = get_userdata( (int) $partner['user_id'] );
		if ( $user ) {
			Mailer::send_type(
				(string) $user->user_email,
				'partner_payout',
				array_merge(
					self::vars( $partner ),
					array(
						'{amount}'    => Commissions::money( $paid['amount'] ),
						'{reference}' => '' !== $reference ? $reference : __( 'not given', 'talkwyn-hub' ),
					)
				)
			);
		}
		Events::log(
			'partner',
			null,
			array(
				'partner' => $partner_id,
				'payout'  => $paid['id'],
				'amount'  => $paid['amount'],
			)
		);
		return $paid;
	}

	/**
	 * Referral link.
	 *
	 * @param array<string, mixed> $partner Partner.
	 * @param string               $path    Optional site path, like /pricing/.
	 */
	public static function link( array $partner, string $path = '/' ): string {
		$path = '/' . ltrim( $path, '/' );
		if ( Settings::get( 'partner_pretty_links' ) && '/' === $path ) {
			return home_url( '/r/' . $partner['referral_code'] );
		}
		return add_query_arg( 'ref', (string) $partner['referral_code'], home_url( $path ) );
	}

	/**
	 * Placeholder values for partner emails.
	 *
	 * @param array<string, mixed> $partner Partner.
	 * @return array<string, string>
	 */
	public static function vars( array $partner ): array {
		$user    = get_userdata( (int) $partner['user_id'] );
		$name    = $user ? ( $user->first_name ? $user->first_name : $user->display_name ) : '';
		$rate    = null !== $partner['commission_rate'] ? (float) $partner['commission_rate'] : (float) Settings::get( 'partner_rate_new' );
		$methods = Settings::payout_methods();
		return array(
			'{partner_name}'     => '' !== $name ? $name : __( 'there', 'talkwyn-hub' ),
			'{referral_link}'    => self::link( $partner ),
			'{commission_rate}'  => rtrim( rtrim( number_format( $rate, 2, '.', '' ), '0' ), '.' ) . '%',
			'{cookie_days}'      => (string) (int) Settings::get( 'partner_cookie_days' ),
			'{approval_days}'    => (string) (int) Settings::get( 'partner_approval_days' ),
			'{payout_threshold}' => Commissions::money( (float) Settings::get( 'partner_payout_threshold' ) ),
			'{payout_method}'    => $methods[ (string) $partner['payout_method'] ] ?? (string) $partner['payout_method'],
			'{dashboard_url}'    => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( PartnerAccount::ENDPOINT ) : home_url( '/' ),
		);
	}
}
