<?php
/**
 * Plugin Name:       Talkwyn Pro
 * Plugin URI:        https://talkwyn.com/pricing/
 * Description:       Pro add-on for Talkwyn: paid AI models, smart search, PDF and URL knowledge, streaming replies, analytics, an unanswered questions inbox, WooCommerce product cards and order lookup, lead alerts, proactive messages, business hours and white label.
 * Version:           1.2.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Requires Plugins:  talkwyn
 * Author:            Talkwyn
 * Author URI:        https://talkwyn.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       talkwyn-pro
 * Update URI:        https://talkwyn.com/talkwyn-pro/
 *
 * @package TalkwynPro
 */

defined( 'ABSPATH' ) || exit;

define( 'TALKWYN_PRO_VERSION', '1.2.1' );
define( 'TALKWYN_PRO_FILE', __FILE__ );
define( 'TALKWYN_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'TALKWYN_PRO_URL', plugin_dir_url( __FILE__ ) );

require_once TALKWYN_PRO_DIR . 'includes/class-talkwyn-license-client.php';

spl_autoload_register(
	static function ( string $class ): void {
		if ( 0 !== strpos( $class, 'TalkwynPro\\' ) ) {
			return;
		}
		$file = TALKWYN_PRO_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, strlen( 'TalkwynPro\\' ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'TalkwynPro\\Installer', 'activate' ) );

add_action( 'plugins_loaded', array( 'TalkwynPro\\Plugin', 'boot' ), 20 );
