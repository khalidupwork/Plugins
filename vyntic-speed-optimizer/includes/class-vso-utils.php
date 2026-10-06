<?php
/**
 * Shared helpers: URL/path mapping, cache files, pattern matching.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Utils {

	/**
	 * Absolute site URL without trailing slash, scheme-less ("//example.com").
	 */
	public static function site_host_url() {
		static $url = null;
		if ( null === $url ) {
			$url = preg_replace( '#^https?:#i', '', untrailingslashit( site_url() ) );
		}
		return $url;
	}

	/**
	 * Makes a URL absolute ("//x", "/x", "x" relative to $base).
	 */
	public static function absolute_url( $url, $base = '' ) {
		$url = trim( html_entity_decode( $url, ENT_QUOTES ) );
		if ( '' === $url || preg_match( '#^(data:|https?:|blob:|\#)#i', $url ) ) {
			return $url;
		}
		$scheme = is_ssl() ? 'https:' : 'http:';
		if ( 0 === strpos( $url, '//' ) ) {
			return $scheme . $url;
		}
		$home   = wp_parse_url( home_url() );
		$origin = ( isset( $home['scheme'] ) ? $home['scheme'] : 'https' ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		if ( 0 === strpos( $url, '/' ) ) {
			return $origin . $url;
		}
		if ( '' === $base ) {
			return $origin . '/' . $url;
		}
		$base_dir = preg_replace( '#[^/]*$#', '', strtok( $base, '?#' ) );
		$full     = $base_dir . $url;
		// Resolve ./ and ../ segments.
		$parts = wp_parse_url( $full );
		$path  = isset( $parts['path'] ) ? $parts['path'] : '/';
		$segs  = array();
		foreach ( explode( '/', $path ) as $seg ) {
			if ( '..' === $seg ) {
				array_pop( $segs );
			} elseif ( '.' !== $seg ) {
				$segs[] = $seg;
			}
		}
		$path = implode( '/', $segs );
		$out  = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//' ) . ( isset( $parts['host'] ) ? $parts['host'] : '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $path;
		if ( isset( $parts['query'] ) ) {
			$out .= '?' . $parts['query'];
		}
		if ( isset( $parts['fragment'] ) ) {
			$out .= '#' . $parts['fragment'];
		}
		return $out;
	}

	/**
	 * Returns true when the URL points at this site.
	 */
	public static function is_local_url( $url ) {
		$url = trim( $url );
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return true;
		}
		$host = wp_parse_url( self::absolute_url( $url ), PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$site = wp_parse_url( site_url(), PHP_URL_HOST );
		return $host && ( strcasecmp( $host, (string) $home ) === 0 || strcasecmp( $host, (string) $site ) === 0 );
	}

	/**
	 * Maps a local URL to a file path inside the WordPress install, or false.
	 */
	public static function url_to_path( $url ) {
		if ( ! self::is_local_url( $url ) ) {
			return false;
		}
		$url  = self::absolute_url( $url );
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}
		$path = rawurldecode( $path );

		$candidates = array(
			array( wp_parse_url( content_url(), PHP_URL_PATH ), WP_CONTENT_DIR ),
			array( wp_parse_url( includes_url(), PHP_URL_PATH ), ABSPATH . WPINC ),
			array( wp_parse_url( site_url( '/' ), PHP_URL_PATH ), ABSPATH ),
		);
		foreach ( $candidates as $pair ) {
			$prefix = untrailingslashit( (string) $pair[0] );
			if ( '' === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
				$file = untrailingslashit( $pair[1] ) . substr( $path, strlen( $prefix ) );
				if ( false === strpos( $file, '..' ) && is_file( $file ) ) {
					return $file;
				}
			}
		}
		return false;
	}

	/**
	 * Case-insensitive "contains any of" check used by all exclusion lists.
	 */
	public static function matches_any( $haystack, array $needles ) {
		foreach ( $needles as $needle ) {
			if ( '' !== $needle && false !== stripos( $haystack, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the first needle found in the haystack, or null.
	 */
	public static function first_match( $haystack, array $needles ) {
		foreach ( $needles as $needle ) {
			if ( '' !== $needle && false !== stripos( $haystack, $needle ) ) {
				return $needle;
			}
		}
		return null;
	}

	public static function ensure_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$index = trailingslashit( $dir ) . 'index.html';
		if ( is_dir( $dir ) && ! file_exists( $index ) ) {
			@file_put_contents( $index, '' ); // phpcs:ignore
		}
		return is_dir( $dir ) && wp_is_writable( $dir );
	}

	/**
	 * Writes a file atomically (temp file + rename) so readers never see half a file.
	 */
	public static function write_file( $file, $contents ) {
		if ( ! self::ensure_dir( dirname( $file ) ) ) {
			return false;
		}
		$tmp = $file . '.' . uniqid( '', true ) . '.tmp';
		if ( false === @file_put_contents( $tmp, $contents ) ) { // phpcs:ignore
			return false;
		}
		return @rename( $tmp, $file ); // phpcs:ignore
	}

	/**
	 * Deletes a directory tree. Refuses to touch anything outside the Vyntic cache dir.
	 */
	public static function rrmdir( $dir ) {
		$dir  = untrailingslashit( $dir );
		$base = untrailingslashit( VSO_CACHE_DIR );
		if ( '' === $dir || 0 !== strpos( $dir, $base ) || ! is_dir( $dir ) ) {
			return;
		}
		$items = @scandir( $dir ); // phpcs:ignore
		if ( ! $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				self::rrmdir( $path );
			} else {
				@unlink( $path ); // phpcs:ignore
			}
		}
		@rmdir( $dir ); // phpcs:ignore
	}

	/**
	 * Returns [file count, bytes] for a directory tree.
	 */
	public static function dir_stats( $dir ) {
		$count = 0;
		$size  = 0;
		if ( ! is_dir( $dir ) ) {
			return array( 0, 0 );
		}
		try {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( $file->isFile() && 'index.html' !== $file->getFilename() ) {
					++$count;
					$size += $file->getSize();
				}
			}
		} catch ( Exception $e ) {
			return array( $count, $size );
		}
		return array( $count, $size );
	}

	/**
	 * Reads an attribute value from a raw HTML tag.
	 */
	public static function attr( $tag, $name ) {
		if ( preg_match( '#\s' . preg_quote( $name, '#' ) . '\s*=\s*(["\'])(.*?)\1#is', $tag, $m ) ) {
			return $m[2];
		}
		if ( preg_match( '#\s' . preg_quote( $name, '#' ) . '\s*=\s*([^\s>"\']+)#is', $tag, $m ) ) {
			return $m[1];
		}
		return null;
	}

	public static function has_attr( $tag, $name ) {
		return (bool) preg_match( '#<[^>]*\s' . preg_quote( $name, '#' ) . '(\s*=|[\s/>])#is', $tag );
	}

	/**
	 * Sets (or replaces) an attribute on the opening tag.
	 */
	public static function set_attr( $tag, $name, $value ) {
		$value = esc_attr( $value );
		$quoted = '#(\s' . preg_quote( $name, '#' ) . '\s*=\s*)(["\']).*?\2#is';
		if ( preg_match( $quoted, $tag ) ) {
			return preg_replace( $quoted, '$1"' . str_replace( array( '\\', '$' ), array( '\\\\', '\$' ), $value ) . '"', $tag, 1 );
		}
		$bare = '#(\s' . preg_quote( $name, '#' ) . ')(\s*=\s*[^\s>"\']+)?(?=[\s/>])#is';
		if ( preg_match( $bare, $tag ) ) {
			return preg_replace( $bare, '$1="' . str_replace( array( '\\', '$' ), array( '\\\\', '\$' ), $value ) . '"', $tag, 1 );
		}
		return preg_replace( '#^<([a-z0-9-]+)#i', '<$1 ' . $name . '="' . str_replace( array( '\\', '$' ), array( '\\\\', '\$' ), $value ) . '"', $tag, 1 );
	}

	public static function remove_attr( $tag, $name ) {
		return preg_replace( '#\s' . preg_quote( $name, '#' ) . '(\s*=\s*(["\']).*?\2|\s*=\s*[^\s>"\']+)?(?=[\s/>])#is', '', $tag );
	}

	public static function format_bytes( $bytes ) {
		return size_format( (int) $bytes, 1 ) ?: '0 B';
	}
}
