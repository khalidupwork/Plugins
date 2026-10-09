<?php
/**
 * Theme setup: supports, assets, head tags. The site is light only.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( 'talkwyn', TALKWYN_THEME_DIR . '/languages' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		remove_theme_support( 'core-block-patterns' );
		add_editor_style( array( 'assets/css/tokens.css', 'assets/css/theme.css', 'assets/css/home.css' ) );
		register_nav_menus(
			array(
				'primary' => __( 'Primary', 'talkwyn' ),
				'footer'  => __( 'Footer', 'talkwyn' ),
			)
		);
	}
);

/**
 * Front-end assets. No jQuery, everything deferred.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$v = TALKWYN_THEME_VERSION;
		wp_enqueue_style( 'talkwyn-tokens', TALKWYN_THEME_URL . '/assets/css/tokens.css', array(), $v );
		wp_enqueue_style( 'talkwyn', TALKWYN_THEME_URL . '/assets/css/theme.css', array( 'talkwyn-tokens' ), $v );
		wp_enqueue_style( 'talkwyn-home', TALKWYN_THEME_URL . '/assets/css/home.css', array( 'talkwyn' ), $v );
		// Small enough to inline: saves a render-blocking request on first paint.
		wp_style_add_data( 'talkwyn-tokens', 'path', TALKWYN_THEME_DIR . '/assets/css/tokens.css' );
		wp_enqueue_script(
			'talkwyn',
			TALKWYN_THEME_URL . '/assets/js/theme.js',
			array(),
			$v,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}
);

/**
 * Head: theme mode before paint, font preloads, icons, manifest, theme-color.
 */
add_action(
	'wp_head',
	static function () {
		// Motion is opt-in from JS (so content is never hidden without it) and skipped for reduced motion.
		echo "<script>(function(){var d=document.documentElement;d.className+=' js';if('IntersectionObserver' in window&&!(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)){d.className+=' tw-motion';setTimeout(function(){if(!window.twReady){d.classList.remove('tw-motion');}},3000);}})();</script>\n";

		$fonts = TALKWYN_THEME_URL . '/assets/fonts/';
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $fonts . 'plus-jakarta-sans-latin-700-normal.woff2' ) );
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $fonts . 'inter-latin-400-normal.woff2' ) );

		$brand = TALKWYN_THEME_URL . '/assets/brand/';
		// Talkwyn favicon set (a Site Icon chosen under Settings, General takes over when set).
		if ( ! has_site_icon() ) {
			$v = '?v=' . rawurlencode( TALKWYN_THEME_VERSION );
			printf( '<link rel="icon" href="%s" sizes="any">' . "\n", esc_url( $brand . 'icons/favicon.ico' . $v ) );
			printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( $brand . 'logo/favicon.svg' . $v ) );
			printf( '<link rel="icon" href="%s" type="image/png" sizes="32x32">' . "\n", esc_url( $brand . 'icons/favicon-32x32.png' . $v ) );
			printf( '<link rel="icon" href="%s" type="image/png" sizes="16x16">' . "\n", esc_url( $brand . 'icons/favicon-16x16.png' . $v ) );
			printf( '<link rel="apple-touch-icon" sizes="180x180" href="%s">' . "\n", esc_url( $brand . 'icons/apple-touch-icon.png' . $v ) );
		}
		printf( '<link rel="manifest" href="%s">' . "\n", esc_url( home_url( '/site.webmanifest' ) ) );
		echo '<meta name="theme-color" content="#FFFFFF">' . "\n";
	},
	1
);

/**
 * /favicon.ico: WordPress answers it with its own "W" logo when no Site Icon is set. Serve the Talkwyn icon instead.
 */
add_action(
	'do_faviconico',
	static function () {
		if ( has_site_icon() ) {
			return;
		}
		$file = TALKWYN_THEME_DIR . '/assets/brand/icons/favicon.ico';
		if ( ! file_exists( $file ) ) {
			return;
		}
		header( 'Content-Type: image/x-icon' );
		header( 'Cache-Control: public, max-age=604800' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- local theme file.
		exit;
	},
	1
);

/**
 * Serve /site.webmanifest with absolute icon URLs from the theme.
 */
add_action(
	'parse_request',
	static function ( $wp ) {
		if ( 'site.webmanifest' !== trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared only.
			return;
		}
		$brand = TALKWYN_THEME_URL . '/assets/brand/icons/';
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=86400' );
		echo wp_json_encode(
			array(
				'name'             => 'Talkwyn',
				'short_name'       => 'Talkwyn',
				'icons'            => array(
					array(
						'src'   => $brand . 'android-chrome-192x192.png',
						'sizes' => '192x192',
						'type'  => 'image/png',
					),
					array(
						'src'   => $brand . 'android-chrome-512x512.png',
						'sizes' => '512x512',
						'type'  => 'image/png',
					),
				),
				'theme_color'      => '#D7263D',
				'background_color' => '#FFFFFF',
				'display'          => 'standalone',
				'start_url'        => '/',
			),
			JSON_UNESCAPED_SLASHES
		);
		exit;
	},
	0
);

/**
 * Block pattern category.
 */
add_action(
	'init',
	static function () {
		register_block_pattern_category( 'talkwyn', array( 'label' => __( 'Talkwyn', 'talkwyn' ) ) );
		register_block_pattern_category( 'talkwyn-pages', array( 'label' => __( 'Talkwyn pages', 'talkwyn' ) ) );

		register_block_style(
			'core/group',
			array(
				'name'  => 'band',
				'label' => __( 'Ink band', 'talkwyn' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'card',
				'label' => __( 'Card', 'talkwyn' ),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'panel',
				'label' => __( 'Linen card', 'talkwyn' ),
			)
		);
		register_block_style(
			'core/button',
			array(
				'name'  => 'secondary',
				'label' => __( 'White pill', 'talkwyn' ),
			)
		);
		register_block_style(
			'core/button',
			array(
				'name'  => 'on-band',
				'label' => __( 'On Ink band', 'talkwyn' ),
			)
		);
	}
);

/**
 * Lazy-load and decode async on content images; LCP images are marked eager in patterns.
 */
add_filter( 'wp_lazy_loading_enabled', '__return_true' );

/**
 * Remove emoji scripts (performance).
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
