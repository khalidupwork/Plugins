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
			'brand_color'         => '',
			'brand_dark'          => '',
			'report_mode'         => 'quick',
			'max_issues'          => 5,
			'from_name'           => get_bloginfo( 'name' ),
			'from_email'          => get_option( 'admin_email' ),
			'send_user_email'     => 1,
			'attach_report'       => 1,
			'email_subject'       => __( 'Your free website audit report for {domain}', 'credit-market-audit' ),
			'email_intro'         => __( 'Thanks for requesting a free website audit. Below is a short summary with the quickest wins for your website. Your PDF report is attached and also available online.', 'credit-market-audit' ),
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

		// Empty colour = auto-detect from the site (Elementor global colours).
		foreach ( array( 'brand_color', 'brand_dark' ) as $key ) {
			$color       = isset( $input[ $key ] ) ? sanitize_hex_color( wp_unslash( $input[ $key ] ) ) : '';
			$out[ $key ] = $color ? $color : '';
		}

		$out['report_mode'] = isset( $input['report_mode'] ) && 'full' === $input['report_mode'] ? 'full' : 'quick';
		$out['max_issues']  = isset( $input['max_issues'] ) ? min( 15, max( 3, absint( $input['max_issues'] ) ) ) : $defaults['max_issues'];

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

	/**
	 * Resolved branding: explicit settings first, then this site's own logo / Elementor colours.
	 *
	 * Because the plugin runs on the company's own WordPress site, the report automatically
	 * matches the website (custom logo, site icon, Elementor global colours).
	 *
	 * @return array{name: string, logo: string, color: string, dark: string, on_color: string}
	 */
	public static function brand() {
		static $brand = null;
		if ( null !== $brand ) {
			return $brand;
		}

		$settings = self::all();
		$kit      = self::elementor_kit_colors();

		$logo = $settings['brand_logo'];
		if ( ! $logo ) {
			$logo = self::site_logo_url();
		}

		$color = $settings['brand_color'];
		if ( ! $color ) {
			foreach ( array( 'primary', 'accent', 'secondary' ) as $id ) {
				// Skip near-white / near-black colours: they don't work as a header or button colour.
				if ( ! empty( $kit[ $id ] ) && self::luminance( $kit[ $id ] ) < 0.8 && self::luminance( $kit[ $id ] ) > 0.03 ) {
					$color = $kit[ $id ];
					break;
				}
			}
		}
		if ( ! $color ) {
			$color = '#2563eb';
		}

		$dark = $settings['brand_dark'];
		if ( ! $dark ) {
			foreach ( array( 'secondary', 'text', 'primary' ) as $id ) {
				if ( ! empty( $kit[ $id ] ) && self::luminance( $kit[ $id ] ) < 0.12 ) {
					$dark = $kit[ $id ];
					break;
				}
			}
		}
		if ( ! $dark ) {
			$dark = '#0f172a';
		}

		$brand = apply_filters(
			'cma_brand',
			array(
				'name'     => $settings['brand_name'] ? $settings['brand_name'] : get_bloginfo( 'name' ),
				'logo'     => $logo,
				'color'    => $color,
				'dark'     => $dark,
				// Text colour that is readable on top of the brand colour.
				'on_color' => self::luminance( $color ) > 0.3 ? '#0f172a' : '#ffffff',
			)
		);
		return $brand;
	}

	/**
	 * Logo of this WordPress site: theme custom logo → Elementor site logo → site icon.
	 *
	 * @return string
	 */
	public static function site_logo_url() {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}

		$kit_settings = self::elementor_kit_settings();
		if ( ! empty( $kit_settings['site_logo']['url'] ) ) {
			return $kit_settings['site_logo']['url'];
		}

		$icon = get_site_icon_url( 512 );
		return $icon ? $icon : '';
	}

	/**
	 * Elementor global ("system") colours keyed by id: primary, secondary, text, accent.
	 *
	 * @return array
	 */
	public static function elementor_kit_colors() {
		$colors   = array();
		$settings = self::elementor_kit_settings();
		if ( ! empty( $settings['system_colors'] ) && is_array( $settings['system_colors'] ) ) {
			foreach ( $settings['system_colors'] as $item ) {
				if ( isset( $item['_id'], $item['color'] ) ) {
					$hex = self::normalize_hex( $item['color'] );
					if ( $hex ) {
						$colors[ $item['_id'] ] = $hex;
					}
				}
			}
		}
		return $colors;
	}

	/**
	 * Active Elementor kit settings (empty when Elementor isn't used).
	 *
	 * @return array
	 */
	private static function elementor_kit_settings() {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) {
			return array();
		}
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Normalise #rgb / #rrggbb / #rrggbbaa to #rrggbb.
	 *
	 * @param string $color Colour.
	 * @return string Empty when not a hex colour.
	 */
	public static function normalize_hex( $color ) {
		$color = strtolower( trim( (string) $color ) );
		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m ) ) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}
		if ( preg_match( '/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $color, $m ) ) {
			return '#' . $m[1];
		}
		return '';
	}

	/**
	 * Relative luminance 0 (black) – 1 (white).
	 *
	 * @param string $hex Colour.
	 * @return float
	 */
	public static function luminance( $hex ) {
		$hex = self::normalize_hex( $hex );
		if ( ! $hex ) {
			return 0.0;
		}
		$rgb = array();
		foreach ( array( 1, 3, 5 ) as $i ) {
			$c     = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$rgb[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
	}
}
