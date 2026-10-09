<?php
/**
 * Manual renewal and upgrade cart flows.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

use TWH\Domain\ExpiryCalculator;
use TWH\Domain\KeyGenerator;
use TWH\Repository\Licenses;
use TWH\Repository\Products;
use TWH\Support\Secrets;
use TWH\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renewal: re-adds the license's product at its current price minus the renewal
 * discount. Upgrade: adds the target plan at the prorated price difference.
 * Both carry the license id in cart item data and are applied when the order is paid.
 */
final class Cart {

	public const RENEW   = 'twh_renew_license_id';
	public const UPGRADE = 'twh_upgrade_license_id';
	public const CONVERT = 'twh_convert_license_id';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_loaded', array( self::class, 'handle_renew_link' ), 30 );
		add_action( 'wp_loaded', array( self::class, 'handle_upgrade_request' ), 30 );
		add_action( 'woocommerce_before_calculate_totals', array( self::class, 'set_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( self::class, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'to_order_item' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_quantity', array( self::class, 'lock_quantity' ), 10, 3 );
		add_filter( 'woocommerce_update_cart_validation', array( self::class, 'validate_quantity' ), 10, 4 );
		add_action( 'woocommerce_check_cart_items', array( self::class, 'validate_cart' ) );
		add_filter( 'woocommerce_get_cart_item_from_session', array( self::class, 'from_session' ), 10, 2 );
		add_filter( 'woocommerce_checkout_registration_required', array( self::class, 'require_account' ) );
		add_filter( 'woocommerce_checkout_registration_enabled', array( self::class, 'allow_account' ) );
	}

