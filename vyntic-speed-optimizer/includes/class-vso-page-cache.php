<?php
/**
 * Page cache: stores optimized HTML, installs the drop-in, purges on content changes.
 *
 * @package VynticSpeedOptimizer
 */

defined( 'ABSPATH' ) || exit;

require_once VSO_PATH . 'includes/class-vso-cache-engine.php';

class VSO_Page_Cache {

	const DROPIN_SIGNATURE = 'Vyntic Speed Optimizer page cache drop-in';

	/** @var array URLs queued for purge at shutdown (deduplicated). */
	private static $purge_queue = array();

	/** @var bool */
	private static $purge_all = false;

	public static function init() {
		// Content changes.
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_id' ) );
		add_action( 'trashed_post', array( __CLASS__, 'on_post_id' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
		add_action( 'comment_post', array( __CLASS__, 'on_comment' ) );
		add_action( 'edit_comment', array( __CLASS__, 'on_comment' ) );
		add_action( 'wp_set_comment_status', array( __CLASS__, 'on_comment' ) );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_wc_product' ) );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_wc_product' ) );

		// Site-wide changes => full purge.
		foreach ( array( 'switch_theme', 'customize_save_after', 'wp_update_nav_menu', 'update_option_sidebars_widgets', 'activated_plugin', 'deactivated_plugin', 'upgrader_process_complete', 'permalink_structure_changed', 'update_option_blogname', 'update_option_blogdescription', 'elementor/core/files/clear_cache', 'vso_settings_updated' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'schedule_purge_all' ) );
		}
		add_action( 'edited_term', array( __CLASS__, 'schedule_purge_all' ) );

