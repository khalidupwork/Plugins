<?php
/**
 * WooCommerce emails in the Talkwyn look: logo, Talkwyn Red, Linen background,
 * Inter, and a footer without the "Built with WooCommerce" line.
 *
 * The colours and logo are WooCommerce's own email settings (WooCommerce, Settings,
 * Emails), set once by a button, so they can still be changed there. The font has no
 * setting, so it is added to the email styles while the branding is on.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

use TWH\Admin\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Brand the WooCommerce emails.
 */
final class EmailBrand {

	public const OPTION = 'twh_wc_email_branded';

	/**
	 * WooCommerce email settings and their Talkwyn values.
	 *
	 * @return array<string, string>
	 */
	public static function values(): array {
		return array(
			'woocommerce_email_header_image'          => TWH_URL . 'assets/img/email-logo-wc.png',
			'woocommerce_email_base_color'            => '#D7263D',
			'woocommerce_email_background_color'      => '#F7F3F3',
			'woocommerce_email_body_background_color' => '#FFFFFF',
			'woocommerce_email_text_color'            => '#1A0F12',
			'woocommerce_email_footer_text_color'     => '#6B5E61',
			'woocommerce_email_footer_text'           => '{site_title} · {site_url}',
		);
	}

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_twh_brand_emails', array( self::class, 'handle' ) );
		add_filter( 'woocommerce_email_styles', array( self::class, 'styles' ), 20 );
	}

	/**
	 * Whether the WooCommerce emails use the Talkwyn values.
	 */
	public static function is_branded(): bool {
		return (bool) get_option( self::OPTION );
	}

	/**
	 * Inter, with system fallbacks (email apps that cannot load web fonts use those).
	 *
	 * @param string $css WooCommerce email CSS.
	 */
	public static function styles( $css ): string {
		if ( ! self::is_branded() ) {
			return (string) $css;
		}
		$font = "Inter, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
		return (string) $css . "\n#wrapper, #template_container, #body_content, #body_content_inner, #body_content td, #body_content p, #body_content li, h1, h2, h3, #template_footer td, .td, .address { font-family: {$font} !important; }\n"
			. "h1 { font-weight: 700; letter-spacing: -0.02em; }\nh2, h3 { font-weight: 600; }\n#template_header_image img { max-width: 180px; height: auto; }\n";
	}

	/**
	 * Card section on the Software products page (inside Store setup).
	 */
	public static function render(): void {
		echo '<hr style="margin:18px 0"><h3 style="margin:0 0 6px">' . esc_html__( 'WooCommerce emails', 'talkwyn-hub' ) . '</h3>';
		if ( self::is_branded() ) {
			echo '<p>' . esc_html__( 'Order emails (receipts, refunds, password resets) use the Talkwyn logo, colours and Inter. Change them any time under WooCommerce, Settings, Emails.', 'talkwyn-hub' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html__( 'Order emails (receipts, refunds, password resets) still use the WooCommerce look. This sets the Talkwyn logo, Talkwyn Red, the Linen background, Inter, and a footer with your site name instead of "Built with WooCommerce". Your license, trial and partner emails already use the Talkwyn look.', 'talkwyn-hub' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="twh_brand_emails">';
		wp_nonce_field( 'twh_brand_emails' );
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Use the Talkwyn look for WooCommerce emails', 'talkwyn-hub' ) . '</button></form>';
	}

	/**
	 * Apply the values.
	 */
	public static function handle(): void {
		Admin::guard( 'twh_brand_emails' );
		foreach ( self::values() as $option => $value ) {
			update_option( $option, $value );
		}
		update_option( self::OPTION, 1, false );
		Admin::redirect( 'twh-products', 'emails_branded' );
	}
}
