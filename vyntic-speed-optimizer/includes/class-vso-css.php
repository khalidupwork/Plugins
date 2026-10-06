<?php
/**
 * CSS optimization: minify, font-display, async loading and a fully local
 * "Remove Unused CSS" engine (used CSS is inlined, the full stylesheet loads
 * later on user interaction).
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_CSS {

	/** @var array Selector tokens present in the current page. */
	private $used = array(
		'class' => array(),
		'id'    => array(),
		'tag'   => array(),
	);

	/** @var string[] */
	private $safelist = array();

	/** @var bool */
	private $has_delayed = false;

	/**
	 * Optimizes every stylesheet and <style> block of the page.
	 */
	public function process( $html ) {
		$rucss = VSO_Settings::enabled( 'remove_unused_css' );
		$async = ! $rucss && VSO_Settings::enabled( 'async_css' );

		if ( $rucss ) {
			$this->collect_used( $html );
		}

		$exclude = array_merge( array( 'data-vso-skip', 'admin-bar', 'vso-' ), VSO_Settings::lines( 'css_exclude' ) );

		// <link rel="stylesheet">.
		$html = preg_replace_callback(
			'#<link\b[^>]*>#i',
			function ( $m ) use ( $exclude, $rucss, $async ) {
				$tag = $m[0];
				$rel = strtolower( (string) VSO_Utils::attr( $tag, 'rel' ) );
				if ( 'stylesheet' !== $rel ) {
					return $tag;
				}
				$href = VSO_Utils::attr( $tag, 'href' );
				if ( ! $href || VSO_Utils::matches_any( $tag, $exclude ) ) {
					return $tag;
				}
				$media = strtolower( (string) VSO_Utils::attr( $tag, 'media' ) );
				if ( 'print' === $media ) {
					return $tag;
				}

				$local = VSO_Utils::url_to_path( $href );
				$css   = null;
				if ( $local && preg_match( '#\.css$#i', $local ) ) {
					$css = $this->load_file( $local, VSO_Utils::absolute_url( $href ) );
					if ( null !== $css && VSO_Settings::enabled( 'minify_css' ) ) {
						$cached = $this->cache_css( $css );
						if ( $cached ) {
							$tag = VSO_Utils::set_attr( $tag, 'href', $cached );
						}
					}
				}

				if ( $rucss && null !== $css ) {
					$used = $this->used_css( $css );
					$id   = VSO_Utils::attr( $tag, 'id' );
					$out  = '<style data-vso-used' . ( $id ? ' id="' . esc_attr( $id ) . '-used"' : '' ) . ( $media && 'all' !== $media ? ' media="' . esc_attr( $media ) . '"' : '' ) . '>' . $used . '</style>';
					$this->has_delayed = true;
					$lazy = VSO_Utils::set_attr( $tag, 'rel', 'vso-stylesheet' );
					$lazy = VSO_Utils::set_attr( $lazy, 'data-vso-css', '1' );
					return $out . $lazy . '<noscript>' . $tag . '</noscript>';
				}

				if ( $async ) {
					$async_tag = VSO_Utils::set_attr( $tag, 'media', 'print' );
					$async_tag = VSO_Utils::set_attr( $async_tag, 'onload', "this.media='" . ( $media ? $media : 'all' ) . "';this.onload=null" );
					return $async_tag . '<noscript>' . $tag . '</noscript>';
				}
				return $tag;
			},
			$html
		);

		// Inline <style> blocks: font-display + minify.
		$html = preg_replace_callback(
			'#(<style\b[^>]*>)(.*?)(</style>)#is',
			function ( $m ) {
				if ( false !== strpos( $m[1], 'data-vso-used' ) || '' === trim( $m[2] ) ) {
					return $m[0];
				}
				$css = $this->font_display( $m[2] );
				if ( VSO_Settings::enabled( 'minify_css' ) && strlen( $css ) < 300000 ) {
					$css = $this->minify( $css );
				}
				return $m[1] . $css . $m[3];
			},
			$html
		);

		$critical = trim( (string) VSO_Settings::get( 'critical_css' ) );
		if ( $async && '' !== $critical ) {
			$html = VSO_Optimizer::inject_head( $html, '<style id="vso-critical-css">' . $this->minify( $critical ) . '</style>', true );
		}

		return $html;
	}

	public function has_delayed_css() {
		return $this->has_delayed;
	}

	/* ---------------------------------------------------------------------
	 * File handling
	 * ------------------------------------------------------------------- */

	/**
	 * Reads a local stylesheet and makes all url()/@import references root-relative.
	 */
	private function load_file( $path, $url ) {
		$size = @filesize( $path ); // phpcs:ignore
		if ( ! $size || $size > 2 * MB_IN_BYTES ) {
			return null;
		}
		$css = (string) @file_get_contents( $path ); // phpcs:ignore
		$css = preg_replace( '/^\xEF\xBB\xBF/', '', $css );
		$css = $this->rewrite_urls( $css, $url );
		return $this->font_display( $css );
	}

	public function rewrite_urls( $css, $base_url ) {
		$css = preg_replace_callback(
			'#url\(\s*([\'"]?)([^\'")]+)\1\s*\)#i',
			static function ( $m ) use ( $base_url ) {
				$url = trim( $m[2] );
				if ( preg_match( '#^(data:|https?:|//|\#|%23)#i', $url ) || 0 === strpos( $url, '/' ) ) {
					return $m[0];
				}
				return 'url(' . $m[1] . VSO_CSS::relative_root( VSO_Utils::absolute_url( $url, $base_url ) ) . $m[1] . ')';
			},
			$css
		);
		return preg_replace_callback(
			'#@import\s+([\'"])([^\'"]+)\1#i',
			static function ( $m ) use ( $base_url ) {
				if ( preg_match( '#^(https?:|//|/)#i', $m[2] ) ) {
					return $m[0];
				}
				return '@import ' . $m[1] . VSO_CSS::relative_root( VSO_Utils::absolute_url( $m[2], $base_url ) ) . $m[1];
			},
			$css
		);
	}

	/**
	 * Turns a local absolute URL into "/path" so cached CSS works on http + https.
	 */
	public static function relative_root( $url ) {
		if ( VSO_Utils::is_local_url( $url ) ) {
			$parts = wp_parse_url( $url );
			return ( isset( $parts['path'] ) ? $parts['path'] : '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
		}
		return $url;
	}

	public function font_display( $css ) {
		if ( ! VSO_Settings::enabled( 'font_display_swap' ) || false === stripos( $css, '@font-face' ) ) {
			return $css;
		}
		return preg_replace_callback(
			'#@font-face\s*\{([^}]*)\}#i',
			static function ( $m ) {
				if ( false !== stripos( $m[1], 'font-display' ) ) {
					return preg_replace( '#font-display\s*:\s*(auto|block|fallback)#i', 'font-display:swap', $m[0] );
				}
				return '@font-face{font-display:swap;' . ltrim( $m[1] ) . '}';
			},
			$css
		);
	}

	public function minify( $css ) {
		if ( ! class_exists( 'MatthiasMullie\\Minify\\CSS' ) ) {
			return $css;
		}
		try {
			$minifier = new MatthiasMullie\Minify\CSS();
			$minifier->setMaxImportSize( 0 );
			$minifier->add( $css );
			$out = $minifier->minify();
			return '' !== trim( $out ) ? $out : $css;
		} catch ( Exception $e ) {
			return $css;
		}
	}

	/**
	 * Stores minified CSS in the asset cache and returns its URL.
	 */
	private function cache_css( $css ) {
		$hash = substr( md5( $css . VSO_VERSION ), 0, 16 );
		$file = VSO_CACHE_DIR . 'assets/css/' . $hash . '.css';
		if ( ! is_file( $file ) ) {
			if ( ! VSO_Utils::write_file( $file, $this->minify( $css ) ) ) {
				return false;
			}
		}
		return VSO_CSS::relative_root( VSO_CACHE_URL . 'assets/css/' . $hash . '.css' );
	}

	/* ---------------------------------------------------------------------
	 * Remove Unused CSS
	 * ------------------------------------------------------------------- */

	/**
	 * Builds the set of classes / ids / tags that appear in the page.
	 */
	public function collect_used( $html ) {
		// Ignore what's inside scripts, styles and comments.
		$clean = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>|<!--.*?-->#is', '', $html );

		if ( preg_match_all( '#\sclass\s*=\s*(["\'])(.*?)\1#is', $clean, $m ) ) {
			foreach ( $m[2] as $list ) {
				foreach ( preg_split( '/\s+/', html_entity_decode( $list, ENT_QUOTES ) ) as $class ) {
					if ( '' !== $class ) {
						$this->used['class'][ $class ] = true;
					}
				}
			}
		}
		if ( preg_match_all( '#\sid\s*=\s*(["\'])(.*?)\1#is', $clean, $m ) ) {
			foreach ( $m[2] as $id ) {
				$this->used['id'][ html_entity_decode( trim( $id ), ENT_QUOTES ) ] = true;
			}
		}
		if ( preg_match_all( '#<([a-z][a-z0-9-]*)#i', $clean, $m ) ) {
			foreach ( $m[1] as $tag ) {
				$this->used['tag'][ strtolower( $tag ) ] = true;
			}
		}

		// Elementor / theme entrance animations add their class names from JSON settings.
		if ( preg_match_all( '#(?:&quot;|")_?animation(?:_[a-z]+)?(?:&quot;|")\s*:\s*(?:&quot;|")([a-zA-Z]+)(?:&quot;|")#', $html, $m ) ) {
			foreach ( $m[1] as $anim ) {
				$this->used['class'][ $anim ] = true;
			}
			$this->used['class']['animated'] = true;
		}

		$this->safelist = array_merge(
			array( 'is-open', 'is-active', 'active', 'open', 'show', 'visible', 'loaded', 'lazyloaded', 'menu-open', 'toggled', 'focus', 'expanded' ),
			VSO_Settings::lines( 'unused_css_safelist' )
		);
		foreach ( $this->safelist as $item ) {
			if ( false === strpos( $item, '*' ) ) {
				$item = ltrim( $item, '.#' );
				$this->used['class'][ $item ] = true;
				$this->used['id'][ $item ]    = true;
			}
		}
	}

	/**
	 * Returns only the rules of $css that can match the current page.
	 */
	public function used_css( $css ) {
		// The parsed tree is built from minified CSS, so the output needs no second pass.
		return $this->filter_nodes( $this->parse_cached( $css ) );
	}

	private function parse_cached( $css ) {
		$hash = md5( $css );
		$file = VSO_CACHE_DIR . 'assets/parsed/' . $hash . '.php';
		if ( is_file( $file ) ) {
			$tree = @unserialize( (string) file_get_contents( $file ), array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( is_array( $tree ) ) {
				return $tree;
			}
		}
		$tree = self::parse( $this->minify( $css ) );
		VSO_Utils::write_file( $file, serialize( $tree ) ); // phpcs:ignore
		return $tree;
	}

	private function filter_nodes( array $nodes ) {
		$out = '';
		foreach ( $nodes as $node ) {
			switch ( $node['t'] ) {
				case 'raw':
					$out .= $node['c'];
					break;
				case 'group':
					$inner = $this->filter_nodes( $node['c'] );
					if ( '' !== $inner ) {
						$out .= $node['p'] . '{' . $inner . '}';
					}
					break;
				case 'rule':
					foreach ( $node['s'] as $selector ) {
						if ( $this->selector_used( $selector ) ) {
							$out .= $node['p'] . '{' . $node['b'] . '}';
							break;
						}
					}
					break;
			}
		}
		return $out;
	}

	/**
	 * True when every class, id and tag the selector needs exists in the page.
	 *
	 * @param array $selector Pre-tokenised selector: ['c'=>[], 'i'=>[], 'g'=>[]].
	 */
	private function selector_used( array $selector ) {
		foreach ( $selector['g'] as $tag ) {
			if ( ! isset( $this->used['tag'][ $tag ] ) ) {
				return false;
			}
		}
		foreach ( $selector['i'] as $id ) {
			if ( ! isset( $this->used['id'][ $id ] ) && ! $this->safelisted( $id ) ) {
				return false;
			}
		}
		foreach ( $selector['c'] as $class ) {
			if ( ! isset( $this->used['class'][ $class ] ) && ! $this->safelisted( $class ) ) {
				return false;
			}
		}
		return true;
	}

	private function safelisted( $name ) {
		foreach ( $this->safelist as $pattern ) {
			if ( false !== strpos( $pattern, '*' ) ) {
				$regex = '#^' . str_replace( '\*', '.*', preg_quote( ltrim( $pattern, '.#' ), '#' ) ) . '$#i';
				if ( preg_match( $regex, $name ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Small, fast CSS parser. Produces:
	 *  - ['t'=>'raw',   'c'=>text]                 @font-face, @keyframes, @import, ...
	 *  - ['t'=>'group', 'p'=>'@media x', 'c'=>[...]] @media / @supports / @layer / @container
	 *  - ['t'=>'rule',  'p'=>selectors, 'b'=>body, 's'=>tokenised selectors]
	 */
	public static function parse( $css ) {
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$pos = 0;
		return self::parse_block( $css, $pos, strlen( $css ) );
	}

	private static function parse_block( $css, &$pos, $len ) {
		$nodes = array();
		while ( $pos < $len ) {
			// Skip whitespace.
			$pos += strspn( $css, " \t\r\n", $pos );
			if ( $pos >= $len ) {
				break;
			}
			if ( '}' === $css[ $pos ] ) {
				++$pos;
				return $nodes;
			}

			$start   = $pos;
			$end     = self::scan_until( $css, $pos, $len, '{;}' );
			$prelude = trim( substr( $css, $start, $end - $start ) );
			$pos     = $end;

			if ( $pos >= $len ) {
				if ( '' !== $prelude ) {
					$nodes[] = array( 't' => 'raw', 'c' => $prelude );
				}
				break;
			}

			$char = $css[ $pos ];
			if ( ';' === $char ) {
				++$pos;
				if ( '' !== $prelude ) {
					$nodes[] = array( 't' => 'raw', 'c' => $prelude . ';' );
				}
				continue;
			}
			if ( '}' === $char ) {
				// Stray declaration without block; drop it.
				continue;
			}

			// $char === '{'.
			++$pos;
			if ( preg_match( '#^@(-[a-z]+-)?(media|supports|layer|container|document|scope)\b#i', $prelude ) ) {
				$children = self::parse_block( $css, $pos, $len );
				$nodes[]  = array(
					't' => 'group',
					'p' => $prelude,
					'c' => $children,
				);
				continue;
			}

			$body_start = $pos;
			$body_end   = self::matching_brace( $css, $pos, $len );
			$body       = substr( $css, $body_start, $body_end - $body_start );
			$pos        = min( $len, $body_end + 1 );

			if ( '@' === substr( $prelude, 0, 1 ) ) {
				$nodes[] = array( 't' => 'raw', 'c' => $prelude . '{' . $body . '}' );
				continue;
			}

			$nodes[] = array(
				't' => 'rule',
				'p' => $prelude,
				'b' => $body,
				's' => self::tokenize_selectors( $prelude ),
			);
		}
		return $nodes;
	}

	/**
	 * Advances to the first unquoted, un-parenthesised char from $stops.
	 */
	private static function scan_until( $css, $pos, $len, $stops ) {
		$depth = 0;
		$mask  = $stops . '"\'()';
		while ( $pos < $len ) {
			$pos += strcspn( $css, $mask, $pos );
			if ( $pos >= $len ) {
				return $len;
			}
			$c = $css[ $pos ];
			if ( '"' === $c || "'" === $c ) {
				$pos = self::skip_string( $css, $pos, $len );
				continue;
			}
			if ( '(' === $c ) {
				++$depth;
			} elseif ( ')' === $c ) {
				$depth = max( 0, $depth - 1 );
			} elseif ( 0 === $depth ) {
				return $pos;
			}
			++$pos;
		}
		return $len;
	}

	private static function matching_brace( $css, $pos, $len ) {
		$depth = 1;
		while ( $pos < $len ) {
			$pos += strcspn( $css, '{}"\'', $pos );
			if ( $pos >= $len ) {
				return $len;
			}
			$c = $css[ $pos ];
			if ( '"' === $c || "'" === $c ) {
				$pos = self::skip_string( $css, $pos, $len );
				continue;
			}
			if ( '{' === $c ) {
				++$depth;
			} else {
				--$depth;
				if ( 0 === $depth ) {
					return $pos;
				}
			}
			++$pos;
		}
		return $len;
	}

	private static function skip_string( $css, $pos, $len ) {
		$quote = $css[ $pos ];
		++$pos;
		while ( $pos < $len ) {
			$c = $css[ $pos ];
			if ( '\\' === $c ) {
				$pos += 2;
				continue;
			}
			++$pos;
			if ( $c === $quote ) {
				break;
			}
		}
		return $pos;
	}

	/**
	 * Splits a selector list and extracts required classes / ids / tags per selector.
	 */
	public static function tokenize_selectors( $prelude ) {
		$list   = array();
		$depth  = 0;
		$buffer = '';
		$length = strlen( $prelude );
		for ( $i = 0; $i < $length; $i++ ) {
			$c = $prelude[ $i ];
			if ( '\\' === $c && $i + 1 < $length ) {
				$buffer .= $c . $prelude[ ++$i ];
				continue;
			}
			if ( '(' === $c || '[' === $c ) {
				++$depth;
			} elseif ( ')' === $c || ']' === $c ) {
				--$depth;
			} elseif ( ',' === $c && 0 === $depth ) {
				$list[] = $buffer;
				$buffer = '';
				continue;
			}
			$buffer .= $c;
		}
		$list[] = $buffer;

		$out = array();
		foreach ( $list as $selector ) {
			$out[] = self::tokenize_selector( trim( $selector ) );
		}
		return $out;
	}

	private static function tokenize_selector( $selector ) {
		// Drop attribute selectors and pseudo classes/elements (with nested arguments).
		$s = preg_replace( '#\[(?:[^\]"\']|"[^"]*"|\'[^\']*\')*\]#', '', $selector );
		$s = preg_replace( '#(?<!\\\\)::?[a-zA-Z-]+(\((?:[^()]++|(?1))*\))?#', '', (string) $s );

		$tokens = array(
			'c' => array(),
			'i' => array(),
			'g' => array(),
		);
		if ( preg_match_all( '#\.((?:\\\\.|[\w\-\x80-\xff])+)#', (string) $s, $m ) ) {
			foreach ( $m[1] as $class ) {
				$tokens['c'][] = self::unescape( $class );
			}
		}
		if ( preg_match_all( '#\#((?:\\\\.|[\w\-\x80-\xff])+)#', (string) $s, $m ) ) {
			foreach ( $m[1] as $id ) {
				$tokens['i'][] = self::unescape( $id );
			}
		}
		foreach ( preg_split( '#\s*[\s>+~]\s*#', trim( (string) $s ) ) as $compound ) {
			if ( preg_match( '#^([a-zA-Z][a-zA-Z0-9-]*)#', $compound, $m ) ) {
				$tag = strtolower( $m[1] );
				// Selectors like "from"/"to" or percentages never reach here; skip root-ish tags.
				if ( ! in_array( $tag, array( 'html', 'body' ), true ) ) {
					$tokens['g'][] = $tag;
				}
			}
		}
		return $tokens;
	}

	private static function unescape( $name ) {
		if ( false === strpos( $name, '\\' ) ) {
			return $name;
		}
		$name = preg_replace_callback(
			'#\\\\([0-9a-fA-F]{1,6})\s?#',
			static function ( $m ) {
				return mb_chr( hexdec( $m[1] ), 'UTF-8' );
			},
			$name
		);
		return preg_replace( '#\\\\(.)#s', '$1', $name );
	}
}
