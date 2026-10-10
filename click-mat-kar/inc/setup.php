<?php
/**
 * Theme supports, menus and assets.
 *
 * @package ClickMatKar
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'cmk_setup' );
function cmk_setup() {
	load_theme_textdomain( 'click-mat-kar', CMK_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'click-mat-kar' ),
			'footer'  => __( 'Footer menu', 'click-mat-kar' ),
		)
	);

	add_image_size( 'cmk-card', 640, 640, true );
}

/**
 * Google Fonts URL: three families, only the weights the brand guide uses.
 */
function cmk_fonts_url() {
	return 'https://fonts.googleapis.com/css2?family=Bowlby+One+SC&family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&display=swap';
}

add_action( 'wp_enqueue_scripts', 'cmk_enqueue' );
function cmk_enqueue() {
	wp_enqueue_style( 'cmk-fonts', cmk_fonts_url(), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_style( 'cmk-main', CMK_URI . '/assets/css/main.css', array(), CMK_VERSION );

	wp_enqueue_script( 'cmk-main', CMK_URI . '/assets/js/main.js', array(), CMK_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	wp_localize_script( 'cmk-main', 'CMK', cmk_js_config() );

	if ( is_singular( 'cmk_game' ) && 'shop' === cmk_game_engine( get_queried_object_id() ) ) {
		wp_enqueue_script( 'cmk-shop', CMK_URI . '/assets/js/game-shop.js', array( 'cmk-main' ), CMK_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}

	if ( is_page( 'result' ) ) {
		wp_enqueue_script( 'cmk-result', CMK_URI . '/assets/js/result.js', array( 'cmk-main' ), CMK_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
}

/**
 * Data shared with the front-end scripts.
 */
function cmk_js_config() {
	$result_page = get_page_by_path( 'result' );
	$shop_game   = cmk_get_game_by_slug( 'shop-like-youre-rich' );

	return array(
		'home'      => home_url( '/' ),
		'resultUrl' => $result_page ? get_permalink( $result_page ) : home_url( '/result/' ),
		'shopUrl'   => $shop_game ? get_permalink( $shop_game ) : home_url( '/games/shop-like-youre-rich/' ),
		'gamesUrl'  => get_post_type_archive_link( 'cmk_game' ),
		'siteName'  => 'clickmatkar.com',
		'debug'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
	);
}

add_filter( 'wp_resource_hints', 'cmk_resource_hints', 10, 2 );
function cmk_resource_hints( $urls, $relation ) {
	if ( 'preconnect' === $relation ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
		$urls[] = 'https://fonts.googleapis.com';
	}
	return $urls;
}

add_action( 'wp_head', 'cmk_head_meta', 2 );
function cmk_head_meta() {
	echo '<meta name="theme-color" content="#111111">' . "\n";
}

/**
 * Inline SVG favicon (CMK. on lime) when no site icon is set.
 */
add_action( 'wp_head', 'cmk_fallback_favicon', 99 );
function cmk_fallback_favicon() {
	if ( has_site_icon() ) {
		return;
	}
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="#D7FF3F"/><text x="32" y="41" font-family="Arial Black,Arial" font-weight="900" font-size="22" text-anchor="middle" fill="#111">CMK.</text></svg>';
	echo '<link rel="icon" href="data:image/svg+xml,' . rawurlencode( $svg ) . '">' . "\n";
}

add_filter( 'body_class', 'cmk_body_class' );
function cmk_body_class( $classes ) {
	if ( is_singular( 'cmk_game' ) ) {
		$classes[] = 'cmk-engine-' . sanitize_html_class( cmk_game_engine( get_queried_object_id() ) );
	}
	return $classes;
}

/**
 * Menu fallback: clear links that always resolve, never placeholder hrefs.
 */
function cmk_primary_menu_fallback() {
	$links = array(
		array( get_post_type_archive_link( 'cmk_game' ), __( 'Games', 'click-mat-kar' ) ),
		array( home_url( '/#how-it-works' ), __( 'How it works', 'click-mat-kar' ) ),
		array( home_url( '/#results' ), __( 'Results', 'click-mat-kar' ) ),
		array( home_url( '/about/' ), __( 'WTF is this?', 'click-mat-kar' ) ),
	);
	echo '<ul class="cmk-nav__list">';
	foreach ( $links as $link ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $link[0] ), esc_html( $link[1] ) );
	}
	echo '</ul>';
}
