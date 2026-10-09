<?php
/**
 * WooCommerce order lifecycle → licenses.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

use TWH\Email\Mailer;
use TWH\LicenseService;
use TWH\Repository\Events;
use TWH\Repository\Licenses;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

/**
 * Reacts to order status changes only, so it works with any payment gateway.
 */
final class OrderHandler {

	public const ITEM_LICENSE_IDS   = '_twh_license_ids';
	public const ITEM_RENEW_LICENSE = '_twh_renewal_license_id';
	public const ITEM_UPGRADE       = '_twh_upgrade_license_id';
	public const ITEM_TRIAL_CONVERT = '_twh_trial_convert_license_id';
	public const ITEM_PROCESSED     = '_twh_processed';
	public const ITEM_PREVIOUS      = '_twh_previous_state';
	public const ORDER_DISPUTE      = '_twh_dispute_suspended';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_order_status_processing', array( self::class, 'on_paid' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'on_paid' ), 20 );
		add_action( 'woocommerce_order_fully_refunded', array( self::class, 'on_full_refund' ), 20, 2 );
		add_action( 'woocommerce_order_status_refunded', array( self::class, 'on_full_refund' ), 20 );
		add_action( 'woocommerce_order_partially_refunded', array( self::class, 'on_partial_refund' ), 20, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( self::class, 'on_cancelled' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( self::class, 'on_status_changed' ), 20, 3 );
	}

	/**
	 * Order paid: issue, renew or upgrade. Idempotent.
	 *
	 * @param int $order_id Order id.
	 */
	public static function on_paid( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund ) {
			return;
		}
		// Subscription renewals/switches are handled by the Subscriptions integration.
		if ( Subscriptions::is_handled_elsewhere( $order ) ) {
			return;
		}

		// Atomic lock so two concurrent status updates never both issue licenses.
		$lock = 'twh_lock_order_' . $order->get_id();
		if ( ! add_option( $lock, time(), '', 'no' ) ) {
			$since = (int) get_option( $lock );
			if ( $since > time() - 120 ) {
				return;
			}
			update_option( $lock, time(), false );
		}

