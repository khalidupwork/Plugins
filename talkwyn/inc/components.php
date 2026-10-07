<?php
/**
 * Shared components exposed as shortcodes (usable in templates, patterns and posts).
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * [tw_logo_link] Home link with the inline logo.
 */
add_shortcode(
	'tw_logo_link',
	static function () {
		return '<a class="tw-logo-link" href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . talkwyn_logo_svg( 'full', __( 'Talkwyn home', 'talkwyn' ) ) . '</a>';
	}
);

/**
 * [tw_theme_toggle] Cycles light, dark, system. State lives in localStorage("tw-theme").
 */
add_shortcode(
	'tw_theme_toggle',
	static function () {
		return '<button type="button" class="tw-theme-toggle" data-tw-theme-toggle aria-live="polite">'
			. '<span class="tw-theme-toggle__icon tw-theme-toggle__icon--system">' . talkwyn_icon( 'monitor', 18 ) . '</span>'
			. '<span class="tw-theme-toggle__icon tw-theme-toggle__icon--light">' . talkwyn_icon( 'sun', 18 ) . '</span>'
			. '<span class="tw-theme-toggle__icon tw-theme-toggle__icon--dark">' . talkwyn_icon( 'moon', 18 ) . '</span>'
			. '<span class="screen-reader-text" data-tw-theme-label>' . esc_html__( 'Theme: system', 'talkwyn' ) . '</span>'
			. '</button>';
	}
);

/**
 * [tw_social] Social profile links from Site Settings (hidden when empty).
 */
add_shortcode(
	'tw_social',
	static function () {
		$profiles = array(
			'social_x'         => array( 'brand-x', 'X' ),
			'social_linkedin'  => array( 'brand-linkedin', 'LinkedIn' ),
			'social_facebook'  => array( 'brand-facebook', 'Facebook' ),
			'social_youtube'   => array( 'brand-youtube', 'YouTube' ),
			'social_instagram' => array( 'brand-instagram', 'Instagram' ),
			'social_github'    => array( 'brand-github', 'GitHub' ),
		);
		$items    = '';
		foreach ( $profiles as $key => $meta ) {
			$url = (string) talkwyn_setting( $key );
			if ( '' !== $url ) {
				/* translators: %s: social network name */
				$label  = sprintf( __( 'Talkwyn on %s', 'talkwyn' ), $meta[1] );
				$items .= '<li><a href="' . esc_url( $url ) . '" rel="me noopener" target="_blank">' . talkwyn_icon( $meta[0], 20, $label ) . '</a></li>';
			}
		}
		return '' === $items ? '' : '<ul class="tw-social">' . $items . '</ul>';
	}
);

/**
 * [tw_icon name="stethoscope" size="24"].
 *
 * @param array<string, string>|string $atts Attributes.
 */
add_shortcode(
	'tw_icon',
	static function ( $atts ) {
		$atts = shortcode_atts(
			array(
				'name' => '',
				'size' => 24,
			),
			$atts,
			'tw_icon'
		);
		return talkwyn_icon( sanitize_key( $atts['name'] ), absint( $atts['size'] ) );
	}
);

/**
 * [tw_badge]Coming soon[/tw_badge] Inline badge; type="soon|pro|new".
 *
 * @param array<string, string>|string $atts    Attributes.
 * @param string|null                   $content Label.
 */
add_shortcode(
	'tw_badge',
	static function ( $atts, $content = null ) {
		$atts  = shortcode_atts( array( 'type' => 'soon' ), $atts, 'tw_badge' );
		$label = null !== $content && '' !== $content ? $content : __( 'Coming soon', 'talkwyn' );
		return '<span class="tw-badge tw-badge--' . esc_attr( sanitize_key( $atts['type'] ) ) . '">' . esc_html( $label ) . '</span>';
	}
);

// "Install free" buttons and menu items (class tw-install) follow the WordPress.org URL once it is set.
foreach ( array( 'render_block_core/button', 'render_block_core/navigation-link' ) as $talkwyn_install_hook ) {
	add_filter( $talkwyn_install_hook, 'talkwyn_install_href', 10, 2 );
}

/**
 * Point a block with the tw-install class at the current install URL
 * (the /download/ page until a WordPress.org URL is set in Site Settings).
 *
 * @param string               $html  Block HTML.
 * @param array<string, mixed> $block Block.
 */
function talkwyn_install_href( $html, $block ) {
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( false === strpos( $class, 'tw-install' ) ) {
		return $html;
	}
	$url = talkwyn_install_url();
	$out = preg_replace( '/href="[^"]*"/', 'href="' . esc_url( $url ) . '"', (string) $html, 1 );
	if ( '' !== (string) talkwyn_setting( 'wporg_url' ) ) {
		$out = str_replace( '<a ', '<a rel="noopener" ', (string) $out );
	}
	return (string) $out;
}
