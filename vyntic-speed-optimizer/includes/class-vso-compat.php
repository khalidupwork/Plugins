<?php
/**
 * Compatibility layer for "delay JavaScript": built-in exclusions for well-known
 * plugins (cookie banners, sliders, lazy loaders, tracking pixels), pages where
 * delaying is never safe, and CSS that keeps "hidden until JS" content visible.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Compat {

	/**
	 * Exclusion groups. Keywords are matched (case-insensitive) against the
	 * script tag, its URL and its inline code. Each one only matches scripts of
	 * that product, so a keyword for a plugin you don't use does nothing.
	 */
	public static function groups() {
		return array(
			'cookie'   => array(
				'setting'  => 'compat_cookie',
				'label'    => __( 'Cookie / GDPR consent banners', 'vyntic-speed-optimizer' ),
				'keywords' => array(
					'cookie-law-info', 'cookieyes', 'cky-consent', 'cookielawinfo', 'complianz', 'cmplz', 'cookiebot', 'CookieConsent',
					'borlabs-cookie', 'BorlabsCookie', 'iubenda', 'onetrust', 'otSDKStub', 'optanon', 'termly', 'real-cookie-banner',
					'realCookieBanner', 'moove_gdpr', 'gdpr-cookie-compliance', 'cookie-notice', 'cnArgs', 'cookieadmin', 'osano.com',
					'usercentrics', 'cookie-script.com', 'gdpr-cookie-consent', 'cookiefirst', 'cookiehub', 'axeptio', 'didomi', 'quantcast.mgr',
				),
				'plugins'  => array(
					'cookie-law-info'             => 'CookieYes',
					'complianz-gdpr'              => 'Complianz',
					'complianz-gdpr-premium'      => 'Complianz',
					'cookiebot'                   => 'Cookiebot',
					'borlabs-cookie'              => 'Borlabs Cookie',
					'real-cookie-banner'          => 'Real Cookie Banner',
					'gdpr-cookie-compliance'      => 'GDPR Cookie Compliance',
					'cookie-notice'               => 'Cookie Notice',
					'iubenda-cookie-law-solution' => 'iubenda',
					'uk-cookie-consent'           => 'Termly',
					'gdpr-cookie-consent'         => 'WP Cookie Consent',
					'cookieadmin'                 => 'CookieAdmin',
				),
			),
			'sliders'  => array(
				'setting'  => 'compat_sliders',
				'label'    => __( 'Sliders (Slider Revolution, Smart Slider, LayerSlider, MetaSlider and more)', 'vyntic-speed-optimizer' ),
				'keywords' => array(
					'revslider', 'rs6', 'rbtools', 'tp-tools', 'revapi', 'setREVStartSize', 'RS_MODULES', 'smart-slider', 'smartslider',
					'n2-ss', '_N2.r(', 'nextend', 'layerslider', 'metaslider', 'ml-slider', 'soliloquy', 'masterslider', 'master-slider',
				),
				'plugins'  => array(
					'revslider'                   => 'Slider Revolution',
					'smart-slider-3'              => 'Smart Slider 3',
					'nextend-smart-slider3-pro'   => 'Smart Slider 3 Pro',
					'LayerSlider'                 => 'LayerSlider',
					'ml-slider'                   => 'MetaSlider',
					'soliloquy-lite'              => 'Soliloquy',
					'master-slider'               => 'Master Slider',
				),
			),
			'lazyload' => array(
				'setting'  => 'compat_lazyload',
				'label'    => __( 'JavaScript image lazy loaders (Smush, a3, Jetpack, theme lazy load and more)', 'vyntic-speed-optimizer' ),
				'keywords' => array(
					'lazysizes', 'lazyload', 'lazy-load', 'lazy_load', 'smush-lazy', 'a3-lazy', 'a3_lazy', 'jetpack-lazy', 'lazyLoadOptions',
					'lazyloadRunObserver', 'e-lazyload', 'rocket-lazy', 'lozad', 'data-lazy-src',
				),
				'plugins'  => array(
					'wp-smushit'       => 'Smush',
					'a3-lazy-load'     => 'a3 Lazy Load',
					'jetpack'          => 'Jetpack',
					'rocket-lazy-load' => 'Lazy Load by WP Rocket',
					'lazy-load'        => 'Lazy Load',
				),
			),
			'tracking' => array(
				'setting'  => 'tracking_mode',
				'label'    => __( 'Tracking & pixels (Meta/Facebook Pixel, Google Analytics, Tag Manager, Google Ads, TikTok, Clarity, Hotjar and more)', 'vyntic-speed-optimizer' ),
				'keywords' => array(
					// Google.
					'googletagmanager.com', 'google-analytics.com', 'gtag(', 'gtm4wp', 'google_gtagjs', 'googleadservices.com', 'google_conversion',
					'googlesyndication.com', 'monsterinsights', 'exactmetrics', 'gtm-kit', 'gtmkit', 'dataLayer.push',
					// Meta / Facebook.
					'connect.facebook.net', 'fbevents.js', 'fbq(', 'facebook-for-woocommerce', 'official-facebook-pixel', 'pixelyoursite', 'pysOptions',
					'pys-js', 'pys_',
					// Others.
					'analytics.tiktok.com', 'ttq.load', 'ttq.page', 'ttq.track', 'clarity.ms', 'static.hotjar.com', 'hotjar', 'snap.licdn.com', '_linkedin_partner_id',
					'pintrk', 's.pinimg.com', 'sc-static.net', 'snaptr', 'static.ads-twitter.com', 'twq(', 'bat.bing.com', 'uetq',
					'wpmDataLayer', 'pixel-manager', 'woocommerce-google-adwords-conversion-tracking-tag', 'mc.yandex',
				),
				'plugins'  => array(
					'pixelyoursite'                    => 'PixelYourSite',
					'pixelyoursite-pro'                => 'PixelYourSite Pro',
					'official-facebook-pixel'          => 'Meta Pixel for WordPress',
					'facebook-for-woocommerce'         => 'Facebook for WooCommerce',
					'google-site-kit'                  => 'Site Kit by Google',
					'duracelltomi-google-tag-manager'  => 'GTM4WP',
					'gtm-kit'                          => 'GTM Kit',
					'google-analytics-for-wordpress'   => 'MonsterInsights',
					'google-analytics-dashboard-for-wp' => 'ExactMetrics',
					'woocommerce-google-analytics-integration' => 'Google Analytics for WooCommerce',
					'woocommerce-google-adwords-conversion-tracking-tag' => 'Pixel Manager for WooCommerce',
					'tiktok-for-business'              => 'TikTok',
					'microsoft-clarity'                => 'Microsoft Clarity',
					'hotjar'                           => 'Hotjar',
				),
			),
		);
	}

	/**
	 * Keywords whose scripts must NOT be delayed with the current settings.
	 */
	public static function delay_exclusions() {
		$keywords = array();
		foreach ( self::groups() as $key => $group ) {
			$on = 'tracking' === $key ? 'normal' === VSO_Settings::get( 'tracking_mode' ) : VSO_Settings::enabled( $group['setting'] );
			if ( $on ) {
				$keywords = array_merge( $keywords, $group['keywords'] );
			}
		}
		return $keywords;
	}

	/**
	 * Built-in rules for inline scripts that are always safe (and useful) to run early.
	 */
	public static function is_safe_inline( $code ) {
		$length = strlen( $code );
		// Google tag stub: only defines dataLayer/gtag(), loads nothing. Consent
		// banners need it to exist before they run.
		if ( false !== strpos( $code, 'function gtag(' ) && false === stripos( $code, 'createElement' ) && false === stripos( $code, 'gtm.js' ) && $length < 3000 ) {
			return true;
		}
		// Pure data such as "var settings = {...};" (wp_localize_script output, theme
		// config): runs nothing, costs nothing, and excluded scripts may read it.
		if ( $length < 20000 && self::is_data_only( $code ) ) {
			return true;
		}
		// Theme "no-js" → "js" class switch: content styled for .js would otherwise wait.
		if ( $length < 400 && false !== strpos( $code, 'no-js' ) && false === stripos( $code, 'createElement' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * True for scripts that only assign literal values to variables.
	 */
	public static function is_data_only( $code ) {
		$code = trim( preg_replace( '#/\*.*?\*/|^\s*//.*$|<!\[CDATA\[|\]\]>#ms', '', $code ) );
		if ( '' === $code || ! preg_match( '#^(var|let|const|window\.)\s*[\w$.\[\]\'"]+\s*=#', $code ) ) {
			return false;
		}
		// Remove string literals, then any call, function, arrow or "new" means it does something.
		$bare = preg_replace( '#"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'#s', '""', $code );
		if ( preg_match( '#[()`]|=>|\+\+|--#', (string) $bare ) ) {
			return false;
		}
		// Every remaining word must be a declaration, an assignment target, an
		// object key or a literal. A reference to another variable could be undefined.
		$bare = preg_replace( '#(?:\b(?:var|let|const)\s+|window\.)[\w$]+(?:\.[\w$]+|\[""\])*\s*=#', '=', (string) $bare );
		$bare = preg_replace( '#[\w$]+\s*:#', ':', (string) $bare );
		$bare = preg_replace( '#\b\d[\w.]*#', '0', (string) $bare );
		preg_match_all( '#[A-Za-z_$][\w$]*#', (string) $bare, $words );
		return ! array_diff( $words[0], array( 'true', 'false', 'null', 'undefined' ) );
	}

	/**
	 * Pages where delaying scripts is never worth the risk: checkout and thank-you
	 * pages carry purchase/conversion events and payment scripts.
	 */
	public static function delay_disabled_here() {
		if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() || is_account_page() ) ) {
			return true;
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return true;
		}
		if ( function_exists( 'edd_is_checkout' ) && ( edd_is_checkout() || ( function_exists( 'edd_is_success_page' ) && edd_is_success_page() ) ) ) {
			return true;
		}
		return (bool) apply_filters( 'vso_disable_delay_here', false );
	}

	/**
	 * Known plugins active on this site, grouped: [ group => [ names ] ].
	 */
	public static function detected() {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$folders = array();
		foreach ( $active as $file ) {
			$folders[ strtok( (string) $file, '/' ) ] = true;
		}
		$found = array();
		foreach ( self::groups() as $key => $group ) {
			foreach ( $group['plugins'] as $folder => $name ) {
				if ( isset( $folders[ $folder ] ) ) {
					$found[ $key ][] = $name;
				}
			}
		}
		return $found;
	}

	/**
	 * CSS printed while scripts are delayed. Animation rules are scoped to
	 * html:not(.vso-js-loaded), so they switch off by themselves once scripts run.
	 */
	public static function safety_css() {
		// Page preloaders only cover the page while it loads; once scripts are
		// delayed they would cover it until the first interaction, so hide them for good.
		$css = ':is(#preloader,#pre-loader,#page-preloader,#loader-wrapper,#loading,.preloader,.page-preloader,.site-preloader,.loader-wrapper,.loading-screen,.pre-loader,#qode-page-loading-effect,.fusion-loader,#wptime-plugin-preloader,.astra-preloader){display:none!important}'
			. 'html:not(.vso-js-loaded) [data-aos]{opacity:1!important;transform:none!important}'
			. 'html:not(.vso-js-loaded) :is(.et_animated,.et-waypoint:not(.et_pb_counters)){opacity:1!important}'
			. 'html:not(.vso-js-loaded) .wow{visibility:visible!important}';
		return '<style id="vso-safety-css">' . apply_filters( 'vso_safety_css', $css ) . '</style>';
	}
}
