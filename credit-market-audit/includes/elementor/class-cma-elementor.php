<?php
/**
 * Elementor integration.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Credit Market" widget category and the Free Audit widget.
 */
class CMA_Elementor {

	/**
	 * Hooks (only when Elementor is active).
	 */
	public static function init() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Widget category.
	 *
	 * @param \Elementor\Elements_Manager $manager Elements manager.
	 */
	public static function register_category( $manager ) {
		$manager->add_category(
			'credit-market',
			array(
				'title' => __( 'Credit Market', 'credit-market-audit' ),
				'icon'  => 'eicon-search-results',
			)
		);
	}

	/**
	 * Register widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once CMA_PATH . 'includes/elementor/class-cma-audit-widget.php';
		$widgets_manager->register( new CMA_Audit_Widget() );
	}
}
