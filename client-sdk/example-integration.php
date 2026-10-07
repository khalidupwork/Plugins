<?php
/**
 * Example: wiring Talkwyn_License_Client into the Talkwyn plugin.
 *
 * Copy class-talkwyn-license-client.php into the plugin (e.g. includes/) and
 * adapt the snippet below in the main plugin file.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-talkwyn-license-client.php';

/**
 * Shared client instance.
 */
function talkwyn_license(): Talkwyn_License_Client {
	static $client = null;
	if ( null === $client ) {
		$client = new Talkwyn_License_Client(
			array(
				'api_url'     => 'https://talkwyn.com/wp-json/talkwyn-hub/v1/',
				'product'     => 'talkwyn-pro',
				'plugin_file' => WP_PLUGIN_DIR . '/talkwyn/talkwyn.php', // Use __FILE__ from the main plugin file.
				'version'     => '1.0.0',                                  // Use your TALKWYN_VERSION constant.
				// Copy from Talkwyn Hub → Settings → Signing keys. List the "next" key too during a rotation.
				'public_keys' => array(
					'REPLACE_WITH_BASE64_PUBLIC_KEY=',
				),
				'manage_url'  => 'https://talkwyn.com/my-account/licenses/',
			)
		);
	}
	return $client;
}
add_action(
	'plugins_loaded',
	static function () {
		talkwyn_license()->init();
	}
);

// Gate Pro features.
add_action(
	'init',
	static function () {
		if ( talkwyn_license()->is_pro() ) {
			do_action( 'talkwyn_load_pro' ); // Load Pro modules.
		}
		if ( talkwyn_license()->has_feature( 'white_label' ) ) {
			add_filter( 'talkwyn_show_branding', '__return_false' ); // Feature-flagged behavior.
		}
		if ( in_array( talkwyn_license()->get_plan(), array( 'business', 'agency' ), true ) ) {
			add_filter( 'talkwyn_max_bots', static fn() => 10 ); // Plan-specific limit.
		}
	}
);

// Render the license box on your settings page.
add_action(
	'talkwyn_settings_license_tab', // Replace with your own settings page hook.
	static function () {
		talkwyn_license()->render_settings();
	}
);
