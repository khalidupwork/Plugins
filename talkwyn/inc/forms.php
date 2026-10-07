<?php
/**
 * Lightweight forms: contact and Shopify waitlist. No form plugin needed.
 *
 * Spam protection: nonce, honeypot field, minimum fill time and a per-IP rate limit.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Status notice after a redirect.
 *
 * @param string $form Form id.
 */
function talkwyn_form_notice( string $form ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$status   = isset( $_GET['tw_form'], $_GET['tw_status'] ) && $form === $_GET['tw_form'] ? sanitize_key( wp_unslash( $_GET['tw_status'] ) ) : '';
	$messages = array(
		'sent'    => array( 'success', 'contact' === $form ? __( 'Thanks. Your message is on its way, and we will reply by email soon.', 'talkwyn' ) : __( 'You are on the list. We will email you when the Shopify app launches.', 'talkwyn' ) ),
		'invalid' => array( 'error', __( 'Please check the form. A valid email address is required.', 'talkwyn' ) ),
		'limited' => array( 'error', __( 'Too many attempts. Please try again in a few minutes.', 'talkwyn' ) ),
		'failed'  => array( 'error', __( 'Something went wrong. Please email us directly instead.', 'talkwyn' ) ),
	);
	if ( ! isset( $messages[ $status ] ) ) {
		return '';
	}
	return '<p class="tw-notice tw-notice--' . esc_attr( $messages[ $status ][0] ) . '" role="status">' . esc_html( $messages[ $status ][1] ) . '</p>';
}

/**
 * Shared hidden fields.
 *
 * @param string $form Form id.
 */
function talkwyn_form_hidden( string $form ): string {
	return wp_nonce_field( 'talkwyn_form_' . $form, '_tw_nonce', true, false )
		. '<input type="hidden" name="action" value="talkwyn_form">'
		. '<input type="hidden" name="tw_form" value="' . esc_attr( $form ) . '">'
		. '<input type="hidden" name="tw_t" value="' . esc_attr( (string) time() ) . '">'
		. '<input type="hidden" name="tw_back" value="' . esc_attr( talkwyn_current_url() ) . '">'
		. '<label class="tw-hp" aria-hidden="true">Website <input type="text" name="tw_website" tabindex="-1" autocomplete="off"></label>';
}

add_shortcode(
	'tw_contact_form',
	static function () {
		return talkwyn_form_notice( 'contact' ) . '<form class="tw-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '#contact-form" id="contact-form">'
			. talkwyn_form_hidden( 'contact' )
			. '<label>' . esc_html__( 'Your name', 'talkwyn' ) . '<input type="text" name="tw_name" autocomplete="name" required maxlength="100"></label>'
			. '<label>' . esc_html__( 'Email', 'talkwyn' ) . '<input type="email" name="tw_email" autocomplete="email" required maxlength="190"></label>'
			. '<label>' . esc_html__( 'Website (optional)', 'talkwyn' ) . '<input type="url" name="tw_site" autocomplete="url" maxlength="190" placeholder="https://"></label>'
			. '<label>' . esc_html__( 'Topic', 'talkwyn' ) . '<select name="tw_topic"><option>' . esc_html__( 'Question before buying', 'talkwyn' ) . '</option><option>' . esc_html__( 'Help with my license or setup', 'talkwyn' ) . '</option><option>' . esc_html__( 'Agency or partnership', 'talkwyn' ) . '</option><option>' . esc_html__( 'Something else', 'talkwyn' ) . '</option></select></label>'
			. '<label>' . esc_html__( 'Message', 'talkwyn' ) . '<textarea name="tw_message" rows="6" required maxlength="5000"></textarea></label>'
			. '<p class="tw-small">' . wp_kses_post( sprintf( /* translators: %s: privacy URL */ __( 'We use your details only to reply to you. See our <a href="%s">privacy policy</a>.', 'talkwyn' ), esc_url( home_url( '/privacy/' ) ) ) ) . '</p>'
			. '<p><button class="tw-btn" type="submit">' . esc_html__( 'Send message', 'talkwyn' ) . '</button></p></form>';
	}
);

add_shortcode(
	'tw_waitlist_form',
	static function () {
		$external = (string) talkwyn_setting( 'waitlist_action' );
		if ( '' !== $external ) {
			return '<form class="tw-inline-form" method="post" action="' . esc_url( $external ) . '"><label class="screen-reader-text" for="tw-waitlist-email">' . esc_html__( 'Email', 'talkwyn' ) . '</label><input id="tw-waitlist-email" type="email" name="email" autocomplete="email" required placeholder="you@store.com"><button class="tw-btn" type="submit">' . esc_html__( 'Join the waitlist', 'talkwyn' ) . '</button></form>';
		}
		return talkwyn_form_notice( 'waitlist' ) . '<form class="tw-inline-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '#waitlist" id="waitlist">'
			. talkwyn_form_hidden( 'waitlist' )
			. '<label class="screen-reader-text" for="tw-waitlist-email">' . esc_html__( 'Email', 'talkwyn' ) . '</label><input id="tw-waitlist-email" type="email" name="tw_email" autocomplete="email" required maxlength="190" placeholder="you@store.com">'
			. '<button class="tw-btn" type="submit">' . esc_html__( 'Join the waitlist', 'talkwyn' ) . '</button></form>';
	}
);

