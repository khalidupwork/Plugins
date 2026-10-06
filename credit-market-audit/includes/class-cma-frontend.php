<?php
/**
 * Front-end form: assets + shortcode.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers assets and the [credit_market_audit] shortcode. The Elementor widget reuses render_form().
 */
class CMA_Frontend {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'credit_market_audit', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Register (not enqueue) CSS/JS so they only load where the form is used.
	 */
	public static function register_assets() {
		wp_register_style( 'cma-report', CMA_URL . 'assets/css/cma-report.css', array(), CMA_VERSION );
		wp_register_style( 'cma-frontend', CMA_URL . 'assets/css/cma-frontend.css', array( 'cma-report' ), CMA_VERSION );
		wp_register_script( 'cma-frontend', CMA_URL . 'assets/js/cma-frontend.js', array(), CMA_VERSION, true );

		wp_localize_script(
			'cma-frontend',
			'CMA_AUDIT',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => CMA_Ajax::ACTION,
				'nonce'   => wp_create_nonce( CMA_Ajax::ACTION ),
				'i18n'    => array(
					'start'      => __( 'Fetching your website and checking SEO & design…', 'credit-market-audit' ),
					'mobile'     => __( 'Running Google PageSpeed test (mobile)…', 'credit-market-audit' ),
					'desktop'    => __( 'Running Google PageSpeed test (desktop)…', 'credit-market-audit' ),
					'finalize'   => __( 'Building your report and sending the email…', 'credit-market-audit' ),
					'done'       => __( 'Your report is ready!', 'credit-market-audit' ),
					/* translators: %s: email address */
					'emailSent'  => __( 'A copy has been sent to %s.', 'credit-market-audit' ),
					'emailFail'  => __( 'We could not email the report, but you can download it below.', 'credit-market-audit' ),
					'download'   => CMA_PDF::available() ? __( 'Download PDF', 'credit-market-audit' ) : __( 'Download report', 'credit-market-audit' ),
					'pdf'        => __( 'Print', 'credit-market-audit' ),
					'view'       => __( 'Open online', 'credit-market-audit' ),
					'again'      => __( 'Audit another website', 'credit-market-audit' ),
					'error'      => __( 'Something went wrong. Please try again.', 'credit-market-audit' ),
					'timeout'    => __( 'The request took too long. Please try again.', 'credit-market-audit' ),
					'invalidEmail' => __( 'Please enter a valid email address.', 'credit-market-audit' ),
					'invalidUrl'   => __( 'Please enter a valid website URL, e.g. https://example.com', 'credit-market-audit' ),
					'consent'      => __( 'Please accept the consent checkbox to receive your report.', 'credit-market-audit' ),
				),
			)
		);
	}

	/**
	 * Default form arguments.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'heading'           => __( 'Get Your Free Website Audit', 'credit-market-audit' ),
			'heading_tag'       => 'h3',
			'subheading'        => __( 'Find out how fast your website is, how well it ranks on Google and how good it looks — in under a minute.', 'credit-market-audit' ),
			'show_name'         => 'no',
			'name_placeholder'  => __( 'Your name', 'credit-market-audit' ),
			'email_placeholder' => __( 'Your email address', 'credit-market-audit' ),
			'url_placeholder'   => __( 'Your website URL (e.g. example.com)', 'credit-market-audit' ),
			'button_text'       => __( 'Get My Free Report', 'credit-market-audit' ),
			'show_consent'      => 'no',
			'consent_text'      => CMA_Settings::get( 'consent_text' ),
			'layout'            => 'stacked',
			'theme'             => 'dark',
			'class'             => '',
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( self::defaults(), $atts, 'credit_market_audit' );
		return self::render_form( $atts );
	}

	/**
	 * Render the audit form.
	 *
	 * @param array $args See defaults().
	 * @return string
	 */
	public static function render_form( array $args = array() ) {
		$args = wp_parse_args( $args, self::defaults() );

		wp_enqueue_style( 'cma-frontend' );
		wp_enqueue_script( 'cma-frontend' );

		$args['id']           = wp_unique_id( 'cma-audit-' );
		$args['show_name']    = self::truthy( $args['show_name'] );
		$args['show_consent'] = self::truthy( $args['show_consent'] );
		$args['layout']       = 'inline' === $args['layout'] ? 'inline' : 'stacked';
		$args['theme']        = 'light' === $args['theme'] ? 'light' : 'dark';
		$args['heading_tag']  = in_array( $args['heading_tag'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ), true ) ? $args['heading_tag'] : 'h3';
		$brand                = CMA_Settings::brand();
		$accent               = $brand['color'];
		$on_accent            = $brand['on_color'];

		ob_start();
		include CMA_Report::template( 'form.php' );
		return ob_get_clean();
	}

	/**
	 * Interpret yes/no/1/0/true/false.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function truthy( $value ) {
		return in_array( strtolower( (string) $value ), array( 'yes', '1', 'true', 'on' ), true ) || true === $value;
	}
}
