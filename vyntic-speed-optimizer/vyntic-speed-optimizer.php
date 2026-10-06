<?php
/**
 * Plugin Name:       Vyntic Speed Optimizer
 * Plugin URI:        https://github.com/khalidupwork/Plugins
 * Description:       All-in-one, 100% local speed optimizer — no account, no login, no connect. Page cache, delay JavaScript until user interaction, remove unused CSS, lazy load, WebP, font optimization and more to push PageSpeed scores into the green.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Vyntic
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vyntic-speed-optimizer
 * Domain Path:       /languages
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

define( 'VSO_VERSION', '1.0.0' );
define( 'VSO_FILE', __FILE__ );
define( 'VSO_PATH', plugin_dir_path( __FILE__ ) );
define( 'VSO_URL', plugin_dir_url( __FILE__ ) );
define( 'VSO_CACHE_DIR', WP_CONTENT_DIR . '/cache/vyntic/' );
define( 'VSO_CACHE_URL', content_url( '/cache/vyntic/' ) );

// Bundled MIT-licensed minifier (matthiasmullie/minify + path-converter).
spl_autoload_register(
	static function ( $class ) {
		$map = array(
			'MatthiasMullie\\Minify\\'        => VSO_PATH . 'vendor/minify/src/',
			'MatthiasMullie\\PathConverter\\' => VSO_PATH . 'vendor/path-converter/src/',
		);
		foreach ( $map as $prefix => $dir ) {
			if ( 0 === strpos( $class, $prefix ) ) {
				$file = $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
				if ( file_exists( $file ) ) {
					require_once $file;
				}
				return;
			}
		}
	}
);

require_once VSO_PATH . 'includes/class-vso-settings.php';
require_once VSO_PATH . 'includes/class-vso-utils.php';
require_once VSO_PATH . 'includes/class-vso-page-cache.php';
require_once VSO_PATH . 'includes/class-vso-css.php';
require_once VSO_PATH . 'includes/class-vso-js.php';
require_once VSO_PATH . 'includes/class-vso-media.php';
require_once VSO_PATH . 'includes/class-vso-fonts.php';
require_once VSO_PATH . 'includes/class-vso-optimizer.php';
require_once VSO_PATH . 'includes/class-vso-tweaks.php';
require_once VSO_PATH . 'includes/class-vso-webp.php';
require_once VSO_PATH . 'includes/class-vso-database.php';
require_once VSO_PATH . 'includes/class-vso-preload.php';
require_once VSO_PATH . 'includes/class-vso-install.php';
require_once VSO_PATH . 'includes/class-vso-adminbar.php';

if ( is_admin() ) {
	require_once VSO_PATH . 'includes/class-vso-admin.php';
}

register_activation_hook( __FILE__, array( 'VSO_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VSO_Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'vyntic-speed-optimizer', false, dirname( plugin_basename( VSO_FILE ) ) . '/languages' );

		VSO_Install::maybe_upgrade();
		VSO_Optimizer::init();
		VSO_Page_Cache::init();
		VSO_Tweaks::init();
		VSO_WebP::init();
		VSO_Database::init();
		VSO_Preload::init();

		if ( is_admin() ) {
			VSO_Admin::init();
		}
	}
);
