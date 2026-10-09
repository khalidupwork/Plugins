<?php
/**
 * Spam check (Cloudflare Turnstile) for every site form: contact, waitlist,
 * free trial (Talkwyn Hub) and the WooCommerce login, register and lost
 * password forms. Set the keys and pick the forms under Appearance, Talkwyn
 * Site Settings, Spam protection.
 *
 * The widget script loads only when a form with a check is on screen.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Forms that can carry the check, setting key => label.
 *
 * @return array<string, string>
 */
function talkwyn_captcha_forms(): array {
	return array(
		'captcha_contact'  => __( 'Contact form', 'talkwyn' ),
		'captcha_waitlist' => __( 'Waitlist forms', 'talkwyn' ),
		'captcha_trial'    => __( 'Free trial form and popup (Talkwyn Hub)', 'talkwyn' ),
		'captcha_account'  => __( 'Log in, register and lost password', 'talkwyn' ),
	);
}

/**
 * Whether the check runs on a form.
 *
 * @param string $form contact, waitlist, trial or account.
 */
function talkwyn_captcha_on( string $form ): bool {
	if ( '' === (string) talkwyn_setting( 'turnstile_site_key' ) || '' === (string) talkwyn_setting( 'turnstile_secret' ) ) {
		return false;
	}
	return (bool) talkwyn_setting( 'captcha_' . $form );
}

/**
 * Widget placeholder. theme.js renders it when the form is visible.
 *
 * @param string $form Form id.
 */
function talkwyn_captcha_field( string $form ): string {
	if ( ! talkwyn_captcha_on( $form ) ) {
		return '';
	}
	return '<div class="tw-captcha" data-action="' . esc_attr( $form ) . '"></div>';
}

/**
 * Check the token sent with a form.
 *
 * @param string $form Form id.
 * @return bool True when the check passed or is off for this form.
 */
function talkwyn_captcha_verify( string $form ): bool {
	if ( ! talkwyn_captcha_on( $form ) ) {
		return true;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- each form handler checks its own nonce.
	$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
	if ( '' === $token ) {
		return false;
	}
	$res = wp_remote_post(
		'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => (string) talkwyn_setting( 'turnstile_secret' ),
				'response' => $token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
		)
	);
	// If Cloudflare cannot be reached, let the form through: the honeypot, fill time and rate limit still apply.
	if ( is_wp_error( $res ) ) {
		return true;
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	return is_array( $data ) && ! empty( $data['success'] );
}

/**
 * Site key for theme.js. The script itself is added by theme.js on demand.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$key = (string) talkwyn_setting( 'turnstile_site_key' );
		if ( '' === $key || '' === (string) talkwyn_setting( 'turnstile_secret' ) ) {
			return;
		}
		wp_add_inline_script(
			'talkwyn',
			'window.twCaptcha=' . wp_json_encode(
				array(
					'key' => $key,
					'src' => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=twCaptchaReady',
				)
			) . ';',
			'before'
		);
	},
	20
);

// Free trial form from Talkwyn Hub.
add_filter(
	'twh_trial_form_extra',
	static function ( $html ) {
		return (string) $html . talkwyn_captcha_field( 'trial' );
	}
);
add_filter(
	'twh_trial_verify',
	static function ( $ok ) {
		return true === $ok ? talkwyn_captcha_verify( 'trial' ) : $ok;
	}
);

// WooCommerce log in, register and lost password.
foreach ( array( 'woocommerce_login_form', 'woocommerce_register_form', 'woocommerce_lostpassword_form' ) as $talkwyn_hook ) {
	add_action(
		$talkwyn_hook,
		static function () {
			echo talkwyn_captcha_field( 'account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in talkwyn_captcha_field().
		}
	);
}
add_filter(
	'woocommerce_process_login_errors',
	static function ( $errors ) {
		if ( ! talkwyn_captcha_verify( 'account' ) ) {
			$errors->add( 'talkwyn_captcha', talkwyn_captcha_message() );
		}
		return $errors;
	}
);
add_filter(
	'woocommerce_process_registration_errors',
	static function ( $errors ) {
		if ( ! talkwyn_captcha_verify( 'account' ) ) {
			$errors->add( 'talkwyn_captcha', talkwyn_captcha_message() );
		}
		return $errors;
	}
);
add_action(
	'lostpassword_post',
	static function ( $errors ) {
		// Only the WooCommerce form carries the widget; wp-login.php is left alone.
		if ( isset( $_POST['wc_reset_password'] ) && ! talkwyn_captcha_verify( 'account' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checks the nonce.
			$errors->add( 'talkwyn_captcha', talkwyn_captcha_message() );
		}
	}
);

/**
 * Error text when the check fails.
 */
function talkwyn_captcha_message(): string {
	return __( 'Please complete the spam check and try again.', 'talkwyn' );
}
