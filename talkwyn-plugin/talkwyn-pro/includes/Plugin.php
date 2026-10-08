<?php
/**
 * Boot: checks the free plugin, starts the license client, loads Pro modules.
 *
 * @package TalkwynPro
 */

namespace TalkwynPro;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap.
 */
final class Plugin {

	/**
	 * Boot on plugins_loaded.
	 */
	public static function boot(): void {
		if ( ! class_exists( '\Talkwyn_Settings' ) || ! defined( 'TALKWYN_VERSION' ) ) {
			add_action( 'admin_notices', array( self::class, 'missing_free_notice' ) );
			return;
		}

		License::client()->init();
		Settings::init();
		Installer::maybe_upgrade();
		Admin::init();

		add_filter( 'talkwyn_pro_active', array( License::class, 'active' ) );

		if ( ! License::active() ) {
			// Free features keep working; Pro data and settings are kept.
			return;
		}

		Providers::init();
		Search::init();
		Knowledge::init();
		Stream::init();
		Insights::init();
		Woo::init();
		Alerts::init();
		Engage::init();
		WhiteLabel::init();
		Transfer::init();
		Frontend::init();

		/**
		 * Fires after Pro modules loaded.
		 */
		do_action( 'talkwyn_pro_loaded' );
	}

	/**
	 * Notice when the free plugin is missing.
	 */
	public static function missing_free_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Talkwyn Pro needs the free Talkwyn plugin.', 'talkwyn-pro' ) . '</strong> ' . esc_html__( 'Install and activate Talkwyn from Plugins, Add New, then Pro switches on.', 'talkwyn-pro' ) . '</p></div>';
	}
}
