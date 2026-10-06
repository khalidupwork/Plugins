<?php
/**
 * Admin bar shortcuts: clear cache, clear this page, test speed.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin bar menu (front end + back end).
 */
add_action(
	'admin_bar_menu',
	static function ( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'vso',
				'title' => '<span class="ab-icon dashicons dashicons-performance" style="top:2px"></span>' . esc_html__( 'Vyntic', 'vyntic-speed-optimizer' ),
				'href'  => admin_url( 'admin.php?page=vyntic-speed' ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'vso',
				'id'     => 'vso-clear',
				'title'  => esc_html__( 'Clear all cache', 'vyntic-speed-optimizer' ),
				'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=vso_clear' ), 'vso_clear' ),
			)
		);
		if ( ! is_admin() ) {
			$host    = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
			$uri     = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			$current = ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;
			$bar->add_node(
				array(
					'parent' => 'vso',
					'id'     => 'vso-clear-page',
					'title'  => esc_html__( 'Clear this page', 'vyntic-speed-optimizer' ),
					'href'   => wp_nonce_url( add_query_arg( 'vso_url', rawurlencode( $current ), admin_url( 'admin-post.php?action=vso_clear' ) ), 'vso_clear' ),
				)
			);
			$bar->add_node(
				array(
					'parent' => 'vso',
					'id'     => 'vso-preview',
					'title'  => esc_html__( 'Preview optimized page', 'vyntic-speed-optimizer' ),
					'href'   => add_query_arg( 'vso_preview', 1, $current ),
				)
			);
			$bar->add_node(
				array(
					'parent' => 'vso',
					'id'     => 'vso-psi',
					'title'  => esc_html__( 'Test on PageSpeed Insights', 'vyntic-speed-optimizer' ),
					'href'   => 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $current ),
					'meta'   => array( 'target' => '_blank' ),
				)
			);
		}
	},
	100
);