/**
 * Handle both forms (logged in or not).
 */
function talkwyn_handle_form(): void {
	$form = isset( $_POST['tw_form'] ) ? sanitize_key( wp_unslash( $_POST['tw_form'] ) ) : '';
	$back = isset( $_POST['tw_back'] ) ? esc_url_raw( wp_unslash( $_POST['tw_back'] ) ) : home_url( '/' );
	$go   = static function ( string $status ) use ( $form, $back ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'tw_form'   => $form,
					'tw_status' => $status,
				),
				wp_validate_redirect( $back, home_url( '/' ) )
			) . ( 'contact' === $form ? '#contact-form' : '#waitlist' )
		);
		exit;
	};
	if ( ! in_array( $form, array( 'contact', 'waitlist' ), true ) || ! isset( $_POST['_tw_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_tw_nonce'] ) ), 'talkwyn_form_' . $form ) ) {
		$go( 'failed' );
	}
	// Honeypot or submitted faster than a human could: pretend success.
	$started = absint( $_POST['tw_t'] ?? 0 );
	if ( ! empty( $_POST['tw_website'] ) || ( $started && time() - $started < 3 ) ) {
		$go( 'sent' );
	}
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$limit = 'tw_form_rl_' . md5( $ip . wp_salt() );
	$count = (int) get_transient( $limit );
	if ( $count >= 5 ) {
		$go( 'limited' );
	}
	set_transient( $limit, $count + 1, 15 * MINUTE_IN_SECONDS );

	$email = sanitize_email( wp_unslash( $_POST['tw_email'] ?? '' ) );
	if ( ! is_email( $email ) ) {
		$go( 'invalid' );
	}
	$to = talkwyn_value( 'contact_email' );

	if ( 'waitlist' === $form ) {
		$list = get_option( 'talkwyn_waitlist', array() );
		$list = is_array( $list ) ? $list : array();
		if ( ! isset( $list[ strtolower( $email ) ] ) ) {
			$list[ strtolower( $email ) ] = gmdate( 'c' );
			update_option( 'talkwyn_waitlist', $list, false );
			wp_mail( $to, '[Talkwyn] New Shopify waitlist sign-up', $email . "\n\n" . count( $list ) . ' people on the list.' );
		}
		$go( 'sent' );
	}

	$name    = sanitize_text_field( wp_unslash( $_POST['tw_name'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['tw_message'] ?? '' ) );
	$topic   = sanitize_text_field( wp_unslash( $_POST['tw_topic'] ?? '' ) );
	$site    = esc_url_raw( wp_unslash( $_POST['tw_site'] ?? '' ) );
	if ( '' === $name || '' === $message ) {
		$go( 'invalid' );
	}
	$body = "Name: {$name}\nEmail: {$email}\nWebsite: {$site}\nTopic: {$topic}\n\n{$message}\n";
	$sent = wp_mail( $to, '[Talkwyn contact] ' . $topic, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
	$go( $sent ? 'sent' : 'failed' );
}
add_action( 'admin_post_talkwyn_form', 'talkwyn_handle_form' );
add_action( 'admin_post_nopriv_talkwyn_form', 'talkwyn_handle_form' );

/**
 * Waitlist export under Tools.
 */
add_action(
	'admin_menu',
	static function () {
		add_management_page(
			__( 'Shopify waitlist', 'talkwyn' ),
			__( 'Shopify waitlist', 'talkwyn' ),
			'manage_options',
			'talkwyn-waitlist',
			static function () {
				$list = get_option( 'talkwyn_waitlist', array() );
				echo '<div class="wrap"><h1>' . esc_html__( 'Shopify waitlist', 'talkwyn' ) . '</h1><p>' . esc_html( sprintf( /* translators: %d: count */ _n( '%d sign-up', '%d sign-ups', count( (array) $list ), 'talkwyn' ), count( (array) $list ) ) ) . '</p><textarea class="large-text code" rows="20" readonly>';
				foreach ( (array) $list as $email => $date ) {
					echo esc_textarea( $email . ',' . $date ) . "\n";
				}
				echo '</textarea></div>';
			}
		);
	}
);
