<?php
/**
 * Front-end output buffer: runs every optimization on the final HTML, then
 * hands it to the page cache.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Optimizer {

	/** @var array Placeholders for blocks that must not be touched. */
	private static $protected = array();

	public static function init() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), -999 );
	}

	public static function start_buffer() {
		if ( self::should_skip_request() ) {
			return;
		}
		ob_start( array( __CLASS__, 'end_buffer' ) );
	}

	/**
	 * Requests where touching the HTML would break editors, previews, feeds, etc.
	 */
	public static function should_skip_request() {
		// phpcs:disable WordPress.Security.NonceVerification
		$editor_params = array( 'elementor-preview', 'fl_builder', 'et_fb', 'ct_builder', 'bricks', 'vc_editable', 'vcv-action', 'tve', 'brizy-edit', 'brizy-edit-iframe', 'customize_changeset_uuid', 'preview', 'vso_off', 'wc-ajax', 'nooptimize' );
		foreach ( $editor_params as $param ) {
			if ( isset( $_GET[ $param ] ) ) {
				return true;
			}
		}
		// phpcs:enable
		if ( is_customize_preview() || is_feed() || is_embed() || is_robots() || is_trackback() || is_preview() ) {
			return true;
		}
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return true;
		}
		if ( defined( 'DONOTOPTIMIZE' ) && DONOTOPTIMIZE ) {
			return true;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return true;
		}
		return (bool) apply_filters( 'vso_skip_request', false );
	}

	/**
	 * Whether HTML optimizations (not caching) apply to this visitor/page.
	 */
	private static function should_optimize() {
		if ( 'off' === VSO_Settings::get( 'level' ) ) {
			return false;
		}
		if ( is_user_logged_in() && ! VSO_Settings::enabled( 'optimize_logged_in' ) ) {
			return false;
		}
		if ( is_singular() && get_post_meta( get_queried_object_id(), '_vso_disable', true ) ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
		if ( VSO_Cache_Engine::is_excluded_url( $uri, VSO_Settings::lines( 'exclude_urls' ) ) ) {
			return false;
		}
		if ( defined( 'DONOTOPTIMIZE' ) && DONOTOPTIMIZE ) {
			return false;
		}
		return true;
	}

	/**
	 * Output buffer callback.
	 */
	public static function end_buffer( $html ) {
		if ( ! is_string( $html ) || '' === $html || ! preg_match( '#<html\b#i', $html ) || false === stripos( $html, '</head>' ) || preg_match( '#^\s*<\?xml#', $html ) ) {
			return $html;
		}

		try {
			if ( self::should_optimize() ) {
				$html = self::optimize( $html );
			}
		} catch ( Throwable $e ) {
			// Never break a page because of an optimization error.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Vyntic Speed Optimizer: ' . $e->getMessage() ); // phpcs:ignore
			}
		}

		if ( VSO_Page_Cache::is_cacheable_request() ) {
			VSO_Page_Cache::store( $html );
		}
		return $html;
	}

	/**
	 * The optimization pipeline. Public so it can be unit tested.
	 */
	public static function optimize( $html ) {
		$html = self::protect( $html );

		$fonts = new VSO_Fonts();
		$html  = $fonts->process( $html );

		$media = new VSO_Media();
		$html  = $media->process( $html );

		$has_elementor_anim = false !== strpos( $html, 'elementor-invisible' );

		$css = new VSO_CSS();
		$html = $css->process( $html );

		$js   = new VSO_JS();
		$html = $js->process( $html );

		// Head: loader + resource hints go first so they're discovered early.
		$head = VSO_JS::loader_tag( $js->has_delayed_js(), $css->has_delayed_css() ) . VSO_Fonts::hints( $fonts->uses_google() );
		if ( '' !== $head ) {
			$html = self::inject_head( $html, $head, true );
		}

		$footer = '';
		if ( $media->youtube_used() ) {
			$footer .= VSO_Media::youtube_assets();
		}
		if ( $has_elementor_anim && $js->has_delayed_js() ) {
			$footer .= '<script data-vso-nodelay id="vso-anim">' . VSO_JS::compact( (string) @file_get_contents( VSO_PATH . 'assets/js/vso-animations.js' ) ) . '</script>'; // phpcs:ignore
		}
		if ( VSO_Settings::enabled( 'link_prefetch' ) ) {
			$footer .= '<script data-vso-nodelay id="vso-prefetch">' . VSO_JS::compact( (string) @file_get_contents( VSO_PATH . 'assets/js/vso-prefetch.js' ) ) . '</script>'; // phpcs:ignore
		}
		if ( '' !== $footer ) {
			$html = self::inject_footer( $html, $footer );
		}

		if ( VSO_Settings::enabled( 'minify_html' ) ) {
			$html = self::minify_html( $html );
		}

		$html = self::restore( $html );

		return (string) apply_filters( 'vso_optimized_html', $html );
	}

	/**
	 * Hides <noscript>, <template>, <textarea>, <pre> and HTML comments from the optimizers.
	 */
	private static function protect( $html ) {
		self::$protected = array();
		return preg_replace_callback(
			'#<(script|style)\b[^>]*>.*?</\1>|<!--(?!\s*\[if|<!).*?-->|<(noscript|template|textarea|pre|xmp|code)\b[^>]*>.*?</\2>#is',
			static function ( $m ) {
				// Scripts/styles are matched only so comment-like strings inside them are skipped.
				if ( ! empty( $m[1] ) ) {
					return $m[0];
				}
				// Drop ordinary comments when minifying, keep conditional/legal ones.
				if ( 0 === strpos( $m[0], '<!--' ) && VSO_Settings::enabled( 'minify_html' ) && ! preg_match( '#^<!--\s*(/?noindex|/?googleoff|/?googleon|\#|esi|ko\s|/ko)#i', $m[0] ) ) {
					return '';
				}
				$key                     = '<!--VSO_PROTECT_' . count( self::$protected ) . '-->';
				self::$protected[ $key ] = $m[0];
				return $key;
			},
			$html
		);
	}

	private static function restore( $html ) {
		if ( self::$protected ) {
			// Nested placeholders are possible; two passes are enough.
			$html = strtr( $html, self::$protected );
			$html = strtr( $html, self::$protected );
		}
		self::$protected = array();
		return $html;
	}

	public static function inject_head( $html, $code, $top = false ) {
		if ( $top && preg_match( '#<head\b[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			// Keep <meta charset> first, as browsers expect it in the first 1024 bytes.
			$pos = $m[0][1] + strlen( $m[0][0] );
			if ( preg_match( '#\s*<meta\s+charset=[^>]*>#i', $html, $c, PREG_OFFSET_CAPTURE, $pos ) && $c[0][1] === $pos ) {
				$pos += strlen( $c[0][0] );
			}
			return substr( $html, 0, $pos ) . $code . substr( $html, $pos );
		}
		$pos = stripos( $html, '</head>' );
		return false === $pos ? $html : substr( $html, 0, $pos ) . $code . substr( $html, $pos );
	}

	public static function inject_footer( $html, $code ) {
		$pos = strripos( $html, '</body>' );
		return false === $pos ? $html . $code : substr( $html, 0, $pos ) . $code . substr( $html, $pos );
	}

	/**
	 * Safe HTML minification: collapses whitespace outside scripts/styles
	 * (which are left byte-for-byte untouched).
	 */
	public static function minify_html( $html ) {
		$parts = preg_split( '#(<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}
		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 ) {
				continue;
			}
			// Collapse runs of whitespace; keep a single space so inline layout is unchanged.
			$part        = preg_replace( '#\s{2,}#', ' ', $part );
			$parts[ $i ] = preg_replace( '#>\s+<#', '> <', $part );
		}
		return trim( implode( '', $parts ) );
	}
}
