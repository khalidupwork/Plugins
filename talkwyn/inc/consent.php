<?php
/**
 * Cookie banner and the homepage trial popup.
 *
 * The banner shows only when the site uses a cookie that needs consent: Google
 * Analytics 4, or the Talkwyn Hub partner cookie (twh_ref). Plausible sets no cookies.
 * The choice is kept in the essential "tw_consent" cookie ("all" or "essential") for
 * 180 days, and passed on to the WP Consent API when that plugin is active.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cookies that need consent on this site, as short labels.
 *
 * @return array<string, string>
 */
function talkwyn_consent_uses(): array {
	$uses = array();
	if ( 'ga4' === talkwyn_setting( 'analytics' ) && preg_match( '/^G-[A-Z0-9]+$/', (string) talkwyn_setting( 'ga4_id' ) ) ) {
		$uses['analytics'] = __( 'Google Analytics, to see which pages help', 'talkwyn' );
	}
	if ( class_exists( '\TWH\Support\Settings' ) && \TWH\Support\Settings::get( 'partners_enabled' ) ) {
		$uses['partner'] = __( 'a partner cookie that credits the partner who sent you', 'talkwyn' );
	}
	return $uses;
}

/**
 * Whether the banner is on for this site.
 */
function talkwyn_consent_needed(): bool {
	$mode = (string) talkwyn_setting( 'cookie_banner' );
	if ( 'off' === $mode ) {
		return false;
	}
	return 'always' === $mode || (bool) talkwyn_consent_uses();
}

/**
 * The visitor's choice: "all", "essential" or "" (not decided yet).
 */
function talkwyn_consent(): string {
	$v = isset( $_COOKIE['tw_consent'] ) ? sanitize_key( wp_unslash( $_COOKIE['tw_consent'] ) ) : '';
	return in_array( $v, array( 'all', 'essential' ), true ) ? $v : '';
}

// The Hub's partner cookie waits for "Accept all" while the banner is on.
add_filter(
	'twh_referral_cookie_consent',
	static function ( $allowed ) {
		return talkwyn_consent_needed() ? 'all' === talkwyn_consent() : $allowed;
	}
);

add_action(
	'wp_footer',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$out = '';
		if ( talkwyn_consent_needed() ) {
			$uses = talkwyn_consent_uses();
			$text = '' !== trim( (string) talkwyn_setting( 'cookie_text' ) )
				? (string) talkwyn_setting( 'cookie_text' )
				: ( $uses
					/* translators: %s: list of optional cookies */
					? sprintf( __( 'We use essential cookies to run this site. With your OK we also use %s. You can change this any time under Cookie settings.', 'talkwyn' ), implode( __( ' and ', 'talkwyn' ), $uses ) )
					: __( 'We use essential cookies to run this site, such as your cart and log in. You can change your choice any time under Cookie settings.', 'talkwyn' ) );
			$out .= '<div class="tw-cookie" role="region" aria-labelledby="tw-cookie-title" data-tw-cookie hidden>'
				. '<p class="tw-cookie__title" id="tw-cookie-title">' . talkwyn_icon( 'shield-check', 18 ) . esc_html__( 'Your privacy', 'talkwyn' ) . '</p>'
				. '<p class="tw-cookie__text">' . esc_html( $text ) . ' <a href="' . esc_url( home_url( '/privacy/#cookies' ) ) . '">' . esc_html__( 'Privacy policy', 'talkwyn' ) . '</a></p>'
				. '<div class="tw-cookie__actions"><button type="button" class="tw-btn tw-btn--secondary tw-btn--sm" data-tw-consent="essential">' . esc_html__( 'Only essential', 'talkwyn' ) . '</button>'
				. '<button type="button" class="tw-btn tw-btn--sm" data-tw-consent="all">' . esc_html__( 'Accept all', 'talkwyn' ) . '</button></div></div>';
		}
		// Homepage trial popup after the visitor scrolls part of the page.
		if ( is_front_page() && talkwyn_setting( 'trial_popup' ) && talkwyn_show_trial() && ! ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) ) {
			$cfg  = array(
				'scroll' => max( 10, min( 95, (int) talkwyn_setting( 'trial_popup_scroll' ) ) ),
				'days'   => max( 1, (int) talkwyn_setting( 'trial_popup_days' ) ),
			);
			$out .= '<script>window.twNudge=' . wp_json_encode( $cfg ) . ';</script>';
		}
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
	},
	25
);

// "Cookie settings" in the footer reopens the banner.
add_filter(
	'talkwyn_footer_columns',
	static function ( $columns ) {
		if ( talkwyn_consent_needed() ) {
			$last                   = array_key_last( $columns );
			$columns[ $last ][]     = array( __( 'Cookie settings', 'talkwyn' ), '#cookie-settings' );
		}
		return $columns;
	}
);
