<?php
/**
 * Plugin Name:       Credit Market Free Audit
 * Plugin URI:        https://github.com/khalidupwork/Plugins
 * Description:       Free website audit tool: collects a visitor's email + website URL, runs Google PageSpeed Insights, a basic SEO check and a design check, then shows a downloadable report and emails it. Includes a shortcode and an Elementor widget.
 * Version:           1.3.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Credit Market
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       credit-market-audit
 * Domain Path:       /languages
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

define( 'CMA_VERSION', '1.3.1' );
define( 'CMA_DB_VERSION', '1.0.0' );
define( 'CMA_FILE', __FILE__ );
define( 'CMA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CMA_URL', plugin_dir_url( __FILE__ ) );

require_once CMA_PATH . 'includes/class-cma-settings.php';
require_once CMA_PATH . 'includes/class-cma-install.php';
require_once CMA_PATH . 'includes/class-cma-repository.php';
require_once CMA_PATH . 'includes/class-cma-pagespeed.php';
require_once CMA_PATH . 'includes/class-cma-analyzer.php';
require_once CMA_PATH . 'includes/class-cma-audit.php';
require_once CMA_PATH . 'includes/class-cma-report.php';
require_once CMA_PATH . 'includes/class-cma-pdf.php';
require_once CMA_PATH . 'includes/class-cma-mailer.php';
require_once CMA_PATH . 'includes/class-cma-ajax.php';
require_once CMA_PATH . 'includes/class-cma-frontend.php';
require_once CMA_PATH . 'includes/class-cma-admin.php';
require_once CMA_PATH . 'includes/elementor/class-cma-elementor.php';

register_activation_hook( __FILE__, array( 'CMA_Install', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'credit-market-audit', false, dirname( plugin_basename( CMA_FILE ) ) . '/languages' );

		CMA_Install::maybe_upgrade();
		CMA_Ajax::init();
		CMA_Frontend::init();
		CMA_Report::init();
		CMA_Elementor::init();

		if ( is_admin() ) {
			CMA_Admin::init();
		}
	}
);
