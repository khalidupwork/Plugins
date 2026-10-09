<?php
/**
 * Plugin Name:       Talkwyn
 * Plugin URI:        https://talkwyn.com/
 * Description:       AI chatbot that learns your website in one click, answers visitors in their language, and captures leads. Works with free AI provider tiers.
 * Version:           2.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Talkwyn
 * Author URI:        https://talkwyn.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       talkwyn
 * Domain Path:       /languages
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

define( 'TALKWYN_VERSION', '2.3.0' );
define( 'TALKWYN_FILE', __FILE__ );
define( 'TALKWYN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TALKWYN_URL', plugin_dir_url( __FILE__ ) );

require_once TALKWYN_DIR . 'includes/class-talkwyn-text.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-settings.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-db.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-migration.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-i18n.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-contrast.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-markdown.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-indexer.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-retriever.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-providers.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-history.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-rate-limiter.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-conversation.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-logs.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-leads.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-chat.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-rest.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-privacy.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-frontend.php';
require_once TALKWYN_DIR . 'includes/class-talkwyn-block.php';

if ( is_admin() ) {
	require_once TALKWYN_DIR . 'includes/admin/class-talkwyn-admin.php';
	require_once TALKWYN_DIR . 'includes/admin/class-talkwyn-onboarding.php';
}

register_activation_hook( __FILE__, array( 'Talkwyn_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Talkwyn_DB', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Talkwyn_DB::maybe_upgrade();
		Talkwyn_Migration::init();
		Talkwyn_I18n::init();
		Talkwyn_Indexer::init();
		Talkwyn_REST::init();
		Talkwyn_Privacy::init();
		Talkwyn_Frontend::init();
		Talkwyn_Block::init();
		if ( is_admin() ) {
			Talkwyn_Admin::init();
			Talkwyn_Onboarding::init();
		}

		/**
		 * Fires after Talkwyn has loaded. Add-ons hook in here.
		 *
		 * @param string $version Talkwyn version.
		 */
		do_action( 'talkwyn_loaded', TALKWYN_VERSION );
	}
);

add_action( 'talkwyn_daily_cleanup', array( 'Talkwyn_DB', 'cleanup' ) );
