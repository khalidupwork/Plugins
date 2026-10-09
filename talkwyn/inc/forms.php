<?php
/**
 * Lightweight forms: contact and waitlist (see waitlist.php). No form plugin needed.
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
	$status  = isset( $_GET['tw_form'], $_GET['tw_status'] ) && $form === $_GET['tw_form'] ? sanitize_key( wp_unslash( $_GET['tw_status'] ) ) : '';
	$message = talkwyn_form_message( $form, $status );
	if ( ! $message ) {
		return '';
	}
	return '<p class="tw-notice tw-notice--' . esc_attr( $message[0] ) . '" role="status">' . esc_html( $message[1] ) . '</p>';
}

/**
 * Message for a form status.
 *
 * @param string $form   Form id.
 * @param string $status Status code.
 * @return array{0: string, 1: string}|null Type (success|error) and text.
 */
function talkwyn_form_message( string $form, string $status ): ?array {
	$messages = array(
		'sent'    => array( 'success', 'contact' === $form ? __( 'Thanks. Your message is on its way, and we will reply by email soon.', 'talkwyn' ) : __( 'You are on the list. Check your inbox for a confirmation, and we will email you the day it launches.', 'talkwyn' ) ),
		'invalid' => array( 'error', __( 'Please check the form. A valid email address is required.', 'talkwyn' ) ),
		'limited' => array( 'error', __( 'Too many attempts. Please try again in a few minutes.', 'talkwyn' ) ),
		'failed'  => array( 'error', __( 'Something went wrong. Please email us directly instead.', 'talkwyn' ) ),
	);
	return $messages[ $status ] ?? null;
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
		return talkwyn_form_notice( 'contact' ) . '<form class="tw-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '#contact-form" id="contact-form" data-tw-ajax="contact">'
			. talkwyn_form_hidden( 'contact' )
			. '<label>' . esc_html__( 'Your name', 'talkwyn' ) . '<input type="text" name="tw_name" autocomplete="name" required maxlength="100"></label>'
			. '<label>' . esc_html__( 'Email', 'talkwyn' ) . '<input type="email" name="tw_email" autocomplete="email" required maxlength="190"></label>'
			. '<label>' . esc_html__( 'Website (optional)', 'talkwyn' ) . '<input type="url" name="tw_site" autocomplete="url" maxlength="190" placeholder="https://"></label>'
			. '<label>' . esc_html__( 'Topic', 'talkwyn' ) . '<select name="tw_topic"><option>' . esc_html__( 'Question before buying', 'talkwyn' ) . '</option><option>' . esc_html__( 'Help with my license or setup', 'talkwyn' ) . '</option><option>' . esc_html__( 'Agency or partnership', 'talkwyn' ) . '</option><option>' . esc_html__( 'Something else', 'talkwyn' ) . '</option></select></label>'
			. '<label>' . esc_html__( 'Message', 'talkwyn' ) . '<textarea name="tw_message" rows="6" required maxlength="5000"></textarea></label>'
			. '<p class="tw-small">' . wp_kses_post( sprintf( /* translators: %s: privacy URL */ __( 'We use your details only to reply to you. See our <a href="%s">privacy policy</a>.', 'talkwyn' ), esc_url( home_url( '/privacy/' ) ) ) ) . '</p>'
			. '<p><button class="tw-pill tw-pill--ink" type="submit">' . esc_html__( 'Send message', 'talkwyn' ) . '</button></p></form>';
	}
);

/**
 * Handle both forms (logged in or not).
 */
function talkwyn_handle_form(): void {
	$form = isset( $_POST['tw_form'] ) ? sanitize_key( wp_unslash( $_POST['tw_form'] ) ) : '';
	$back = isset( $_POST['tw_back'] ) ? esc_url_raw( wp_unslash( $_POST['tw_back'] ) ) : home_url( '/' );
	$go   = static function ( string $status ) use ( $form, $back ) {
		if ( ! empty( $_POST['tw_ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only picks the response format.
			$message = talkwyn_form_message( $form, $status );
			wp_send_json(
				array(
					'success' => 'sent' === $status,
					'type'    => $message ? $message[0] : 'error',
					'message' => $message ? $message[1] : '',
				)
			);
		}
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
		talkwyn_waitlist_add( $email, sanitize_text_field( wp_unslash( $_POST['tw_platform'] ?? '' ) ), esc_url_raw( wp_unslash( $_POST['tw_site'] ?? '' ) ) );
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

/**
 * Fresh nonce for a site form (cached pages can hold one that has expired).
 */
function talkwyn_form_nonce(): void {
	$form = isset( $_GET['form'] ) ? sanitize_key( wp_unslash( $_GET['form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- returns a nonce, changes nothing.
	nocache_headers();
	if ( ! in_array( $form, array( 'contact', 'waitlist' ), true ) ) {
		wp_send_json_error();
	}
	wp_send_json_success( array( 'nonce' => wp_create_nonce( 'talkwyn_form_' . $form ) ) );
}
add_action( 'wp_ajax_talkwyn_form_nonce', 'talkwyn_form_nonce' );
add_action( 'wp_ajax_nopriv_talkwyn_form_nonce', 'talkwyn_form_nonce' );
add_action( 'admin_post_nopriv_talkwyn_form', 'talkwyn_handle_form' );
