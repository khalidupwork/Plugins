<?php
/**
 * Optional WooCommerce Subscriptions integration.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

use TWH\LicenseService;
use TWH\Repository\Licenses;

defined( 'ABSPATH' ) || exit;

/**
 * Only active when WooCommerce Subscriptions is installed. Without it, the
 * manual renewal flow (Cart) is used.
 */
final class Subscriptions {

	/**
	 * Whether WooCommerce Subscriptions is active.
	 */
	public static function active(): bool {
		return class_exists( 'WC_Subscriptions' ) && function_exists( 'wcs_get_subscriptions_for_order' );
	}

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		if ( ! self::active() ) {
			return;
		}
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( self::class, 'on_renewal_paid' ), 20, 2 );
		add_action( 'woocommerce_subscriptions_switch_completed', array( self::class, 'on_switch_completed' ), 20 );
		add_action( 'woocommerce_order_status_processing', array( self::class, 'on_resubscribe_paid' ), 25 );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'on_resubscribe_paid' ), 25 );
	}

	/**
	 * Orders whose licensing is driven by subscription hooks instead of OrderHandler.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function is_handled_elsewhere( \WC_Order $order ): bool {
		if ( ! self::active() ) {
			return false;
		}
		return ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) )
			|| ( function_exists( 'wcs_order_contains_switch' ) && wcs_order_contains_switch( $order ) )
			|| ( function_exists( 'wcs_order_contains_resubscribe' ) && wcs_order_contains_resubscribe( $order ) );
	}

	/**
	 * Subscription id created by a parent order for a given line item.
	 *
	 * @param \WC_Order              $order Order.
	 * @param \WC_Order_Item_Product $item  Item.
	 */
	public static function subscription_for_item( \WC_Order $order, \WC_Order_Item_Product $item ): ?int {
		if ( ! self::active() ) {
			return null;
		}
		$wanted = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
		foreach ( wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'parent' ) ) as $subscription ) {
			foreach ( $subscription->get_items() as $sub_item ) {
				$id = $sub_item->get_variation_id() ? $sub_item->get_variation_id() : $sub_item->get_product_id();
				if ( (int) $id === (int) $wanted ) {
					return (int) $subscription->get_id();
				}
			}
		}
		return null;
	}

	/**
	 * Automatic renewal paid: extend every license linked to the subscription.
	 *
	 * @param \WC_Subscription $subscription Subscription.
	 * @param \WC_Order        $last_order   Renewal order.
	 */
	public static function on_renewal_paid( $subscription, $last_order ): void {
		$order_id = $last_order instanceof \WC_Order ? $last_order->get_id() : 0;
		foreach ( Licenses::for_subscription( (int) $subscription->get_id() ) as $license ) {
			// Idempotency per renewal order.
			$flag = '_twh_renewed_' . (int) $license['id'];
			if ( $last_order instanceof \WC_Order && $last_order->get_meta( $flag ) ) {
				continue;
			}
			LicenseService::renew(
				$license,
				array(
					'order_id'        => $order_id,
					'subscription_id' => (int) $subscription->get_id(),
					'amount'          => $last_order instanceof \WC_Order ? round( (float) $last_order->get_total(), 2 ) : 0,
					'currency'        => $last_order instanceof \WC_Order ? $last_order->get_currency() : '',
					'source'          => 'subscription',
				)
			);
			if ( $last_order instanceof \WC_Order ) {
				$last_order->update_meta_data( $flag, time() );
				$last_order->save();
			}
		}
	}

	/**
	 * Subscription switch (upgrade/downgrade) completed: same key, new plan.
	 *
	 * @param \WC_Order $order Switch order.
	 */
	public static function on_switch_completed( $order ): void {
		if ( ! $order instanceof \WC_Order || ! function_exists( 'wcs_get_subscriptions_for_switch_order' ) ) {
			return;
		}
		foreach ( wcs_get_subscriptions_for_switch_order( $order ) as $subscription ) {
			$target = null;
			foreach ( $subscription->get_items() as $item ) {
				$mapping = Mapping::for_product( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() );
				if ( $mapping ) {
					$target = array( $mapping, $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() );
					break;
				}
			}
			if ( ! $target ) {
				continue;
			}
			foreach ( Licenses::for_subscription( (int) $subscription->get_id() ) as $license ) {
				if ( (int) $license['product_id'] === (int) $target[0]['product_id'] ) {
					LicenseService::upgrade(
						$license,
						$target[0],
						(int) $target[1],
						array(
							'order_id' => $order->get_id(),
							'amount'   => round( (float) $order->get_total(), 2 ),
							'currency' => $order->get_currency(),
							'source'   => 'subscription_switch',
						)
					);
				}
			}
		}
	}

	/**
	 * Resubscribe paid: move licenses to the new subscription and extend them.
	 *
	 * @param int $order_id Order id.
	 */
	public static function on_resubscribe_paid( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || ! function_exists( 'wcs_order_contains_resubscribe' ) || ! wcs_order_contains_resubscribe( $order ) ) {
			return;
		}
		if ( $order->get_meta( '_twh_resubscribe_done' ) ) {
			return;
		}
		$order->update_meta_data( '_twh_resubscribe_done', time() );
		$order->save();

		foreach ( wcs_get_subscriptions_for_resubscribe_order( $order ) as $new_subscription ) {
			$old_id = (int) $new_subscription->get_meta( '_subscription_resubscribe' );
			if ( $old_id <= 0 ) {
				continue;
			}
			foreach ( Licenses::for_subscription( $old_id ) as $license ) {
				Licenses::update( (int) $license['id'], array( 'subscription_id' => (int) $new_subscription->get_id() ) );
				LicenseService::renew(
					$license,
					array(
						'order_id'        => $order->get_id(),
						'subscription_id' => (int) $new_subscription->get_id(),
						'amount'          => round( (float) $order->get_total(), 2 ),
						'currency'        => $order->get_currency(),
						'source'          => 'resubscribe',
					)
				);
			}
		}
	}

	/**
	 * Whether a license auto-renews through an active subscription.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function auto_renews( array $license ): bool {
		if ( ! self::active() || empty( $license['subscription_id'] ) || ! function_exists( 'wcs_get_subscription' ) ) {
			return false;
		}
		$subscription = wcs_get_subscription( (int) $license['subscription_id'] );
		return $subscription && $subscription->has_status( 'active' );
	}

	/**
	 * URL to manage the subscription of a license.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function manage_url( array $license ): ?string {
		if ( ! self::active() || empty( $license['subscription_id'] ) || ! function_exists( 'wcs_get_subscription' ) ) {
			return null;
		}
		$subscription = wcs_get_subscription( (int) $license['subscription_id'] );
		return $subscription ? $subscription->get_view_order_url() : null;
	}
}
