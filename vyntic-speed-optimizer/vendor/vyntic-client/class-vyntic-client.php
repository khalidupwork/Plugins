<?php
/**
 * Vyntic Client: one shared "Vyntic" admin menu for all Vyntic plugins, plus
 * updates delivered from the Vyntic Hub (vyntic.studio) through the normal
 * WordPress update screens.
 *
 * Only the site URL, WordPress/PHP version and the versions of Vyntic plugins
 * are sent to the hub. Define VYNTIC_HUB_URL to use a different hub.
 *
 * @package VynticClient
 */

defined( 'ABSPATH' ) || exit;

class Vyntic_Client {

	const VERSION   = '1.0.0';
	const MENU      = 'vyntic';
	const CACHE_KEY = 'vyntic_client_updates';
	const CATALOG   = 'vyntic_client_catalog';

	/** @var array slug => plugin config */
	private static $plugins = array();

	public static function boot( array $plugins ) {
		foreach ( $plugins as $slug => $plugin ) {
			if ( empty( $plugin['file'] ) ) {
				continue;
			}
			$plugin['basename']     = plugin_basename( $plugin['file'] );
			self::$plugins[ $slug ] = $plugin;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_updates' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 0 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_post_vyntic_install', array( __CLASS__, 'handle_install' ) );
		add_action( 'admin_post_vyntic_check', array( __CLASS__, 'handle_check' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_css' ) );
	}

	public static function hub_url() {
		$url = defined( 'VYNTIC_HUB_URL' ) ? VYNTIC_HUB_URL : 'https://vyntic.studio';
		return untrailingslashit( (string) apply_filters( 'vyntic_hub_url', $url ) );
	}

	/**
	 * REST URL that works with and without pretty permalinks on the hub.
	 */
	public static function api( $route ) {
		return self::hub_url() . '/?rest_route=/vyntic-hub/v1/' . ltrim( $route, '/' );
	}

	/**
	 * True when both URLs are on the same host (www. ignored) over HTTPS.
	 */
	public static function same_host( $url, $hub ) {
		$strip = static function ( $u ) {
			return preg_replace( '/^www\./i', '', strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) ) );
		};
		return 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) && '' !== $strip( $hub ) && $strip( $url ) === $strip( $hub );
	}

	public static function plugins() {
		return self::$plugins;
	}

	/* ---------------------------------------------------------------------
	 * Updates
	 * ------------------------------------------------------------------- */

	/**
	 * Asks the hub (at most every 12 hours) for the latest versions of all
	 * installed Vyntic plugins in one request.
	 */
	public static function remote_updates( $force = false ) {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( ! $force && is_array( $cached ) ) {
			return $cached;
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$versions = array();
		foreach ( self::$plugins as $slug => $plugin ) {
			$data              = get_plugin_data( $plugin['file'], false, false );
			$versions[ $slug ] = $data['Version'];
		}
		if ( ! $versions ) {
			return array();
		}

		$response = wp_remote_post(
			self::api( 'check' ),
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'site'    => home_url( '/' ),
						'wp'      => get_bloginfo( 'version' ),
						'php'     => PHP_VERSION,
						'plugins' => $versions,
					)
				),
			)
		);

		$data = array();
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$data = is_array( $body ) && isset( $body['plugins'] ) && is_array( $body['plugins'] ) ? $body['plugins'] : array();
		}
		// Cache failures for an hour only, so a hub outage does not hide updates for long.
		set_site_transient( self::CACHE_KEY, $data, $data ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $data;
	}

	public static function inject_updates( $transient ) {
		if ( ! is_object( $transient ) || empty( self::$plugins ) ) {
			return $transient;
		}
		$remote = self::remote_updates();
		foreach ( self::$plugins as $slug => $plugin ) {
			if ( empty( $remote[ $slug ]['version'] ) ) {
				continue;
			}
			$info    = $remote[ $slug ];
			$current = isset( $transient->checked[ $plugin['basename'] ] ) ? $transient->checked[ $plugin['basename'] ] : '';
			$item    = (object) array(
				'id'            => self::hub_url() . '/plugins/' . $slug,
				'slug'          => $slug,
				'plugin'        => $plugin['basename'],
				'new_version'   => $info['version'],
				'url'           => isset( $info['url'] ) ? $info['url'] : self::hub_url(),
				'package'       => isset( $info['package'] ) ? $info['package'] : '',
				'tested'        => isset( $info['tested'] ) ? $info['tested'] : '',
				'requires'      => isset( $info['requires'] ) ? $info['requires'] : '',
				'requires_php'  => isset( $info['requires_php'] ) ? $info['requires_php'] : '',
				'icons'         => isset( $info['icon'] ) && $info['icon'] ? array( 'default' => $info['icon'] ) : array(),
			);
			if ( $item->package && ! self::same_host( $item->package, self::hub_url() ) ) {
				$item->package = ''; // Never install code from anywhere but the hub.
			}
			if ( $current && version_compare( $info['version'], $current, '>' ) && $item->package ) {
				$transient->response[ $plugin['basename'] ] = $item;
			} else {
				// Listing it as "no update" makes the auto-update toggle appear.
				$transient->no_update[ $plugin['basename'] ] = $item;
			}
		}
		return $transient;
	}

	/**
	 * Content of the "View details" popup.
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}
		$known = isset( self::$plugins[ $args->slug ] );
		if ( ! $known ) {
			$catalog = self::catalog();
			$known   = isset( $catalog[ $args->slug ] );
		}
		if ( ! $known ) {
			return $result;
		}
		$response = wp_remote_get( self::api( 'info/' . rawurlencode( $args->slug ) ), array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $result;
		}
		$info = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $info ) || empty( $info['version'] ) ) {
			return $result;
		}
		return (object) array(
			'name'          => $info['name'],
			'slug'          => $args->slug,
			'version'       => $info['version'],
			'author'        => '<a href="https://vyntic.studio/">Vyntic Studio</a>',
			'homepage'      => $info['url'],
			'requires'      => $info['requires'],
			'requires_php'  => $info['requires_php'],
			'tested'        => $info['tested'],
			'last_updated'  => $info['updated'],
			'download_link' => $info['package'],
			'sections'      => array_map( 'wp_kses_post', (array) $info['sections'] ),
			'banners'       => isset( $info['banner'] ) && $info['banner'] ? array( 'low' => $info['banner'], 'high' => $info['banner'] ) : array(),
			'icons'         => isset( $info['icon'] ) && $info['icon'] ? array( 'default' => $info['icon'] ) : array(),
		);
	}

	public static function flush() {
		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( self::CATALOG );
	}

	/**
	 * All plugins published on the hub (cached 12 hours).
	 */
	public static function catalog() {
		$cached = get_site_transient( self::CATALOG );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$list     = array();
		$response = wp_remote_get( self::api( 'plugins' ), array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			foreach ( is_array( $body ) ? $body : array() as $item ) {
				if ( ! empty( $item['slug'] ) ) {
					$list[ $item['slug'] ] = $item;
				}
			}
		}
		set_site_transient( self::CATALOG, $list, $list ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $list;
	}

	/* ---------------------------------------------------------------------
	 * Shared "Vyntic" menu
	 * ------------------------------------------------------------------- */

	public static function icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M2 3h3.6L10 13.4 14.4 3H18l-6.6 14H8.6z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore
	}

	public static function menu() {
		add_menu_page(
			'Vyntic',
			'Vyntic',
			'manage_options',
			self::MENU,
			array( __CLASS__, 'render_home' ),
			self::icon(),
			80
		);
		add_submenu_page( self::MENU, 'Vyntic', __( 'All Vyntic plugins', 'vyntic-client' ), 'manage_options', self::MENU, array( __CLASS__, 'render_home' ) );
	}

	public static function menu_css() {
		echo '<style>#adminmenu .toplevel_page_vyntic .wp-menu-image img{width:18px;height:18px;padding-top:8px;opacity:.85}</style>';
	}

	public static function render_home() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$updates = get_site_transient( 'update_plugins' );
		$catalog = self::catalog();
		$msg     = isset( $_GET['vyntic_msg'] ) ? sanitize_key( wp_unslash( $_GET['vyntic_msg'] ) ) : ''; // phpcs:ignore
		?>
		<div class="wrap vyntic-home">
			<style>
				.vyntic-home h1{display:flex;align-items:center;gap:10px}
				.vyntic-home .vy-sub{color:#646970;margin:4px 0 20px}
				.vyntic-home .vy-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:28px}
				.vyntic-home .vy-card{background:#fff;border:1px solid #e2e4ea;border-radius:12px;padding:18px;display:flex;flex-direction:column;gap:8px}
				.vyntic-home .vy-card-head{display:flex;gap:12px;align-items:center}
				.vyntic-home .vy-icon{width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,#5b3df5,#00c2a8);color:#fff;display:grid;place-items:center;font-weight:800;font-size:20px;flex:none;overflow:hidden}
				.vyntic-home .vy-icon img{width:100%;height:100%;object-fit:cover}
				.vyntic-home .vy-card h3{margin:0;font-size:15px}
				.vyntic-home .vy-meta{color:#646970;font-size:12px}
				.vyntic-home .vy-card p{margin:0;color:#3c434a;flex:1}
				.vyntic-home .vy-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
				.vyntic-home .vy-badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;background:#e7f7ee;color:#0c7a3e}
				.vyntic-home .vy-badge.up{background:#fff4e0;color:#9a5b00}
				.vyntic-home h2{font-size:16px;margin:8px 0 12px}
			</style>
			<h1><img src="<?php echo esc_attr( self::icon() ); ?>" alt="" width="22" height="22" style="filter:brightness(.3)"> Vyntic</h1>
			<p class="vy-sub"><?php esc_html_e( 'All your Vyntic plugins in one place. Updates arrive automatically from vyntic.studio.', 'vyntic-client' ); ?></p>

			<?php if ( 'installed' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Plugin installed and activated.', 'vyntic-client' ); ?></p></div>
			<?php elseif ( 'failed' === $msg ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Installation failed. Please try again or upload the zip manually.', 'vyntic-client' ); ?></p></div>
			<?php elseif ( 'checked' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Checked for updates.', 'vyntic-client' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Installed', 'vyntic-client' ); ?></h2>
			<div class="vy-grid">
				<?php
				foreach ( self::$plugins as $slug => $plugin ) :
					$data   = get_plugin_data( $plugin['file'], false, false );
					$update = ( is_object( $updates ) && isset( $updates->response[ $plugin['basename'] ] ) ) ? $updates->response[ $plugin['basename'] ] : null;
					$icon   = isset( $catalog[ $slug ]['icon'] ) ? $catalog[ $slug ]['icon'] : '';
					?>
					<div class="vy-card">
						<div class="vy-card-head">
							<span class="vy-icon"><?php echo $icon ? '<img src="' . esc_url( $icon ) . '" alt="">' : esc_html( strtoupper( substr( $data['Name'], 0, 1 ) ) ); ?></span>
							<div>
								<h3><?php echo esc_html( $data['Name'] ); ?></h3>
								<span class="vy-meta"><?php echo esc_html( 'v' . $data['Version'] ); ?></span>
								<?php if ( $update ) : ?>
									<span class="vy-badge up"><?php echo esc_html( sprintf( /* translators: %s: version */ __( 'Update %s available', 'vyntic-client' ), $update->new_version ) ); ?></span>
								<?php else : ?>
									<span class="vy-badge"><?php esc_html_e( 'Up to date', 'vyntic-client' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<p><?php echo esc_html( wp_strip_all_tags( $data['Description'] ) ); ?></p>
						<div class="vy-actions">
							<?php if ( ! empty( $plugin['page'] ) ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $plugin['page'] ) ); ?>"><?php esc_html_e( 'Open', 'vyntic-client' ); ?></a>
							<?php endif; ?>
							<?php if ( $update && current_user_can( 'update_plugins' ) ) : ?>
								<a class="button" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $plugin['basename'] ) ), 'upgrade-plugin_' . $plugin['basename'] ) ); ?>"><?php esc_html_e( 'Update now', 'vyntic-client' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			$more = array_diff_key( $catalog, self::$plugins );
			if ( $more ) :
				?>
				<h2><?php esc_html_e( 'More from Vyntic', 'vyntic-client' ); ?></h2>
				<div class="vy-grid">
					<?php foreach ( $more as $slug => $item ) : ?>
						<div class="vy-card">
							<div class="vy-card-head">
								<span class="vy-icon"><?php echo ! empty( $item['icon'] ) ? '<img src="' . esc_url( $item['icon'] ) . '" alt="">' : esc_html( strtoupper( substr( (string) $item['name'], 0, 1 ) ) ); ?></span>
								<div>
									<h3><?php echo esc_html( $item['name'] ); ?></h3>
									<span class="vy-meta"><?php echo esc_html( 'v' . $item['version'] ); ?></span>
								</div>
							</div>
							<p><?php echo esc_html( isset( $item['short_description'] ) ? $item['short_description'] : '' ); ?></p>
							<div class="vy-actions">
								<?php if ( ! empty( $item['package'] ) && current_user_can( 'install_plugins' ) && ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'vyntic_install' ); ?>
										<input type="hidden" name="action" value="vyntic_install">
										<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>">
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Install & activate', 'vyntic-client' ); ?></button>
									</form>
								<?php endif; ?>
								<?php if ( ! empty( $item['url'] ) ) : ?>
									<a class="button" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'vyntic-client' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'vyntic_check' ); ?>
				<input type="hidden" name="action" value="vyntic_check">
				<button type="submit" class="button-link"><?php esc_html_e( 'Check for updates now', 'vyntic-client' ); ?></button>
			</form>
		</div>
		<?php
	}

	public static function handle_check() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'vyntic-client' ), 403 );
		}
		check_admin_referer( 'vyntic_check' );
		self::flush();
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU . '&vyntic_msg=checked' ) );
		exit;
	}

	/**
	 * Installs and activates a plugin from the hub catalog.
	 */
	public static function handle_install() {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'vyntic-client' ), 403 );
		}
		check_admin_referer( 'vyntic_install' );
		$slug    = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$catalog = self::catalog();
		$back    = admin_url( 'admin.php?page=' . self::MENU );
		if ( ! isset( $catalog[ $slug ]['package'] ) ) {
			wp_safe_redirect( add_query_arg( 'vyntic_msg', 'failed', $back ) );
			exit;
		}
		// Only ever install packages served by the hub itself.
		$package = (string) $catalog[ $slug ]['package'];
		if ( ! self::same_host( $package, self::hub_url() ) ) {
			wp_safe_redirect( add_query_arg( 'vyntic_msg', 'failed', $back ) );
			exit;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $package );
		if ( true !== $result || ! $upgrader->plugin_info() ) {
			wp_safe_redirect( add_query_arg( 'vyntic_msg', 'failed', $back ) );
			exit;
		}
		activate_plugin( $upgrader->plugin_info() );
		self::flush();
		wp_safe_redirect( add_query_arg( 'vyntic_msg', 'installed', $back ) );
		exit;
	}
}
