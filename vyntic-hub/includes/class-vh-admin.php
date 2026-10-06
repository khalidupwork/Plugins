<?php
/**
 * Hub admin: one "Vyntic Hub" menu with Dashboard, Plugins, Sites and Settings.
 *
 * @package VynticHub
 */

defined( 'ABSPATH' ) || exit;

class VH_Admin {

	const MENU = 'vyntic-hub';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'submenus' ), 11 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_vh_settings', array( __CLASS__, 'save_settings' ) );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
	}

	public static function menu() {
		add_menu_page( __( 'Vyntic Hub', 'vyntic-hub' ), __( 'Vyntic Hub', 'vyntic-hub' ), 'manage_options', self::MENU, array( __CLASS__, 'render_dashboard' ), 'dashicons-store', 3 );
		add_submenu_page( self::MENU, __( 'Hub dashboard', 'vyntic-hub' ), __( 'Dashboard', 'vyntic-hub' ), 'manage_options', self::MENU, array( __CLASS__, 'render_dashboard' ) );
	}

	public static function submenus() {
		add_submenu_page( self::MENU, __( 'Sites using your plugins', 'vyntic-hub' ), __( 'Sites', 'vyntic-hub' ), 'manage_options', 'vyntic-hub-sites', array( __CLASS__, 'render_sites' ) );
		add_submenu_page( self::MENU, __( 'Hub settings', 'vyntic-hub' ), __( 'Settings', 'vyntic-hub' ), 'manage_options', 'vyntic-hub-settings', array( __CLASS__, 'render_settings' ) );
	}

	/**
	 * Keep "Vyntic Hub" highlighted while adding/editing a plugin.
	 */
	public static function parent_file( $parent ) {
		$screen = get_current_screen();
		if ( $screen && VH_Plugins::TYPE === $screen->post_type ) {
			return self::MENU;
		}
		return $parent;
	}

	public static function assets( $hook ) {
		$screen  = get_current_screen();
		$is_ours = false !== strpos( (string) $hook, 'vyntic-hub' ) || ( $screen && VH_Plugins::TYPE === $screen->post_type );
		if ( ! $is_ours ) {
			return;
		}
		wp_enqueue_style( 'vh-admin', VH_URL . 'assets/css/admin.css', array(), VH_VERSION );
		if ( $screen && VH_Plugins::TYPE === $screen->post_type && 'post' === $screen->base ) {
			wp_enqueue_media();
			wp_enqueue_script( 'vh-admin', VH_URL . 'assets/js/admin.js', array( 'jquery' ), VH_VERSION, true );
			wp_localize_script(
				'vh-admin',
				'vhAdmin',
				array(
					'ajax' => admin_url( 'admin-ajax.php' ),
					'i18n' => array(
						'choose'   => __( 'Choose the plugin zip', 'vyntic-hub' ),
						'use'      => __( 'Use this zip', 'vyntic-hub' ),
						'working'  => __( 'Reading zip...', 'vyntic-hub' ),
						'confirm'  => __( 'Delete this version? Sites already on it keep working.', 'vyntic-hub' ),
					),
				)
			);
		}
	}

	private static function header( $title, $subtitle = '' ) {
		?>
		<div class="vh-header">
			<div>
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( $subtitle ) : ?>
					<p><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . VH_Plugins::TYPE ) ); ?>"><?php esc_html_e( 'Add plugin', 'vyntic-hub' ); ?></a>
		</div>
		<?php
	}

	public static function render_dashboard() {
		$posts     = get_posts( array( 'post_type' => VH_Plugins::TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 100 ) );
		$downloads = 0;
		$published = 0;
		foreach ( $posts as $post ) {
			$downloads += (int) get_post_meta( $post->ID, '_vh_downloads', true );
			$published += 'publish' === $post->post_status && VH_Plugins::live_release( $post->ID ) ? 1 : 0;
		}
		$page = get_page_by_path( VH_Settings::base() );
		?>
		<div class="wrap vh-wrap">
			<?php self::header( __( 'Vyntic Hub', 'vyntic-hub' ), __( 'Publish a new version here and every site using that plugin gets the update.', 'vyntic-hub' ) ); ?>

			<div class="vh-stats">
				<div class="vh-stat"><span><?php esc_html_e( 'Published plugins', 'vyntic-hub' ); ?></span><strong><?php echo esc_html( number_format_i18n( $published ) ); ?></strong></div>
				<div class="vh-stat"><span><?php esc_html_e( 'Active sites', 'vyntic-hub' ); ?></span><strong><?php echo esc_html( number_format_i18n( VH_Sites::active_count() ) ); ?></strong><em><?php echo esc_html( sprintf( /* translators: %d: days */ __( 'checked in, last %d days', 'vyntic-hub' ), VH_Sites::ACTIVE_DAYS ) ); ?></em></div>
				<div class="vh-stat"><span><?php esc_html_e( 'Downloads', 'vyntic-hub' ); ?></span><strong><?php echo esc_html( number_format_i18n( $downloads ) ); ?></strong></div>
			</div>

			<div class="vh-card">
				<h2><?php esc_html_e( 'Your plugins', 'vyntic-hub' ); ?></h2>
				<?php if ( ! $posts ) : ?>
					<div class="vh-empty-state">
						<p><?php esc_html_e( 'No plugins yet.', 'vyntic-hub' ); ?></p>
						<ol>
							<li><?php esc_html_e( 'Click "Add plugin".', 'vyntic-hub' ); ?></li>
							<li><?php esc_html_e( 'Upload the plugin zip in the Releases box (name and version are read automatically).', 'vyntic-hub' ); ?></li>
							<li><?php esc_html_e( 'Write the description, set an icon (Featured image) and Publish.', 'vyntic-hub' ); ?></li>
						</ol>
					</div>
				<?php else : ?>
					<table class="widefat vh-table">
						<thead><tr><th></th><th><?php esc_html_e( 'Plugin', 'vyntic-hub' ); ?></th><th><?php esc_html_e( 'Live', 'vyntic-hub' ); ?></th><th><?php esc_html_e( 'Active sites by version', 'vyntic-hub' ); ?></th><th><?php esc_html_e( 'Downloads', 'vyntic-hub' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php
						foreach ( $posts as $post ) :
							$slug     = VH_Plugins::slug( $post->ID );
							$live     = VH_Plugins::live_release( $post->ID );
							$versions = $slug ? VH_Sites::versions( $slug ) : array();
							$total    = array_sum( $versions );
							$icon     = VH_Plugins::icon_url( $post->ID );
							?>
							<tr>
								<td class="vh-col-icon"><?php echo $icon ? '<img src="' . esc_url( $icon ) . '" alt="">' : '<span class="vh-icon-ph">' . esc_html( strtoupper( substr( get_the_title( $post ), 0, 1 ) ) ) . '</span>'; ?></td>
								<td><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong><br><code><?php echo esc_html( $slug ? $slug : '-' ); ?></code><?php echo 'publish' !== $post->post_status ? ' <span class="vh-pill">' . esc_html( get_post_status_object( $post->post_status )->label ) . '</span>' : ''; ?></td>
								<td><?php echo $live ? '<strong>' . esc_html( $live['version'] ) . '</strong>' : '<span class="vh-muted">' . esc_html__( 'No release', 'vyntic-hub' ) . '</span>'; ?></td>
								<td>
									<?php if ( $total ) : ?>
										<div class="vh-bars">
											<?php foreach ( $versions as $version => $n ) : ?>
												<span class="vh-bar <?php echo $live && $live['version'] === $version ? 'is-live' : ''; ?>" style="flex:<?php echo (int) $n; ?>" title="<?php echo esc_attr( $version . ': ' . $n ); ?>"></span>
											<?php endforeach; ?>
										</div>
										<span class="vh-muted">
											<?php
											$parts = array();
											foreach ( $versions as $version => $n ) {
												$parts[] = $version . ' (' . $n . ')';
											}
											echo esc_html( implode( ', ', $parts ) );
											?>
										</span>
									<?php else : ?>
										<span class="vh-muted"><?php esc_html_e( 'No sites yet', 'vyntic-hub' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format_i18n( (int) get_post_meta( $post->ID, '_vh_downloads', true ) ) ); ?></td>
								<td class="vh-row-actions">
									<a class="button" href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Edit / new version', 'vyntic-hub' ); ?></a>
									<?php if ( 'publish' === $post->post_status ) : ?>
										<a class="button-link" href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank"><?php esc_html_e( 'View page', 'vyntic-hub' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="vh-grid-2">
				<div class="vh-card">
					<h2><?php esc_html_e( 'Public plugins page', 'vyntic-hub' ); ?></h2>
					<?php if ( $page ) : ?>
						<p><a href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank"><?php echo esc_html( get_permalink( $page ) ); ?></a></p>
					<?php endif; ?>
					<p class="vh-muted"><?php esc_html_e( 'Each plugin also has its own page for search engines. Add the shortcode [vyntic_plugins] to any page to show the plugin grid.', 'vyntic-hub' ); ?></p>
				</div>
				<div class="vh-card">
					<h2><?php esc_html_e( 'Connect a plugin to this hub', 'vyntic-hub' ); ?></h2>
					<p class="vh-muted"><?php esc_html_e( 'Copy the vyntic-client folder into the plugin (vendor/vyntic-client) and add this to its main file:', 'vyntic-hub' ); ?></p>
					<pre class="vh-code">require_once __DIR__ . '/vendor/vyntic-client/loader.php';
vyntic_client_register( array(
	'file' => __FILE__,
	'slug' => 'your-plugin-folder',
	'name' => 'Your Plugin',
	'page' => 'your-admin-page',
) );</pre>
					<?php if ( untrailingslashit( home_url() ) !== 'https://vyntic.studio' ) : ?>
						<p class="vh-muted"><?php esc_html_e( 'This hub is not at https://vyntic.studio, so also add to wp-config.php of client sites (or the plugin):', 'vyntic-hub' ); ?> <code>define( 'VYNTIC_HUB_URL', '<?php echo esc_html( untrailingslashit( home_url() ) ); ?>' );</code></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_sites() {
		// phpcs:disable WordPress.Security.NonceVerification
		$slug     = isset( $_GET['plugin'] ) ? sanitize_key( wp_unslash( $_GET['plugin'] ) ) : '';
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:enable
		$per_page = 50;
		list( $total, $rows ) = VH_Sites::query( $slug, $search, $page, $per_page );
		$catalog  = VH_Plugins::published();
		?>
		<div class="wrap vh-wrap">
			<?php self::header( __( 'Sites', 'vyntic-hub' ), __( 'Every site that checked for updates. Only the site address and versions are stored.', 'vyntic-hub' ) ); ?>
			<div class="vh-card">
				<form method="get" class="vh-filters">
					<input type="hidden" name="page" value="vyntic-hub-sites">
					<select name="plugin">
						<option value=""><?php esc_html_e( 'All plugins', 'vyntic-hub' ); ?></option>
						<?php foreach ( $catalog as $item ) : ?>
							<option value="<?php echo esc_attr( $item['slug'] ); ?>" <?php selected( $slug, $item['slug'] ); ?>><?php echo esc_html( $item['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search site', 'vyntic-hub' ); ?>">
					<button class="button"><?php esc_html_e( 'Filter', 'vyntic-hub' ); ?></button>
				</form>
				<?php if ( ! $rows ) : ?>
					<p class="vh-muted"><?php esc_html_e( 'No sites yet. Sites appear here after their first update check (WordPress checks about twice a day).', 'vyntic-hub' ); ?></p>
				<?php else : ?>
					<table class="widefat striped vh-table">
						<thead><tr><th><?php esc_html_e( 'Site', 'vyntic-hub' ); ?></th><th><?php esc_html_e( 'Plugin', 'vyntic-hub' ); ?></th><th><?php esc_html_e( 'Version', 'vyntic-hub' ); ?></th><th>WordPress</th><th>PHP</th><th><?php esc_html_e( 'Last check', 'vyntic-hub' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php $live = isset( $catalog[ $row['slug'] ] ) ? $catalog[ $row['slug'] ]['version'] : ''; ?>
							<tr class="<?php echo VH_Sites::is_active( $row['last_seen'] ) ? '' : 'vh-inactive'; ?>">
								<td><a href="<?php echo esc_url( $row['site_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( preg_replace( '#^https?://#', '', $row['site_url'] ) ); ?></a></td>
								<td><?php echo esc_html( isset( $catalog[ $row['slug'] ] ) ? $catalog[ $row['slug'] ]['name'] : $row['slug'] ); ?></td>
								<td><?php echo esc_html( $row['version'] ); ?><?php echo $live && version_compare( $row['version'], $live, '<' ) ? ' <span class="vh-pill vh-pill-warn">' . esc_html__( 'outdated', 'vyntic-hub' ) . '</span>' : ''; ?></td>
								<td><?php echo esc_html( $row['wp'] ); ?></td>
								<td><?php echo esc_html( $row['php'] ); ?></td>
								<td><?php echo esc_html( sprintf( /* translators: %s: time */ __( '%s ago', 'vyntic-hub' ), human_time_diff( strtotime( $row['last_seen'] . ' UTC' ) ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php
					$pages = (int) ceil( $total / $per_page );
					if ( $pages > 1 ) {
						echo '<div class="vh-pagination">' . paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
							array(
								'base'    => add_query_arg( 'paged', '%#%' ),
								'format'  => '',
								'current' => $page,
								'total'   => $pages,
							)
						) . '</div>';
					}
					?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function render_settings() {
		$s = VH_Settings::all();
		// phpcs:ignore WordPress.Security.NonceVerification
		$saved = isset( $_GET['saved'] );
		?>
		<div class="wrap vh-wrap">
			<?php self::header( __( 'Settings', 'vyntic-hub' ) ); ?>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'vyntic-hub' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vh-card vh-form">
				<?php wp_nonce_field( 'vh_settings' ); ?>
				<input type="hidden" name="action" value="vh_settings">
				<div class="vh-field">
					<label for="vh-base"><?php esc_html_e( 'Plugin pages address', 'vyntic-hub' ); ?></label>
					<div><code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code><input id="vh-base" type="text" name="vh[base]" value="<?php echo esc_attr( $s['base'] ); ?>" class="regular-text" style="width:160px"><code>/plugin-name/</code>
					<p class="vh-muted"><?php esc_html_e( 'Keep a keyword here (default "plugins"). Changing it later breaks links that are already indexed.', 'vyntic-hub' ); ?></p></div>
				</div>
				<div class="vh-field">
					<label for="vh-brand"><?php esc_html_e( 'Author name', 'vyntic-hub' ); ?></label>
					<div><input id="vh-brand" type="text" name="vh[brand]" value="<?php echo esc_attr( $s['brand'] ); ?>" class="regular-text">
					<p class="vh-muted"><?php esc_html_e( 'Shown on plugin pages and in search results (structured data).', 'vyntic-hub' ); ?></p></div>
				</div>
				<div class="vh-field">
					<span class="vh-label"><?php esc_html_e( 'Plugin pages', 'vyntic-hub' ); ?></span>
					<div>
						<label><input type="checkbox" name="vh[show_download]" value="1" <?php checked( $s['show_download'] ); ?>> <?php esc_html_e( 'Show a download button', 'vyntic-hub' ); ?></label><br>
						<label><input type="checkbox" name="vh[show_installs]" value="1" <?php checked( $s['show_installs'] ); ?>> <?php esc_html_e( 'Show "Active on N sites"', 'vyntic-hub' ); ?></label>
					</div>
				</div>
				<p><button class="button button-primary"><?php esc_html_e( 'Save settings', 'vyntic-hub' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'vyntic-hub' ), 403 );
		}
		check_admin_referer( 'vh_settings' );
		$input = isset( $_POST['vh'] ) && is_array( $_POST['vh'] ) ? wp_unslash( $_POST['vh'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in VH_Settings::save().
		VH_Settings::save( $input );
		wp_safe_redirect( admin_url( 'admin.php?page=vyntic-hub-settings&saved=1' ) );
		exit;
	}
}