	/**
	 * Whether the cart holds a product that issues license keys.
	 */
	public static function cart_has_license(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $line ) {
			$product = $line['data'] ?? null;
			if ( $product instanceof \WC_Product && Mapping::for_product( $product ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keys are not emailed, so a buyer needs an account to see them: no guest checkout for licenses.
	 *
	 * @param bool $required Current value.
	 */
	public static function require_account( $required ): bool {
		return ( ! Settings::email_keys() && ! is_user_logged_in() && self::cart_has_license() ) ? true : (bool) $required;
	}

	/**
	 * Make sure the "create account" fields show when an account is required.
	 *
	 * @param bool $enabled Current value.
	 */
	public static function allow_account( $enabled ): bool {
		return self::require_account( false ) ? true : (bool) $enabled;
	}

	/**
	 * One-click renewal URL (works logged out; used in reminder emails).
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function renew_url( array $license ): string {
		$manage = Subscriptions::manage_url( $license );
		if ( $manage ) {
			return $manage;
		}
		return add_query_arg(
			array(
				'twh_renew' => (int) $license['id'],
				't'         => Secrets::renew_token( (int) $license['id'] ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Whether a license can be renewed manually.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function can_renew( array $license ): bool {
		// Trials convert through Trial::upgrade_url(), never the discounted renewal.
		return empty( $license['is_trial'] )
			&& (int) $license['duration_days'] > 0
			&& in_array( Licenses::effective_status( $license ), array( 'active', 'expired' ), true );
	}

	/**
	 * The WooCommerce product used to renew a license.
	 *
	 * @param array<string, mixed> $license License.
	 */
	public static function renewal_product( array $license ): ?\WC_Product {
		$product = (int) $license['wc_product_id'] ? wc_get_product( (int) $license['wc_product_id'] ) : null;
		if ( $product instanceof \WC_Product && $product->is_purchasable() && Mapping::for_product( $product ) ) {
			return $product;
		}
		// Fallback: any purchasable product mapped to the same software product and plan.
		$software = Products::find( (int) $license['product_id'] );
		if ( $software ) {
			foreach ( Mapping::products_for_software( (string) $software['slug'] ) as $candidate ) {
				if ( $candidate['mapping']['plan_slug'] === $license['plan_slug'] ) {
					return $candidate['wc_product'];
				}
			}
		}
		return null;
	}

	/**
	 * Renewal price.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function renewal_price( \WC_Product $product ): float {
		$discount = min( 100, max( 0, (float) Settings::get( 'renewal_discount' ) ) );
		$price    = (float) $product->get_price( 'edit' );
		return round( $price * ( 1 - $discount / 100 ), wc_get_price_decimals() );
	}

	/**
	 * Handle ?twh_renew=ID&t=TOKEN.
	 */
	public static function handle_renew_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- HMAC token in the URL replaces a nonce so links work from emails.
		if ( empty( $_GET['twh_renew'] ) || empty( $_GET['t'] ) || ! function_exists( 'WC' ) || is_admin() ) {
			return;
		}
		$license_id = absint( $_GET['twh_renew'] );
		$token      = sanitize_text_field( wp_unslash( $_GET['t'] ) );
		// phpcs:enable

		$license = Licenses::find( $license_id );
		if ( ! $license || ! hash_equals( Secrets::renew_token( $license_id ), $token ) ) {
			self::fail( __( 'This renewal link is not valid.', 'talkwyn-hub' ) );
			return;
		}
		$manage = Subscriptions::manage_url( $license );
		if ( $manage ) {
			wp_safe_redirect( $manage );
			exit;
		}
		if ( ! self::can_renew( $license ) ) {
			self::fail( __( 'This license cannot be renewed online. Please contact support.', 'talkwyn-hub' ) );
			return;
		}
		$product = self::renewal_product( $license );
		if ( ! $product ) {
			self::fail( __( 'The renewal product is currently unavailable. Please contact support.', 'talkwyn-hub' ) );
			return;
		}

		self::ensure_cart();
		self::remove_existing( self::RENEW, $license_id );
		$added = self::add( $product, array( self::RENEW => $license_id ) );
		if ( ! $added ) {
			self::fail( __( 'Could not add the renewal to your cart.', 'talkwyn-hub' ) );
			return;
		}
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Upgrade targets for a license: more expensive plans of the same software
	 * product with the same term type (yearly → yearly, lifetime → lifetime).
	 *
	 * @param array<string, mixed> $license License.
	 * @return array<int, array{wc_product: \WC_Product, mapping: array<string, mixed>, price: float}>
	 */
	public static function upgrade_targets( array $license ): array {
		if ( ! empty( $license['is_trial'] ) || 'active' !== Licenses::effective_status( $license ) || Subscriptions::manage_url( $license ) ) {
			return array();
		}
		$software = Products::find( (int) $license['product_id'] );
		$current  = (int) $license['wc_product_id'] ? wc_get_product( (int) $license['wc_product_id'] ) : null;
		if ( ! $software || ! $current instanceof \WC_Product ) {
			return array();
		}
		$current_price = (float) $current->get_price( 'edit' );
		$lifetime      = 0 === (int) $license['duration_days'];
		$out           = array();
		foreach ( Mapping::products_for_software( (string) $software['slug'] ) as $candidate ) {
			$product = $candidate['wc_product'];
			$mapping = $candidate['mapping'];
			if ( $product->get_id() === $current->get_id() || ( 0 === (int) $mapping['duration_days'] ) !== $lifetime ) {
				continue;
			}
			if ( (float) $product->get_price( 'edit' ) <= $current_price ) {
				continue;
			}
			$out[] = array(
				'wc_product' => $product,
				'mapping'    => $mapping,
				'price'      => self::upgrade_price( $license, $product ),
			);
		}
		return $out;
	}

	/**
	 * Prorated upgrade price.
	 *
	 * @param array<string, mixed> $license License.
	 * @param \WC_Product          $target  Target product.
	 */
	public static function upgrade_price( array $license, \WC_Product $target ): float {
		$current = wc_get_product( (int) $license['wc_product_id'] );
		$from    = $current instanceof \WC_Product ? (float) $current->get_price( 'edit' ) : 0.0;
		return ExpiryCalculator::upgrade_price(
			$from,
			(float) $target->get_price( 'edit' ),
			Licenses::expires_ts( $license ),
			(int) $license['duration_days'],
			time()
		);
	}

	/**
	 * Handle the upgrade form POST from My Account.
	 */
	public static function handle_upgrade_request(): void {
		if ( empty( $_POST['twh_upgrade_license'] ) || ! function_exists( 'WC' ) || is_admin() ) {
			return;
		}
		$license_id = absint( $_POST['twh_upgrade_license'] );
		if ( ! isset( $_POST['_twh_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_twh_nonce'] ) ), 'twh_upgrade_' . $license_id ) ) {
			self::fail( __( 'Your session expired. Please try again.', 'talkwyn-hub' ) );
			return;
		}
		$license = Licenses::find( $license_id );
		if ( ! $license || ! Licenses::is_owned_by( $license, get_current_user_id() ) ) {
			self::fail( __( 'License not found.', 'talkwyn-hub' ) );
			return;
		}
		$target_id = absint( $_POST['twh_target_product'] ?? 0 );
		$target    = null;
		foreach ( self::upgrade_targets( $license ) as $candidate ) {
			if ( $candidate['wc_product']->get_id() === $target_id ) {
				$target = $candidate['wc_product'];
				break;
			}
		}
		if ( ! $target ) {
			self::fail( __( 'This upgrade is not available for your license.', 'talkwyn-hub' ) );
			return;
		}
		self::ensure_cart();
		self::remove_existing( self::UPGRADE, $license_id );
		if ( ! self::add( $target, array( self::UPGRADE => $license_id ) ) ) {
			self::fail( __( 'Could not add the upgrade to your cart.', 'talkwyn-hub' ) );
			return;
		}
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Apply renewal/upgrade prices. Always computed from the fresh product price so
	 * discounts never stack across recalculations.
	 *
	 * @param \WC_Cart $cart Cart.
	 */
	public static function set_prices( $cart ): void {
		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof \WC_Product ) {
				continue;
			}
			$fresh = wc_get_product( $item['data']->get_id() );
			if ( ! $fresh ) {
				continue;
			}
			if ( ! empty( $item[ self::RENEW ] ) ) {
				$item['data']->set_price( self::renewal_price( $fresh ) );
			} elseif ( ! empty( $item[ self::UPGRADE ] ) ) {
				$license = Licenses::find( (int) $item[ self::UPGRADE ] );
				if ( $license ) {
					$item['data']->set_price( self::upgrade_price( $license, $fresh ) );
				}
			}
		}
	}

