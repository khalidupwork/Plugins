<?php
/**
 * Settings storage, defaults and one-click optimization presets.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Settings {

	const OPTION = 'vso_settings';

	/** @var array|null */
	private static $cache = null;

	/**
	 * Every setting with its default value. Booleans are stored as 0/1.
	 */
	public static function defaults() {
		return array(
			'level'                 => 'balanced',

			// Page cache.
			'page_cache'            => 1,
			'cache_lifespan'        => 10, // Hours, 0 = never expire.
			'cache_mobile'          => 0,  // Separate cache for mobile devices.
			'cache_logged_in'       => 0,
			'cache_exclude_urls'    => "/cart/\n/checkout/\n/my-account/",
			'cache_exclude_cookies' => '',
			'cache_ignore_params'   => 'utm_source,utm_medium,utm_campaign,utm_term,utm_content,utm_id,gclid,fbclid,msclkid,mc_cid,mc_eid,_ga,ref',
			'cache_preload'         => 1,
			'browser_cache'         => 1,  // .htaccess expires + gzip rules.

			// HTML.
			'minify_html'           => 1,

			// CSS.
			'minify_css'            => 1,
			'remove_unused_css'     => 0,
			'unused_css_safelist'   => '',
			'full_css_load'         => 'interaction', // interaction | onload.
			'async_css'             => 0,
			'critical_css'          => '',
			'css_exclude'           => '',

			// JavaScript.
			'minify_js'             => 0,
			'defer_js'              => 1,
			'defer_exclude'         => '',
			'delay_js'              => 1,
			'delay_js_timeout'      => 0, // Seconds; 0 = wait for user interaction only.
			'delay_js_exclude'      => '',
			'compat_cookie'         => 1,
			'compat_sliders'        => 1,
			'compat_lazyload'       => 1,
			'tracking_mode'         => 'delay', // delay | normal.

			// Media.
			'lazy_images'           => 1,
			'lazy_skip'             => 3,
			'lcp_priority'          => 1,
			'add_dimensions'        => 1,
			'lazy_iframes'          => 1,
			'youtube_facade'        => 1,
			'webp'                  => 1,
			'webp_quality'          => 80,
			'lazy_exclude'          => '',

			// Fonts & hints.
			'font_display_swap'     => 1,
			'local_google_fonts'    => 1,
			'preconnect'            => '',
			'preload'               => '',
			'link_prefetch'         => 1,

			// Tweaks.
			'disable_emojis'        => 1,
			'disable_embeds'        => 1,
			'remove_jquery_migrate' => 0,
			'disable_dashicons'     => 1,
			'remove_query_strings'  => 0,
			'disable_xmlrpc'        => 0,
			'heartbeat'             => 'reduce', // default | reduce | disable.
			'remove_wp_meta'        => 1,

			// Scope.
			'optimize_logged_in'    => 0,
			'exclude_urls'          => '',

			// Database.
			'db_auto_clean'         => 0,

			// Tools.
			'psi_api_key'           => '',
		);
	}

	/**
	 * One-click optimization levels. Each preset only overrides the keys it lists.
	 */
	public static function presets() {
		$off = array_fill_keys(
			array(
				'page_cache', 'cache_preload', 'browser_cache', 'minify_html', 'minify_css', 'remove_unused_css', 'async_css',
				'minify_js', 'defer_js', 'delay_js', 'lazy_images', 'lcp_priority', 'add_dimensions', 'lazy_iframes',
				'youtube_facade', 'webp', 'font_display_swap', 'local_google_fonts', 'link_prefetch', 'disable_emojis',
				'disable_embeds', 'remove_jquery_migrate', 'disable_dashicons', 'remove_query_strings', 'remove_wp_meta',
			),
			0
		);

		$safe = array_merge(
			$off,
			array(
				'page_cache'        => 1,
				'cache_preload'     => 1,
				'browser_cache'     => 1,
				'minify_html'       => 1,
				'minify_css'        => 1,
				'defer_js'          => 1,
				'lazy_images'       => 1,
				'lcp_priority'      => 1,
				'add_dimensions'    => 1,
				'lazy_iframes'      => 1,
				'webp'              => 1,
				'font_display_swap' => 1,
				'disable_emojis'    => 1,
				'disable_embeds'    => 1,
				'remove_wp_meta'    => 1,
				'heartbeat'         => 'reduce',
			)
		);

		$balanced = array_merge(
			$safe,
			array(
				'delay_js'           => 1,
				'youtube_facade'     => 1,
				'local_google_fonts' => 1,
				'link_prefetch'      => 1,
				'disable_dashicons'  => 1,
			)
		);

		$extreme = array_merge(
			$balanced,
			array(
				'remove_unused_css'    => 1,
				'full_css_load'        => 'interaction',
				'minify_js'            => 1,
				'remove_query_strings' => 1,
			)
		);

		return array(
			'off'      => array_merge( $off, array( 'heartbeat' => 'default' ) ),
			'safe'     => $safe,
			'balanced' => $balanced,
			'extreme'  => $extreme,
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function enabled( $key ) {
		return ! empty( self::all()[ $key ] );
	}

	/**
	 * Splits a textarea/comma setting into a clean list.
	 */
	public static function lines( $key ) {
		$value = (string) self::get( $key, '' );
		$parts = preg_split( '/[\r\n,]+/', $value );
		return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
	}

	public static function update( array $values ) {
		$current = self::all();
		$clean   = self::sanitize( array_merge( $current, $values ) );
		update_option( self::OPTION, $clean );
		self::$cache = null;
		do_action( 'vso_settings_updated', $clean, $current );
		return $clean;
	}

	public static function apply_preset( $level ) {
		$presets = self::presets();
		if ( ! isset( $presets[ $level ] ) ) {
			return self::all();
		}
		return self::update( array_merge( $presets[ $level ], array( 'level' => $level ) ) );
	}

	public static function sanitize( array $input ) {
		$defaults = self::defaults();
		$out      = array();

		foreach ( $defaults as $key => $default ) {
			$value = array_key_exists( $key, $input ) ? $input[ $key ] : $default;

			if ( is_int( $default ) ) {
				$out[ $key ] = max( 0, (int) $value );
			} elseif ( 'critical_css' === $key ) {
				$out[ $key ] = wp_strip_all_tags( (string) $value );
			} else {
				$out[ $key ] = sanitize_textarea_field( (string) $value );
			}
		}

		$out['level']         = in_array( $out['level'], array( 'off', 'safe', 'balanced', 'extreme', 'custom' ), true ) ? $out['level'] : 'custom';
		$out['heartbeat']     = in_array( $out['heartbeat'], array( 'default', 'reduce', 'disable' ), true ) ? $out['heartbeat'] : 'default';
		$out['tracking_mode'] = in_array( $out['tracking_mode'], array( 'delay', 'normal' ), true ) ? $out['tracking_mode'] : 'delay';
		$out['full_css_load'] = in_array( $out['full_css_load'], array( 'interaction', 'onload' ), true ) ? $out['full_css_load'] : 'interaction';
		$out['webp_quality']  = min( 100, max( 30, $out['webp_quality'] ) );
		$out['lazy_skip']     = min( 20, $out['lazy_skip'] );

		return $out;
	}
}
