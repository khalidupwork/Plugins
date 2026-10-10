<?php
/**
 * Plugin Name: MyRepairCo Site Kit
 * Description: One-click setup of the MyRepairCo red theme for Elementor Pro: global colors, fonts and theme style, plus a Home page, Header and Footer built with native Elementor containers and widgets.
 * Version:     1.0.0
 * Author:      MyRepairCo
 * Requires PHP: 7.4
 * Text Domain: myrepairco-site-kit
 */

defined( 'ABSPATH' ) || exit;

final class MyRepairCo_Site_Kit {

	const BACKUP_OPTION = 'mrc_site_kit_backup';
	const ASSETS_OPTION = 'mrc_site_kit_assets';
	const MENU_NAME     = 'MyRepairCo Main';

	/** @var array<string,int> */
	private $assets = array();

	public static function init() {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'menu' ) );
		add_action( 'admin_post_mrc_site_kit', array( $self, 'handle' ) );
	}

	public function menu() {
		add_management_page( 'MyRepairCo Site Kit', 'MyRepairCo Site Kit', 'manage_options', 'mrc-site-kit', array( $this, 'page' ) );
	}

	private function ready() {
		return did_action( 'elementor/loaded' ) && defined( 'ELEMENTOR_PRO_VERSION' );
	}

	public function page() {
		$notice = isset( $_GET['mrc_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['mrc_notice'] ) ) : '';
		$backup = get_option( self::BACKUP_OPTION );
		?>
		<div class="wrap">
			<h1>MyRepairCo Site Kit</h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $this->ready() ) : ?>
				<div class="notice notice-error"><p>Elementor and Elementor Pro must both be active.</p></div>
			<?php else : ?>
				<h2>1. Global styles</h2>
				<p>Sets Site Settings &rarr; Global Colors (Brand Red, Dark, Body Text, Red Hover, Light Background, White, Blush), Global Fonts (Montserrat + Open Sans), Theme Style (headings, body, buttons, form fields), container width, custom CSS helper classes, and turns on <em>Disable Default Colors / Fonts</em> so every widget inherits from Theme Style.
				The current settings are backed up first and can be restored below.</p>
				<?php $this->button( 'apply_styles', 'Apply global styles', true ); ?>

				<h2>2. Page, Header &amp; Footer</h2>
				<p>Creates <strong>drafts</strong> so nothing on the live site changes yet: a page "MyRepairCo Home", a Theme Builder header and footer (display condition: Entire Site), the "<?php echo esc_html( self::MENU_NAME ); ?>" menu, and uploads the logo and phone images to the Media Library.
				Review them in Elementor, then publish the header/footer and set the page as your homepage under Settings &rarr; Reading.</p>
				<?php $this->button( 'import_templates', 'Create page, header & footer', true ); ?>

				<h2>Restore</h2>
				<?php if ( $backup ) : ?>
					<p>Backup from <?php echo esc_html( $backup['time'] ); ?>.</p>
					<?php $this->button( 'restore_styles', 'Restore previous global styles', false ); ?>
				<?php else : ?>
					<p>No backup yet.</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function button( $task, $label, $primary ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mrc_site_kit">
			<input type="hidden" name="task" value="<?php echo esc_attr( $task ); ?>">
			<?php wp_nonce_field( 'mrc_site_kit_' . $task ); ?>
			<?php submit_button( $label, $primary ? 'primary' : 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
		check_admin_referer( 'mrc_site_kit_' . $task );
		if ( ! $this->ready() ) {
			wp_die( 'Elementor and Elementor Pro must both be active.' );
		}

		switch ( $task ) {
			case 'apply_styles':
				$notice = $this->apply_styles();
				break;
			case 'import_templates':
				$notice = $this->import_templates();
				break;
			case 'restore_styles':
				$notice = $this->restore_styles();
				break;
			default:
				wp_die( 'Unknown task.' );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'mrc-site-kit', 'mrc_notice' => rawurlencode( $notice ) ), admin_url( 'tools.php' ) ) );
		exit;
	}

	// ------------------------------------------------------------ global styles

	private function apply_styles() {
		$kit     = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		$current = $kit->get_settings();

		if ( ! get_option( self::BACKUP_OPTION ) ) {
			update_option(
				self::BACKUP_OPTION,
				array(
					'time'       => current_time( 'mysql' ),
					'kit'        => $current,
					'colors'     => get_option( 'elementor_disable_color_schemes' ),
					'typography' => get_option( 'elementor_disable_typography_schemes' ),
				),
				false
			);
		}

		$new = $this->read_json( 'data/kit-settings.json' );
		$this->save_kit( $kit, array_merge( $current, $new ) );

		// Let every widget inherit Theme Style instead of Elementor's default colors/fonts.
		update_option( 'elementor_disable_color_schemes', 'yes' );
		update_option( 'elementor_disable_typography_schemes', 'yes' );

		return 'Global styles applied. Open Elementor > Site Settings to review them.';
	}

	private function restore_styles() {
		$backup = get_option( self::BACKUP_OPTION );
		if ( ! $backup ) {
			return 'No backup found.';
		}
		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		$this->save_kit( $kit, $backup['kit'] );
		update_option( 'elementor_disable_color_schemes', $backup['colors'] );
		update_option( 'elementor_disable_typography_schemes', $backup['typography'] );
		delete_option( self::BACKUP_OPTION );

		return 'Previous global styles restored.';
	}

	private function save_kit( $kit, array $settings ) {
		$kit->save( array( 'settings' => $settings ) );
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	// ---------------------------------------------------------------- templates

	private function import_templates() {
		$this->assets = $this->upload_assets();
		$menu_slug    = $this->ensure_menu();

		$page_id = $this->create_document( 'templates/home.json', 'page', $menu_slug );
		update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );

		$conditions = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
		foreach ( array( 'header', 'footer' ) as $type ) {
			$id = $this->create_document( "templates/{$type}.json", $type, $menu_slug );
			$conditions->save_conditions( $id, array( array( 'type' => 'include', 'name' => 'general' ) ) );
		}

		\Elementor\Plugin::$instance->files_manager->clear_cache();

		return 'Drafts created: "MyRepairCo Home" page, header and footer. Find them under Pages and Templates > Theme Builder.';
	}

	/**
	 * @return int Post ID of the new draft document.
	 */
	private function create_document( $file, $type, $menu_slug ) {
		$template = $this->read_json( $file );
		$content  = $this->resolve( $template['content'], $menu_slug );

		$document = \Elementor\Plugin::$instance->documents->create(
			$type,
			array(
				'post_title'  => $template['title'],
				'post_status' => 'draft',
			)
		);
		if ( is_wp_error( $document ) || ! $document ) {
			wp_die( esc_html( 'Could not create ' . $template['title'] ) );
		}

		$document->save(
			array(
				'elements' => $content,
				'settings' => $template['page_settings'],
			)
		);
		update_post_meta( $document->get_main_id(), '_elementor_edit_mode', 'builder' );

		return $document->get_main_id();
	}

	/** Replaces {{asset:..}}, {{asset_id:..}} and {{menu}} placeholders. */
	private function resolve( $value, $menu_slug ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->resolve( $item, $menu_slug );
			}
			return $value;
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}
		if ( '{{menu}}' === $value ) {
			return $menu_slug;
		}
		if ( preg_match( '/^\{\{asset(_id)?:([\w.-]+)\}\}$/', $value, $m ) ) {
			$id = isset( $this->assets[ $m[2] ] ) ? $this->assets[ $m[2] ] : 0;
			if ( $m[1] ) {
				return $id;
			}
			return $id ? wp_get_attachment_url( $id ) : plugins_url( 'assets/images/' . $m[2], __FILE__ );
		}
		return $value;
	}

	/** @return array<string,int> file name => attachment ID */
	private function upload_assets() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$ids = (array) get_option( self::ASSETS_OPTION, array() );
		$files = array_merge( (array) glob( __DIR__ . '/assets/images/*.png' ), (array) glob( __DIR__ . '/assets/images/*.jpg' ) );
		foreach ( $files as $path ) {
			$name = basename( $path );
			if ( ! empty( $ids[ $name ] ) && get_post( $ids[ $name ] ) ) {
				continue;
			}
			$tmp = wp_tempnam( $name );
			copy( $path, $tmp );
			$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0, 'MyRepairCo ' . $name );
			if ( is_wp_error( $id ) ) {
				@unlink( $tmp );
				continue;
			}
			$ids[ $name ] = $id;
		}
		update_option( self::ASSETS_OPTION, $ids, false );

		return $ids;
	}

	/** @return string Menu slug. */
	private function ensure_menu() {
		$menu = wp_get_nav_menu_object( self::MENU_NAME );
		if ( $menu ) {
			return $menu->slug;
		}
		$menu_id = wp_create_nav_menu( self::MENU_NAME );
		$items   = array(
			'Home'            => '/',
			'Services'        => '/services/',
			'Pricing'         => '/pricing/',
			'Tech Team'       => '/tech-team/',
			'Customer Portal' => '/customer-portal/',
			'FAQ'             => '/faq/',
			'Contact Us'      => '/contact-us/',
		);
		foreach ( $items as $title => $path ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'  => $title,
					'menu-item-url'    => home_url( $path ),
					'menu-item-type'   => 'custom',
					'menu-item-status' => 'publish',
				)
			);
		}
		return wp_get_nav_menu_object( $menu_id )->slug;
	}

	private function read_json( $file ) {
		$data = json_decode( (string) file_get_contents( __DIR__ . '/' . $file ), true );
		if ( ! is_array( $data ) ) {
			wp_die( esc_html( 'Invalid JSON in ' . $file ) );
		}
		return $data;
	}
}

MyRepairCo_Site_Kit::init();
