<?php
/**
 * Analytics (GA4 or Plausible), controlled from Site Settings.
 *
 * Events sent by assets/js/theme.js: install_click, demo_question, demo_lead_saved,
 * pricing_cta, checkout_start, doc_feedback.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	static function () {
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return; // Don't count the team.
		}
		$provider = (string) talkwyn_setting( 'analytics' );
		if ( 'ga4' === $provider ) {
			$id = (string) talkwyn_setting( 'ga4_id' );
			if ( ! preg_match( '/^G-[A-Z0-9]+$/', $id ) ) {
				return;
			}
			printf( '<script async src="%s"></script>' . "\n", esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . $id ) ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- vendor snippet must load as written, before theme.js.
			echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config'," . wp_json_encode( $id ) . ",{anonymize_ip:true});</script>\n";
		} elseif ( 'plausible' === $provider ) {
			$domain = (string) talkwyn_setting( 'plausible_domain' );
			if ( '' === $domain ) {
				return;
			}
			printf( '<script defer data-domain="%s" src="%s"></script>' . "\n", esc_attr( $domain ), esc_url( (string) talkwyn_setting( 'plausible_src' ) ) ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- vendor snippet needs data-domain on the tag.
			echo "<script>window.plausible=window.plausible||function(){(window.plausible.q=window.plausible.q||[]).push(arguments)};</script>\n";
		}
	},
	5
);

/**
 * Fire checkout_start once on the checkout page.
 */
add_action(
	'wp_footer',
	static function () {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			echo "<script>window.addEventListener('load',function(){if(window.twTrack){window.twTrack('checkout_start');}});</script>\n";
		}
	},
	30
);
