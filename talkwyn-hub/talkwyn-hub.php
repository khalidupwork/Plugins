<?php
/**
 * Plugin Name:       Talkwyn Hub
 * Plugin URI:        https://talkwyn.com
 * Description:       License, activation and update server for Talkwyn commercial products, powered by WooCommerce.
 * Version:           1.6.5
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Talkwyn
 * Author URI:        https://talkwyn.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       talkwyn-hub
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.3
 *
 * @package TalkwynHub
 */

defined( 'ABSPATH' ) || exit;

define( 'TWH_VERSION', '1.6.5' );
define( 'TWH_DB_VERSION', '1.2.0' );
define( 'TWH_FILE', __FILE__ );
define( 'TWH_DIR', plugin_dir_path( __FILE__ ) );
define( 'TWH_URL', plugin_dir_url( __FILE__ ) );
define( 'TWH_BASENAME', plugin_basename( __FILE__ ) );

require_once TWH_DIR . 'includes/Autoloader.php';
\TWH\Autoloader::register( 'TWH\\', TWH_DIR . 'includes/' );

// Declare HPOS (custom order tables) compatibility.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, array( \TWH\Install\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \TWH\Install\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \TWH\Plugin::class, 'boot' ), 20 );
