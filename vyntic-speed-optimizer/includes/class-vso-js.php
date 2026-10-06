<?php
/**
 * JavaScript optimization: minify, defer and delay-until-interaction.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_JS {

	/** Script types that are real JavaScript (everything else, like JSON or templates, is left alone). */
	const JS_TYPES = array( '', 'text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript' );

	/** Never delayed: our own scripts, markers used by other optimizers, the admin bar. */
	const ALWAYS_EXCLUDE = array( 'data-vso-nodelay', 'data-no-delay', 'data-no-optimize', 'nowprocket', 'data-cfasync="false"', 'admin-bar', 'hoverintent' );

	/** Never deferred: consent tools that must block other scripts before they run. */
	const DEFER_NEVER = array( 'cookiebot', 'otSDKStub', 'otAutoBlock', 'usercentrics', 'iubenda', 'termly', 'data-blockingmode' );

	/** Kept for the filter's default value. */
	const DEFER_EXCLUDE = array();

	/** Handles that make up jQuery. */
	const JQUERY_HANDLES = array( 'jquery', 'jquery-core', 'jquery-migrate' );

	/** @var bool */
	private $delayed = false;

	/** @var array[] Parsed <script> tags of the page. */
	private $scripts = array();

	public function process( $html ) {
		$delay  = VSO_Settings::enabled( 'delay_js' ) && ! VSO_Compat::delay_disabled_here();
		$defer  = VSO_Settings::enabled( 'defer_js' );
		$minify = VSO_Settings::enabled( 'minify_js' );

		$delay_excl = apply_filters( 'vso_delay_js_exclusions', array_merge( self::ALWAYS_EXCLUDE, VSO_Compat::delay_exclusions(), VSO_Settings::lines( 'delay_js_exclude' ) ) );
		$defer_excl = apply_filters( 'vso_defer_js_exclusions', array_merge( self::ALWAYS_EXCLUDE, self::DEFER_EXCLUDE, VSO_Settings::lines( 'defer_exclude' ) ) );

		// Pass 1: collect every real JavaScript tag.
		$this->scripts = array();
		$html          = preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			function ( $m ) use ( $minify ) {
				$open = '<script' . $m[1] . '>';
				$type = strtolower( trim( (string) VSO_Utils::attr( $open, 'type' ) ) );
				if ( ! in_array( $type, self::JS_TYPES, true ) ) {
					return $m[0];
				}
				$src      = VSO_Utils::attr( $open, 'src' );
				$original = $src;
				if ( $src && $minify ) {
					$cached = $this->minified_url( $src );
					if ( $cached ) {
						$open = VSO_Utils::set_attr( $open, 'src', $cached );
						$src  = $cached;
					}
				}
				$id     = (string) VSO_Utils::attr( $open, 'id' );
				$handle = '';
				$part   = '';
				if ( preg_match( '#^(.+)-js(?:-(extra|before|after|translations))?$#', $id, $hm ) ) {
					$handle = $hm[1];
					$part   = isset( $hm[2] ) ? $hm[2] : '';
				}
				$index                   = count( $this->scripts );
				$this->scripts[ $index ] = array(
					'open'     => $open,
					'code'     => $m[2],
					'type'     => $type,
					'src'      => $src,
					'original' => $original,
					'handle'   => $handle,
					'part'     => $part,
					'exclude'  => false,
					'defer'    => false,
				);
				return '<!--VSO_SCRIPT_' . $index . '-->';
			},
			$html
		);

		// Pass 2: decide what runs normally and what waits for interaction.
		if ( $delay ) {
			$this->resolve_delay_exclusions( $delay_excl );
		} else {
			foreach ( $this->scripts as $i => $script ) {
				$this->scripts[ $i ]['exclude'] = true;
			}
		}
		$this->resolve_defer( $defer, $delay, $defer_excl );

		// Pass 3: write the tags back.
		$replace = array();
		foreach ( $this->scripts as $i => $script ) {
			$replace[ '<!--VSO_SCRIPT_' . $i . '-->' ] = $this->render( $script );
		}
		return strtr( $html, $replace );
	}

	/**
	 * Marks excluded scripts, then pulls in everything they depend on so an
	 * excluded script never runs before (delayed) code it needs.
	 */
	private function resolve_delay_exclusions( array $keywords ) {
		$handles      = array();
		$needs_jquery = false;

		foreach ( $this->scripts as $i => $script ) {
			// Config blocks printed for a script (localized data, "before" code,
			// translations) mention lots of feature names, e.g. Elementor's config
			// contains "lazyload". They never decide on their own; they follow their
			// script, or run early when they are plain data.
			if ( in_array( $script['part'], array( 'extra', 'before', 'translations' ), true ) ) {
				if ( VSO_Compat::is_safe_inline( $script['code'] ) ) {
					$this->exclude( $i, 'plain data' );
				}
				continue;
			}
			$haystack = $script['open'] . ( $script['original'] ? ' ' . $script['original'] : '' ) . ( '' !== $script['code'] ? ' ' . substr( $script['code'], 0, 20000 ) : '' );
			$keyword  = VSO_Utils::first_match( $haystack, $keywords );
			if ( null !== $keyword ) {
				$this->exclude( $i, 'matches "' . $keyword . '"' );
			} elseif ( ! $script['src'] && VSO_Compat::is_safe_inline( $script['code'] ) ) {
				$this->exclude( $i, 'plain data / safe inline' );
				continue; // Safe inline code needs nothing else.
			} else {
				continue;
			}
			if ( $script['handle'] ) {
				$handles[ $script['handle'] ] = true;
			}
			if ( $this->uses_jquery( $script ) ) {
				$needs_jquery = true;
			}
		}

		if ( $needs_jquery ) {
			foreach ( self::JQUERY_HANDLES as $h ) {
				$handles[ $h ] = true;
			}
		}

		// WordPress knows every enqueued script's dependencies: follow them.
		$handles = $this->dependency_closure( array_keys( $handles ) );

		// Dependencies may need jQuery too (e.g. a slider library).
		if ( ! $needs_jquery ) {
			foreach ( $this->scripts as $script ) {
				if ( $script['handle'] && isset( $handles[ $script['handle'] ] ) && $this->uses_jquery( $script ) ) {
					$handles = $this->dependency_closure( array_merge( array_keys( $handles ), self::JQUERY_HANDLES ) );
					$needs_jquery = true;
					break;
				}
			}
		}

		foreach ( $this->scripts as $i => $script ) {
			$is_dep = $script['handle'] && isset( $handles[ $script['handle'] ] );
			// jQuery printed without an id (hard-coded by a theme).
			$is_jq = $needs_jquery && $script['original'] && preg_match( '#/jquery(\.min)?\.js|/jquery-migrate(\.min)?\.js#i', $script['original'] );
			if ( ( $is_dep || $is_jq ) && ! $this->scripts[ $i ]['exclude'] ) {
				$this->exclude( $i, 'needed by a script that is not delayed' );
			}
		}
	}

	private function exclude( $index, $reason ) {
		$this->scripts[ $index ]['exclude'] = true;
		$this->scripts[ $index ]['reason']  = $reason;
	}

	/**
	 * Lines for the ?vso_debug=1 report: which scripts run right away and why.
	 */
	public function debug_report() {
		$lines = array();
		foreach ( $this->scripts as $script ) {
			$name = $script['handle'] ? $script['handle'] . ( $script['part'] ? ' (' . $script['part'] . ')' : '' ) : ( $script['original'] ? $script['original'] : 'inline: ' . substr( preg_replace( '/\s+/', ' ', $script['code'] ), 0, 60 ) );
			if ( ! $script['exclude'] ) {
				$state = 'delayed';
			} else {
				$state = ( $script['defer'] ? 'runs early, deferred' : 'runs early' ) . ( isset( $script['reason'] ) ? ': ' . $script['reason'] : '' );
			}
			$lines[] = str_replace( '--', '- -', $name . ' => ' . $state );
		}
		return $lines;
	}

	/**
	 * All handles plus their (recursive) dependencies from the WP script registry.
	 *
	 * @return array handle => true
	 */
	private function dependency_closure( array $handles ) {
		$registered = ( isset( $GLOBALS['wp_scripts'] ) && is_object( $GLOBALS['wp_scripts'] ) ) ? $GLOBALS['wp_scripts']->registered : array();
		$out        = array();
		$stack      = $handles;
		while ( $stack ) {
			$handle = array_pop( $stack );
			if ( isset( $out[ $handle ] ) ) {
				continue;
			}
			$out[ $handle ] = true;
			if ( isset( $registered[ $handle ] ) && ! empty( $registered[ $handle ]->deps ) ) {
				foreach ( (array) $registered[ $handle ]->deps as $dep ) {
					$stack[] = $dep;
				}
			}
		}
		return $out;
	}

	private function uses_jquery( array $script ) {
		if ( $script['handle'] && in_array( $script['handle'], self::JQUERY_HANDLES, true ) ) {
			return false;
		}
		$code = $script['code'];
		if ( $script['original'] ) {
			$path = VSO_Utils::url_to_path( $script['original'] );
			$code = $path ? (string) @file_get_contents( $path, false, null, 0, 300000 ) : ''; // phpcs:ignore
		}
		return (bool) preg_match( '#\bjQuery\b|(?<![\w.$])\$\s*\(\s*(document|window|function|[\'"])#', $code );
	}

	/**
	 * Adds "defer" to scripts that run early (not delayed), but only where that
	 * cannot change what other code sees. Deferred files keep their order, so a
	 * file can be deferred as long as nothing that runs immediately comes after
	 * it in the page (that code might use it).
	 */
	private function resolve_defer( $defer, $delay, array $defer_excl ) {
		if ( ! $defer ) {
			return;
		}
		$immediate_after = false; // Some code after this point runs right away.
		$jquery_after    = false; // ...and some of it uses jQuery.
		for ( $i = count( $this->scripts ) - 1; $i >= 0; $i-- ) {
			$script = $this->scripts[ $i ];
			if ( ! $script['exclude'] || 'module' === $script['type'] ) {
				continue; // Delayed scripts run later anyway; modules are deferred by nature.
			}
			if ( ! $script['src'] ) {
				if ( ! VSO_Compat::is_safe_inline( $script['code'] ) ) {
					$immediate_after = true;
					$jquery_after    = $jquery_after || $this->uses_jquery( $script );
				}
				continue;
			}
			if ( VSO_Utils::has_attr( $script['open'], 'async' ) || VSO_Utils::has_attr( $script['open'], 'defer' ) ) {
				continue;
			}
			// jQuery only has to stay in place for code that actually uses it;
			// any other file stays in place when anything at all runs after it.
			$is_jquery = in_array( $script['handle'], self::JQUERY_HANDLES, true )
				|| preg_match( '#/jquery(-migrate)?(\.min)?\.js#i', (string) $script['original'] );
			$blocked   = ( $is_jquery ? $jquery_after : $immediate_after )
				|| VSO_Utils::matches_any( $script['open'] . ' ' . $script['original'], array_merge( $defer_excl, self::DEFER_NEVER ) );
			if ( $blocked ) {
				$immediate_after = true;
				$jquery_after    = $jquery_after || $this->uses_jquery( $script );
			} else {
				$this->scripts[ $i ]['defer'] = true;
			}
		}
	}

	private function render( array $script ) {
		$open = $script['open'];
		$code = $script['code'];

		if ( ! $script['exclude'] ) {
			$this->delayed = true;
			$tag           = VSO_Utils::remove_attr( $open, 'type' );
			if ( 'module' === $script['type'] ) {
				$tag = VSO_Utils::set_attr( $tag, 'data-vso-type', 'module' );
			}
			if ( $script['src'] ) {
				$tag = VSO_Utils::remove_attr( $tag, 'src' );
				$tag = VSO_Utils::set_attr( $tag, 'data-vso-src', html_entity_decode( $script['src'], ENT_QUOTES ) );
			}
			$tag = VSO_Utils::set_attr( $tag, 'type', 'vso/javascript' );
			return $tag . $code . '</script>';
		}
		if ( $script['defer'] ) {
			$open = preg_replace( '#<script\b#i', '<script defer', $open, 1 );
		}
		return $open . $code . '</script>';
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
