<?php
/**
 * Theme setup: supports, assets, head tags, theme mode.
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
		add_editor_style( array( 'assets/css/tokens.css', 'assets/css/theme.css' ) );
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
		// Apply the saved light/dark choice before first paint (no flash).
		echo "<script>(function(){var d=document.documentElement;d.className+=' js';try{var m=localStorage.getItem('tw-theme');if(m==='light'||m==='dark'){d.setAttribute('data-theme',m);}}catch(e){}})();</script>\n";

		$fonts = TALKWYN_THEME_URL . '/assets/fonts/';
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $fonts . 'bricolage-grotesque-basic-800-normal.woff2' ) );
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $fonts . 'figtree-latin-400-normal.woff2' ) );

		$brand = TALKWYN_THEME_URL . '/assets/brand/';
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( $brand . 'logo/favicon.svg' ) );
			printf( '<link rel="icon" href="%s" sizes="48x48">' . "\n", esc_url( $brand . 'icons/favicon.ico' ) );
			printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $brand . 'icons/apple-touch-icon.png' ) );
		}
		printf( '<link rel="manifest" href="%s">' . "\n", esc_url( home_url( '/site.webmanifest' ) ) );
		echo '<meta name="theme-color" content="#5B2A86" media="(prefers-color-scheme: light)">' . "\n";
		echo '<meta name="theme-color" content="#120A1A" media="(prefers-color-scheme: dark)">' . "\n";
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
				'theme_color'      => '#5B2A86',
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
				'label' => __( 'Plum band', 'talkwyn' ),
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
				'label' => __( 'Lilac panel', 'talkwyn' ),
			)
		);
		register_block_style(
			'core/button',
			array(
				'name'  => 'secondary',
				'label' => __( 'Secondary', 'talkwyn' ),
			)
		);
		register_block_style(
			'core/button',
			array(
				'name'  => 'on-band',
				'label' => __( 'On Plum band', 'talkwyn' ),
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
