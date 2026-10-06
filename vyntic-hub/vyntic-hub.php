<?php
/**
 * Plugin Name:       Vyntic Hub
 * Plugin URI:        https://vyntic.studio/
 * Description:       Your own plugin hub: publish plugin versions once and every site using them gets the update in its WordPress dashboard. Includes SEO-friendly plugin pages, a plugin grid and an overview of the sites using each plugin.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Vyntic Studio
 * Author URI:        https://vyntic.studio/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vyntic-hub
 *
 * @package VynticHub
 */

defined( 'ABSPATH' ) || exit;

define( 'VH_VERSION', '1.0.0' );
define( 'VH_FILE', __FILE__ );
define( 'VH_PATH', plugin_dir_path( __FILE__ ) );
define( 'VH_URL', plugin_dir_url( __FILE__ ) );

require_once VH_PATH . 'includes/class-vh-settings.php';
require_once VH_PATH . 'includes/class-vh-zip.php';
require_once VH_PATH . 'includes/class-vh-sites.php';
require_once VH_PATH . 'includes/class-vh-plugins.php';
require_once VH_PATH . 'includes/class-vh-api.php';
require_once VH_PATH . 'includes/class-vh-frontend.php';
require_once VH_PATH . 'includes/class-vh-admin.php';

VH_Plugins::init();
VH_API::init();
VH_Frontend::init();
if ( is_admin() ) {
	VH_Admin::init();
}

// Any change to a plugin post refreshes the cached catalog.
foreach ( array( 'save_post_' . VH_Plugins::TYPE, 'deleted_post', 'trashed_post', 'untrashed_post' ) as $vh_hook ) {
	add_action( $vh_hook, array( 'VH_API', 'flush' ) );
}

add_action(
	'init',
	static function () {
		if ( get_option( 'vh_flush_rewrite' ) ) {
			delete_option( 'vh_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	},
	99
);

register_activation_hook(
	__FILE__,
	static function () {
		VH_Sites::install();
		update_option( 'vh_version', VH_VERSION );

		// A "Plugins" page with the grid, unless the address is already used.
		$base = VH_Settings::base();
		if ( ! get_page_by_path( $base ) ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'WordPress Plugins', 'vyntic-hub' ),
					'post_name'    => $base,
					'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html__( 'Fast, lightweight WordPress plugins by Vyntic Studio. Free to download, with automatic updates.', 'vyntic-hub' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[vyntic_plugins]\n<!-- /wp:shortcode -->",
				)
			);
		}
		update_option( 'vh_flush_rewrite', 1 );
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules( false );
	}
);
