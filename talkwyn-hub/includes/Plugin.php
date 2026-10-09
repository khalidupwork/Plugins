<?php
/**
 * Plugin bootstrap.
 *
 * @package TalkwynHub
 */

namespace TWH;

use TWH\Install\Installer;
use TWH\Support\Secrets;

defined( 'ABSPATH' ) || exit;

/**
 * Wires all services to WordPress hooks.
 */
final class Plugin {

	/**
	 * Boot once.
	 */
	public static function boot(): void {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		load_plugin_textdomain( 'talkwyn-hub', false, dirname( TWH_BASENAME ) . '/languages' );

		if ( ! extension_loaded( 'sodium' ) ) {
			add_action( 'admin_notices', array( self::class, 'notice_sodium' ) );
			return;
		}

		Installer::maybe_upgrade();
		if ( ! Secrets::maybe_migrate() ) {
			add_action( 'admin_notices', array( self::class, 'notice_secret_mismatch' ) );
		}

		Api\RestController::init();
		Api\Subscribe::init();
		Cron\Daily::init();

		if ( class_exists( 'WooCommerce' ) ) {
			Woo\ProductTab::init();
			Woo\OrderHandler::init();
			Woo\Subscriptions::init();
			Woo\Cart::init();
			Trial\Trial::init();
			Partners\Program::init();
			Woo\OrderDisplay::init();
			Woo\Invoices::init();
			Account\Account::init();
			Account\App::init();
		} else {
			add_action( 'admin_notices', array( self::class, 'notice_woocommerce' ) );
		}

		if ( is_admin() ) {
			Admin\Admin::init();
			add_action( 'admin_notices', array( self::class, 'notice_secret' ) );
		}
	}

	/**
	 * Missing WooCommerce.
	 */
	public static function notice_woocommerce(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Talkwyn Hub needs WooCommerce to sell and issue licenses. The license API keeps working for existing licenses.', 'talkwyn-hub' ) . '</p></div>';
	}

	/**
	 * Missing sodium.
	 */
	public static function notice_sodium(): void {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Talkwyn Hub requires the PHP sodium extension and is currently inactive.', 'talkwyn-hub' ) . '</p></div>';
	}

	/**
	 * Secret changed and data cannot be decrypted.
	 */
	public static function notice_secret_mismatch(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Talkwyn Hub:', 'talkwyn-hub' ) . '</strong> ' . esc_html__( 'TWH_SECRET_KEY (or your WordPress auth salt) has changed since license data was encrypted. Stored keys and the signing key cannot be decrypted. Restore the previous value in wp-config.php.', 'talkwyn-hub' ) . '</p></div>';
	}

	/**
	 * Secret constant missing.
	 */
	public static function notice_secret(): void {
		if ( Secrets::has_constant() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false === strpos( (string) $screen->id, 'twh' ) && 'dashboard' !== $screen->id && 'plugins' !== $screen->id ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Talkwyn Hub:', 'talkwyn-hub' ) . '</strong> ';
		printf(
			/* translators: %s: PHP code */
			esc_html__( 'Add %s to wp-config.php. Until then, license keys and signing keys are encrypted with a key derived from your WordPress auth salt, which breaks if the salts are rotated. When you add the constant, existing data is re-encrypted automatically. Never change or remove it afterwards.', 'talkwyn-hub' ),
			'<code>define( \'TWH_SECRET_KEY\', \'' . esc_html( bin2hex( random_bytes( 32 ) ) ) . '\' );</code>'
		);
		echo '</p></div>';
	}
}
