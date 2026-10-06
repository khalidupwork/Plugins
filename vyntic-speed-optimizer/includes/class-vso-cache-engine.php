<?php
/**
 * Page cache engine. Loaded by the advanced-cache.php drop-in BEFORE WordPress
 * boots, so it must not use any WordPress function.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'VSO_Cache_Engine' ) ) {
	return;
}

class VSO_Cache_Engine {

	/** Cookies that always mean "personalised page – do not serve cache". */
	const BYPASS_COOKIES = array(
		'wordpress_logged_in_',
		'wp-postpass_',
		'comment_author_',
		'woocommerce_items_in_cart',
		'woocommerce_cart_hash',
		'wp_woocommerce_session_',
		'edd_items_in_cart',
		'wordpress_no_cache',
	);

	/** URL fragments that are never cached. */
	const BYPASS_PATHS = array(
		'/wp-admin',
		'/wp-login.php',
		'/wp-register.php',
		'/wp-json',
		'/xmlrpc.php',
		'/wp-cron.php',
		'/wp-comments-post.php',
		'/feed',
		'.xml',
		'.txt',
		'/robots.txt',
		'/wc-api/',
		'/?rest_route',
	);

	/**
	 * Called from the drop-in: serves a cached page and exits, or returns quietly.
	 */
	public static function serve( array $config ) {
		if ( ! empty( $_GET['vso_nocache'] ) || ! empty( $_GET['vso_off'] ) ) { // phpcs:ignore
			return;
		}
		$file = self::cache_file( $config );
		if ( ! $file || self::should_bypass( $config ) ) {
			return;
		}
		if ( ! is_file( $file ) ) {
			header( 'X-Vyntic-Cache: MISS' );
			return;
		}

		$mtime    = (int) @filemtime( $file );
		$lifespan = isset( $config['lifespan'] ) ? (int) $config['lifespan'] : 0;
		if ( $lifespan > 0 && $mtime + $lifespan < time() ) {
			header( 'X-Vyntic-Cache: EXPIRED' );
			return;
		}

		$charset = ! empty( $config['charset'] ) ? $config['charset'] : 'UTF-8';
		header( 'Content-Type: text/html; charset=' . $charset );
		header( 'X-Vyntic-Cache: HIT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT' );
		header( 'Vary: Accept-Encoding' . ( ! empty( $config['mobile'] ) ? ', User-Agent' : '' ) );
		header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );

		if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) >= $mtime ) { // phpcs:ignore
			header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1' ) . ' 304 Not Modified', true, 304 ); // phpcs:ignore
			exit;
		}

		$accept   = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : ''; // phpcs:ignore
		$zlib_on  = in_array( strtolower( (string) ini_get( 'zlib.output_compression' ) ), array( '1', 'on' ), true );
		$use_gzip = ! $zlib_on && false !== stripos( $accept, 'gzip' ) && is_file( $file . '.gz' ) && ! headers_sent();

		if ( $use_gzip ) {
			header( 'Content-Encoding: gzip' );
			header( 'Content-Length: ' . filesize( $file . '.gz' ) );
			readfile( $file . '.gz' );
		} else {
			readfile( $file );
		}
		exit;
	}

	/**
	 * True when this request must never be answered from / stored in the cache.
	 */
	public static function should_bypass( array $config ) {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET'; // phpcs:ignore
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) || defined( 'DOING_CRON' ) || defined( 'DOING_AJAX' ) || defined( 'REST_REQUEST' ) ) {
			return true;
		}

		$cookies = array_merge( self::BYPASS_COOKIES, isset( $config['cookies'] ) ? (array) $config['cookies'] : array() );
		if ( ! empty( $config['logged_in'] ) ) {
			$cookies = array_diff( $cookies, array( 'wordpress_logged_in_' ) );
		}
		foreach ( array_keys( $_COOKIE ) as $name ) {
			foreach ( $cookies as $needle ) {
				if ( '' !== $needle && 0 === stripos( (string) $name, $needle ) ) {
					return true;
				}
			}
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/'; // phpcs:ignore
		foreach ( self::BYPASS_PATHS as $needle ) {
			if ( false !== stripos( $uri, $needle ) ) {
				return true;
			}
		}
		if ( preg_match( '#\.php($|\?)#i', strtok( $uri, '?' ) ) && false === stripos( $uri, '/index.php' ) ) {
			return true;
		}
		return self::is_excluded_url( $uri, isset( $config['exclude'] ) ? (array) $config['exclude'] : array() );
	}

	/**
	 * Matches a URI against exclusion patterns. "*" is a wildcard; otherwise "contains".
	 */
	public static function is_excluded_url( $uri, array $patterns ) {
		$path = (string) strtok( $uri, '?' );
		foreach ( $patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}
			// Allow full URLs in the setting: compare their path only.
			if ( preg_match( '#^https?://#i', $pattern ) ) {
				$pattern = (string) parse_url( $pattern, PHP_URL_PATH ); // phpcs:ignore
				if ( '' === $pattern ) {
					$pattern = '/';
				}
			}
			if ( '/' === $pattern ) {
				// A lone "/" means "the home page", not "every URL".
				if ( '/' === $path || '' === $path ) {
					return true;
				}
				continue;
			}
			if ( false !== strpos( $pattern, '*' ) ) {
				$regex = '#' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '#i';
				if ( preg_match( $regex, $uri ) ) {
					return true;
				}
			} elseif ( false !== stripos( $uri, $pattern ) || ( '/' !== $pattern && rtrim( $path, '/' ) === rtrim( $pattern, '/' ) ) ) {
				return true;
			}
		}
		return false;
	}

	public static function is_mobile() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore
		return (bool) preg_match( '/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi|iPhone|iPod/i', $ua );
	}

	/**
	 * Cache file for the current request, or false when the URL can't be cached.
	 */
	public static function cache_file( array $config, $uri = null, $host = null, $https = null, $mobile = null ) {
		$uri   = null === $uri ? ( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/' ) : $uri; // phpcs:ignore
		$host  = null === $host ? ( isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '' ) : $host; // phpcs:ignore
		$https = null === $https ? self::is_https() : $https;

		$host = strtolower( preg_replace( '/[^a-z0-9.\-:]/i', '', $host ) );
		$host = str_replace( ':', '_', $host );
		if ( '' === $host ) {
			return false;
		}

		$parts = explode( '?', $uri, 2 );
		$path  = $parts[0];
		if ( isset( $parts[1] ) && '' !== $parts[1] ) {
			parse_str( $parts[1], $query );
			$ignore = isset( $config['ignore_params'] ) ? (array) $config['ignore_params'] : array();
			foreach ( array_keys( $query ) as $key ) {
				if ( in_array( strtolower( (string) $key ), $ignore, true ) ) {
					unset( $query[ $key ] );
				}
			}
			if ( ! empty( $query ) ) {
				return false; // Unknown query string = dynamic page.
			}
		}

		$path = rawurldecode( $path );
		if ( false !== strpos( $path, '..' ) || preg_match( '/[\x00-\x1f<>"|\\\\]/', $path ) ) {
			return false;
		}
		$path = trim( preg_replace( '#/+#', '/', $path ), '/' );
		if ( strlen( $path ) > 400 ) {
			return false;
		}

		$suffix = '';
		if ( null === $mobile ) {
			$mobile = ! empty( $config['mobile'] ) && self::is_mobile();
		}
		if ( $mobile ) {
			$suffix .= '-mobile';
		}
		if ( $https ) {
			$suffix .= '-https';
		}

		$dir = rtrim( $config['dir'], '/' ) . '/pages/' . $host . ( '' !== $path ? '/' . $path : '' );
		return $dir . '/index' . $suffix . '.html';
	}

	public static function is_https() {
		if ( isset( $_SERVER['HTTPS'] ) && ( 'on' === strtolower( (string) $_SERVER['HTTPS'] ) || '1' === (string) $_SERVER['HTTPS'] ) ) { // phpcs:ignore
			return true;
		}
		if ( isset( $_SERVER['SERVER_PORT'] ) && '443' === (string) $_SERVER['SERVER_PORT'] ) { // phpcs:ignore
			return true;
		}
		return isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] ); // phpcs:ignore
	}
}
