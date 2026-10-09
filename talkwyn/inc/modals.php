<?php
/**
 * Header popups: "Log in" and "Start free trial" open as dialogs. Links keep their
 * real href, so without JavaScript they still go to My Account and /pricing/#trial.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Login form markup. Posts to My Account, where WooCommerce handles the login and shows any error.
 */
function talkwyn_modal_login_form(): string {
	$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
	if ( ! function_exists( 'wc_get_page_permalink' ) ) {
		return wp_login_form(
			array(
				'echo'     => false,
				'redirect' => $account,
			)
		);
	}
	$lost     = wc_lostpassword_url();
	$register = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
	return '<form class="tw-mform" method="post" action="' . esc_url( $account ) . '">'
		. '<p><label for="tw-m-user">' . esc_html__( 'Email or username', 'talkwyn' ) . '</label><input id="tw-m-user" type="text" name="username" autocomplete="username" required></p>'
		. '<p><label for="tw-m-pass">' . esc_html__( 'Password', 'talkwyn' ) . '</label><input id="tw-m-pass" type="password" name="password" autocomplete="current-password" required></p>'
		. '<p class="tw-mform__row"><label class="tw-mform__check"><input type="checkbox" name="rememberme" value="forever"> ' . esc_html__( 'Remember me', 'talkwyn' ) . '</label>'
		. '<a href="' . esc_url( $lost ) . '">' . esc_html__( 'Forgot password?', 'talkwyn' ) . '</a></p>'
		. wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce', true, false )
		. '<input type="hidden" name="redirect" value="' . esc_url( $account ) . '">'
		. talkwyn_captcha_field( 'account' )
		. '<p><button class="tw-pill tw-pill--red tw-pill--block" type="submit" name="login" value="' . esc_attr__( 'Log in', 'talkwyn' ) . '">' . esc_html__( 'Log in', 'talkwyn' ) . '</button></p>'
		. ( $register ? '<p class="tw-mform__alt">' . esc_html__( 'New here?', 'talkwyn' ) . ' <a href="' . esc_url( $account ) . '#register">' . esc_html__( 'Create an account', 'talkwyn' ) . '</a></p>' : '' )
		. '</form>';
}

/**
 * Trial form markup: the Hub's form when the Hub runs on this site, otherwise a link.
 */
function talkwyn_modal_trial_form(): string {
	if ( shortcode_exists( 'twh_trial_form' ) ) {
		return do_shortcode( '[twh_trial_form labels="hidden"]' );
	}
	return '<a class="tw-pill tw-pill--red tw-pill--block" href="' . esc_url( home_url( '/pricing/#trial' ) ) . '">' . esc_html__( 'Start my free trial', 'talkwyn' ) . '</a>';
}

add_action(
	'wp_footer',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$days  = talkwyn_trial()['days'];
		$close = '<button type="button" class="tw-modal__close" data-tw-modal-close>' . talkwyn_icon( 'x', 20 ) . '<span class="screen-reader-text">' . esc_html__( 'Close', 'talkwyn' ) . '</span></button>';
		$out   = '';
		if ( ! is_user_logged_in() ) {
			$out .= '<dialog class="tw-modal" id="tw-modal-login" aria-labelledby="tw-modal-login-title">' . $close
				. '<div class="tw-modal__head"><span class="tw-modal__mark" aria-hidden="true">' . talkwyn_logo_svg( 'mark', '' ) . '</span>'
				. '<h2 id="tw-modal-login-title">' . esc_html__( 'Welcome back', 'talkwyn' ) . '</h2>'
				. '<p>' . esc_html__( 'Log in to see your licenses, downloads, invoices, and partner dashboard.', 'talkwyn' ) . '</p></div>'
				. talkwyn_modal_login_form() . '</dialog>';
		}
		if ( talkwyn_show_trial() ) {
		$out .= '<dialog class="tw-modal tw-modal--trial" id="tw-modal-trial" aria-labelledby="tw-modal-trial-title">' . $close
			. '<div class="tw-modal__head"><span class="tw-modal__badge">' . talkwyn_icon( 'sparkles', 16 ) . esc_html__( 'Free trial', 'talkwyn' ) . '</span>'
			/* translators: %d: trial days */
			. '<h2 id="tw-modal-trial-title">' . esc_html( sprintf( __( 'Try every Pro feature free for %d days', 'talkwyn' ), $days ) ) . '</h2>'
			. '<p>' . esc_html__( 'Confirm your email, then your license key and account arrive right away. Upgrade with the same key, or keep the free plan.', 'talkwyn' ) . '</p></div>'
			. '<div class="tw-modal__body" data-tw-modal-body></div>'
			. '<template data-tw-modal-tpl>' . talkwyn_modal_trial_form() . '</template></dialog>';
		}
		echo $out; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- built from escaped parts above.
	},
	20
);

/**
 * Whether "Start free trial" buttons show for the current visitor. Talkwyn Hub answers through
 * the talkwyn_show_trial_cta filter: logged out visitors always see them, a customer who already
 * had a trial or holds a license does not.
 */
function talkwyn_show_trial(): bool {
	return (bool) apply_filters( 'talkwyn_show_trial_cta', true );
}

/**
 * Body class so trial buttons written into page content hide too.
 */
add_filter(
	'body_class',
	static function ( $classes ) {
		if ( ! talkwyn_show_trial() ) {
			$classes[] = 'tw-no-trial';
		}
		return $classes;
	}
);

/**
 * [tw_cta_line] The sentence under "Let your website do the talking." that fits the visitor.
 */
add_shortcode(
	'tw_cta_line',
	static function () {
		if ( talkwyn_show_trial() ) {
			/* translators: %d: trial days */
			return esc_html( sprintf( __( 'Start your %d-day trial, scan your site, and see your first answer in about five minutes.', 'talkwyn' ), talkwyn_trial()['days'] ) );
		}
		return esc_html__( 'Install Talkwyn on another site, or pick the plan that fits your next project.', 'talkwyn' );
	}
);