	/**
	 * Show the license reference in the cart.
	 *
	 * @param array<int, array<string, string>> $data Item data.
	 * @param array<string, mixed>              $item Cart item.
	 * @return array<int, array<string, string>>
	 */
	public static function item_data( $data, $item ) {
		foreach ( array( self::RENEW, self::UPGRADE, self::CONVERT ) as $key ) {
			if ( empty( $item[ $key ] ) ) {
				continue;
			}
			$license = Licenses::find( (int) $item[ $key ] );
			if ( ! $license ) {
				continue;
			}
			$labels = array(
				self::RENEW   => __( 'Renewal of license', 'talkwyn-hub' ),
				self::UPGRADE => __( 'Upgrade of license', 'talkwyn-hub' ),
				self::CONVERT => __( 'Upgrade of your trial', 'talkwyn-hub' ),
			);
			$data[] = array(
				'key'   => $labels[ $key ],
				'value' => KeyGenerator::mask( (string) $license['key_last4'] ),
			);
			if ( self::CONVERT === $key ) {
				$data[] = array(
					'key'   => __( 'Note', 'talkwyn-hub' ),
					'value' => __( 'You keep the same license key. Your yearly term starts today.', 'talkwyn-hub' ),
				);
			}
			if ( self::RENEW === $key && (float) Settings::get( 'renewal_discount' ) > 0 ) {
				$data[] = array(
					'key'   => __( 'Renewal discount', 'talkwyn-hub' ),
					'value' => (float) Settings::get( 'renewal_discount' ) . '%',
				);
			}
			if ( self::UPGRADE === $key ) {
				$data[] = array(
					'key'   => __( 'Note', 'talkwyn-hub' ),
					'value' => __( 'Prorated for the remaining license term. Your key and expiry date stay the same.', 'talkwyn-hub' ),
				);
			}
		}
		return $data;
	}

	/**
	 * Copy cart data to the order item.
	 *
	 * @param \WC_Order_Item_Product $order_item Order item.
	 * @param string                 $cart_key   Cart item key.
	 * @param array<string, mixed>   $values     Cart item.
	 */
	public static function to_order_item( $order_item, $cart_key, $values ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
		if ( ! empty( $values[ self::RENEW ] ) ) {
			$order_item->add_meta_data( OrderHandler::ITEM_RENEW_LICENSE, (int) $values[ self::RENEW ], true );
		}
		if ( ! empty( $values[ self::UPGRADE ] ) ) {
			$order_item->add_meta_data( OrderHandler::ITEM_UPGRADE, (int) $values[ self::UPGRADE ], true );
		}
		if ( ! empty( $values[ self::CONVERT ] ) ) {
			$order_item->add_meta_data( OrderHandler::ITEM_TRIAL_CONVERT, (int) $values[ self::CONVERT ], true );
		}
	}

	/**
	 * Fixed quantity of 1 for renewals/upgrades.
	 *
	 * @param string               $html     Quantity HTML.
	 * @param string               $cart_key Cart key.
	 * @param array<string, mixed> $item     Cart item.
	 */
	public static function lock_quantity( $html, $cart_key, $item = array() ) {
		if ( ! empty( $item[ self::RENEW ] ) || ! empty( $item[ self::UPGRADE ] ) || ! empty( $item[ self::CONVERT ] ) ) {
			return sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', esc_attr( $cart_key ) );
		}
		return $html;
	}

