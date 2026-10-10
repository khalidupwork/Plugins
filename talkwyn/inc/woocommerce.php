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
		// Renewals are automatic only with WooCommerce Subscriptions.
		$renew = class_exists( 'WC_Subscriptions' ) ? __( 'Cancel renewal anytime.', 'talkwyn' ) : __( 'No automatic renewal.', 'talkwyn' );
		$text  = $days > 0
			/* translators: 1: refund window in days, 2: renewal sentence */
			? sprintf( __( '%1$d-day refund policy. %2$s', 'talkwyn' ), $days, $renew )
			: $renew;
		// Keys are emailed in full only when Talkwyn Hub's "Keys in emails" is on.
		$emailed = class_exists( '\TWH\Support\Settings' ) && \TWH\Support\Settings::email_keys();
		$key     = $emailed
			? __( 'Your license key appears right after payment and arrives by email.', 'talkwyn' )
			: __( 'Your license key appears right after payment and stays in your account.', 'talkwyn' );
		return '<ul class="tw-trustline">'
			. '<li>' . talkwyn_icon( 'shield-check', 18 ) . '<span>' . esc_html( $text ) . ' <a href="' . esc_url( home_url( '/refund-policy/' ) ) . '">' . esc_html__( 'Read the policy', 'talkwyn' ) . '</a></span></li>'
			. '<li>' . talkwyn_icon( 'key-round', 18 ) . '<span>' . esc_html( $key ) . '</span></li>'
			. '<li>' . talkwyn_icon( 'file-text', 18 ) . '<span>' . esc_html__( 'A numbered invoice for every order, in your account.', 'talkwyn' ) . '</span></li>'
			. '</ul>';
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

/**
 * My Account, logged out: the login form in a card, with what the account holds beside it.
 */
add_action(
	'woocommerce_before_customer_login_form',
	static function () {
		echo '<div class="tw-acct-login"><div class="tw-acct-login__main">';
	}
);
add_action(
	'woocommerce_after_customer_login_form',
	static function () {
		$items = array(
			array( 'key-round', __( 'Licenses and sites', 'talkwyn' ), __( 'Your keys, plans, and the sites they run on.', 'talkwyn' ) ),
			array( 'download', __( 'Downloads', 'talkwyn' ), __( 'The latest Talkwyn Pro version.', 'talkwyn' ) ),
			array( 'file-text', __( 'Orders and invoices', 'talkwyn' ), __( 'Every order with a printable invoice.', 'talkwyn' ) ),
			array( 'handshake', __( 'Partner dashboard', 'talkwyn' ), __( 'Your link, referrals, and payouts.', 'talkwyn' ) ),
		);
		$list  = '';
		foreach ( $items as $i ) {
			$list .= '<li><span class="tw-card__icon">' . talkwyn_icon( $i[0], 18 ) . '</span><span><b>' . esc_html( $i[1] ) . '</b><i>' . esc_html( $i[2] ) . '</i></span></li>';
		}
		echo '</div><aside class="tw-acct-login__aside"><p class="tw-acct-login__kicker">' . esc_html__( 'Your Talkwyn account', 'talkwyn' ) . '</p><ul>' . $list . '</ul>' // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped above.
			. '<div class="tw-acct-login__new"><p>' . esc_html__( 'New to Talkwyn?', 'talkwyn' ) . '</p>'
			. '<a class="tw-pill tw-pill--red" href="' . esc_url( home_url( '/pricing/#trial' ) ) . '" data-tw-modal="trial" data-tw-event="trial_click" data-tw-location="account_login">' . esc_html__( 'Start free trial', 'talkwyn' ) . '</a>'
			. '<a class="tw-arrow-link" href="' . esc_url( talkwyn_install_url() ) . '">' . esc_html__( 'Or install free', 'talkwyn' ) . talkwyn_icon( 'arrow-right', 16 ) . '</a></div></aside></div>';
	}
);

/**
 * Clearer account wording.
 */
add_filter(
	'gettext',
	static function ( $text, $original, $domain ) {
		if ( 'woocommerce' !== $domain ) {
			return $text;
		}
		if ( 'Login' === $original ) {
			return __( 'Log in to your account', 'talkwyn' );
		}
		if ( 'Lost your password?' === $original ) {
			return __( 'Forgot your password?', 'talkwyn' );
		}
		return $text;
	},
	10,
	3
);
add_filter(
	'woocommerce_account_menu_items',
	static function ( $items ) {
		if ( isset( $items['orders'] ) ) {
			$items['orders'] = __( 'Orders and invoices', 'talkwyn' );
		}
		if ( isset( $items['downloads'], $items['software-downloads'] ) ) {
			unset( $items['downloads'] ); // Software downloads already lists every file a customer owns.
		}
		return $items;
	},
	50
);

/**
 * My Account dashboard: one card per section.
 */
add_action(
	'woocommerce_account_dashboard',
	static function () {
		$icons = array(
			'orders'             => array( 'file-text', __( 'Orders, receipts, and printable invoices.', 'talkwyn' ) ),
			'licenses'           => array( 'key-round', __( 'Your keys, plans, and active sites.', 'talkwyn' ) ),
			'software-downloads' => array( 'download', __( 'The latest Talkwyn Pro version.', 'talkwyn' ) ),
			'downloads'          => array( 'download', __( 'Files from your orders.', 'talkwyn' ) ),
			'partners'           => array( 'handshake', __( 'Your partner link, referrals, and payouts.', 'talkwyn' ) ),
			'edit-address'       => array( 'house', __( 'Billing details for your invoices.', 'talkwyn' ) ),
			'edit-account'       => array( 'user-check', __( 'Name, email, and password.', 'talkwyn' ) ),
		);
		$cards = '';
		foreach ( wc_get_account_menu_items() as $key => $label ) {
			if ( ! isset( $icons[ $key ] ) ) {
				continue;
			}
			$cards .= '<a class="tw-acct-card" href="' . esc_url( wc_get_account_endpoint_url( $key ) ) . '"><span class="tw-card__icon">' . talkwyn_icon( $icons[ $key ][0], 20 ) . '</span>'
				. '<span><b>' . esc_html( $label ) . '</b><i>' . esc_html( $icons[ $key ][1] ) . '</i></span>' . talkwyn_icon( 'arrow-right', 16 ) . '</a>';
		}
		echo '<div class="tw-acct-cards">' . $cards . '</div>'; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped above.
		echo '<div class="tw-acct-help"><span class="tw-card__icon">' . talkwyn_icon( 'circle-help', 20 ) . '</span><p>' . esc_html__( 'Need a hand with setup or billing?', 'talkwyn' ) . ' <a href="' . esc_url( home_url( '/docs/getting-started/' ) ) . '">' . esc_html__( 'Read the setup guide', 'talkwyn' ) . '</a> ' . esc_html__( 'or', 'talkwyn' ) . ' <a href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html__( 'contact us', 'talkwyn' ) . '</a>.</p></div>'; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped above.
	}
);
