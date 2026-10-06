<?php
/**
 * JavaScript optimization: minify, defer and delay-until-interaction.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_JS {

	/** Script types that are real JavaScript (everything else – JSON, templates – is left alone). */
	const JS_TYPES = array( '', 'text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript' );

	/** Never delayed: our own scripts and markers used by other optimizers. */
	const ALWAYS_EXCLUDE = array( 'data-vso-nodelay', 'data-no-delay', 'data-no-optimize', 'nowprocket', 'data-cfasync="false"' );

	/** Never deferred: jQuery core is required by many inline scripts. */
	const DEFER_EXCLUDE = array( '/jquery.min.js', '/jquery.js', 'jquery-core', 'jquery-migrate' );

	/** @var bool */
	private $delayed = false;

	public function process( $html ) {
		$delay        = VSO_Settings::enabled( 'delay_js' );
		$defer        = VSO_Settings::enabled( 'defer_js' );
		$minify       = VSO_Settings::enabled( 'minify_js' );
		$delay_excl   = array_merge( self::ALWAYS_EXCLUDE, VSO_Settings::lines( 'delay_js_exclude' ) );
		$defer_excl   = array_merge( self::ALWAYS_EXCLUDE, self::DEFER_EXCLUDE, VSO_Settings::lines( 'defer_exclude' ) );

		$delay_excl = apply_filters( 'vso_delay_js_exclusions', $delay_excl );
		$defer_excl = apply_filters( 'vso_defer_js_exclusions', $defer_excl );

		return preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			function ( $m ) use ( $delay, $defer, $minify, $delay_excl, $defer_excl ) {
				$open  = '<script' . $m[1] . '>';
				$code  = $m[2];
				$type  = strtolower( trim( (string) VSO_Utils::attr( $open, 'type' ) ) );
				if ( ! in_array( $type, self::JS_TYPES, true ) ) {
					return $m[0];
				}
				$src = VSO_Utils::attr( $open, 'src' );

				// Minify local, not-yet-minified files.
				if ( $src && $minify ) {
					$cached = $this->minified_url( $src );
					if ( $cached ) {
						$open = VSO_Utils::set_attr( $open, 'src', $cached );
						$src  = $cached;
					}
				}

				$haystack = $open . ( $src ? '' : substr( $code, 0, 2000 ) );

				if ( $delay && ! VSO_Utils::matches_any( $haystack, $delay_excl ) ) {
					$this->delayed = true;
					$tag = VSO_Utils::remove_attr( $open, 'type' );
					if ( 'module' === $type ) {
						$tag = VSO_Utils::set_attr( $tag, 'data-vso-type', 'module' );
					}
					if ( $src ) {
						$tag = VSO_Utils::remove_attr( $tag, 'src' );
						$tag = VSO_Utils::set_attr( $tag, 'data-vso-src', html_entity_decode( $src, ENT_QUOTES ) );
					}
					$tag = VSO_Utils::set_attr( $tag, 'type', 'vso/javascript' );
					return $tag . $code . '</script>';
				}

				if ( $defer && $src && 'module' !== $type
					&& ! VSO_Utils::has_attr( $open, 'defer' ) && ! VSO_Utils::has_attr( $open, 'async' )
					&& ! VSO_Utils::matches_any( $open, $defer_excl ) ) {
					$open = preg_replace( '#<script\b#i', '<script defer', $open, 1 );
				}

				return $open . $code . '</script>';
			},
			$html
		);
	}

	public function has_delayed_js() {
		return $this->delayed;
	}

	/**
	 * Returns a cached, minified copy URL for a local script, or false.
	 */
	private function minified_url( $src ) {
		if ( preg_match( '#[.-]min\.js($|\?)#i', $src ) || false !== strpos( $src, '/cache/vyntic/' ) ) {
			return false;
		}
		$path = VSO_Utils::url_to_path( $src );
		if ( ! $path || ! preg_match( '#\.js$#i', $path ) ) {
			return false;
		}
		$size = @filesize( $path ); // phpcs:ignore
		if ( ! $size || $size > MB_IN_BYTES ) {
			return false;
		}
		$hash = substr( md5( $path . '|' . @filemtime( $path ) . '|' . $size . '|' . VSO_VERSION ), 0, 16 ); // phpcs:ignore
		$file = VSO_CACHE_DIR . 'assets/js/' . $hash . '.js';
		if ( ! is_file( $file ) ) {
			$js = (string) @file_get_contents( $path ); // phpcs:ignore
			try {
				$minifier = new MatthiasMullie\Minify\JS();
				$minifier->add( $js );
				$out = $minifier->minify();
			} catch ( Exception $e ) {
				$out = '';
			}
			// Keep the original if minification fails or does not help.
			if ( '' === trim( $out ) || strlen( $out ) >= strlen( $js ) ) {
				$out = $js;
			}
			if ( ! VSO_Utils::write_file( $file, $out . "\n" ) ) {
				return false;
			}
		}
		return VSO_CSS::relative_root( VSO_CACHE_URL . 'assets/js/' . $hash . '.js' );
	}

	/**
	 * Inline loader placed at the top of <head>.
	 */
	public static function loader_tag( $delay_js, $delayed_css ) {
		$config = array(
			'delay'   => (bool) $delay_js,
			'timeout' => (int) VSO_Settings::get( 'delay_js_timeout' ),
			'css'     => VSO_Settings::get( 'full_css_load' ),
		);
		$js = (string) @file_get_contents( VSO_PATH . 'assets/js/vso-loader.js' ); // phpcs:ignore
		if ( ! $delay_js && ! $delayed_css ) {
			return '';
		}
		return '<script data-vso-nodelay id="vso-loader">window.vsoConfig=' . wp_json_encode( $config ) . ';' . self::compact( $js ) . '</script>';
	}

	/**
	 * Light-weight compaction for our own small scripts.
	 */
	public static function compact( $js ) {
		$js = preg_replace( '#^\s*/\*!?.*?\*/\s*#s', '', $js );
		if ( class_exists( 'MatthiasMullie\\Minify\\JS' ) ) {
			try {
				$minifier = new MatthiasMullie\Minify\JS();
				$minifier->add( $js );
				return $minifier->minify();
			} catch ( Exception $e ) {
				return $js;
			}
		}
		return $js;
	}
}