	/**
	 * Reject quantity changes.
	 *
	 * @param bool                 $passed   Passed.
	 * @param string               $cart_key Cart key.
	 * @param array<string, mixed> $values   Cart item.
	 * @param int                  $quantity Quantity.
	 */
	public static function validate_quantity( $passed, $cart_key, $values, $quantity ) {
		if ( ( ! empty( $values[ self::RENEW ] ) || ! empty( $values[ self::UPGRADE ] ) || ! empty( $values[ self::CONVERT ] ) ) && 1 !== (int) $quantity ) {
			wc_add_notice( __( 'Renewals and upgrades are limited to a quantity of 1.', 'talkwyn-hub' ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Keep license references when the cart is restored from the session.
	 *
	 * @param array<string, mixed> $item   Cart item.
	 * @param array<string, mixed> $values Session values.
	 * @return array<string, mixed>
	 */
	public static function from_session( $item, $values ) {
		foreach ( array( self::RENEW, self::UPGRADE, self::CONVERT ) as $key ) {
			if ( ! empty( $values[ $key ] ) ) {
				$item[ $key ] = (int) $values[ $key ];
			}
		}
		return $item;
	}

	/**
	 * Remove renewals/upgrades that are no longer valid.
	 */
	public static function validate_cart(): void {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}
		foreach ( $cart->get_cart() as $key => $item ) {
			$valid = true;
			if ( ! empty( $item[ self::RENEW ] ) ) {
				$license = Licenses::find( (int) $item[ self::RENEW ] );
				$valid   = $license && self::can_renew( $license );
			} elseif ( ! empty( $item[ self::UPGRADE ] ) ) {
				$license = Licenses::find( (int) $item[ self::UPGRADE ] );
				$valid   = $license && Licenses::is_owned_by( $license, get_current_user_id() ) && 'active' === Licenses::effective_status( $license );
			} elseif ( ! empty( $item[ self::CONVERT ] ) ) {
				// Anyone holding the signed link may pay for a trial; expired trials can still convert.
				$license = Licenses::find( (int) $item[ self::CONVERT ] );
				$valid   = $license && ! empty( $license['is_trial'] ) && in_array( $license['status'], array( 'active', 'expired' ), true );
			}
			if ( ! $valid ) {
				$cart->remove_cart_item( $key );
				wc_add_notice( __( 'A license renewal or upgrade in your cart is no longer valid and was removed.', 'talkwyn-hub' ), 'error' );
			} elseif ( (int) $item['quantity'] > 1 && ( ! empty( $item[ self::RENEW ] ) || ! empty( $item[ self::UPGRADE ] ) || ! empty( $item[ self::CONVERT ] ) ) ) {
				$cart->set_quantity( $key, 1, false );
			}
		}
	}

	/**
	 * Add a trial conversion to the cart (replacing any earlier one for the license).
	 *
	 * @param \WC_Product $product    Paid plan product.
	 * @param int         $license_id Trial license id.
	 */
	public static function add_conversion( \WC_Product $product, int $license_id ): bool {
		self::ensure_cart();
		self::remove_existing( self::CONVERT, $license_id );
		return self::add( $product, array( self::CONVERT => $license_id ) );
	}

	/**
	 * Add a product (simple or variation) to the cart.
	 *
	 * @param \WC_Product          $product Product.
	 * @param array<string, mixed> $data    Cart item data.
	 */
	private static function add( \WC_Product $product, array $data ): bool {
		if ( $product->is_type( 'variation' ) ) {
			$key = WC()->cart->add_to_cart( $product->get_parent_id(), 1, $product->get_id(), $product->get_variation_attributes(), $data );
		} else {
			$key = WC()->cart->add_to_cart( $product->get_id(), 1, 0, array(), $data );
		}
		return (bool) $key;
	}

	/**
	 * Remove cart items with the same license reference.
	 *
	 * @param string $type       Data key.
	 * @param int    $license_id License id.
	 */
	private static function remove_existing( string $type, int $license_id ): void {
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( ! empty( $item[ $type ] ) && (int) $item[ $type ] === $license_id ) {
				WC()->cart->remove_cart_item( $key );
			}
		}
	}

	/**
	 * Make sure the cart and session are loaded (also for guests).
	 */
	private static function ensure_cart(): void {
		if ( null === WC()->session ) {
			WC()->initialize_session();
		}
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		if ( null === WC()->cart ) {
			wc_load_cart();
		}
	}

	/**
	 * Show an error and send the customer to their licenses page.
	 *
	 * @param string $message Message.
	 */
	private static function fail( string $message ): void {
		if ( function_exists( 'wc_add_notice' ) ) {
			self::ensure_cart();
			wc_add_notice( $message, 'error' );
		}
		$url = is_user_logged_in() ? wc_get_account_endpoint_url( 'licenses' ) : wc_get_cart_url();
		wp_safe_redirect( $url );
		exit;
	}
}
