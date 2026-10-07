<?php
/**
 * Commission lifecycle.
 *
 * @package TalkwynHub
 */

namespace TWH\Partners;

use TWH\Domain\ReferralPolicy;
use TWH\Email\Mailer;
use TWH\LicenseService;
use TWH\Repository\Events;
use TWH\Repository\Licenses;
use TWH\Repository\Partners;
use TWH\Repository\Referrals;
use TWH\Support\Settings;
use TWH\Woo\OrderHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Creates commissions when orders are paid, rejects them on refunds and
 * chargebacks, and approves them after the refund window.
 */
final class Commissions {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_order_status_processing', array( self::class, 'on_paid' ), 40 );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'on_paid' ), 40 );
		add_action( 'twh_trial_converted', array( self::class, 'on_trial_converted' ), 10, 2 );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( self::class, 'on_subscription_renewal' ), 40, 2 );
		add_action( 'woocommerce_order_refunded', array( self::class, 'on_refund' ), 20, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( self::class, 'on_cancelled' ), 20 );
		add_action( 'woocommerce_order_status_refunded', array( self::class, 'on_fully_refunded' ), 20 );
		add_action( 'twh_license_revoked', array( self::class, 'on_license_revoked' ), 10, 2 );
		add_action( 'twh_daily_tasks', array( self::class, 'approve_due' ) );
	}

	/**
	 * New sales, upgrades and manual renewals in a paid order.
	 *
	 * @param int $order_id Order id.
	 */
	public static function on_paid( $order_id ): void {
		if ( ! Settings::get( 'partners_enabled' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund || (float) $order->get_total() <= 0 ) {
			return;
		}
		$order_partner = (int) $order->get_meta( '_twh_partner_id' );

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product || (float) $item->get_total() <= 0 ) {
				continue;
			}
			// Trial conversions are credited by on_trial_converted().
			if ( $item->get_meta( OrderHandler::ITEM_TRIAL_CONVERT ) ) {
				continue;
			}
			$existing_id = (int) ( $item->get_meta( OrderHandler::ITEM_RENEW_LICENSE ) ? $item->get_meta( OrderHandler::ITEM_RENEW_LICENSE ) : $item->get_meta( OrderHandler::ITEM_UPGRADE ) );
			if ( $existing_id ) {
				$license = Licenses::find( $existing_id );
				if ( $license && (int) $license['partner_id'] ) {
					self::credit( (int) $license['partner_id'], $order, (float) $item->get_total(), 'renewal', $license, 'license' );
				}
				continue;
			}
			if ( ! $order_partner ) {
				continue;
			}
			$ids = (array) $item->get_meta( OrderHandler::ITEM_LICENSE_IDS );
			if ( ! $ids ) {
				continue;
			}
			$unit = (float) $item->get_total() / max( 1, count( $ids ) );
			foreach ( $ids as $license_id ) {
				$license = Licenses::find( (int) $license_id );
				if ( ! $license ) {
					continue;
				}
				// Remember who referred this license so renewals can pay too.
				if ( ! (int) $license['partner_id'] ) {
					Licenses::update( (int) $license['id'], array( 'partner_id' => $order_partner ) );
				}
				self::credit( $order_partner, $order, $unit, 'new', $license, (string) $order->get_meta( '_twh_ref_source' ) );
			}
		}
	}

	/**
	 * A trial became paid: credit the partner who referred the trial (or the order).
	 *
	 * @param int                  $license_id License id.
	 * @param array<string, mixed> $meta       order_id, amount, subscription_id.
	 */
	public static function on_trial_converted( $license_id, $meta ): void {
		if ( ! Settings::get( 'partners_enabled' ) ) {
			return;
		}
		$license = Licenses::find( (int) $license_id );
		if ( ! $license ) {
			return;
		}
		$order  = null;
		$amount = (float) ( $meta['amount'] ?? 0 );
		if ( ! empty( $meta['order_id'] ) ) {
			$order = wc_get_order( (int) $meta['order_id'] );
		} elseif ( ! empty( $meta['subscription_id'] ) && function_exists( 'wcs_get_subscription' ) ) {
			$sub   = wcs_get_subscription( (int) $meta['subscription_id'] );
			$order = $sub ? $sub->get_last_order( 'all' ) : null;
			$order = $order instanceof \WC_Order ? $order : null;
			if ( $order ) {
				$amount = (float) $order->get_total();
			}
		}
		if ( ! $order instanceof \WC_Order || $amount <= 0 ) {
			return;
		}
		$partner_id = (int) $license['partner_id'] ? (int) $license['partner_id'] : (int) $order->get_meta( '_twh_partner_id' );
		if ( ! $partner_id ) {
			return;
		}
		if ( ! (int) $license['partner_id'] ) {
			Licenses::update( (int) $license['id'], array( 'partner_id' => $partner_id ) );
		}
		self::credit( $partner_id, $order, $amount, 'new', $license, 'trial' );
	}

	/**
	 * Automatic subscription renewals.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param \WC_Order        $order        Renewal order.
	 */
	public static function on_subscription_renewal( $subscription, $order ): void {
		if ( ! Settings::get( 'partners_enabled' ) || ! $order instanceof \WC_Order || (float) $order->get_total() <= 0 ) {
			return;
		}
		foreach ( Licenses::for_subscription( (int) $subscription->get_id() ) as $license ) {
			if ( ! (int) $license['partner_id'] ) {
				continue;
			}
			// A card-mode trial's first charge was already credited as a new sale.
			foreach ( Referrals::for_order( $order->get_id() ) as $existing ) {
				if ( (int) $existing['license_id'] === (int) $license['id'] ) {
					continue 2;
				}
			}
			self::credit( (int) $license['partner_id'], $order, (float) $order->get_total(), 'renewal', $license, 'license' );
		}
	}

	/**
	 * Create one commission (if allowed).
	 *
	 * @param int                  $partner_id Partner id.
	 * @param \WC_Order            $order      Order.
	 * @param float                $amount     Base amount (net of discounts, before tax).
	 * @param string               $type       new|renewal.
	 * @param array<string, mixed> $license    License.
	 * @param string               $source     cookie|coupon|trial|license.
	 */
	public static function credit( int $partner_id, \WC_Order $order, float $amount, string $type, array $license, string $source ): int {
		$partner = Partners::find( $partner_id );
		if ( ! $partner || 'approved' !== $partner['status'] ) {
			return 0;
		}
		$rate = ReferralPolicy::rate( $type, (float) Settings::get( 'partner_rate_new' ), (float) Settings::get( 'partner_rate_renewal' ), $partner['commission_rate'] );
		if ( $rate <= 0 ) {
			return 0;
		}
		$user    = get_userdata( (int) $partner['user_id'] );
		$self    = ReferralPolicy::is_self_referral(
			array(
				'user_id'     => (int) $partner['user_id'],
				'email'       => $user ? (string) $user->user_email : '',
				'fingerprint' => (string) apply_filters( 'twh_partner_payment_fingerprint', '', (int) $partner['user_id'] ),
			),
			array(
				'user_id'     => (int) $order->get_customer_id(),
				'email'       => (string) $order->get_billing_email(),
				'fingerprint' => (string) apply_filters( 'twh_order_payment_fingerprint', '', $order ),
			)
		);
		$clicked = (int) $order->get_meta( '_twh_ref_clicked_at' );
		$created = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time();
		$id      = Referrals::create(
			array(
				'partner_id'   => $partner_id,
				'order_id'     => $order->get_id(),
				'customer_id'  => $order->get_customer_id(),
				'license_id'   => (int) $license['id'],
				'type'         => $type,
				'plan_slug'    => (string) $license['plan_slug'],
				'amount'       => round( $amount, 2 ),
				'commission'   => ReferralPolicy::commission( $amount, $rate ),
				'currency'     => $order->get_currency(),
				'status'       => $self ? 'rejected' : 'pending',
				'reason'       => $self ? 'self_referral' : '',
				'source'       => $source,
				'click_to_buy' => $clicked && 'cookie' === $source ? max( 1, $created - $clicked ) : 0,
				'ip_hash'      => (string) $order->get_meta( '_twh_ref_ip' ),
			)
		);
		if ( ! $id ) {
			return 0;
		}
		Events::log(
			'referral',
			(int) $license['id'],
			array(
				'partner_id' => $partner_id,
				'order_id'   => $order->get_id(),
				'type'       => $type,
				'status'     => $self ? 'rejected' : 'pending',
			),
			''
		);
		if ( ! $self ) {
			$row = Referrals::find( $id );
			if ( $row ) {
				self::email( $partner, 'partner_referral', $row );
			}
		}
		return $id;
	}

	/**
	 * Refund on an order: full refunds reject, partial refunds scale pending commissions.
	 *
	 * @param int $order_id  Order id.
	 * @param int $refund_id Refund id (unused).
	 */
	public static function on_refund( $order_id, $refund_id = 0 ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$total    = (float) $order->get_total();
		$refunded = (float) $order->get_total_refunded();
		foreach ( Referrals::for_order( $order->get_id() ) as $row ) {
			$new = ReferralPolicy::after_refund( $row['status'], $total, $refunded );
			if ( 'rejected' === $new && 'rejected' !== $row['status'] ) {
				Referrals::set_status( (int) $row['id'], 'rejected', 'refund' );
			} elseif ( 'pending' === $row['status'] && $total > 0 && $refunded > 0 ) {
				$share = max( 0.0, 1 - $refunded / $total );
				Referrals::set_amounts( (int) $row['id'], round( $row['amount'] * $share, 2 ), round( $row['commission'] * $share, 2 ) );
			}
		}
	}

	/**
	 * Order fully refunded through its status.
	 *
	 * @param int $order_id Order id.
	 */
	public static function on_fully_refunded( $order_id ): void {
		self::reject_order( (int) $order_id, 'refund' );
	}

	/**
	 * Order cancelled.
	 *
	 * @param int $order_id Order id.
	 */
	public static function on_cancelled( $order_id ): void {
		self::reject_order( (int) $order_id, 'cancelled' );
	}

	/**
	 * A license was revoked (refund or lost dispute): reject unpaid commissions on it.
	 *
	 * @param int    $license_id License id.
	 * @param string $reason     Reason.
	 */
	public static function on_license_revoked( $license_id, $reason ): void {
		if ( ! in_array( $reason, array( 'refund', 'chargeback', 'cancelled' ), true ) ) {
			return;
		}
		$license = Licenses::find( (int) $license_id );
		if ( $license && (int) $license['order_id'] ) {
			self::reject_order( (int) $license['order_id'], (string) $reason );
		}
	}

	/**
	 * Reject unpaid commissions of an order.
	 *
	 * @param int    $order_id Order id.
	 * @param string $reason   Reason.
	 */
	public static function reject_order( int $order_id, string $reason ): void {
		foreach ( Referrals::for_order( $order_id ) as $row ) {
			if ( in_array( $row['status'], array( 'pending', 'approved' ), true ) ) {
				Referrals::set_status( (int) $row['id'], 'rejected', $reason );
			}
		}
	}

	/**
	 * Daily: approve pending commissions older than the refund window.
	 */
	public static function approve_due(): int {
		$days     = (int) Settings::get( 'partner_approval_days' );
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$approved = 0;
		foreach ( Referrals::pending_before( $cutoff ) as $row ) {
			$order = $row['order_id'] ? wc_get_order( (int) $row['order_id'] ) : null;
			if ( $order instanceof \WC_Order ) {
				if ( in_array( $order->get_status(), array( 'refunded', 'cancelled', 'failed' ), true ) ) {
					Referrals::set_status( (int) $row['id'], 'rejected', $order->get_status() );
					continue;
				}
				if ( 'rejected' === ReferralPolicy::after_refund( 'pending', (float) $order->get_total(), (float) $order->get_total_refunded() ) ) {
					Referrals::set_status( (int) $row['id'], 'rejected', 'refund' );
					continue;
				}
			}
			Referrals::set_status( (int) $row['id'], 'approved' );
			$partner = Partners::find( (int) $row['partner_id'] );
			if ( $partner ) {
				self::email( $partner, 'partner_commission', $row );
			}
			++$approved;
		}
		return $approved;
	}

	/**
	 * Email a partner about a referral.
	 *
	 * @param array<string, mixed> $partner Partner.
	 * @param string               $type    Email type.
	 * @param array<string, mixed> $row     Referral.
	 */
	public static function email( array $partner, string $type, array $row ): void {
		$user = get_userdata( (int) $partner['user_id'] );
		if ( ! $user ) {
			return;
		}
		Mailer::send_type(
			(string) $user->user_email,
			$type,
			array_merge(
				Program::vars( $partner ),
				array(
					'{amount}'     => self::money( (float) $row['amount'], (string) $row['currency'] ),
					'{commission}' => self::money( (float) $row['commission'], (string) $row['currency'] ),
					'{plan}'       => LicenseService::plan_label( (string) $row['plan_slug'] ),
				)
			)
		);
	}

	/**
	 * Plain money string.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency.
	 */
	public static function money( float $amount, string $currency = '' ): string {
		if ( function_exists( 'wc_price' ) ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => '' !== $currency ? $currency : get_woocommerce_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
		}
		return number_format( $amount, 2 );
	}
}
