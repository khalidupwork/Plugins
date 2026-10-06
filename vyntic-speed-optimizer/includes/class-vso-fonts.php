<?php
/**
 * Fonts: Google Fonts self-hosting, display=swap and resource hints.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Fonts {

	/** Modern browser UA so Google serves woff2. */
	const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

	/** @var bool */
	private $uses_google = false;

	public function process( $html ) {
		$local = VSO_Settings::enabled( 'local_google_fonts' );

		$html = preg_replace_callback(
			'#<link\b[^>]*fonts\.googleapis\.com/css[^>]*>#i',
			function ( $m ) use ( $local ) {
				$tag  = $m[0];
				$href = html_entity_decode( (string) VSO_Utils::attr( $tag, 'href' ), ENT_QUOTES );
				$rel  = strtolower( (string) VSO_Utils::attr( $tag, 'rel' ) );
				if ( '' === $href ) {
					return $tag;
				}
				if ( in_array( $rel, array( 'preload', 'prefetch' ), true ) && $local ) {
					return ''; // The self-hosted copy replaces it.
				}
				if ( 'stylesheet' !== $rel ) {
					return $tag;
				}
				if ( $local ) {
					$url = self::localize( $href );
					if ( $url ) {
						return VSO_Utils::set_attr( $tag, 'href', $url );
					}
				}
				$this->uses_google = true;
				if ( VSO_Settings::enabled( 'font_display_swap' ) && false === strpos( $href, 'display=' ) ) {
					$href .= ( false === strpos( $href, '?' ) ? '?' : '&' ) . 'display=swap';
					$tag   = VSO_Utils::set_attr( $tag, 'href', $href );
				}
				return $tag;
			},
			$html
		);

		// @import of Google Fonts inside inline styles.
		if ( $local ) {
			$html = preg_replace_callback(
				'#@import\s+(?:url\()?\s*["\']?((?:https?:)?//fonts\.googleapis\.com/css[^"\')\s;]+)["\']?\s*\)?\s*;#i',
				static function ( $m ) {
					$url = self::localize( html_entity_decode( $m[1], ENT_QUOTES ) );
					return $url ? '@import url("' . $url . '");' : $m[0];
				},
				$html
			);
			// Drop now-useless preconnects when nothing from Google is left.
			if ( ! $this->uses_google && false === stripos( $html, 'fonts.googleapis.com/css' ) ) {
				$html = preg_replace( '#<link\b[^>]*rel=["\']?(?:preconnect|dns-prefetch)["\']?[^>]*fonts\.(?:googleapis|gstatic)\.com[^>]*>#i', '', $html );
				$html = preg_replace( '#<link\b[^>]*fonts\.(?:googleapis|gstatic)\.com[^>]*rel=["\']?(?:preconnect|dns-prefetch)["\']?[^>]*>#i', '', $html );
			}
		}
		return $html;
	}

	public function uses_google() {
		return $this->uses_google;
	}

	/**
	 * Downloads a Google Fonts stylesheet and its font files; returns the local CSS URL.
	 */
	public static function localize( $href ) {
		if ( 0 === strpos( $href, '//' ) ) {
			$href = 'https:' . $href;
		}
		if ( false === strpos( $href, 'display=' ) ) {
			$href .= ( false === strpos( $href, '?' ) ? '?' : '&' ) . 'display=swap';
		}
		$hash = substr( md5( $href ), 0, 16 );
		$dir  = VSO_CACHE_DIR . 'fonts/';
		$file = $dir . $hash . '.css';
		$url  = VSO_CSS::relative_root( VSO_CACHE_URL . 'fonts/' . $hash . '.css' );

		if ( is_file( $file ) ) {
			return $url;
		}

		// Avoid hammering Google when downloads fail: retry at most every hour.
		$fail_key = 'vso_gf_fail_' . $hash;
		if ( get_transient( $fail_key ) ) {
			return false;
		}

		$response = wp_remote_get(
			$href,
			array(
				'timeout'    => 8,
				'user-agent' => self::UA,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $fail_key, 1, HOUR_IN_SECONDS );
			return false;
		}
		$css = (string) wp_remote_retrieve_body( $response );
		if ( false === stripos( $css, '@font-face' ) ) {
			set_transient( $fail_key, 1, HOUR_IN_SECONDS );
			return false;
		}

		$ok  = true;
		$css = preg_replace_callback(
			'#url\(\s*["\']?(https://fonts\.gstatic\.com/[^"\')]+)["\']?\s*\)#i',
			static function ( $m ) use ( $dir, &$ok ) {
				$name = substr( md5( $m[1] ), 0, 16 ) . '.' . ( pathinfo( wp_parse_url( $m[1], PHP_URL_PATH ), PATHINFO_EXTENSION ) ?: 'woff2' );
				$path = $dir . 'files/' . $name;
				if ( ! is_file( $path ) ) {
					$font = wp_remote_get( $m[1], array( 'timeout' => 8 ) );
					if ( is_wp_error( $font ) || 200 !== (int) wp_remote_retrieve_response_code( $font ) || ! VSO_Utils::write_file( $path, wp_remote_retrieve_body( $font ) ) ) {
						$ok = false;
						return $m[0];
					}
				}
				return 'url(' . VSO_CSS::relative_root( VSO_CACHE_URL . 'fonts/files/' . $name ) . ')';
			},
			$css
		);

		if ( ! $ok ) {
			set_transient( $fail_key, 1, HOUR_IN_SECONDS );
			return false;
		}
		$css = ( new VSO_CSS() )->font_display( $css );
		return VSO_Utils::write_file( $file, $css ) ? $url : false;
	}

	/**
	 * <link rel=preconnect / preload> tags from the settings.
	 */
	public static function hints( $uses_google ) {
		$out   = '';
		$hosts = VSO_Settings::lines( 'preconnect' );
		if ( $uses_google ) {
			$hosts[] = 'https://fonts.gstatic.com';
		}
		foreach ( array_unique( $hosts ) as $host ) {
			if ( ! preg_match( '#^(https?:)?//#i', $host ) ) {
				$host = 'https://' . $host;
			}
			$out .= '<link rel="preconnect" href="' . esc_url( $host ) . '" crossorigin>';
		}

		foreach ( VSO_Settings::lines( 'preload' ) as $url ) {
			$ext  = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			$map  = array(
				'woff2' => array( 'font', 'font/woff2' ),
				'woff'  => array( 'font', 'font/woff' ),
				'ttf'   => array( 'font', 'font/ttf' ),
				'css'   => array( 'style', '' ),
				'js'    => array( 'script', '' ),
				'jpg'   => array( 'image', '' ),
				'jpeg'  => array( 'image', '' ),
				'png'   => array( 'image', '' ),
				'webp'  => array( 'image', '' ),
				'avif'  => array( 'image', '' ),
				'svg'   => array( 'image', '' ),
			);
			if ( ! isset( $map[ $ext ] ) ) {
				continue;
			}
			list( $as, $type ) = $map[ $ext ];
			$out .= '<link rel="preload" href="' . esc_url( $url ) . '" as="' . $as . '"' . ( $type ? ' type="' . $type . '"' : '' ) . ( 'font' === $as ? ' crossorigin' : '' ) . '>';
		}
		return $out;
	}
}
