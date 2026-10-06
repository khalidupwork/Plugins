<?php
/**
 * Admin UI: dashboard, settings tabs, admin bar, meta box, AJAX tools.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

class VSO_Admin {

	const SLUG = 'vyntic-speed';

	/** Plugins that do the same job; running two optimizers breaks sites. */
	const CONFLICTS = array(
		'wp-rocket/wp-rocket.php'                             => 'WP Rocket',
		'w3-total-cache/w3-total-cache.php'                   => 'W3 Total Cache',
		'wp-super-cache/wp-cache.php'                         => 'WP Super Cache',
		'wp-fastest-cache/wpFastestCache.php'                 => 'WP Fastest Cache',
		'litespeed-cache/litespeed-cache.php'                 => 'LiteSpeed Cache',
		'autoptimize/autoptimize.php'                         => 'Autoptimize',
		'tenweb-speed-optimizer/tenweb_speed_optimizer.php'   => '10Web Booster',
		'sg-cachepress/sg-cachepress.php'                     => 'SiteGround Optimizer',
		'breeze/breeze.php'                                   => 'Breeze',
		'hummingbird-performance/wp-hummingbird.php'          => 'Hummingbird',
		'swift-performance-lite/performance.php'              => 'Swift Performance',
		'nitropack/main.php'                                  => 'NitroPack',
		'flying-press/flying-press.php'                       => 'FlyingPress',
		'perfmatters/perfmatters.php'                         => 'Perfmatters',
		'wp-optimize/wp-optimize.php'                         => 'WP-Optimize',
		'cache-enabler/cache-enabler.php'                     => 'Cache Enabler',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_vso_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_vso_preset', array( __CLASS__, 'handle_preset' ) );
		add_action( 'admin_post_vso_clear', array( __CLASS__, 'handle_clear' ) );
		add_action( 'admin_post_vso_preload', array( __CLASS__, 'handle_preload' ) );
		add_action( 'admin_post_vso_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_vso_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_vso_reset', array( __CLASS__, 'handle_reset' ) );
		add_action( 'wp_ajax_vso_psi', array( __CLASS__, 'ajax_psi' ) );
		add_action( 'wp_ajax_vso_webp_batch', array( __CLASS__, 'ajax_webp' ) );
		add_action( 'wp_ajax_vso_db_clean', array( __CLASS__, 'ajax_db' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 5 );
		add_filter( 'plugin_action_links_' . plugin_basename( VSO_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function url( $tab = 'dashboard', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		// Lives inside the shared "Vyntic" menu, so all Vyntic plugins take one menu item.
		if ( class_exists( 'Vyntic_Client' ) ) {
			add_submenu_page( Vyntic_Client::MENU, __( 'Vyntic Speed Optimizer', 'vyntic-speed-optimizer' ), __( 'Speed Optimizer', 'vyntic-speed-optimizer' ), 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
			return;
		}
		add_menu_page( __( 'Vyntic Speed Optimizer', 'vyntic-speed-optimizer' ), __( 'Vyntic Speed', 'vyntic-speed-optimizer' ), 'manage_options', self::SLUG, array( __CLASS__, 'render' ), 'dashicons-performance', 81 );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'vyntic-speed-optimizer' ) . '</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'vso-admin', VSO_URL . 'assets/css/admin.css', array(), VSO_VERSION );
		wp_enqueue_script( 'vso-admin', VSO_URL . 'assets/js/admin.js', array(), VSO_VERSION, true );
		wp_localize_script(
			'vso-admin',
			'vsoAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'vso_ajax' ),
				'home'  => home_url( '/' ),
				'i18n'  => array(
					'testing'   => __( 'Testing, this takes 20 to 60 seconds...', 'vyntic-speed-optimizer' ),
					'error'     => __( 'Something went wrong:', 'vyntic-speed-optimizer' ),
					'converted' => __( 'Converted', 'vyntic-speed-optimizer' ),
					'done'      => __( 'All images converted.', 'vyntic-speed-optimizer' ),
					'cleaned'   => __( 'Cleaned', 'vyntic-speed-optimizer' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------- */

	public static function tabs() {
		return array(
			'dashboard' => __( 'Dashboard', 'vyntic-speed-optimizer' ),
			'cache'     => __( 'Cache', 'vyntic-speed-optimizer' ),
			'files'     => __( 'CSS & JavaScript', 'vyntic-speed-optimizer' ),
			'media'     => __( 'Images & Media', 'vyntic-speed-optimizer' ),
			'fonts'     => __( 'Fonts & Preload', 'vyntic-speed-optimizer' ),
			'tweaks'    => __( 'Tweaks', 'vyntic-speed-optimizer' ),
			'database'  => __( 'Database', 'vyntic-speed-optimizer' ),
			'tools'     => __( 'Tools', 'vyntic-speed-optimizer' ),
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}
		?>
		<div class="wrap vso-wrap">
			<div class="vso-header">
				<div class="vso-brand">
					<span class="vso-logo" aria-hidden="true">V</span>
					<div>
						<h1><?php esc_html_e( 'Vyntic Speed Optimizer', 'vyntic-speed-optimizer' ); ?></h1>
						<p><?php esc_html_e( 'Runs 100% on your server. No account, no login, no connect.', 'vyntic-speed-optimizer' ); ?>
							<span class="vso-by"><?php esc_html_e( 'by', 'vyntic-speed-optimizer' ); ?> <a href="https://vyntic.studio/" target="_blank" rel="noopener">Vyntic Studio</a></span></p>
					</div>
				</div>
				<div class="vso-header-actions">
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vso_clear' ), 'vso_clear' ) ); ?>"><?php esc_html_e( 'Clear all cache', 'vyntic-speed-optimizer' ); ?></a>
				</div>
			</div>

			<?php self::flash(); ?>

			<nav class="vso-tabs">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="vso-tab <?php echo $key === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::url( $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="vso-body">
				<?php
				switch ( $tab ) {
					case 'dashboard':
						self::tab_dashboard();
						break;
					case 'database':
						self::tab_database();
						break;
					case 'tools':
						self::tab_tools();
						break;
					default:
						self::settings_form( $tab );
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function flash() {
		$msg = isset( $_GET['vso_msg'] ) ? sanitize_key( wp_unslash( $_GET['vso_msg'] ) ) : ''; // phpcs:ignore
		$map = array(
			'saved'    => __( 'Settings saved. Cache cleared so changes show right away.', 'vyntic-speed-optimizer' ),
			'preset'   => __( 'Optimization level applied. Cache cleared and preloading started.', 'vyntic-speed-optimizer' ),
			'cleared'  => __( 'All cache cleared.', 'vyntic-speed-optimizer' ),
			'preload'  => __( 'Cache preload started in the background.', 'vyntic-speed-optimizer' ),
			'imported' => __( 'Settings imported.', 'vyntic-speed-optimizer' ),
			'reset'    => __( 'Settings reset to the recommended defaults.', 'vyntic-speed-optimizer' ),
			'badfile'  => __( 'That file is not a valid Vyntic settings export.', 'vyntic-speed-optimizer' ),
		);
		if ( isset( $map[ $msg ] ) ) {
			$type = 'badfile' === $msg ? 'error' : 'success';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $map[ $msg ] ) . '</p></div>';
		}
	}

	private static function tab_dashboard() {
		$level   = VSO_Settings::get( 'level' );
		$status  = VSO_Page_Cache::status();
		$webp    = VSO_WebP::stats();
		$last    = get_option( 'vso_psi_last', array() );
		$levels  = array(
			'safe'     => array(
				__( 'Safe', 'vyntic-speed-optimizer' ),
				__( 'Page cache, minify, defer JS, lazy load, WebP. Works on every site.', 'vyntic-speed-optimizer' ),
			),
			'balanced' => array(
				__( 'Balanced', 'vyntic-speed-optimizer' ),
				__( 'Safe + delay all JavaScript until user interaction, YouTube facade, local Google Fonts. Recommended.', 'vyntic-speed-optimizer' ),
			),
			'extreme'  => array(
				__( 'Extreme', 'vyntic-speed-optimizer' ),
				__( 'Balanced + remove unused CSS (inline only the CSS each page needs). Highest scores. Check your pages after enabling.', 'vyntic-speed-optimizer' ),
			),
			'off'      => array(
				__( 'Off', 'vyntic-speed-optimizer' ),
				__( 'Disable all optimizations and caching (troubleshooting).', 'vyntic-speed-optimizer' ),
			),
		);
		?>
		<section class="vso-card">
			<h2><?php esc_html_e( 'Optimization level', 'vyntic-speed-optimizer' ); ?></h2>
			<p class="vso-muted"><?php esc_html_e( 'One click sets every option below. You can still fine-tune any setting afterwards (the level then shows as "Custom").', 'vyntic-speed-optimizer' ); ?></p>
			<div class="vso-levels">
				<?php foreach ( $levels as $key => $info ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vso-level <?php echo $key === $level ? 'is-current' : ''; ?> vso-level-<?php echo esc_attr( $key ); ?>">
						<?php wp_nonce_field( 'vso_preset' ); ?>
						<input type="hidden" name="action" value="vso_preset">
						<input type="hidden" name="level" value="<?php echo esc_attr( $key ); ?>">
						<h3><?php echo esc_html( $info[0] ); ?><?php echo 'balanced' === $key ? ' <span class="vso-pill">' . esc_html__( 'Recommended', 'vyntic-speed-optimizer' ) . '</span>' : ''; ?></h3>
						<p><?php echo esc_html( $info[1] ); ?></p>
						<?php if ( $key === $level ) : ?>
							<span class="vso-current"><?php esc_html_e( 'Active', 'vyntic-speed-optimizer' ); ?></span>
						<?php else : ?>
							<button type="submit" class="button <?php echo 'off' === $key ? '' : 'button-primary'; ?>"><?php esc_html_e( 'Activate', 'vyntic-speed-optimizer' ); ?></button>
						<?php endif; ?>
					</form>
				<?php endforeach; ?>
			</div>
			<?php if ( 'custom' === $level ) : ?>
				<p class="vso-muted"><strong><?php esc_html_e( 'Current level: Custom', 'vyntic-speed-optimizer' ); ?></strong></p>
			<?php endif; ?>
		</section>

		<section class="vso-card">
			<div class="vso-card-head">
				<h2><?php esc_html_e( 'PageSpeed score', 'vyntic-speed-optimizer' ); ?></h2>
				<div>
					<input type="url" id="vso-psi-url" class="regular-text" value="<?php echo esc_attr( home_url( '/' ) ); ?>">
					<button type="button" class="button button-primary" id="vso-psi-run"><?php esc_html_e( 'Test now', 'vyntic-speed-optimizer' ); ?></button>
				</div>
			</div>
			<p class="vso-muted" id="vso-psi-status">
				<?php
				if ( ! empty( $last['time'] ) ) {
					/* translators: %s: human time diff */
					printf( esc_html__( 'Last test: %s ago', 'vyntic-speed-optimizer' ), esc_html( human_time_diff( (int) $last['time'] ) ) );
				} else {
					esc_html_e( 'Uses Google PageSpeed Insights (same engine as pagespeed.web.dev). Tip: visit the page once first so it is cached.', 'vyntic-speed-optimizer' );
				}
				?>
			</p>
			<div class="vso-scores" id="vso-psi-results" data-last="<?php echo esc_attr( wp_json_encode( $last ) ); ?>"></div>
		</section>

		<div class="vso-grid">
			<section class="vso-card vso-stat">
				<h3><?php esc_html_e( 'Page cache', 'vyntic-speed-optimizer' ); ?></h3>
				<?php if ( $status['enabled'] && $status['dropin'] && $status['wp_cache'] ) : ?>
					<p class="vso-ok"><?php esc_html_e( 'Active', 'vyntic-speed-optimizer' ); ?></p>
				<?php elseif ( $status['enabled'] ) : ?>
					<p class="vso-warn"><?php esc_html_e( 'Enabled, but not fully installed. See the notice above.', 'vyntic-speed-optimizer' ); ?></p>
				<?php else : ?>
					<p class="vso-muted"><?php esc_html_e( 'Off', 'vyntic-speed-optimizer' ); ?></p>
				<?php endif; ?>
				<p class="vso-big"><?php echo esc_html( number_format_i18n( $status['stats'][0] ) ); ?> <small><?php esc_html_e( 'files', 'vyntic-speed-optimizer' ); ?> · <?php echo esc_html( VSO_Utils::format_bytes( $status['stats'][1] ) ); ?></small></p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vso_preload' ), 'vso_preload' ) ); ?>"><?php esc_html_e( 'Preload cache now', 'vyntic-speed-optimizer' ); ?></a>
				<?php if ( VSO_Preload::remaining() ) : ?>
					<p class="vso-muted">
						<?php
						/* translators: %d: number of URLs */
						printf( esc_html__( '%d URLs waiting to be preloaded.', 'vyntic-speed-optimizer' ), (int) VSO_Preload::remaining() );
						?>
					</p>
				<?php endif; ?>
			</section>
			<section class="vso-card vso-stat">
				<h3><?php esc_html_e( 'WebP images', 'vyntic-speed-optimizer' ); ?></h3>
				<?php if ( ! VSO_WebP::supported() ) : ?>
					<p class="vso-warn"><?php esc_html_e( 'Your server has no WebP support (GD or Imagick). Ask your host to enable it.', 'vyntic-speed-optimizer' ); ?></p>
				<?php endif; ?>
				<p class="vso-big"><?php echo esc_html( number_format_i18n( $webp['converted'] ) . ' / ' . number_format_i18n( $webp['total'] ) ); ?> <small><?php esc_html_e( 'images', 'vyntic-speed-optimizer' ); ?> · <?php echo esc_html( VSO_Utils::format_bytes( $webp['bytes'] ) ); ?> <?php esc_html_e( 'saved', 'vyntic-speed-optimizer' ); ?></small></p>
				<a class="button" href="<?php echo esc_url( self::url( 'media' ) ); ?>#vso-webp-bulk"><?php esc_html_e( 'Bulk convert', 'vyntic-speed-optimizer' ); ?></a>
			</section>
			<section class="vso-card vso-stat">
				<h3><?php esc_html_e( 'Active features', 'vyntic-speed-optimizer' ); ?></h3>
				<ul class="vso-checks">
					<?php
					$features = array(
						'page_cache'         => __( 'Page cache', 'vyntic-speed-optimizer' ),
						'delay_js'           => __( 'Delay JavaScript', 'vyntic-speed-optimizer' ),
						'remove_unused_css'  => __( 'Remove unused CSS', 'vyntic-speed-optimizer' ),
						'lazy_images'        => __( 'Lazy load', 'vyntic-speed-optimizer' ),
						'webp'               => __( 'WebP', 'vyntic-speed-optimizer' ),
						'local_google_fonts' => __( 'Local Google Fonts', 'vyntic-speed-optimizer' ),
						'minify_css'         => __( 'Minify CSS/JS/HTML', 'vyntic-speed-optimizer' ),
					);
					foreach ( $features as $key => $label ) {
						$on = VSO_Settings::enabled( $key );
						echo '<li class="' . ( $on ? 'on' : 'off' ) . '">' . esc_html( $label ) . '</li>';
					}
					?>
				</ul>
			</section>
		</div>
		<?php
	}

	/**
	 * Field definitions per settings tab.
	 */
	private static function fields() {
		return array(
			'cache'  => array(
				array( 'section', __( 'Page cache', 'vyntic-speed-optimizer' ), __( 'Stores a ready-made HTML copy of every page. Visitors get it instantly without PHP or database work. This is the biggest win for server response time (TTFB).', 'vyntic-speed-optimizer' ) ),
				array( 'page_cache', 'toggle', __( 'Enable page cache', 'vyntic-speed-optimizer' ) ),
				array( 'cache_lifespan', 'number', __( 'Cache lifespan (hours)', 'vyntic-speed-optimizer' ), __( '0 = never expires (cache is still cleared automatically whenever you edit content).', 'vyntic-speed-optimizer' ) ),
				array( 'cache_preload', 'toggle', __( 'Preload cache', 'vyntic-speed-optimizer' ), __( 'Automatically visits your pages in the background after the cache is cleared.', 'vyntic-speed-optimizer' ) ),
				array( 'cache_mobile', 'toggle', __( 'Separate cache for mobile', 'vyntic-speed-optimizer' ), __( 'Only needed if your theme shows different HTML on phones.', 'vyntic-speed-optimizer' ) ),
				array( 'cache_exclude_urls', 'textarea', __( 'Never cache these URLs', 'vyntic-speed-optimizer' ), __( 'One per line. Partial match, "*" wildcard allowed. Example: /cart/', 'vyntic-speed-optimizer' ) ),
				array( 'cache_exclude_cookies', 'textarea', __( 'Never cache when these cookies exist', 'vyntic-speed-optimizer' ), __( 'Cookie name prefixes, one per line. Logged-in users and WooCommerce carts are always excluded.', 'vyntic-speed-optimizer' ) ),
				array( 'cache_ignore_params', 'textarea', __( 'Ignored query parameters', 'vyntic-speed-optimizer' ), __( 'URLs with only these parameters are still served from cache (tracking tags).', 'vyntic-speed-optimizer' ) ),
				array( 'section', __( 'Browser cache', 'vyntic-speed-optimizer' ), '' ),
				array( 'browser_cache', 'toggle', __( 'Browser caching + GZIP rules (.htaccess)', 'vyntic-speed-optimizer' ), __( 'Adds long cache headers for images, CSS, JS and fonts on Apache/LiteSpeed servers.', 'vyntic-speed-optimizer' ) ),
			),
			'files'  => array(
				array( 'section', __( 'JavaScript', 'vyntic-speed-optimizer' ), '' ),
				array( 'delay_js', 'toggle', __( 'Delay all JavaScript until user interaction', 'vyntic-speed-optimizer' ), __( 'Scripts run on the first scroll, tap, mouse move or key press. Removes almost all Total Blocking Time, which is the main reason scores turn green.', 'vyntic-speed-optimizer' ) ),
				array( 'delay_js_timeout', 'number', __( 'Also run scripts after (seconds)', 'vyntic-speed-optimizer' ), __( '0 = only on interaction (best score). Set e.g. 8 if you have auto-playing sliders.', 'vyntic-speed-optimizer' ) ),
				array( 'delay_js_exclude', 'textarea', __( 'Do not delay scripts containing', 'vyntic-speed-optimizer' ), __( 'One per line: part of a file URL, script id or inline code. Example: jquery.min.js', 'vyntic-speed-optimizer' ) ),
				array( 'section', __( 'Compatibility', 'vyntic-speed-optimizer' ), __( 'Built-in exclusions for well-known plugins. When a script is excluded, everything it needs (for example jQuery) is excluded with it automatically, so nothing runs in the wrong order. Checkout, cart, account and order-received pages never delay scripts, so purchase events are always tracked.', 'vyntic-speed-optimizer' ) ),
				array( 'compat_detected', 'compat_detected', __( 'Detected on your site', 'vyntic-speed-optimizer' ) ),
				array( 'compat_cookie', 'toggle', __( 'Cookie / GDPR consent banners', 'vyntic-speed-optimizer' ), __( 'CookieYes, Complianz, Cookiebot, Borlabs, iubenda, OneTrust and more. The banner must show right away (legal requirement).', 'vyntic-speed-optimizer' ) ),
				array( 'compat_sliders', 'toggle', __( 'Sliders', 'vyntic-speed-optimizer' ), __( 'Slider Revolution, Smart Slider, LayerSlider, MetaSlider and more. These are usually the first thing on the page and stay blank until their script runs.', 'vyntic-speed-optimizer' ) ),
				array( 'compat_lazyload', 'toggle', __( 'JavaScript lazy loaders', 'vyntic-speed-optimizer' ), __( 'Smush, a3 Lazy Load, Jetpack, theme lazy load, Elementor background lazy load. Without this, those images stay empty.', 'vyntic-speed-optimizer' ) ),
				array( 'tracking_mode', 'select', __( 'Tracking & pixels', 'vyntic-speed-optimizer' ), __( 'Meta/Facebook Pixel, Google Analytics, Tag Manager, Google Ads, TikTok, Clarity, Hotjar, PixelYourSite and more. "Delay" gives the best score; visitors who leave without scrolling or tapping are not counted. "Load normally" tracks every visit but lowers the score (often 10 to 25 points on mobile).', 'vyntic-speed-optimizer' ), array( 'delay' => __( 'Delay until interaction (best score)', 'vyntic-speed-optimizer' ), 'normal' => __( 'Load normally (track every visit)', 'vyntic-speed-optimizer' ) ) ),
				array( 'section', __( 'More JavaScript options', 'vyntic-speed-optimizer' ), '' ),
				array( 'defer_js', 'toggle', __( 'Defer JavaScript', 'vyntic-speed-optimizer' ), __( 'Adds "defer" to scripts that are not delayed (jQuery is kept in place for compatibility).', 'vyntic-speed-optimizer' ) ),
				array( 'defer_exclude', 'textarea', __( 'Do not defer scripts containing', 'vyntic-speed-optimizer' ), '' ),
				array( 'minify_js', 'toggle', __( 'Minify JavaScript files', 'vyntic-speed-optimizer' ) ),
				array( 'section', __( 'CSS', 'vyntic-speed-optimizer' ), '' ),
				array( 'minify_css', 'toggle', __( 'Minify CSS', 'vyntic-speed-optimizer' ) ),
				array( 'remove_unused_css', 'toggle', __( 'Remove unused CSS', 'vyntic-speed-optimizer' ), __( 'For every page, only the CSS rules it actually uses are inlined; full stylesheets load later. Eliminates render-blocking CSS. Calculated locally, no external service.', 'vyntic-speed-optimizer' ) ),
				array( 'full_css_load', 'select', __( 'Load full CSS', 'vyntic-speed-optimizer' ), '', array( 'interaction' => __( 'On user interaction (best score)', 'vyntic-speed-optimizer' ), 'onload' => __( 'After page load', 'vyntic-speed-optimizer' ) ) ),
				array( 'unused_css_safelist', 'textarea', __( 'Always keep these selectors', 'vyntic-speed-optimizer' ), __( 'Class/ID names, one per line, "*" wildcard allowed (e.g. slick-*). Use for things added by JavaScript that must look right before interaction.', 'vyntic-speed-optimizer' ) ),
				array( 'async_css', 'toggle', __( 'Load CSS asynchronously', 'vyntic-speed-optimizer' ), __( 'Alternative to "Remove unused CSS". Needs Critical CSS below or the page may flash unstyled.', 'vyntic-speed-optimizer' ) ),
				array( 'critical_css', 'code', __( 'Critical CSS', 'vyntic-speed-optimizer' ), __( 'Used with async CSS: styles for the above-the-fold area.', 'vyntic-speed-optimizer' ) ),
				array( 'css_exclude', 'textarea', __( 'Exclude stylesheets containing', 'vyntic-speed-optimizer' ), '' ),
				array( 'section', __( 'HTML', 'vyntic-speed-optimizer' ), '' ),
				array( 'minify_html', 'toggle', __( 'Minify HTML', 'vyntic-speed-optimizer' ) ),
			),
			'media'  => array(
				array( 'section', __( 'Lazy loading', 'vyntic-speed-optimizer' ), '' ),
				array( 'lazy_images', 'toggle', __( 'Lazy load images', 'vyntic-speed-optimizer' ) ),
				array( 'lazy_skip', 'number', __( 'Skip the first N images', 'vyntic-speed-optimizer' ), __( 'Images at the top of the page (logo, hero) must load immediately for a good LCP.', 'vyntic-speed-optimizer' ) ),
				array( 'lcp_priority', 'toggle', __( 'High priority for the main (LCP) image', 'vyntic-speed-optimizer' ), __( 'Adds fetchpriority="high" to the first image.', 'vyntic-speed-optimizer' ) ),
				array( 'lazy_iframes', 'toggle', __( 'Lazy load iframes', 'vyntic-speed-optimizer' ) ),
				array( 'youtube_facade', 'toggle', __( 'YouTube click-to-load', 'vyntic-speed-optimizer' ), __( 'Shows a thumbnail; the heavy YouTube player loads only when clicked.', 'vyntic-speed-optimizer' ) ),
				array( 'lazy_exclude', 'textarea', __( 'Never lazy load images containing', 'vyntic-speed-optimizer' ), '' ),
				array( 'section', __( 'Images', 'vyntic-speed-optimizer' ), '' ),
				array( 'add_dimensions', 'toggle', __( 'Add missing width/height', 'vyntic-speed-optimizer' ), __( 'Prevents layout shift (CLS).', 'vyntic-speed-optimizer' ) ),
				array( 'webp', 'toggle', __( 'Convert & serve WebP', 'vyntic-speed-optimizer' ), __( 'New uploads are converted automatically on your server. Originals are never changed.', 'vyntic-speed-optimizer' ) ),
				array( 'webp_quality', 'number', __( 'WebP quality (30 to 100)', 'vyntic-speed-optimizer' ) ),
				array( 'webp_bulk', 'webp_bulk', __( 'Bulk convert existing images', 'vyntic-speed-optimizer' ) ),
			),
			'fonts'  => array(
				array( 'section', __( 'Fonts', 'vyntic-speed-optimizer' ), '' ),
				array( 'font_display_swap', 'toggle', __( 'Font display: swap', 'vyntic-speed-optimizer' ), __( 'Text shows immediately while web fonts load.', 'vyntic-speed-optimizer' ) ),
				array( 'local_google_fonts', 'toggle', __( 'Host Google Fonts locally', 'vyntic-speed-optimizer' ), __( 'Downloads fonts to your server: no third-party connection, faster and GDPR-friendly.', 'vyntic-speed-optimizer' ) ),
				array( 'section', __( 'Resource hints', 'vyntic-speed-optimizer' ), '' ),
				array( 'link_prefetch', 'toggle', __( 'Instant page navigation', 'vyntic-speed-optimizer' ), __( 'Prefetches a page when the visitor hovers a link, so it opens instantly.', 'vyntic-speed-optimizer' ) ),
				array( 'preconnect', 'textarea', __( 'Preconnect to domains', 'vyntic-speed-optimizer' ), __( 'One per line, e.g. https://cdn.example.com', 'vyntic-speed-optimizer' ) ),
				array( 'preload', 'textarea', __( 'Preload files', 'vyntic-speed-optimizer' ), __( 'Full URLs of key fonts or the hero image, one per line.', 'vyntic-speed-optimizer' ) ),
			),
			'tweaks' => array(
				array( 'section', __( 'Remove WordPress bloat', 'vyntic-speed-optimizer' ), '' ),
				array( 'disable_emojis', 'toggle', __( 'Disable emojis script', 'vyntic-speed-optimizer' ) ),
				array( 'disable_embeds', 'toggle', __( 'Disable WordPress embeds', 'vyntic-speed-optimizer' ) ),
				array( 'disable_dashicons', 'toggle', __( 'Remove Dashicons for visitors', 'vyntic-speed-optimizer' ) ),
				array( 'remove_jquery_migrate', 'toggle', __( 'Remove jQuery Migrate', 'vyntic-speed-optimizer' ), __( 'Only if no plugin needs old jQuery code.', 'vyntic-speed-optimizer' ) ),
				array( 'remove_query_strings', 'toggle', __( 'Remove ?ver= from CSS/JS', 'vyntic-speed-optimizer' ) ),
				array( 'remove_wp_meta', 'toggle', __( 'Remove generator, WLW and shortlink tags', 'vyntic-speed-optimizer' ) ),
				array( 'disable_xmlrpc', 'toggle', __( 'Disable XML-RPC', 'vyntic-speed-optimizer' ) ),
				array( 'heartbeat', 'select', __( 'Heartbeat API', 'vyntic-speed-optimizer' ), '', array( 'default' => __( 'WordPress default', 'vyntic-speed-optimizer' ), 'reduce' => __( 'Reduce (60s) and off on the front end', 'vyntic-speed-optimizer' ), 'disable' => __( 'Disable (except post editor)', 'vyntic-speed-optimizer' ) ) ),
				array( 'section', __( 'Scope', 'vyntic-speed-optimizer' ), '' ),
				array( 'optimize_logged_in', 'toggle', __( 'Optimize for logged-in users too', 'vyntic-speed-optimizer' ), __( 'Off = admins always see the original site, which is safer while editing.', 'vyntic-speed-optimizer' ) ),
				array( 'exclude_urls', 'textarea', __( 'Never optimize these URLs', 'vyntic-speed-optimizer' ), __( 'One per line, partial match. Single pages can also be excluded from the editor sidebar.', 'vyntic-speed-optimizer' ) ),
			),
		);
	}

	private static function settings_form( $tab ) {
		$fields = self::fields();
		if ( empty( $fields[ $tab ] ) ) {
			return;
		}
		$s = VSO_Settings::all();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vso-card vso-form">
			<?php wp_nonce_field( 'vso_save' ); ?>
			<input type="hidden" name="action" value="vso_save">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php
			foreach ( $fields[ $tab ] as $field ) {
				if ( 'section' === $field[0] ) {
					echo '<h2 class="vso-section">' . esc_html( $field[1] ) . '</h2>';
					if ( ! empty( $field[2] ) ) {
						echo '<p class="vso-muted">' . esc_html( $field[2] ) . '</p>';
					}
					continue;
				}
				list( $key, $type, $label ) = $field;
				$help  = isset( $field[3] ) ? $field[3] : '';
				$value = isset( $s[ $key ] ) ? $s[ $key ] : '';
				$id    = 'vso-' . $key;
				echo '<div class="vso-field vso-field-' . esc_attr( $type ) . '">';
				echo '<div class="vso-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
				if ( $help ) {
					echo '<p>' . esc_html( $help ) . '</p>';
				}
				echo '</div><div class="vso-control">';
				switch ( $type ) {
					case 'toggle':
						echo '<input type="hidden" name="vso[' . esc_attr( $key ) . ']" value="0">';
						echo '<label class="vso-switch"><input type="checkbox" id="' . esc_attr( $id ) . '" name="vso[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $value ), true, false ) . '><span></span></label>';
						break;
					case 'number':
						echo '<input type="number" min="0" class="small-text" id="' . esc_attr( $id ) . '" name="vso[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
						break;
					case 'textarea':
						echo '<textarea rows="4" class="large-text code" id="' . esc_attr( $id ) . '" name="vso[' . esc_attr( $key ) . ']">' . esc_textarea( $value ) . '</textarea>';
						break;
					case 'code':
						echo '<textarea rows="8" class="large-text code" id="' . esc_attr( $id ) . '" name="vso[' . esc_attr( $key ) . ']">' . esc_textarea( $value ) . '</textarea>';
						break;
					case 'select':
						echo '<select id="' . esc_attr( $id ) . '" name="vso[' . esc_attr( $key ) . ']">';
						foreach ( $field[4] as $opt => $opt_label ) {
							echo '<option value="' . esc_attr( $opt ) . '" ' . selected( $value, $opt, false ) . '>' . esc_html( $opt_label ) . '</option>';
						}
						echo '</select>';
						break;
					case 'compat_detected':
						$found  = VSO_Compat::detected();
						$groups = VSO_Compat::groups();
						if ( ! $found ) {
							echo '<p class="vso-muted">' . esc_html__( 'No known cookie banner, slider, lazy-load or tracking plugin found. Scripts added by your theme or by hand are still matched.', 'vyntic-speed-optimizer' ) . '</p>';
						} else {
							echo '<ul class="vso-detected">';
							foreach ( $found as $group => $names ) {
								echo '<li><strong>' . esc_html( $groups[ $group ]['label'] ) . ':</strong> ' . esc_html( implode( ', ', array_unique( $names ) ) ) . '</li>';
							}
							echo '</ul>';
						}
						break;
					case 'webp_bulk':
						$stats = VSO_WebP::stats();
						echo '<div id="vso-webp-bulk" data-remaining="' . esc_attr( $stats['remaining'] ) . '">';
						echo '<div class="vso-progress"><span style="width:' . esc_attr( $stats['total'] ? round( $stats['converted'] / $stats['total'] * 100 ) : 100 ) . '%"></span></div>';
						echo '<p class="vso-muted" id="vso-webp-text">' . esc_html( sprintf( /* translators: 1: converted, 2: total */ __( '%1$d of %2$d images processed', 'vyntic-speed-optimizer' ), $stats['converted'], $stats['total'] ) ) . '</p>';
						echo '<button type="button" class="button" id="vso-webp-run" ' . disabled( VSO_WebP::supported(), false, false ) . '>' . esc_html__( 'Convert all images', 'vyntic-speed-optimizer' ) . '</button>';
						echo '</div>';
						break;
				}
				echo '</div></div>';
			}
			?>
			<p class="vso-submit"><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save changes', 'vyntic-speed-optimizer' ); ?></button></p>
		</form>
		<?php
	}

	private static function tab_database() {
		$counts = VSO_Database::counts();
		?>
		<section class="vso-card">
			<h2><?php esc_html_e( 'Database cleanup', 'vyntic-speed-optimizer' ); ?></h2>
			<p class="vso-muted"><?php esc_html_e( 'Removes data WordPress no longer needs. Take a backup first if you are unsure.', 'vyntic-speed-optimizer' ); ?></p>
			<table class="widefat striped vso-db">
				<tbody>
				<?php foreach ( VSO_Database::items() as $key => $label ) : ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td class="vso-db-count" data-item="<?php echo esc_attr( $key ); ?>"><?php echo 'optimize' === $key ? '-' : esc_html( number_format_i18n( $counts[ $key ] ) ); ?></td>
						<td><button type="button" class="button vso-db-clean" data-item="<?php echo esc_attr( $key ); ?>"><?php echo 'optimize' === $key ? esc_html__( 'Optimize', 'vyntic-speed-optimizer' ) : esc_html__( 'Clean', 'vyntic-speed-optimizer' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vso-inline-form">
				<?php wp_nonce_field( 'vso_save' ); ?>
				<input type="hidden" name="action" value="vso_save">
				<input type="hidden" name="tab" value="database">
				<input type="hidden" name="vso[db_auto_clean]" value="0">
				<label><input type="checkbox" name="vso[db_auto_clean]" value="1" <?php checked( VSO_Settings::enabled( 'db_auto_clean' ) ); ?>> <?php esc_html_e( 'Clean revisions, auto drafts, spam/trashed comments and expired transients automatically every week', 'vyntic-speed-optimizer' ); ?></label>
				<button type="submit" class="button"><?php esc_html_e( 'Save', 'vyntic-speed-optimizer' ); ?></button>
			</form>
		</section>
		<?php
	}

	private static function tab_tools() {
		?>
		<section class="vso-card">
			<h2><?php esc_html_e( 'PageSpeed API key (optional)', 'vyntic-speed-optimizer' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'vso_save' ); ?>
				<input type="hidden" name="action" value="vso_save">
				<input type="hidden" name="tab" value="tools">
				<p class="vso-muted"><?php esc_html_e( 'The score test works without a key, but Google limits anonymous requests. A free key from Google Cloud (PageSpeed Insights API) removes the limit.', 'vyntic-speed-optimizer' ); ?></p>
				<input type="text" class="regular-text" name="vso[psi_api_key]" value="<?php echo esc_attr( VSO_Settings::get( 'psi_api_key' ) ); ?>" autocomplete="off">
				<button type="submit" class="button"><?php esc_html_e( 'Save', 'vyntic-speed-optimizer' ); ?></button>
			</form>
		</section>
		<section class="vso-card">
			<h2><?php esc_html_e( 'Export / import settings', 'vyntic-speed-optimizer' ); ?></h2>
			<p class="vso-muted"><?php esc_html_e( 'Copy your exact setup to another site.', 'vyntic-speed-optimizer' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vso_export' ), 'vso_export' ) ); ?>"><?php esc_html_e( 'Download settings (.json)', 'vyntic-speed-optimizer' ); ?></a></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'vso_import' ); ?>
				<input type="hidden" name="action" value="vso_import">
				<input type="file" name="vso_file" accept=".json,application/json" required>
				<button type="submit" class="button"><?php esc_html_e( 'Import', 'vyntic-speed-optimizer' ); ?></button>
			</form>
		</section>
		<section class="vso-card">
			<h2><?php esc_html_e( 'Reset', 'vyntic-speed-optimizer' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Reset all settings?', 'vyntic-speed-optimizer' ) ); ?>');">
				<?php wp_nonce_field( 'vso_reset' ); ?>
				<input type="hidden" name="action" value="vso_reset">
				<button type="submit" class="button"><?php esc_html_e( 'Reset to recommended defaults', 'vyntic-speed-optimizer' ); ?></button>
			</form>
		</section>
		<section class="vso-card">
			<h2><?php esc_html_e( 'Troubleshooting', 'vyntic-speed-optimizer' ); ?></h2>
			<ul class="vso-list">
				<li><?php esc_html_e( 'Before trusting a change, check pages while logged in with ?vso_preview=1 at the end of the URL (or "Preview optimized page" in the admin bar). Admins normally see the original site.', 'vyntic-speed-optimizer' ); ?></li>
				<li><?php esc_html_e( 'Something looks broken? Open the page with ?vso_off=1 at the end of the URL to see it without any optimization.', 'vyntic-speed-optimizer' ); ?></li>
				<li><?php esc_html_e( 'A slider, menu or popup only works after scrolling? Add part of its script URL to "Do not delay scripts containing".', 'vyntic-speed-optimizer' ); ?></li>
				<li><?php esc_html_e( 'Styles missing before the first scroll with "Remove unused CSS"? Add the class names to "Always keep these selectors".', 'vyntic-speed-optimizer' ); ?></li>
				<li><?php esc_html_e( 'Response header "X-Vyntic-Cache: HIT" means the page came from cache.', 'vyntic-speed-optimizer' ); ?></li>
			</ul>
		</section>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------------- */

	private static function guard( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'vyntic-speed-optimizer' ), 403 );
		}
		check_admin_referer( $nonce );
	}

	private static function back( $tab, $msg ) {
		wp_safe_redirect( self::url( $tab, array( 'vso_msg' => $msg ) ) );
		exit;
	}

	public static function handle_save() {
		self::guard( 'vso_save' );
		$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'dashboard';
		$input  = isset( $_POST['vso'] ) && is_array( $_POST['vso'] ) ? wp_unslash( $_POST['vso'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in VSO_Settings::sanitize().
		$values = array_intersect_key( $input, VSO_Settings::defaults() );

		// Anything that changes optimization behaviour turns the level into "custom".
		$presets  = VSO_Settings::presets();
		$level    = VSO_Settings::get( 'level' );
		$compare  = isset( $presets[ $level ] ) ? $presets[ $level ] : array();
		foreach ( $values as $key => $value ) {
			if ( isset( $compare[ $key ] ) && (string) $compare[ $key ] !== (string) $value ) {
				$values['level'] = 'custom';
				break;
			}
		}
		VSO_Settings::update( $values );
		self::back( $tab, 'saved' );
	}

	public static function handle_preset() {
		self::guard( 'vso_preset' );
		$level = isset( $_POST['level'] ) ? sanitize_key( wp_unslash( $_POST['level'] ) ) : 'balanced';
		VSO_Settings::apply_preset( $level );
		self::back( 'dashboard', 'preset' );
	}

	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'vyntic-speed-optimizer' ), 403 );
		}
		check_admin_referer( 'vso_clear' );
		$url = isset( $_GET['vso_url'] ) ? esc_url_raw( wp_unslash( $_GET['vso_url'] ) ) : '';
		if ( $url ) {
			VSO_Page_Cache::purge_url( $url );
			wp_safe_redirect( $url );
			exit;
		}
		VSO_Page_Cache::purge_all();
		VSO_Utils::rrmdir( VSO_CACHE_DIR . 'assets' );
		VSO_Preload::schedule();
		$referer = wp_get_referer();
		if ( $referer && false === strpos( $referer, self::SLUG ) ) {
			wp_safe_redirect( add_query_arg( 'vso_cleared', 1, $referer ) );
			exit;
		}
		self::back( 'dashboard', 'cleared' );
	}

	public static function handle_preload() {
		self::guard( 'vso_preload' );
		VSO_Preload::schedule();
		self::back( 'dashboard', 'preload' );
	}

	public static function handle_export() {
		self::guard( 'vso_export' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=vyntic-speed-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		$settings = VSO_Settings::all();
		unset( $settings['psi_api_key'] );
		echo wp_json_encode( array( 'vyntic_speed_optimizer' => VSO_VERSION, 'settings' => $settings ), JSON_PRETTY_PRINT );
		exit;
	}

	public static function handle_import() {
		self::guard( 'vso_import' );
		if ( empty( $_FILES['vso_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['vso_file']['tmp_name'] ) ) { // phpcs:ignore
			self::back( 'tools', 'badfile' );
		}
		$data = json_decode( (string) file_get_contents( $_FILES['vso_file']['tmp_name'] ), true ); // phpcs:ignore
		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			self::back( 'tools', 'badfile' );
		}
		VSO_Settings::update( array_intersect_key( $data['settings'], VSO_Settings::defaults() ) );
		self::back( 'tools', 'imported' );
	}

	public static function handle_reset() {
		self::guard( 'vso_reset' );
		delete_option( VSO_Settings::OPTION );
		VSO_Settings::update( array_merge( VSO_Settings::defaults(), VSO_Settings::presets()['balanced'], array( 'level' => 'balanced' ) ) );
		self::back( 'tools', 'reset' );
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	private static function ajax_guard() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'vso_ajax', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'vyntic-speed-optimizer' ) ), 403 );
		}
	}

	public static function ajax_psi() {
		self::ajax_guard();
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore -- PageSpeed Insights can take up to a minute.
		}
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : home_url( '/' );
		$strategy = isset( $_POST['strategy'] ) && 'desktop' === $_POST['strategy'] ? 'desktop' : 'mobile';

		$args = array(
			'url'      => $url,
			'strategy' => $strategy,
			'category' => 'performance',
		);
		$key = trim( (string) VSO_Settings::get( 'psi_api_key' ) );
		if ( '' !== $key ) {
			$args['key'] = $key;
		}
		$response = wp_remote_get( add_query_arg( $args, 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' ), array( 'timeout' => 90 ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['lighthouseResult']['categories']['performance'] ) ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'PageSpeed Insights did not return a result.', 'vyntic-speed-optimizer' );
			wp_send_json_error( array( 'message' => $message ) );
		}
		$audits  = $body['lighthouseResult']['audits'];
		$metrics = array();
		foreach ( array( 'first-contentful-paint' => 'FCP', 'largest-contentful-paint' => 'LCP', 'total-blocking-time' => 'TBT', 'cumulative-layout-shift' => 'CLS', 'speed-index' => 'SI' ) as $audit => $label ) {
			if ( isset( $audits[ $audit ] ) ) {
				$metrics[ $label ] = array(
					'value' => isset( $audits[ $audit ]['displayValue'] ) ? $audits[ $audit ]['displayValue'] : '',
					'score' => isset( $audits[ $audit ]['score'] ) ? (float) $audits[ $audit ]['score'] : 0,
				);
			}
		}
		$result = array(
			'score'   => (int) round( 100 * (float) $body['lighthouseResult']['categories']['performance']['score'] ),
			'metrics' => $metrics,
		);

		$last              = (array) get_option( 'vso_psi_last', array() );
		$last[ $strategy ] = $result;
		$last['time']      = time();
		$last['url']       = $url;
		update_option( 'vso_psi_last', $last, false );

		wp_send_json_success( $result );
	}

	public static function ajax_webp() {
		self::ajax_guard();
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore
		}
		if ( ! empty( $_POST['reset'] ) ) {
			VSO_WebP::reset();
		}
		wp_send_json_success( VSO_WebP::bulk_batch( 5 ) );
	}

	public static function ajax_db() {
		self::ajax_guard();
		$item = isset( $_POST['item'] ) ? sanitize_key( wp_unslash( $_POST['item'] ) ) : '';
		if ( ! array_key_exists( $item, VSO_Database::items() ) ) {
			wp_send_json_error( array( 'message' => 'Unknown item' ) );
		}
		$removed = VSO_Database::clean( $item );
		$counts  = VSO_Database::counts();
		wp_send_json_success(
			array(
				'removed' => $removed,
				'count'   => isset( $counts[ $item ] ) ? $counts[ $item ] : 0,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Notices & meta box
	 * ------------------------------------------------------------------- */

	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_transient( 'vso_activated' ) ) {
			delete_transient( 'vso_activated' );
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Vyntic Speed Optimizer is active with the Balanced level.', 'vyntic-speed-optimizer' ) . '</strong> <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open the dashboard and test your score', 'vyntic-speed-optimizer' ) . '</a></p></div>';
		}

		$active = array();
		foreach ( self::CONFLICTS as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $name;
			}
		}
		if ( $active ) {
			/* translators: %s: plugin names */
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'Vyntic Speed Optimizer: please deactivate %s. Running two speed/cache plugins at once usually breaks pages or slows them down.', 'vyntic-speed-optimizer' ), implode( ', ', $active ) ) ) . '</p></div>';
		}

		if ( VSO_Settings::enabled( 'page_cache' ) ) {
			if ( VSO_Page_Cache::dropin_conflict() ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'Vyntic Speed Optimizer: wp-content/advanced-cache.php belongs to another cache plugin, so page cache cannot start. Deactivate that plugin, delete the file, then save Vyntic settings again.', 'vyntic-speed-optimizer' ) . '</p></div>';
			} elseif ( ! ( defined( 'WP_CACHE' ) && WP_CACHE ) && ! VSO_Page_Cache::set_wp_cache( true ) ) {
				echo '<div class="notice notice-warning"><p>' . wp_kses( __( 'Vyntic Speed Optimizer: could not edit wp-config.php. Add <code>define( \'WP_CACHE\', true );</code> near the top of wp-config.php to turn on page cache.', 'vyntic-speed-optimizer' ), array( 'code' => array() ) ) . '</p></div>';
			}
		}
	}

	public static function meta_box() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );
		add_meta_box( 'vso-meta', __( 'Vyntic Speed', 'vyntic-speed-optimizer' ), array( __CLASS__, 'render_meta_box' ), array_values( $types ), 'side', 'low' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'vso_meta', 'vso_meta_nonce' );
		?>
		<p><label><input type="checkbox" name="vso_disable" value="1" <?php checked( (bool) get_post_meta( $post->ID, '_vso_disable', true ) ); ?>> <?php esc_html_e( 'Disable optimizations on this page', 'vyntic-speed-optimizer' ); ?></label></p>
		<p><label><input type="checkbox" name="vso_no_cache" value="1" <?php checked( (bool) get_post_meta( $post->ID, '_vso_no_cache', true ) ); ?>> <?php esc_html_e( 'Never cache this page', 'vyntic-speed-optimizer' ); ?></label></p>
		<?php
	}

	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['vso_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vso_meta_nonce'] ) ), 'vso_meta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( array( 'vso_disable' => '_vso_disable', 'vso_no_cache' => '_vso_no_cache' ) as $field => $meta ) {
			if ( ! empty( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $meta, 1 );
			} else {
				delete_post_meta( $post_id, $meta );
			}
		}
	}
}