		add_action( 'shutdown', array( __CLASS__, 'run_purge_queue' ) );
		add_action( 'vso_cache_gc', array( __CLASS__, 'garbage_collect' ) );
	}

	public static function config() {
		$lifespan = (int) VSO_Settings::get( 'cache_lifespan' );
		return array(
			'dir'           => VSO_CACHE_DIR,
			'lifespan'      => $lifespan * HOUR_IN_SECONDS,
			'mobile'        => VSO_Settings::enabled( 'cache_mobile' ),
			'logged_in'     => false,
			'cookies'       => VSO_Settings::lines( 'cache_exclude_cookies' ),
			'exclude'       => VSO_Settings::lines( 'cache_exclude_urls' ),
			'ignore_params' => array_map( 'strtolower', VSO_Settings::lines( 'cache_ignore_params' ) ),
			'charset'       => get_option( 'blog_charset', 'UTF-8' ),
		);
	}

	/**
	 * Decides whether the current front-end response can be stored.
	 */
	public static function is_cacheable_request() {
		if ( ! VSO_Settings::enabled( 'page_cache' ) ) {
			return false;
		}
		if ( is_user_logged_in() || is_admin() || is_preview() || is_404() || is_search() || is_feed() || is_trackback() || is_robots() || post_password_required() ) {
			return false;
		}
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return false;
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return false;
		}
		if ( is_singular() && get_post_meta( get_queried_object_id(), '_vso_no_cache', true ) ) {
			return false;
		}
		if ( http_response_code() && 200 !== http_response_code() ) {
			return false;
		}
		foreach ( headers_list() as $header ) {
			if ( preg_match( '#^content-type:\s*(?!text/html)#i', $header ) ) {
				return false;
			}
			if ( preg_match( '#^cache-control:.*(no-cache|no-store|private)#i', $header ) ) {
				return false;
			}
		}
		$config = self::config();
		if ( VSO_Cache_Engine::should_bypass( $config ) ) {
			return false;
		}
		return (bool) apply_filters( 'vso_cache_page', true );
	}

	/**
	 * Writes the final (optimized) HTML to disk.
	 */
	public static function store( $html ) {
		if ( strlen( $html ) < 255 || false === stripos( $html, '</html>' ) ) {
			return;
		}
		$file = VSO_Cache_Engine::cache_file( self::config() );
		if ( ! $file ) {
			return;
		}
		$html .= "\n<!-- Cached by Vyntic Speed Optimizer on " . gmdate( 'Y-m-d H:i:s' ) . ' UTC -->';
		if ( VSO_Utils::write_file( $file, $html ) && function_exists( 'gzencode' ) ) {
			VSO_Utils::write_file( $file . '.gz', gzencode( $html, 6 ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Purging
	 * ------------------------------------------------------------------- */

	public static function purge_all() {
		VSO_Utils::rrmdir( VSO_CACHE_DIR . 'pages' );
		do_action( 'vso_cache_purged_all' );
	}

	/**
	 * Removes every cached variant (mobile/https) of a single URL.
	 */
	public static function purge_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return;
		}
		$host  = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		$path  = isset( $parts['path'] ) ? $parts['path'] : '/';
		$file  = VSO_Cache_Engine::cache_file( array( 'dir' => VSO_CACHE_DIR ), $path, $host, false, false );
		if ( ! $file ) {
			return;
		}
		foreach ( (array) glob( dirname( $file ) . '/index*.html*' ) as $cached ) {
			@unlink( $cached ); // phpcs:ignore
		}
		do_action( 'vso_cache_purged_url', $url );
	}

	public static function schedule_purge_all() {
		self::$purge_all = true;
	}

	public static function queue_url( $url ) {
		if ( $url ) {
			self::$purge_queue[ $url ] = true;
		}
	}

	public static function run_purge_queue() {
		if ( self::$purge_all ) {
			self::purge_all();
			self::$purge_all   = false;
			self::$purge_queue = array();
			VSO_Preload::schedule();
			return;
		}
		if ( self::$purge_queue ) {
			foreach ( array_keys( self::$purge_queue ) as $url ) {
				self::purge_url( $url );
			}
			VSO_Preload::queue_urls( array_keys( self::$purge_queue ) );
			self::$purge_queue = array();
		}
	}

	/**
	 * Queues the post itself plus every page that lists it.
	 */
	public static function queue_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->public ) {
			return;
		}

		self::queue_url( get_permalink( $post ) );
		self::queue_url( home_url( '/' ) );

		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page ) {
			self::queue_url( get_permalink( $posts_page ) );
		}
		$archive = get_post_type_archive_link( $post->post_type );
		if ( $archive ) {
			self::queue_url( $archive );
		}
		self::queue_url( get_author_posts_url( (int) $post->post_author ) );

		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$terms = get_the_terms( $post, $taxonomy );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) ) {
						self::queue_url( $link );
					}
				}
			}
		}
	}

	public static function on_save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( $post && 'publish' === $post->post_status ) {
			self::queue_post( $post_id );
		}
	}

	public static function on_post_id( $post_id ) {
		self::queue_post( $post_id );
	}

	public static function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			self::queue_post( $post->ID );
		}
	}

	public static function on_comment( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			self::queue_url( get_permalink( (int) $comment->comment_post_ID ) );
		}
	}

	public static function on_wc_product( $product ) {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$id = method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
			self::queue_post( $id );
		}
	}

	/**
	 * Deletes expired cache files (cron, twice daily).
	 */
	public static function garbage_collect() {
		$lifespan = (int) VSO_Settings::get( 'cache_lifespan' ) * HOUR_IN_SECONDS;
		$dir      = VSO_CACHE_DIR . 'pages';
		if ( $lifespan <= 0 || ! is_dir( $dir ) ) {
			return;
		}
		try {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( $file->isFile() && preg_match( '/^index.*\.html(\.gz)?$/', $file->getFilename() ) && $file->getMTime() + $lifespan < time() ) {
					@unlink( $file->getPathname() ); // phpcs:ignore
				}
			}
		} catch ( Exception $e ) {
			return;
		}
	}

	/* ---------------------------------------------------------------------
	 * Drop-in + WP_CACHE management
	 * ------------------------------------------------------------------- */

	public static function dropin_path() {
		return WP_CONTENT_DIR . '/advanced-cache.php';
	}

	public static function dropin_is_ours() {
		$file = self::dropin_path();
		return is_file( $file ) && false !== strpos( (string) @file_get_contents( $file ), self::DROPIN_SIGNATURE ); // phpcs:ignore
	}

	/**
	 * Returns true when another plugin owns advanced-cache.php.
	 */
	public static function dropin_conflict() {
		return is_file( self::dropin_path() ) && ! self::dropin_is_ours();
	}

	public static function install_dropin() {
		if ( self::dropin_conflict() ) {
			return false;
		}
		$engine   = VSO_PATH . 'includes/class-vso-cache-engine.php';
		$contents = "<?php\n"
			. '// ' . self::DROPIN_SIGNATURE . ". Generated automatically. Do not edit.\n"
			. "defined( 'ABSPATH' ) || exit;\n"
			. "define( 'VSO_ADVANCED_CACHE', true );\n"
			. '$vso_engine = ' . var_export( $engine, true ) . ";\n"
			. "if ( is_file( \$vso_engine ) ) {\n"
			. "\trequire_once \$vso_engine;\n"
			. "\tVSO_Cache_Engine::serve( " . var_export( self::config(), true ) . " );\n"
			. "}\n";
		return false !== @file_put_contents( self::dropin_path(), $contents ); // phpcs:ignore
	}

	public static function remove_dropin() {
		if ( self::dropin_is_ours() ) {
			@unlink( self::dropin_path() ); // phpcs:ignore
		}
	}

	public static function wp_config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php';
		}
		return false;
	}

	/**
	 * Adds or removes "define( 'WP_CACHE', true );" in wp-config.php.
	 */
	public static function set_wp_cache( $enable ) {
		$file = self::wp_config_path();
		if ( ! $file || ! wp_is_writable( $file ) ) {
			return false;
		}
		$config = (string) file_get_contents( $file ); // phpcs:ignore
		$marker = '/** Enables page caching for Vyntic Speed Optimizer. */';
		if ( ! $enable && false === strpos( $config, $marker ) ) {
			return true; // Never touch a WP_CACHE line we did not add.
		}

		// Strip our previous line (and any other WP_CACHE define when enabling).
		$clean = preg_replace( '#\R?' . preg_quote( $marker, '#' ) . '\R#', "\n", $config );
		if ( $enable ) {
			$clean = preg_replace( '#^\s*define\(\s*[\'"]WP_CACHE[\'"].*$\R?#mi', '', $clean );
			$clean = preg_replace( '#^<\?php\s*#', "<?php\n" . $marker . "\ndefine( 'WP_CACHE', true );\n", $clean, 1 );
		} else {
			$clean = preg_replace( '#^\s*define\(\s*[\'"]WP_CACHE[\'"]\s*,\s*true\s*\);\s*\R?#mi', '', $clean, 1 );
		}
		if ( $clean === $config ) {
			return true;
		}
		return false !== @file_put_contents( $file, $clean, LOCK_EX ); // phpcs:ignore
	}

	/**
	 * Brings drop-in + WP_CACHE in line with the current settings.
	 */
	public static function sync() {
		if ( VSO_Settings::enabled( 'page_cache' ) ) {
			self::install_dropin();
			if ( ! ( defined( 'WP_CACHE' ) && WP_CACHE ) || self::dropin_is_ours() ) {
				self::set_wp_cache( true );
			}
		} else {
			self::remove_dropin();
			self::set_wp_cache( false );
		}
	}

	public static function status() {
		return array(
			'enabled'  => VSO_Settings::enabled( 'page_cache' ),
			'dropin'   => self::dropin_is_ours(),
			'conflict' => self::dropin_conflict(),
			'wp_cache' => defined( 'WP_CACHE' ) && WP_CACHE,
			'stats'    => VSO_Utils::dir_stats( VSO_CACHE_DIR . 'pages' ),
		);
	}
}
