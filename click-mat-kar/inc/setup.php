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
	wp_enqueue_script( 'cmk-fx', CMK_URI . '/assets/js/fx.js', array( 'cmk-main' ), CMK_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );

	$cmk_engine = is_singular( 'cmk_game' ) ? cmk_game_engine( get_queried_object_id() ) : '';
	if ( in_array( $cmk_engine, array( 'shop', 'quiz' ), true ) || is_page( 'result' ) ) {
		wp_enqueue_script( 'cmk-games-data', CMK_URI . '/assets/js/games-data.js', array(), CMK_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
	if ( in_array( $cmk_engine, array( 'shop', 'quiz' ), true ) ) {
		wp_enqueue_script( 'cmk-' . $cmk_engine, CMK_URI . '/assets/js/game-' . $cmk_engine . '.js', array( 'cmk-main', 'cmk-games-data' ), CMK_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}

	if ( is_page( 'result' ) ) {
		wp_enqueue_script( 'cmk-result', CMK_URI . '/assets/js/result.js', array( 'cmk-main', 'cmk-games-data' ), CMK_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
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
		'productImages'    => cmk_product_images(),
		'productImageBase' => CMK_URI . '/assets/img/products/',
		'gameUrls'  => wp_list_pluck( array_filter( cmk_game_cards(), function ( $c ) { return $c['playable']; } ), 'url' ),
		'gameTitles' => array_map(
			function ( $t ) {
				return html_entity_decode( $t, ENT_QUOTES, 'UTF-8' );
			},
			wp_list_pluck( cmk_game_cards(), 'title' )
		),
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

/**
 * Decode a ?r= result payload server-side (same format as CMKUI.encode in main.js).
 *
 * @return array|null
 */
function cmk_request_result() {
	if ( empty( $_GET['r'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return null;
	}
	$raw  = sanitize_text_field( wp_unslash( $_GET['r'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$json = base64_decode( strtr( $raw, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	$data = $json ? json_decode( $json, true ) : null;
	if ( ! is_array( $data ) || ! isset( $data['s'], $data['q'] ) ) {
		return null;
	}
	$packs = array(
		'pkr' => array( 'Rs ', 1 ),
		'inr' => array( '₹', 1 ),
		'usd' => array( '$', 0.01 ),
	);
	$pack = isset( $data['c'], $packs[ $data['c'] ] ) ? $packs[ $data['c'] ] : $packs['usd'];
	$num  = (int) round( min( (float) $data['s'], 1e12 ) * $pack[1] );
	$fmt  = '$' === $pack[0] ? number_format( $num ) : cmk_format_lakh( $num );
	$catalog = cmk_games_catalog();
	$slug    = isset( $data['g'] ) ? sanitize_title( $data['g'] ) : 'shop-like-youre-rich';
	return array(
		'spent' => $pack[0] . $fmt,
		'iq'    => max( 1, min( 99, (int) $data['q'] ) ),
		'game'  => isset( $catalog[ $slug ] ) ? $catalog[ $slug ]['title'] : 'Click Mat Kar',
		'money' => in_array( $slug, array( 'shop-like-youre-rich', 'dream-wedding', 'spend-1-billion', 'dream-life', 'whats-your-price' ), true ),
	);
}

/**
 * 12,34,56,789 style grouping for Rs / ₹.
 */
function cmk_format_lakh( $num ) {
	$s    = (string) absint( $num );
	$last = substr( $s, -3 );
	$rest = substr( $s, 0, -3 );
	if ( '' === $rest ) {
		return $last;
	}
	return preg_replace( '/\B(?=(\d{2})+(?!\d))/', ',', $rest ) . ',' . $last;
}

/**
 * Open Graph / Twitter tags. Result pages get a title built from the shared result.
 */
add_action( 'wp_head', 'cmk_social_meta', 5 );
function cmk_social_meta() {
	$title = wp_get_document_title();
	$desc  = __( 'Fake shopping, ridiculous choices and shareable results. We told you not to click.', 'click-mat-kar' );
	$url   = home_url( add_query_arg( array() ) );

	if ( is_singular( 'cmk_game' ) && has_excerpt() ) {
		$desc = get_the_excerpt();
	}
	if ( is_page( 'result' ) ) {
		$result = cmk_request_result();
		if ( $result ) {
			$title = $result['money']
				/* translators: 1: game title, 2: money, 3: score. */
				? sprintf( __( '%1$s: %2$s. Score %3$d/100 🤡', 'click-mat-kar' ), $result['game'], $result['spent'], $result['iq'] )
				/* translators: 1: game title, 2: score. */
				: sprintf( __( 'My %1$s score: %2$d/100 🤡', 'click-mat-kar' ), $result['game'], $result['iq'] );
			$desc  = __( 'Think you can make worse decisions? Beat me on Click Mat Kar.', 'click-mat-kar' );
		}
	}

	$tags = array(
		'og:site_name'   => 'Click Mat Kar',
		'og:type'        => 'website',
		'og:title'       => $title,
		'og:description' => $desc,
		'og:url'         => $url,
	);
	foreach ( $tags as $property => $content ) {
		printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $content ) );
	}
	printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
}

/* Result URLs are per-player; keep them out of search results. */
add_filter( 'wp_robots', 'cmk_result_robots' );
function cmk_result_robots( $robots ) {
	if ( is_page( 'result' ) ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}

/**
 * Product images dropped into assets/img/products/ ({product-id}.webp|png|jpg).
 * Only files that exist are sent to the game, so missing art falls back to emoji with no 404s.
 *
 * @return array<string,string> product id => file name.
 */
function cmk_product_images() {
	$dir = CMK_DIR . '/assets/img/products';
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	// Directory mtime changes whenever a file is added or removed, so new art shows up immediately.
	$key   = 'cmk_pimg_' . md5( CMK_VERSION . '|' . filemtime( $dir ) );
	$found = get_transient( $key );
	if ( is_array( $found ) ) {
		return $found;
	}
	$found = array();
	foreach ( (array) scandir( $dir ) as $file ) {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'webp', 'png', 'jpg', 'jpeg', 'avif' ), true ) ) {
			continue;
		}
		$id = sanitize_key( pathinfo( $file, PATHINFO_FILENAME ) );
		if ( $id && ! isset( $found[ $id ] ) ) {
			$found[ $id ] = rawurlencode( $file ) . '?v=' . filemtime( $dir . '/' . $file );
		}
	}
	set_transient( $key, $found, DAY_IN_SECONDS );
	return $found;
}
