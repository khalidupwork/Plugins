<?php
/**
 * WordPress bloat removal: emojis, embeds, dashicons, jQuery Migrate, heartbeat, etc.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Tweaks {

	public static function init() {
		if ( VSO_Settings::enabled( 'disable_emojis' ) ) {
			self::disable_emojis();
		}
		if ( VSO_Settings::enabled( 'disable_embeds' ) ) {
			add_action( 'init', array( __CLASS__, 'disable_embeds' ), 9999 );
		}
		if ( VSO_Settings::enabled( 'disable_dashicons' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_dashicons' ), 100 );
		}
		if ( VSO_Settings::enabled( 'remove_jquery_migrate' ) ) {
			add_action( 'wp_default_scripts', array( __CLASS__, 'remove_jquery_migrate' ) );
		}
		if ( VSO_Settings::enabled( 'remove_query_strings' ) ) {
			add_filter( 'script_loader_src', array( __CLASS__, 'strip_ver' ), 15 );
			add_filter( 'style_loader_src', array( __CLASS__, 'strip_ver' ), 15 );
		}
		if ( VSO_Settings::enabled( 'disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );
			remove_action( 'wp_head', 'rsd_link' );
		}
		if ( VSO_Settings::enabled( 'remove_wp_meta' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
			add_filter( 'the_generator', '__return_empty_string' );
		}
		$heartbeat = VSO_Settings::get( 'heartbeat' );
		if ( 'disable' === $heartbeat ) {
			add_action( 'init', array( __CLASS__, 'disable_heartbeat' ), 1 );
		} elseif ( 'reduce' === $heartbeat ) {
			add_filter( 'heartbeat_settings', array( __CLASS__, 'slow_heartbeat' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_heartbeat_off' ), 100 );
		}
	}

	public static function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'embed_head', 'print_emoji_detection_script' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_action(
			'wp_enqueue_scripts',
			static function () {
				wp_dequeue_style( 'wp-emoji-styles' );
			},
			100
		);
		add_filter(
			'tiny_mce_plugins',
			static function ( $plugins ) {
				return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
			}
		);
		add_filter(
			'wp_resource_hints',
			static function ( $urls, $relation ) {
				if ( 'dns-prefetch' === $relation ) {
					$urls = array_filter(
						$urls,
						static function ( $url ) {
							return false === strpos( is_array( $url ) ? (string) ( $url['href'] ?? '' ) : (string) $url, 's.w.org/images/core/emoji' );
						}
					);
				}
				return $urls;
			},
			10,
			2
		);
	}

	public static function disable_embeds() {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		add_filter( 'embed_oembed_discover', '__return_false' );
		add_action(
			'wp_footer',
			static function () {
				wp_dequeue_script( 'wp-embed' );
			}
		);
	}

	public static function dequeue_dashicons() {
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
			wp_deregister_style( 'dashicons' );
		}
	}

	public static function remove_jquery_migrate( $scripts ) {
		if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
			return;
		}
		$scripts->registered['jquery']->deps = array_diff( (array) $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
	}

	public static function strip_ver( $src ) {
		if ( is_string( $src ) && false !== strpos( $src, 'ver=' ) && VSO_Utils::is_local_url( $src ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	public static function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public static function disable_heartbeat() {
		global $pagenow;
		// Keep it in the post editor (autosave + post locking).
		if ( is_admin() && in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			add_filter( 'heartbeat_settings', array( __CLASS__, 'slow_heartbeat' ) );
			return;
		}
		wp_deregister_script( 'heartbeat' );
	}

	public static function slow_heartbeat( $settings ) {
		$settings['interval'] = 60;
		return $settings;
	}

	public static function frontend_heartbeat_off() {
		if ( ! is_admin() ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	/* ---------------------------------------------------------------------
	 * .htaccess browser caching + compression
	 * ------------------------------------------------------------------- */

	public static function htaccess_rules() {
		return array(
			'<IfModule mod_mime.c>',
			'AddType image/webp .webp',
			'AddType image/avif .avif',
			'AddType font/woff2 .woff2',
			'</IfModule>',
			'<IfModule mod_deflate.c>',
			'AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml text/javascript application/javascript application/x-javascript application/json application/xml application/rss+xml image/svg+xml font/ttf font/otf',
			'</IfModule>',
			'<IfModule mod_expires.c>',
			'ExpiresActive On',
			'ExpiresDefault "access plus 1 month"',
			'ExpiresByType text/html "access plus 0 seconds"',
			'ExpiresByType text/css "access plus 1 year"',
			'ExpiresByType text/javascript "access plus 1 year"',
			'ExpiresByType application/javascript "access plus 1 year"',
			'ExpiresByType image/jpeg "access plus 1 year"',
			'ExpiresByType image/png "access plus 1 year"',
			'ExpiresByType image/gif "access plus 1 year"',
			'ExpiresByType image/webp "access plus 1 year"',
			'ExpiresByType image/avif "access plus 1 year"',
			'ExpiresByType image/svg+xml "access plus 1 year"',
			'ExpiresByType image/x-icon "access plus 1 year"',
			'ExpiresByType font/woff2 "access plus 1 year"',
			'ExpiresByType font/woff "access plus 1 year"',
			'ExpiresByType font/ttf "access plus 1 year"',
			'ExpiresByType video/mp4 "access plus 1 year"',
			'</IfModule>',
			'<IfModule mod_headers.c>',
			'<FilesMatch "\.(css|js|jpe?g|png|gif|webp|avif|svg|ico|woff2?|ttf|otf|mp4)$">',
			'Header set Cache-Control "public, max-age=31536000"',
			'</FilesMatch>',
			'</IfModule>',
		);
	}

	public static function sync_htaccess() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		$file = get_home_path() . '.htaccess';
		if ( ! file_exists( $file ) && ! VSO_Settings::enabled( 'browser_cache' ) ) {
			return;
		}
		if ( ( file_exists( $file ) && ! wp_is_writable( $file ) ) || ( ! file_exists( $file ) && ! wp_is_writable( dirname( $file ) ) ) ) {
			return;
		}
		insert_with_markers( $file, 'Vyntic Speed Optimizer', VSO_Settings::enabled( 'browser_cache' ) ? self::htaccess_rules() : array() );
	}
}