		try {
			self::process_items( $order );
		} finally {
			delete_option( $lock );
		}
	}

	/**
	 * Process every line item.
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function process_items( \WC_Order $order ): void {
		$new_keys = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$renew_id   = (int) $item->get_meta( self::ITEM_RENEW_LICENSE );
			$upgrade_id = (int) $item->get_meta( self::ITEM_UPGRADE );
			$convert_id = (int) $item->get_meta( self::ITEM_TRIAL_CONVERT );

			if ( $convert_id > 0 ) {
				self::apply_conversion( $order, $item, $convert_id );
				continue;
			}
			if ( $renew_id > 0 ) {
				self::apply_renewal( $order, $item, $renew_id );
				continue;
			}
			if ( $upgrade_id > 0 ) {
				self::apply_upgrade( $order, $item, $upgrade_id );
				continue;
			}

			$product = $item->get_product();
			$mapping = $product ? Mapping::for_product( $product ) : null;
			if ( ! $mapping ) {
				continue;
			}

			$existing = Licenses::for_order_item( $item->get_id() );

			// A customer on a trial who buys a plan keeps the same key: the trial becomes the paid license.
			if ( ! $existing && 1 === (int) $item->get_quantity() && ! Subscriptions::subscription_for_item( $order, $item ) ) {
				$trial_id = self::open_trial_for( (int) $order->get_customer_id(), (int) $mapping['product_id'] );
				if ( $trial_id ) {
					$item->update_meta_data( self::ITEM_TRIAL_CONVERT, $trial_id );
					$item->save();
					self::apply_conversion( $order, $item, $trial_id );
					continue;
				}
			}

			$needed   = max( 0, (int) $item->get_quantity() - count( $existing ) );
			$ids      = array_map(
				static function ( $l ) {
					return (int) $l['id'];
				},
				$existing
			);
			$unit     = $item->get_quantity() > 0 ? (float) $item->get_total() / (int) $item->get_quantity() : 0.0;

			for ( $i = 0; $i < $needed; $i++ ) {
				$created    = LicenseService::issue(
					array(
						'product_id'       => $mapping['product_id'],
						'plan_slug'        => $mapping['plan_slug'],
						'customer_id'      => $order->get_customer_id(),
						'customer_email'   => $order->get_billing_email(),
						'order_id'         => $order->get_id(),
						'order_item_id'    => $item->get_id(),
						'wc_product_id'    => $product->get_id(),
						'subscription_id'  => Subscriptions::subscription_for_item( $order, $item ),
						'activation_limit' => $mapping['activation_limit'],
						'duration_days'    => $mapping['duration_days'],
						'features'         => $mapping['features'],
					),
					false,
					array(
						'order_id' => $order->get_id(),
						'amount'   => round( $unit, 2 ),
						'currency' => $order->get_currency(),
					)
				);
				$ids[]      = $created['id'];
				$new_keys[] = $created;
			}

			if ( $needed > 0 ) {
				$item->update_meta_data( self::ITEM_LICENSE_IDS, $ids );
				$item->save();
			}
		}

		if ( $new_keys ) {
			$last4 = array();
			foreach ( $new_keys as $created ) {
				$last4[] = '…' . substr( $created['key'], -4 );
			}
			$order->add_order_note(
				/* translators: %s: masked license keys */
				sprintf( __( 'Talkwyn Hub issued license keys: %s', 'talkwyn-hub' ), implode( ', ', $last4 ) )
			);
			Mailer::send_order_licenses( $order, $new_keys );
		}
	}

	/**
	 * Apply a manual renewal line item.
	 *
	 * @param \WC_Order              $order      Order.
	 * @param \WC_Order_Item_Product $item       Item.
	 * @param int                    $license_id License id.
	 */
	private static function apply_renewal( \WC_Order $order, \WC_Order_Item_Product $item, int $license_id ): void {
		if ( 'yes' === $item->get_meta( self::ITEM_PROCESSED ) ) {
			return;
		}
		$license = Licenses::find( $license_id );
		if ( ! $license ) {
			return;
		}
		// Mark first, so a crash mid-way can never double-extend.
		$item->update_meta_data( self::ITEM_PROCESSED, 'yes' );
		$item->update_meta_data(
			self::ITEM_PREVIOUS,
			array(
				'expires_at' => $license['expires_at'],
				'status'     => $license['status'],
			)
		);
		$item->save();

		$new = LicenseService::renew(
			$license,
			array(
				'order_id' => $order->get_id(),
				'amount'   => round( (float) $item->get_total(), 2 ),
				'currency' => $order->get_currency(),
				'source'   => 'manual',
			)
		);
		if ( null !== $new ) {
			$order->add_order_note(
				/* translators: 1: license id, 2: date */
				sprintf( __( 'Talkwyn Hub renewed license #%1$d until %2$s.', 'talkwyn-hub' ), $license_id, gmdate( 'Y-m-d', $new ) )
			);
		}
	}

	/**
	 * Apply an upgrade line item.
	 *
	 * @param \WC_Order              $order      Order.
	 * @param \WC_Order_Item_Product $item       Item.
	 * @param int                    $license_id License id.
	 */
	private static function apply_upgrade( \WC_Order $order, \WC_Order_Item_Product $item, int $license_id ): void {
		if ( 'yes' === $item->get_meta( self::ITEM_PROCESSED ) ) {
			return;
		}
		$license = Licenses::find( $license_id );
		$product = $item->get_product();
		$mapping = $product ? Mapping::for_product( $product ) : null;
		if ( ! $license || ! $mapping || (int) $mapping['product_id'] !== (int) $license['product_id'] ) {
			$order->add_order_note( __( 'Talkwyn Hub could not apply the upgrade: license or product mapping not found. Please review.', 'talkwyn-hub' ) );
			return;
		}
		$item->update_meta_data( self::ITEM_PROCESSED, 'yes' );
		$item->update_meta_data(
			self::ITEM_PREVIOUS,
			array(
				'plan_slug'        => $license['plan_slug'],
				'activation_limit' => (int) $license['activation_limit'],
				'features'         => $license['features'],
				'wc_product_id'    => (int) $license['wc_product_id'],
			)
		);
		$item->save();

		LicenseService::upgrade(
			$license,
			$mapping,
			$product->get_id(),
			array(
				'order_id' => $order->get_id(),
				'amount'   => round( (float) $item->get_total(), 2 ),
				'currency' => $order->get_currency(),
			)
		);
		$order->add_order_note(
			/* translators: 1: license id, 2: plan */
			sprintf( __( 'Talkwyn Hub upgraded license #%1$d to plan "%2$s".', 'talkwyn-hub' ), $license_id, $mapping['plan_slug'] )
		);
	}

	/**
	 * The customer's trial license for a product that has not been paid for yet.
	 *
	 * @param int $customer_id WordPress user id.
	 * @param int $product_id  Software product id.
	 */
	private static function open_trial_for( int $customer_id, int $product_id ): int {
		if ( $customer_id <= 0 ) {
			return 0;
		}
		foreach ( Licenses::for_customer( $customer_id ) as $license ) {
			if ( ! empty( $license['is_trial'] ) && empty( $license['converted_at'] ) && (int) $license['product_id'] === $product_id && in_array( (string) $license['status'], array( 'active', 'expired' ), true ) ) {
				return (int) $license['id'];
			}
		}
		return 0;
	}

	/**
	 * Apply a trial conversion line item: same key, paid plan, term starts now.
	 *
	 * @param \WC_Order              $order      Order.
	 * @param \WC_Order_Item_Product $item       Item.
	 * @param int                    $license_id Trial license id.
	 */
	private static function apply_conversion( \WC_Order $order, \WC_Order_Item_Product $item, int $license_id ): void {
		if ( 'yes' === $item->get_meta( self::ITEM_PROCESSED ) ) {
			return;
		}
		$license = Licenses::find( $license_id );
		$product = $item->get_product();
		$mapping = $product ? Mapping::for_product( $product ) : null;
		if ( ! $license || empty( $license['is_trial'] ) || ! $mapping || (int) $mapping['product_id'] !== (int) $license['product_id'] ) {
			$order->add_order_note( __( 'Talkwyn Hub could not convert the trial: license or product mapping not found. Please review.', 'talkwyn-hub' ) );
			return;
		}
		$item->update_meta_data( self::ITEM_PROCESSED, 'yes' );
		$item->update_meta_data(
			self::ITEM_PREVIOUS,
			array(
				'plan_slug'        => $license['plan_slug'],
				'activation_limit' => (int) $license['activation_limit'],
				'features'         => $license['features'],
				'wc_product_id'    => (int) $license['wc_product_id'],
				'expires_at'       => $license['expires_at'],
				'trial_ends_at'    => $license['trial_ends_at'],
				'was_trial'        => 1,
			)
		);
		$item->save();
		// Link the paying customer to the license.
		$owner = array();
		if ( ! (int) $license['customer_id'] && $order->get_customer_id() ) {
			$owner['customer_id'] = $order->get_customer_id();
		}
		if ( $owner ) {
			Licenses::update( $license_id, $owner );
			$license = array_merge( $license, $owner );
		}
		\TWH\Trial\Trial::convert(
			$license,
			$mapping,
			$product->get_id(),
			array(
				'order_id' => $order->get_id(),
				'amount'   => round( (float) $item->get_total(), 2 ),
				'currency' => $order->get_currency(),
			)
		);
		$order->add_order_note(
			/* translators: 1: license id, 2: plan */
			sprintf( __( 'Talkwyn Hub converted trial license #%1$d to plan "%2$s".', 'talkwyn-hub' ), $license_id, $mapping['plan_slug'] )
		);
	}

	/**
	 * Full refund: revoke new licenses, roll back renewals and upgrades.
	 *
	 * @param int $order_id  Order id.
	 * @param int $refund_id Refund id (unused).
	 */
	public static function on_full_refund( $order_id, $refund_id = 0 ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		self::reverse_order( $order, 'refund' );
	}

	/**
	 * Partial refund: log only.
	 *
	 * @param int $order_id  Order id.
	 * @param int $refund_id Refund id.
	 */
	public static function on_partial_refund( $order_id, $refund_id ): void {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$amount = $refund instanceof \WC_Order_Refund ? (float) $refund->get_amount() : 0.0;
		foreach ( Licenses::for_order( $order->get_id() ) as $license ) {
			Events::log(
				'refund',
				(int) $license['id'],
				array(
					'partial'  => true,
					'order_id' => $order->get_id(),
					'amount'   => round( $amount, 2 ),
					'currency' => $order->get_currency(),
				),
				''
			);
			$amount = 0.0; // Count the money once.
		}
	}

	/**
	 * Cancelled order.
	 *
	 * @param int $order_id Order id.
	 */
	public static function on_cancelled( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order instanceof \WC_Order ) {
			self::reverse_order( $order, 'cancelled' );
		}
	}

	/**
	 * Detect chargebacks/disputes from status transitions (gateway-agnostic).
	 *
	 * Paid → on-hold: most gateways (incl. Stripe) put an order on hold when a
	 * dispute opens; licenses are suspended and restored if the order returns to
	 * processing/completed. Paid/on-hold → failed: dispute lost; licenses revoked.
	 *
	 * @param int    $order_id Order id.
	 * @param string $from     Old status.
	 * @param string $to       New status.
	 */
	public static function on_status_changed( $order_id, $from, $to ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$paid = array( 'processing', 'completed' );

		if ( in_array( $from, $paid, true ) && 'on-hold' === $to && apply_filters( 'twh_suspend_on_hold', true, $order ) ) {
			$suspended = array();
			foreach ( Licenses::for_order( $order->get_id() ) as $license ) {
				if ( 'active' === $license['status'] ) {
					Licenses::update( (int) $license['id'], array( 'status' => 'suspended' ) );
					Events::log(
						'chargeback',
						(int) $license['id'],
						array(
							'stage'    => 'dispute_opened',
							'order_id' => $order->get_id(),
						),
						''
					);
					$suspended[] = (int) $license['id'];
				}
			}
			if ( $suspended ) {
				$order->update_meta_data( self::ORDER_DISPUTE, $suspended );
				$order->save();
				$order->add_order_note( __( 'Talkwyn Hub suspended the licenses of this order (order put on hold after payment).', 'talkwyn-hub' ) );
			}
			return;
		}

		if ( in_array( $to, $paid, true ) ) {
			$suspended = (array) $order->get_meta( self::ORDER_DISPUTE );
			if ( $suspended ) {
				foreach ( $suspended as $license_id ) {
					$license = Licenses::find( (int) $license_id );
					if ( $license && 'suspended' === $license['status'] ) {
						Licenses::update( (int) $license_id, array( 'status' => 'active' ) );
						Events::log(
							'chargeback',
							(int) $license_id,
							array(
								'stage'    => 'resolved',
								'order_id' => $order->get_id(),
							),
							''
						);
					}
				}
				$order->delete_meta_data( self::ORDER_DISPUTE );
				$order->save();
			}
			return;
		}

		if ( 'failed' === $to && in_array( $from, array( 'processing', 'completed', 'on-hold' ), true ) && Licenses::for_order( $order->get_id() ) ) {
			self::reverse_order( $order, 'chargeback' );
		}
	}

	/**
	 * Undo what an order granted.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $reason refund|cancelled|chargeback.
	 */
	private static function reverse_order( \WC_Order $order, string $reason ): void {
		foreach ( Licenses::for_order( $order->get_id() ) as $license ) {
			if ( LicenseService::revoke( $license, $reason, array( 'order_id' => $order->get_id() ) ) && 'cancelled' !== $reason ) {
				$line = $order->get_item( (int) $license['order_item_id'] );
				$unit = $line instanceof \WC_Order_Item_Product && $line->get_quantity() > 0 ? (float) $line->get_total() / (int) $line->get_quantity() : 0.0;
				Events::log(
					'chargeback' === $reason ? 'chargeback' : 'refund',
					(int) $license['id'],
					array(
						'order_id' => $order->get_id(),
						'amount'   => round( $unit, 2 ),
						'currency' => $order->get_currency(),
					),
					''
				);
			}
		}

		// Roll back renewals and upgrades bought in this order.
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product || 'yes' !== $item->get_meta( self::ITEM_PROCESSED ) ) {
				continue;
			}
			$previous   = (array) $item->get_meta( self::ITEM_PREVIOUS );
			$license_id = (int) ( $item->get_meta( self::ITEM_RENEW_LICENSE ) ? $item->get_meta( self::ITEM_RENEW_LICENSE ) : ( $item->get_meta( self::ITEM_UPGRADE ) ? $item->get_meta( self::ITEM_UPGRADE ) : $item->get_meta( self::ITEM_TRIAL_CONVERT ) ) );
			$license    = $license_id ? Licenses::find( $license_id ) : null;
			if ( ! $license || ! $previous ) {
				continue;
			}
			if ( ! empty( $previous['was_trial'] ) ) {
				// Back to the trial it was; if the trial window has passed it is expired.
				$prev_exp = Time::to_ts( $previous['expires_at'] ?? null );
				Licenses::update(
					$license_id,
					array(
						'is_trial'         => 1,
						'converted_at'     => null,
						'plan_slug'        => (string) $previous['plan_slug'],
						'activation_limit' => (int) $previous['activation_limit'],
						'features'         => (string) $previous['features'],
						'wc_product_id'    => (int) $previous['wc_product_id'],
						'expires_at'       => $prev_exp,
						'status'           => null !== $prev_exp && $prev_exp <= time() ? 'expired' : 'active',
					)
				);
			} elseif ( $item->get_meta( self::ITEM_RENEW_LICENSE ) ) {
				$prev_exp = Time::to_ts( $previous['expires_at'] ?? null );
				$fields   = array( 'expires_at' => $prev_exp );
				if ( 'active' === $license['status'] && null !== $prev_exp && $prev_exp <= time() ) {
					$fields['status'] = 'expired';
				}
				Licenses::update( $license_id, $fields );
			} else {
				Licenses::update( $license_id, array_intersect_key( $previous, array_flip( array( 'plan_slug', 'activation_limit', 'features', 'wc_product_id' ) ) ) );
			}
			Events::log(
				'refund',
				$license_id,
				array(
					'order_id'    => $order->get_id(),
					'rolled_back' => ! empty( $previous['was_trial'] ) ? 'trial_conversion' : ( $item->get_meta( self::ITEM_RENEW_LICENSE ) ? 'renewal' : 'upgrade' ),
					'amount'      => round( (float) $item->get_total(), 2 ),
					'currency'    => $order->get_currency(),
					'reason'      => $reason,
				),
				''
			);
			$item->update_meta_data( self::ITEM_PROCESSED, 'reversed' );
			$item->save();
			Mailer::notify_admin(
				/* translators: %d: license id */
				sprintf( __( 'Renewal/upgrade reversed for license #%d', 'talkwyn-hub' ), $license_id ),
				/* translators: 1: order number, 2: reason */
				sprintf( __( 'Order %1$s was %2$s. The renewal or upgrade it contained has been rolled back.', 'talkwyn-hub' ), $order->get_order_number(), $reason ),
				$license
			);
		}
	}
}
