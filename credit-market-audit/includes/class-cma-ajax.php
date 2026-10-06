<?php
/**
 * AJAX endpoints for the audit form.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles `cma_audit` requests. The browser calls it once per step:
 * start → mobile → desktop → finalize.
 */
class CMA_Ajax {

	const ACTION = 'cma_audit';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Router.
	 */
	public static function handle() {
		if ( ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			self::error( __( 'Your session has expired. Please refresh the page and try again.', 'credit-market-audit' ), 403 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 150 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : 'start';

		if ( 'start' === $step ) {
			self::start();
		}

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$audit = CMA_Repository::get_by_token( $token );
		if ( ! $audit ) {
			self::error( __( 'Audit not found. Please start again.', 'credit-market-audit' ), 404 );
		}

		$audit = CMA_Audit::run_step( $audit, $step );
		if ( is_wp_error( $audit ) ) {
			self::error( $audit->get_error_message() );
		}

		$index = array_search( $step, CMA_Audit::STEPS, true );
		$next  = isset( CMA_Audit::STEPS[ $index + 1 ] ) ? CMA_Audit::STEPS[ $index + 1 ] : null;

		if ( null !== $next ) {
			wp_send_json_success(
				array(
					'token' => $audit['token'],
					'next'  => $next,
				)
			);
		}

		wp_send_json_success(
			array(
				'token'        => $audit['token'],
				'next'         => null,
				'score'        => (int) $audit['overall_score'],
				'email_sent'   => (bool) $audit['email_sent'],
				'email'        => $audit['email'],
				'html'         => CMA_Report::render( $audit ),
				'view_url'     => CMA_Report::view_url( $audit['token'] ),
				'print_url'    => CMA_Report::view_url( $audit['token'], array( 'cma_print' => 1 ) ),
				'download_url' => CMA_Report::download_url( $audit['token'] ),
			)
		);
	}

	/**
	 * Validate the form and run the first step.
	 */
	private static function start() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle().

		// Honeypot: bots fill every field.
		if ( ! empty( $_POST['cma_hp'] ) ) {
			self::error( __( 'Something went wrong. Please try again.', 'credit-market-audit' ) );
		}

		$settings = CMA_Settings::all();
		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$url      = isset( $_POST['url'] ) ? self::normalize_url( sanitize_text_field( wp_unslash( $_POST['url'] ) ) ) : '';
		// The checkbox is only enforced when the form actually displayed it.
		$consent_missing = ! empty( $_POST['consent_shown'] ) && empty( $_POST['consent'] );
		// phpcs:enable

		if ( ! is_email( $email ) ) {
			self::error( __( 'Please enter a valid email address.', 'credit-market-audit' ) );
		}
		if ( ! $url ) {
			self::error( __( 'Please enter a valid website URL, e.g. https://example.com', 'credit-market-audit' ) );
		}
		if ( $consent_missing ) {
			self::error( __( 'Please accept the consent checkbox to receive your report.', 'credit-market-audit' ) );
		}

		$ip    = self::ip();
		$limit = (int) $settings['rate_limit'];
		if ( $limit > 0 && ! current_user_can( 'manage_options' ) && CMA_Repository::count_recent_by_ip( $ip ) >= $limit ) {
			self::error( __( 'You have reached the maximum number of free audits for now. Please try again in an hour.', 'credit-market-audit' ), 429 );
		}

		/**
		 * Filter the lead before the audit starts. Return WP_Error to reject.
		 *
		 * @param array $lead name, email, url, ip.
		 */
		$lead = apply_filters(
			'cma_audit_lead',
			array(
				'name'  => $name,
				'email' => $email,
				'url'   => $url,
				'ip'    => $ip,
			)
		);
		if ( is_wp_error( $lead ) ) {
			self::error( $lead->get_error_message() );
		}

		$audit = CMA_Audit::start( $lead );
		if ( is_wp_error( $audit ) ) {
			self::error( $audit->get_error_message() );
		}

		do_action( 'cma_audit_started', $audit );

		wp_send_json_success(
			array(
				'token' => $audit['token'],
				'next'  => 'mobile',
			)
		);
	}

	/**
	 * Add scheme if missing and validate.
	 *
	 * @param string $url Raw URL.
	 * @return string Empty if invalid.
	 */
	public static function normalize_url( $url ) {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}
		$url  = esc_url_raw( $url, array( 'http', 'https' ) );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $url || ! $host || false === strpos( $host, '.' ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Visitor IP (REMOTE_ADDR only; forwarded headers are spoofable).
	 *
	 * @return string
	 */
	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( 'cma_client_ip', filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '' );
	}

	/**
	 * Send a JSON error and exit.
	 *
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 */
	private static function error( $message, $status = 400 ) {
		wp_send_json_error( array( 'message' => $message ), $status );
	}
}
