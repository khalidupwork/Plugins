<?php
/**
 * The "vyntic_plugin" post type: one post per plugin, with its releases.
 * Public pages live at /plugins/{slug}/ for SEO.
 *
 * @package VynticHub
 */

defined( 'ABSPATH' ) || exit;

class VH_Plugins {

	const TYPE = 'vyntic_plugin';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'wp_ajax_vh_add_release', array( __CLASS__, 'ajax_add_release' ) );
		add_action( 'wp_ajax_vh_release_action', array( __CLASS__, 'ajax_release_action' ) );
		add_filter( 'manage_' . self::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_zip' ) );
	}

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'       => array(
					'name'               => __( 'Plugins', 'vyntic-hub' ),
					'singular_name'      => __( 'Plugin', 'vyntic-hub' ),
					'add_new'            => __( 'Add plugin', 'vyntic-hub' ),
					'add_new_item'       => __( 'Add plugin', 'vyntic-hub' ),
					'edit_item'          => __( 'Edit plugin', 'vyntic-hub' ),
					'all_items'          => __( 'Plugins', 'vyntic-hub' ),
					'search_items'       => __( 'Search plugins', 'vyntic-hub' ),
					'not_found'          => __( 'No plugins yet. Click "Add plugin" to publish your first one.', 'vyntic-hub' ),
					'featured_image'     => __( 'Plugin icon', 'vyntic-hub' ),
					'set_featured_image' => __( 'Set plugin icon (square, 256×256)', 'vyntic-hub' ),
				),
				'public'       => true,
				'show_in_menu' => VH_Admin::MENU,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-admin-plugins',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'rewrite'      => array(
					'slug'       => VH_Settings::base(),
					'with_front' => false,
				),
				'has_archive'  => false,
			)
		);
	}

	public static function allow_zip( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['zip'] = 'application/zip';
		}
		return $mimes;
	}

	/* ---------------------------------------------------------------------
	 * Data helpers
	 * ------------------------------------------------------------------- */

	public static function releases( $post_id ) {
		$releases = get_post_meta( $post_id, '_vh_releases', true );
		$releases = is_array( $releases ) ? $releases : array();
		uasort(
			$releases,
			static function ( $a, $b ) {
				return version_compare( $b['version'], $a['version'] );
			}
		);
		return $releases;
	}

	/**
	 * The release visitors and update checks get (normally the newest).
	 */
	public static function live_release( $post_id ) {
		$releases = self::releases( $post_id );
		$live     = (string) get_post_meta( $post_id, '_vh_live', true );
		if ( '' !== $live && isset( $releases[ $live ] ) ) {
			return $releases[ $live ];
		}
		return $releases ? reset( $releases ) : null;
	}

	public static function slug( $post_id ) {
		return (string) get_post_meta( $post_id, '_vh_slug', true );
	}

	public static function find_by_slug( $slug ) {
		$posts = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'publish',
				'meta_key'       => '_vh_slug', // phpcs:ignore
				'meta_value'     => $slug, // phpcs:ignore
				'posts_per_page' => 1,
			)
		);
		return $posts ? $posts[0] : null;
	}

	public static function download_url( $slug, $version = '' ) {
		$args = array( 'vyntic_download' => $slug );
		if ( $version ) {
			$args['version'] = $version;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	public static function icon_url( $post_id ) {
		$url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
		return $url ? $url : '';
	}

	/**
	 * Public data for one plugin (used by the API, the client and the pages).
	 */
	public static function item( WP_Post $post ) {
		$release = self::live_release( $post->ID );
		if ( ! $release ) {
			return null;
		}
		$slug = self::slug( $post->ID );
		return array(
			'slug'              => $slug,
			'name'              => get_the_title( $post ),
			'version'           => $release['version'],
			'short_description' => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'url'               => get_permalink( $post ),
			'icon'              => self::icon_url( $post->ID ),
			'package'           => self::download_url( $slug, $release['version'] ),
			'requires'          => $release['requires'],
			'requires_php'      => $release['requires_php'],
			'tested'            => $release['tested'],
			'updated'           => $release['date'],
			'size'              => (int) $release['size'],
		);
	}

	public static function published() {
		$items = array();
		foreach ( get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $post ) {
			$item = self::item( $post );
			if ( $item && $item['slug'] ) {
				$items[ $item['slug'] ] = $item;
			}
		}
		return $items;
	}

	/* ---------------------------------------------------------------------
	 * Edit screen
	 * ------------------------------------------------------------------- */

	public static function meta_boxes() {
		add_meta_box( 'vh-releases', __( 'Releases', 'vyntic-hub' ), array( __CLASS__, 'render_releases_box' ), self::TYPE, 'normal', 'high' );
		add_meta_box( 'vh-details', __( 'Plugin details', 'vyntic-hub' ), array( __CLASS__, 'render_details_box' ), self::TYPE, 'side', 'default' );
	}

	public static function render_details_box( $post ) {
		wp_nonce_field( 'vh_save', 'vh_nonce' );
		$slug      = self::slug( $post->ID );
		$downloads = (int) get_post_meta( $post->ID, '_vh_downloads', true );
		?>
		<p>
			<label for="vh-slug"><strong><?php esc_html_e( 'Plugin folder (slug)', 'vyntic-hub' ); ?></strong></label><br>
			<input type="text" id="vh-slug" name="vh_slug" value="<?php echo esc_attr( $slug ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Filled from the first zip', 'vyntic-hub' ); ?>" <?php echo $slug ? 'readonly' : ''; ?>>
			<span class="description"><?php esc_html_e( 'Must match the plugin folder inside the zip. Cannot change once set (installed sites use it).', 'vyntic-hub' ); ?></span>
		</p>
		<?php if ( $slug ) : ?>
			<p><strong><?php esc_html_e( 'Active sites', 'vyntic-hub' ); ?>:</strong> <?php echo esc_html( number_format_i18n( VH_Sites::active_count( $slug ) ) ); ?><br>
			<strong><?php esc_html_e( 'Downloads', 'vyntic-hub' ); ?>:</strong> <?php echo esc_html( number_format_i18n( $downloads ) ); ?></p>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Tip: the Excerpt is the short description on cards and in search results. The Featured image is the plugin icon.', 'vyntic-hub' ); ?></p>
		<?php
	}

	public static function render_releases_box( $post ) {
		?>
		<div id="vh-releases-app" data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'vh_release_' . $post->ID ) ); ?>">
			<div class="vh-upload">
				<textarea id="vh-changelog" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'What changed in this version? (optional, taken from readme.txt if empty)', 'vyntic-hub' ); ?>"></textarea>
				<p>
					<button type="button" class="button button-primary" id="vh-upload-btn"><?php esc_html_e( 'Upload new version (.zip)', 'vyntic-hub' ); ?></button>
					<span class="vh-hint"><?php esc_html_e( 'Name, version and requirements are read from the zip automatically. The newest version goes live right away.', 'vyntic-hub' ); ?></span>
				</p>
				<div id="vh-release-msg"></div>
			</div>
			<div id="vh-release-table"><?php echo self::releases_table( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?></div>
		</div>
		<?php
	}

	public static function releases_table( $post_id ) {
		$releases = self::releases( $post_id );
		if ( ! $releases ) {
			return '<p class="vh-empty">' . esc_html__( 'No releases yet. Upload the plugin zip to publish version 1.', 'vyntic-hub' ) . '</p>';
		}
		$live = self::live_release( $post_id );
		$html = '<table class="widefat striped vh-table"><thead><tr><th>' . esc_html__( 'Version', 'vyntic-hub' ) . '</th><th>' . esc_html__( 'Released', 'vyntic-hub' ) . '</th><th>' . esc_html__( 'Requires', 'vyntic-hub' ) . '</th><th>' . esc_html__( 'Changelog', 'vyntic-hub' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $releases as $release ) {
			$is_live = $live && $live['version'] === $release['version'];
			$html   .= '<tr' . ( $is_live ? ' class="is-live"' : '' ) . '><td><strong>' . esc_html( $release['version'] ) . '</strong>' . ( $is_live ? ' <span class="vh-live">' . esc_html__( 'Live', 'vyntic-hub' ) . '</span>' : '' ) . '<br><span class="vh-muted">' . esc_html( size_format( (int) $release['size'] ) ) . '</span></td>'
				. '<td>' . esc_html( mysql2date( get_option( 'date_format' ), $release['date'] ) ) . '</td>'
				. '<td class="vh-muted">WP ' . esc_html( $release['requires'] ? $release['requires'] : '-' ) . '<br>PHP ' . esc_html( $release['requires_php'] ? $release['requires_php'] : '-' ) . '</td>'
				. '<td class="vh-changelog">' . nl2br( esc_html( $release['changelog'] ) ) . '</td>'
				. '<td class="vh-actions">';
			if ( ! $is_live ) {
				$html .= '<button type="button" class="button vh-act" data-act="live" data-version="' . esc_attr( $release['version'] ) . '">' . esc_html__( 'Make live', 'vyntic-hub' ) . '</button> ';
			}
			$html .= '<a class="button-link" href="' . esc_url( wp_get_attachment_url( (int) $release['attachment'] ) ) . '">' . esc_html__( 'Download', 'vyntic-hub' ) . '</a> '
				. '<button type="button" class="button-link vh-delete vh-act" data-act="delete" data-version="' . esc_attr( $release['version'] ) . '">' . esc_html__( 'Delete', 'vyntic-hub' ) . '</button></td></tr>';
		}
		return $html . '</tbody></table>';
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['vh_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vh_nonce'] ) ), 'vh_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( '' === self::slug( $post_id ) && ! empty( $_POST['vh_slug'] ) ) {
			$slug = sanitize_title( wp_unslash( $_POST['vh_slug'] ) );
			if ( $slug && ! self::slug_taken( $slug, $post_id ) ) {
				update_post_meta( $post_id, '_vh_slug', $slug );
			}
		}
	}

	private static function slug_taken( $slug, $post_id ) {
		$posts = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'any',
				'meta_key'       => '_vh_slug', // phpcs:ignore
				'meta_value'     => $slug, // phpcs:ignore
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post__not_in'   => array( (int) $post_id ),
			)
		);
		return ! empty( $posts );
	}

	/* ---------------------------------------------------------------------
	 * AJAX: releases
	 * ------------------------------------------------------------------- */

	private static function guard( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) || ! check_ajax_referer( 'vh_release_' . $post_id, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'vyntic-hub' ) ), 403 );
		}
	}

	public static function ajax_add_release() {
		$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0;
		self::guard( $post_id );
		$attachment = isset( $_POST['attachment'] ) ? (int) $_POST['attachment'] : 0;
		$file       = $attachment ? get_attached_file( $attachment ) : '';
		if ( ! $file || ! is_file( $file ) ) {
			wp_send_json_error( array( 'message' => __( 'Upload a .zip file first.', 'vyntic-hub' ) ) );
		}

		$info = VH_Zip::inspect( $file );
		if ( is_wp_error( $info ) ) {
			wp_send_json_error( array( 'message' => $info->get_error_message() ) );
		}

		$slug = self::slug( $post_id );
		if ( '' === $slug ) {
			if ( self::slug_taken( $info['folder'], $post_id ) ) {
				/* translators: %s: slug */
				wp_send_json_error( array( 'message' => sprintf( __( 'Another plugin already uses the folder "%s".', 'vyntic-hub' ), $info['folder'] ) ) );
			}
			$slug = $info['folder'];
			update_post_meta( $post_id, '_vh_slug', $slug );
		} elseif ( $slug !== $info['folder'] ) {
			/* translators: 1: folder in zip, 2: expected slug */
			wp_send_json_error( array( 'message' => sprintf( __( 'The zip contains the folder "%1$s" but this plugin is "%2$s". Installed sites would not update correctly.', 'vyntic-hub' ), $info['folder'], $slug ) ) );
		}

		$releases = self::releases( $post_id );
		if ( isset( $releases[ $info['version'] ] ) ) {
			/* translators: %s: version */
			wp_send_json_error( array( 'message' => sprintf( __( 'Version %s already exists. Raise the "Version:" number in the plugin file first.', 'vyntic-hub' ), $info['version'] ) ) );
		}

		$changelog = isset( $_POST['changelog'] ) ? sanitize_textarea_field( wp_unslash( $_POST['changelog'] ) ) : '';
		$releases[ $info['version'] ] = array(
			'version'      => $info['version'],
			'attachment'   => $attachment,
			'date'         => current_time( 'mysql' ),
			'changelog'    => '' !== $changelog ? $changelog : $info['changelog'],
			'requires'     => $info['requires'],
			'requires_php' => $info['requires_php'],
			'tested'       => $info['tested'],
			'size'         => $info['size'],
			'file'         => $info['file'],
		);
		update_post_meta( $post_id, '_vh_releases', $releases );

		$live = self::live_release( $post_id );
		if ( ! $live || version_compare( $info['version'], $live['version'], '>' ) ) {
			update_post_meta( $post_id, '_vh_live', $info['version'] );
		}

		// Fill empty title / excerpt from the zip the first time.
		$post   = get_post( $post_id );
		$update = array();
		if ( '' === trim( $post->post_title ) || __( 'Auto Draft' ) === $post->post_title ) {
			$update['post_title'] = $info['name'];
		}
		if ( '' === trim( $post->post_excerpt ) && $info['description'] ) {
			$update['post_excerpt'] = wp_strip_all_tags( $info['description'] );
		}
		if ( $update ) {
			$update['ID'] = $post_id;
			wp_update_post( $update );
		}

		VH_API::flush();
		wp_send_json_success(
			array(
				/* translators: 1: name, 2: version */
				'message' => sprintf( __( '%1$s %2$s uploaded. Remember to Publish/Update the page if it is a new plugin.', 'vyntic-hub' ), $info['name'], $info['version'] ),
				'table'   => self::releases_table( $post_id ),
				'title'   => isset( $update['post_title'] ) ? $update['post_title'] : '',
				'slug'    => $slug,
			)
		);
	}

	public static function ajax_release_action() {
		$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0;
		self::guard( $post_id );
		$act      = isset( $_POST['act'] ) ? sanitize_key( wp_unslash( $_POST['act'] ) ) : '';
		$version  = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';
		$releases = self::releases( $post_id );
		if ( ! isset( $releases[ $version ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown version.', 'vyntic-hub' ) ) );
		}
		if ( 'live' === $act ) {
			update_post_meta( $post_id, '_vh_live', $version );
		} elseif ( 'delete' === $act ) {
			unset( $releases[ $version ] );
			update_post_meta( $post_id, '_vh_releases', $releases );
			if ( get_post_meta( $post_id, '_vh_live', true ) === $version ) {
				delete_post_meta( $post_id, '_vh_live' ); // Falls back to the newest remaining.
			}
		}
		VH_API::flush();
		wp_send_json_success( array( 'table' => self::releases_table( $post_id ) ) );
	}

	/* ---------------------------------------------------------------------
	 * List table columns
	 * ------------------------------------------------------------------- */

	public static function columns( $columns ) {
		return array(
			'cb'           => $columns['cb'],
			'vh_icon'      => '',
			'title'        => __( 'Plugin', 'vyntic-hub' ),
			'vh_version'   => __( 'Live version', 'vyntic-hub' ),
			'vh_sites'     => __( 'Active sites', 'vyntic-hub' ),
			'vh_downloads' => __( 'Downloads', 'vyntic-hub' ),
			'date'         => $columns['date'],
		);
	}

	public static function column( $column, $post_id ) {
		switch ( $column ) {
			case 'vh_icon':
				$icon = self::icon_url( $post_id );
				echo $icon ? '<img src="' . esc_url( $icon ) . '" width="36" height="36" style="border-radius:8px" alt="">' : '<span class="vh-icon-ph">' . esc_html( strtoupper( substr( get_the_title( $post_id ), 0, 1 ) ) ) . '</span>';
				break;
			case 'vh_version':
				$live = self::live_release( $post_id );
				echo $live ? '<strong>' . esc_html( $live['version'] ) . '</strong>' : '<span class="vh-muted">' . esc_html__( 'No release', 'vyntic-hub' ) . '</span>';
				break;
			case 'vh_sites':
				$slug = self::slug( $post_id );
				echo esc_html( $slug ? number_format_i18n( VH_Sites::active_count( $slug ) ) : '-' );
				break;
			case 'vh_downloads':
				echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, '_vh_downloads', true ) ) );
				break;
		}
	}
}
