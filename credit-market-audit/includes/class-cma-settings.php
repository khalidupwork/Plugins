<?php
/**
 * Plugin settings access.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and normalises the single `cma_settings` option.
 */
class CMA_Settings {

	const OPTION = 'cma_settings';

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'psi_api_key'         => '',
			'brand_name'          => get_bloginfo( 'name' ),
			'brand_logo'          => '',
			'brand_color'         => '#2563eb',
			'from_name'           => get_bloginfo( 'name' ),
			'from_email'          => get_option( 'admin_email' ),
			'send_user_email'     => 1,
			'attach_report'       => 1,
			'email_subject'       => __( 'Your free website audit report for {domain}', 'credit-market-audit' ),
			'email_intro'         => __( 'Thanks for requesting a free website audit. Here is a summary of how your website performs. The full report is attached and also available online.', 'credit-market-audit' ),
			'admin_notify'        => 1,
			'admin_email'         => get_option( 'admin_email' ),
			'cta_text'            => __( 'Book a free consultation', 'credit-market-audit' ),
			'cta_url'             => home_url( '/contact/' ),
			'cta_message'         => __( 'Want us to fix these issues for you? Our team can help you improve speed, SEO and design.', 'credit-market-audit' ),
			'report_footer'       => '',
			'rate_limit'          => 5,
			'require_consent'     => 1,
			'consent_text'        => __( 'I agree to receive my audit report and occasional emails.', 'credit-market-audit' ),
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$out      = array();

		$text_fields = array( 'psi_api_key', 'brand_name', 'from_name', 'email_subject', 'cta_text' );
		foreach ( $text_fields as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : $defaults[ $key ];
		}

		$textarea_fields = array( 'email_intro', 'cta_message', 'report_footer', 'consent_text' );
		foreach ( $textarea_fields as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? wp_kses_post( wp_unslash( $input[ $key ] ) ) : $defaults[ $key ];
		}

		$out['brand_logo'] = isset( $input['brand_logo'] ) ? esc_url_raw( wp_unslash( $input['brand_logo'] ) ) : '';
		$out['cta_url']    = isset( $input['cta_url'] ) ? esc_url_raw( wp_unslash( $input['cta_url'] ) ) : '';

		$color              = isset( $input['brand_color'] ) ? sanitize_hex_color( wp_unslash( $input['brand_color'] ) ) : '';
		$out['brand_color'] = $color ? $color : $defaults['brand_color'];

		foreach ( array( 'from_email', 'admin_email' ) as $key ) {
			$email       = isset( $input[ $key ] ) ? sanitize_email( wp_unslash( $input[ $key ] ) ) : '';
			$out[ $key ] = is_email( $email ) ? $email : $defaults[ $key ];
		}

		foreach ( array( 'send_user_email', 'attach_report', 'admin_notify', 'require_consent', 'delete_on_uninstall' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$out['rate_limit'] = isset( $input['rate_limit'] ) ? max( 0, absint( $input['rate_limit'] ) ) : $defaults['rate_limit'];

		return $out;
	}
}
