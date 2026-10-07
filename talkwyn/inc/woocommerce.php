<?php
/**
 * WooCommerce integration: checkout trust line, styles, small UX tweaks.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * [tw_checkout_trust] Shown above the checkout. Uses the real refund window from Site Settings.
 */
add_shortcode(
	'tw_checkout_trust',
	static function () {
		$days = (int) talkwyn_setting( 'refund_days' );
		$text = $days > 0
			/* translators: %d: refund window in days */
			? sprintf( __( '%d-day refund policy. Cancel renewal anytime.', 'talkwyn' ), $days )
			: __( 'Cancel renewal anytime.', 'talkwyn' );
		return '<p class="tw-trustline">' . talkwyn_icon( 'shield-check', 18 ) . ' <span>' . esc_html( $text ) . '</span> <a href="' . esc_url( home_url( '/refund-policy/' ) ) . '">' . esc_html__( 'Read the policy', 'talkwyn' ) . '</a></p>';
	}
);

/**
 * True on pages where WooCommerce actually does something: product, cart, checkout and My Account.
 */
function talkwyn_is_shop_context(): bool {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return false;
	}
	return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( talkwyn_is_shop_context() ) {
			wp_enqueue_style( 'talkwyn-woocommerce', TALKWYN_THEME_URL . '/assets/css/woocommerce.css', array( 'talkwyn' ), TALKWYN_THEME_VERSION );
		}
	},
	20
);

/**
 * Marketing pages don't need WooCommerce's jQuery, cart scripts or 120 KB of shop CSS.
 * Buy buttons are plain links to the checkout, so nothing breaks. Order attribution
 * stays on every page (it records where buyers came from) but loads deferred.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		foreach ( array( 'sourcebuster-js', 'wc-order-attribution' ) as $handle ) {
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
		if ( talkwyn_is_shop_context() || ! apply_filters( 'talkwyn_trim_woocommerce_assets', true ) ) {
			return;
		}
		foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-blocktheme', 'wc-blocks-style' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
		foreach ( array( 'wc-add-to-cart', 'woocommerce', 'wc-cart-fragments', 'jquery-blockui', 'js-cookie', 'wc-js-cookie' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
	},
	99
);

/**
 * Talkwyn products are virtual: skip the "order notes" field and shipping noise.
 */
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

/**
 * Keep WooCommerce's marketing/onboarding banners off the storefront.
 */
add_filter( 'woocommerce_demo_store', '__return_empty_string' );

/**
 * WooCommerce auto-inserts account and mini-cart blocks next to navigation in block
 * themes. The header already has "Log in", and a software store needs no mini cart.
 *
 * @param string[] $hooked Hooked block types.
 */
add_filter(
	'hooked_block_types',
	static function ( $hooked ) {
		return array_values( array_diff( (array) $hooked, array( 'woocommerce/customer-account', 'woocommerce/mini-cart' ) ) );
	},
	20
);

/**
 * Product placeholder image: the Talkwyn app icon instead of a grey box.
 */
add_filter(
	'woocommerce_placeholder_img_src',
	static function () {
		return TALKWYN_THEME_URL . '/assets/brand/icons/android-chrome-192x192.png';
	}
);

// WooCommerce enqueues its blocks stylesheet late, while rendering the page.
add_action(
	'wp_footer',
	static function () {
		if ( class_exists( 'WooCommerce' ) && ! talkwyn_is_shop_context() && apply_filters( 'talkwyn_trim_woocommerce_assets', true ) ) {
			wp_dequeue_style( 'wc-blocks-style' );
		}
	},
	1
);

/**
 * Talkwyn is sold from /pricing/, so WooCommerce's shop, product and product category
 * pages are never part of the site. Send visitors (and search engines) there instead.
 * Return false from the talkwyn_shop_redirect filter to keep the WooCommerce pages.
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) || ! ( is_shop() || is_product() || is_product_taxonomy() ) ) {
			return;
		}
		$target = apply_filters( 'talkwyn_shop_redirect', home_url( '/pricing/' ) );
		if ( $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}
);
