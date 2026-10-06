<?php
/**
 * Admin: settings page, leads list, CSV export.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
class CMA_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_cma_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_cma_resend', array( __CLASS__, 'resend' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CMA_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Menu pages.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Free Audit', 'credit-market-audit' ),
			__( 'Free Audit', 'credit-market-audit' ),
			'manage_options',
			'cma-leads',
			array( __CLASS__, 'leads_page' ),
			'dashicons-chart-area',
			58
		);
		add_submenu_page( 'cma-leads', __( 'Audit Leads', 'credit-market-audit' ), __( 'Leads', 'credit-market-audit' ), 'manage_options', 'cma-leads', array( __CLASS__, 'leads_page' ) );
		add_submenu_page( 'cma-leads', __( 'Free Audit Settings', 'credit-market-audit' ), __( 'Settings', 'credit-market-audit' ), 'manage_options', 'cma-settings', array( __CLASS__, 'settings_page' ) );
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=cma-settings' ) ) . '">' . esc_html__( 'Settings', 'credit-market-audit' ) . '</a>' );
		return $links;
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Page hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'cma-' ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_style( 'cma-admin', CMA_URL . 'assets/css/cma-admin.css', array(), CMA_VERSION );
		wp_enqueue_script( 'cma-admin', CMA_URL . 'assets/js/cma-admin.js', array( 'jquery', 'wp-color-picker' ), CMA_VERSION, true );
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	/**
	 * Settings API registration.
	 */
	public static function register_settings() {
		register_setting(
			'cma_settings_group',
			CMA_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'CMA_Settings', 'sanitize' ),
				'default'           => CMA_Settings::defaults(),
			)
		);

		$sections = array(
			'cma_api'      => __( 'Google PageSpeed Insights', 'credit-market-audit' ),
			'cma_branding' => __( 'Branding & report', 'credit-market-audit' ),
			'cma_email'    => __( 'Emails', 'credit-market-audit' ),
			'cma_form'     => __( 'Form & protection', 'credit-market-audit' ),
		);
		foreach ( $sections as $id => $title ) {
			add_settings_section( $id, $title, array( __CLASS__, 'section_intro' ), 'cma-settings' );
		}

		$fields = array(
			array( 'psi_api_key', __( 'API key', 'credit-market-audit' ), 'password', 'cma_api', __( 'Free key from Google Cloud Console → APIs & Services → enable "PageSpeed Insights API" → Credentials → Create API key. Works without a key too, but with a very low shared quota.', 'credit-market-audit' ) ),
			array( 'brand_name', __( 'Brand name', 'credit-market-audit' ), 'text', 'cma_branding', '' ),
			array( 'brand_logo', __( 'Logo', 'credit-market-audit' ), 'media', 'cma_branding', __( 'Shown at the top of the report and email.', 'credit-market-audit' ) ),
			array( 'brand_color', __( 'Brand colour', 'credit-market-audit' ), 'color', 'cma_branding', '' ),
			array( 'cta_text', __( 'Call-to-action button text', 'credit-market-audit' ), 'text', 'cma_branding', '' ),
			array( 'cta_url', __( 'Call-to-action URL', 'credit-market-audit' ), 'url', 'cma_branding', __( 'E.g. your contact or booking page. Leave empty to hide the CTA box.', 'credit-market-audit' ) ),
			array( 'cta_message', __( 'Call-to-action message', 'credit-market-audit' ), 'textarea', 'cma_branding', '' ),
			array( 'report_footer', __( 'Report footer text', 'credit-market-audit' ), 'textarea', 'cma_branding', __( 'Optional. E.g. your address, phone number or disclaimer.', 'credit-market-audit' ) ),
			array( 'send_user_email', __( 'Email report to visitor', 'credit-market-audit' ), 'checkbox', 'cma_email', '' ),
			array( 'attach_report', __( 'Attach report file to email', 'credit-market-audit' ), 'checkbox', 'cma_email', __( 'Attaches the full HTML report (opens in any browser, can be printed to PDF).', 'credit-market-audit' ) ),
			array( 'from_name', __( 'From name', 'credit-market-audit' ), 'text', 'cma_email', '' ),
			array( 'from_email', __( 'From email', 'credit-market-audit' ), 'email', 'cma_email', __( 'Use an address on your own domain. An SMTP plugin (e.g. WP Mail SMTP) is strongly recommended so emails don\'t land in spam.', 'credit-market-audit' ) ),
			array( 'email_subject', __( 'Email subject', 'credit-market-audit' ), 'text', 'cma_email', __( 'Placeholders: {domain}, {score}', 'credit-market-audit' ) ),
			array( 'email_intro', __( 'Email intro text', 'credit-market-audit' ), 'textarea', 'cma_email', '' ),
			array( 'admin_notify', __( 'Notify me about new leads', 'credit-market-audit' ), 'checkbox', 'cma_email', '' ),
			array( 'admin_email', __( 'Notification email', 'credit-market-audit' ), 'email', 'cma_email', '' ),
			array( 'require_consent', __( 'Show consent checkbox by default', 'credit-market-audit' ), 'checkbox', 'cma_form', __( 'Can also be toggled per form in the Elementor widget / shortcode.', 'credit-market-audit' ) ),
			array( 'consent_text', __( 'Consent text', 'credit-market-audit' ), 'textarea', 'cma_form', '' ),
			array( 'rate_limit', __( 'Max audits per IP per hour', 'credit-market-audit' ), 'number', 'cma_form', __( '0 = unlimited. Protects your PageSpeed quota from abuse.', 'credit-market-audit' ) ),
			array( 'delete_on_uninstall', __( 'Delete all data on uninstall', 'credit-market-audit' ), 'checkbox', 'cma_form', __( 'Removes the leads table and settings when the plugin is deleted.', 'credit-market-audit' ) ),
		);

		foreach ( $fields as $f ) {
			add_settings_field(
				$f[0],
				$f[1],
				array( __CLASS__, 'render_field' ),
				'cma-settings',
				$f[3],
				array(
					'key'       => $f[0],
					'type'      => $f[2],
					'help'      => $f[4],
					'label_for' => 'cma_' . $f[0],
				)
			);
		}
	}

	/**
	 * Section descriptions.
	 *
	 * @param array $section Section.
	 */
	public static function section_intro( $section ) {
		if ( 'cma_api' === $section['id'] ) {
			echo '<p>' . esc_html__( 'Performance, accessibility and best-practice scores plus screenshots come from Google PageSpeed Insights.', 'credit-market-audit' ) . '</p>';
		}
	}

	/**
	 * Render one settings field.
	 *
	 * @param array $args Field args.
	 */
	public static function render_field( $args ) {
		$key   = $args['key'];
		$value = CMA_Settings::get( $key );
		$name  = CMA_Settings::OPTION . '[' . $key . ']';
		$id    = 'cma_' . $key;

		switch ( $args['type'] ) {
			case 'checkbox':
				printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( 1, (int) $value, false ), esc_html__( 'Enabled', 'credit-market-audit' ) );
				break;
			case 'textarea':
				printf( '<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
				break;
			case 'color':
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="cma-color" data-default-color="#2563eb">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
			case 'media':
				printf(
					'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="regular-text cma-media-input"> <button type="button" class="button cma-media-button" data-target="%1$s">%4$s</button><div class="cma-media-preview">%5$s</div>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_html__( 'Select image', 'credit-market-audit' ),
					$value ? '<img src="' . esc_url( $value ) . '" alt="">' : ''
				);
				break;
			case 'number':
				printf( '<input type="number" min="0" step="1" id="%1$s" name="%2$s" value="%3$s" class="small-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
			default:
				$type = in_array( $args['type'], array( 'password', 'email', 'url' ), true ) ? $args['type'] : 'text';
				printf( '<input type="%4$s" id="%1$s" name="%2$s" value="%3$s" class="regular-text" autocomplete="off">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( $type ) );
		}

		if ( ! empty( $args['help'] ) ) {
			echo '<p class="description">' . esc_html( $args['help'] ) . '</p>';
		}
	}

	/**
	 * Settings screen.
	 */
	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap cma-admin">
			<h1><?php esc_html_e( 'Free Audit Settings', 'credit-market-audit' ); ?></h1>
			<?php settings_errors(); ?>

			<div class="cma-admin__usage">
				<h2><?php esc_html_e( 'How to add the audit form', 'credit-market-audit' ); ?></h2>
				<ol>
					<li><?php echo wp_kses_post( __( '<strong>Elementor:</strong> search for the <em>Free Website Audit</em> widget (category <em>Credit Market</em>) and drag it onto your page.', 'credit-market-audit' ) ); ?></li>
					<li><?php esc_html_e( 'Any editor: paste this shortcode', 'credit-market-audit' ); ?> <code>[credit_market_audit]</code></li>
					<li>
						<?php esc_html_e( 'Shortcode options:', 'credit-market-audit' ); ?>
						<code>[credit_market_audit heading="Free SEO Audit" button_text="Check my site" show_name="no" layout="inline"]</code>
					</li>
				</ol>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'cma_settings_group' );
				do_settings_sections( 'cma-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Leads
	 * ------------------------------------------------------------------- */

	/**
	 * Leads screen.
	 */
	public static function leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once CMA_PATH . 'includes/class-cma-leads-table.php';
		$table = new CMA_Leads_Table();
		$table->process_bulk_action();
		$table->prepare_items();

		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=cma_export' ), 'cma_export' );
		?>
		<div class="wrap cma-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Audit Leads', 'credit-market-audit' ); ?></h1>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'credit-market-audit' ); ?></a>
			<hr class="wp-header-end">

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['cma_msg'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$msg = sanitize_key( $_GET['cma_msg'] );
				$map = array(
					'deleted'     => __( 'Leads deleted.', 'credit-market-audit' ),
					'resent'      => __( 'Report email sent again.', 'credit-market-audit' ),
					'resend_fail' => __( 'The email could not be sent. Check your mail / SMTP configuration.', 'credit-market-audit' ),
				);
				if ( isset( $map[ $msg ] ) ) {
					printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 'resend_fail' === $msg ? 'error' : 'success', esc_html( $map[ $msg ] ) );
				}
			}
			?>

			<form method="get">
				<input type="hidden" name="page" value="cma-leads">
				<?php
				$table->search_box( __( 'Search leads', 'credit-market-audit' ), 'cma-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * CSV export.
	 */
	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'credit-market-audit' ) );
		}
		check_admin_referer( 'cma_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="audit-leads-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for Excel.
		fputcsv( $out, array( 'Name', 'Email', 'Website', 'Status', 'Score', 'Email sent', 'IP', 'Date (UTC)' ) );
		foreach ( CMA_Repository::export_rows() as $row ) {
			// Prevent CSV formula injection in spreadsheet apps.
			$row = array_map(
				static function ( $v ) {
					$v = (string) $v;
					return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
				},
				$row
			);
			fputcsv( $out, array_values( $row ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Resend the report email to a lead.
	 */
	public static function resend() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'credit-market-audit' ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'cma_resend_' . $id );

		$audit = CMA_Repository::get( $id );
		$ok    = $audit && 'complete' === $audit['status'] && CMA_Mailer::send_report( $audit );
		if ( $ok ) {
			CMA_Repository::update( $id, array( 'email_sent' => 1 ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=cma-leads&cma_msg=' . ( $ok ? 'resent' : 'resend_fail' ) ) );
		exit;
	}
}
