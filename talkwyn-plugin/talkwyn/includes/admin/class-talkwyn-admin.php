<?php
/**
 * Admin screens.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class Talkwyn_Admin {

	const SLUG = 'talkwyn';

	/**
	 * Checkbox keys printed on the current form.
	 *
	 * @var string[]
	 */
	private static $bools = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_talkwyn_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_talkwyn_export_leads', array( __CLASS__, 'export_leads' ) );
		add_action( 'admin_post_talkwyn_delete_lead', array( __CLASS__, 'delete_lead' ) );
		add_action( 'admin_post_talkwyn_clear_logs', array( __CLASS__, 'clear_logs' ) );
		add_action( 'wp_ajax_talkwyn_test_provider', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_talkwyn_models', array( __CLASS__, 'ajax_models' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TALKWYN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Whether Talkwyn Pro is active.
	 *
	 * @return bool
	 */
	public static function pro_active() {
		return (bool) apply_filters( 'talkwyn_pro_active', false );
	}

	/**
	 * Plugin list links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Settings', 'talkwyn' ) . '</a>' );
		return $links;
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$title = (string) apply_filters( 'talkwyn_admin_menu_title', 'Talkwyn' );
		$icon  = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path fill="black" d="M8 3h16a6 6 0 0 1 6 6v11a6 6 0 0 1-6 6H14l-7 5v-5.2A6 6 0 0 1 2 20V9a6 6 0 0 1 6-6Zm2.2 13.6a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm5.8-3.3a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm5.8-3.8a2.4 2.4 0 1 0 0 4.8 2.4 2.4 0 0 0 0-4.8Z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		add_menu_page( $title, $title, 'manage_options', self::SLUG, array( __CLASS__, 'page' ), $icon, 58 );
		foreach ( self::tabs() as $key => $label ) {
			add_submenu_page( self::SLUG, $label, $label, 'manage_options', 'dashboard' === $key ? self::SLUG : self::SLUG . '&tab=' . $key, '__return_null' );
		}
	}

	/**
	 * Tabs.
	 *
	 * @return array<string, string>
	 */
	public static function tabs() {
		$tabs = array(
			'dashboard'     => __( 'Dashboard', 'talkwyn' ),
			'knowledge'     => __( 'Knowledge', 'talkwyn' ),
			'providers'     => __( 'AI providers', 'talkwyn' ),
			'appearance'    => __( 'Appearance', 'talkwyn' ),
			'behavior'      => __( 'Answers and leads', 'talkwyn' ),
			'leads'         => __( 'Leads', 'talkwyn' ),
			'conversations' => __( 'Conversations', 'talkwyn' ),
			'privacy'       => __( 'Privacy', 'talkwyn' ),
		);

		/**
		 * Filters admin tabs. Render a new tab with the `talkwyn_admin_tab_{$key}` action.
		 *
		 * @param array $tabs Tabs keyed by slug.
		 */
		return (array) apply_filters( 'talkwyn_admin_tabs', $tabs );
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'talkwyn-admin', TALKWYN_URL . 'assets/css/admin.css', array(), TALKWYN_VERSION );
		wp_enqueue_script( 'talkwyn-admin', TALKWYN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-i18n' ), TALKWYN_VERSION, true );
		wp_set_script_translations( 'talkwyn-admin', 'talkwyn' );
		wp_localize_script(
			'talkwyn-admin',
			'TalkwynAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'talkwyn_admin' ),
			)
		);
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'dashboard';
	}

	/**
	 * Page.
	 *
	 * @return void
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = self::current_tab();
		echo '<div class="wrap twa">';
		echo '<header class="twa-head"><div class="twa-brand"><img src="' . esc_url( TALKWYN_URL . 'assets/img/talkwyn-mark.svg' ) . '" alt="" width="36" height="36"><div><h1>' . esc_html( (string) apply_filters( 'talkwyn_admin_menu_title', 'Talkwyn' ) ) . '</h1><p>' . esc_html__( 'Your website assistant: answers visitors, in their language, and captures leads.', 'talkwyn' ) . '</p></div></div>';
		echo '<div class="twa-head__actions"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=talkwyn-setup' ) ) . '">' . esc_html__( 'Setup wizard', 'talkwyn' ) . '</a>';
		if ( ! self::pro_active() ) {
			echo ' <a class="button twa-upgrade" href="https://talkwyn.com/pricing/" target="_blank" rel="noopener">' . esc_html__( 'Upgrade to Pro', 'talkwyn' ) . '</a>';
		}
		echo '</div></header>';

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'talkwyn' ) . '</p></div>';
		}

		echo '<nav class="nav-tab-wrapper twa-tabs">';
		foreach ( self::tabs() as $key => $label ) {
			$url = admin_url( 'admin.php?page=' . self::SLUG . ( 'dashboard' === $key ? '' : '&tab=' . $key ) );
			echo '<a class="nav-tab' . ( $tab === $key ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav><div class="twa-body">';
		if ( method_exists( __CLASS__, 'tab_' . $tab ) ) {
			call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		} else {
			do_action( 'talkwyn_admin_tab_' . $tab );
		}
		echo '</div></div>';
	}

	/* ---------- Form helpers ---------- */

	/**
	 * Open a settings form.
	 *
	 * @param string $tab Tab to return to.
	 * @return void
	 */
	public static function form_open( $tab ) {
		self::$bools = array();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="twa-form">';
		echo '<input type="hidden" name="action" value="talkwyn_save"><input type="hidden" name="return_tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'talkwyn_save' );
	}

	/**
	 * Close a settings form.
	 *
	 * @param string $button Button text.
	 * @return void
	 */
	public static function form_close( $button = '' ) {
		foreach ( array_unique( self::$bools ) as $key ) {
			echo '<input type="hidden" name="talkwyn_bools[]" value="' . esc_attr( $key ) . '">';
		}
		echo '<p class="twa-save"><button class="button button-primary button-large">' . esc_html( '' !== $button ? $button : __( 'Save changes', 'talkwyn' ) ) . '</button></p></form>';
	}

	/**
	 * Text-like field.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $type  Input type or textarea.
	 * @param string $help  Help text.
	 * @param array  $attrs Extra attributes.
	 * @return void
	 */
	public static function field( $key, $label, $type = 'text', $help = '', array $attrs = array() ) {
		$value = Talkwyn_Settings::get( $key, '' );
		$id    = 'twa-' . $key;
		$extra = '';
		foreach ( $attrs as $k => $v ) {
			$extra .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		echo '<div class="twa-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( 'textarea' === $type ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="talkwyn[' . esc_attr( $key ) . ']" rows="4"' . $extra . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="talkwyn[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '"' . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== $help ) {
			echo '<p class="description">' . wp_kses( $help, array( 'code' => array(), 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'strong' => array() ) ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Checkbox.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 * @return void
	 */
	public static function toggle( $key, $label, $help = '' ) {
		self::$bools[] = $key;
		echo '<div class="twa-toggle"><label><input type="checkbox" name="talkwyn[' . esc_attr( $key ) . ']" value="1"' . checked( (bool) Talkwyn_Settings::get( $key ), true, false ) . '> <span>' . esc_html( $label ) . '</span></label>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Select.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Label.
	 * @param array  $options Options.
	 * @return void
	 */
	public static function select( $key, $label, array $options ) {
		$value = (string) Talkwyn_Settings::get( $key );
		echo '<div class="twa-field"><label for="twa-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><select id="twa-' . esc_attr( $key ) . '" name="talkwyn[' . esc_attr( $key ) . ']">';
		foreach ( $options as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $value, (string) $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select></div>';
	}

	/**
	 * Card open.
	 *
	 * @param string $title Title.
	 * @param string $intro Intro text.
	 * @param string $class Extra class.
	 * @return void
	 */
	public static function card( $title, $intro = '', $class = '' ) {
		echo '<section class="twa-card ' . esc_attr( $class ) . '"><h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $intro ) {
			echo '<p class="twa-intro">' . esc_html( $intro ) . '</p>';
		}
	}

	/* ---------- Tabs ---------- */

	/**
	 * Dashboard.
	 *
	 * @return void
	 */
	private static function tab_dashboard() {
		global $wpdb;
		$t        = Talkwyn_DB::tables();
		$s        = Talkwyn_Settings::all();
		$since    = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$chunks   = Talkwyn_Indexer::count();
		$leads    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['leads']} WHERE created_gmt >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$chats    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT session_id) FROM {$t['logs']} WHERE created_gmt >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$ready    = Talkwyn_Providers::order( $s );
		$stats    = array(
			array( __( 'Chats, last 30 days', 'talkwyn' ), number_format_i18n( $chats ) ),
			array( __( 'Leads, last 30 days', 'talkwyn' ), number_format_i18n( $leads ) ),
			array( __( 'Knowledge chunks', 'talkwyn' ), number_format_i18n( $chunks ) ),
			array( __( 'Status', 'talkwyn' ), ! empty( $s['enabled'] ) ? __( 'Live', 'talkwyn' ) : __( 'Off', 'talkwyn' ) ),
		);
		echo '<div class="twa-stats">';
		foreach ( $stats as $stat ) {
			echo '<div class="twa-stat"><span>' . esc_html( $stat[0] ) . '</span><strong>' . esc_html( $stat[1] ) . '</strong></div>';
		}
		echo '</div>';

		$steps = array(
			array( $chunks > 0, __( 'Scan your site', 'talkwyn' ), admin_url( 'admin.php?page=talkwyn&tab=knowledge' ) ),
			array( (bool) $ready, __( 'Add a free AI key', 'talkwyn' ), admin_url( 'admin.php?page=talkwyn&tab=providers' ) ),
			array( count( $ready ) > 1, __( 'Add a second provider for automatic fallback', 'talkwyn' ), admin_url( 'admin.php?page=talkwyn&tab=providers' ) ),
			array( ! empty( $s['enabled'] ), __( 'Turn the chat on', 'talkwyn' ), admin_url( 'admin.php?page=talkwyn&tab=appearance' ) ),
		);
		self::card( __( 'Getting started', 'talkwyn' ), __( 'Four steps and visitors can chat with your website.', 'talkwyn' ) );
		echo '<ol class="twa-steps">';
		foreach ( $steps as $step ) {
			echo '<li class="' . ( $step[0] ? 'is-done' : '' ) . '"><span class="twa-steps__mark" aria-hidden="true"></span><a href="' . esc_url( $step[2] ) . '">' . esc_html( $step[1] ) . '</a>' . ( $step[0] ? ' <span class="screen-reader-text">' . esc_html__( '(done)', 'talkwyn' ) . '</span>' : '' ) . '</li>';
		}
		echo '</ol><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=talkwyn-setup' ) ) . '">' . esc_html__( 'Open the setup wizard', 'talkwyn' ) . '</a> <a class="button" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Test it on your site', 'talkwyn' ) . '</a></p></section>';

		do_action( 'talkwyn_admin_dashboard' );

		if ( ! self::pro_active() ) {
			self::card( __( 'Talkwyn Pro', 'talkwyn' ), __( 'Paid AI models, smart search, PDF and URL knowledge, analytics, an unanswered questions inbox, WooCommerce product cards and order lookup, Slack and Telegram lead alerts, and white label. Try it free for 15 days.', 'talkwyn' ), 'twa-card--pro' );
			echo '<p><a class="button button-primary" href="https://talkwyn.com/pricing/" target="_blank" rel="noopener">' . esc_html__( 'See Pro plans', 'talkwyn' ) . '</a></p></section>';
		}
	}

	/**
	 * Knowledge.
	 *
	 * @return void
	 */
	private static function tab_knowledge() {
		self::card( __( 'Scan your website', 'talkwyn' ), __( 'Talkwyn reads your published pages, posts and products and stores the text in your WordPress database. Password protected and unpublished content is never included.', 'talkwyn' ) );
		echo '<div class="twa-scan"><button type="button" class="button button-primary button-large" id="twa-scan">' . esc_html__( 'Scan entire site', 'talkwyn' ) . '</button> <button type="button" class="button" id="twa-clear">' . esc_html__( 'Clear knowledge', 'talkwyn' ) . '</button> <span id="twa-scan-status" aria-live="polite">';
		/* translators: %s: number of chunks */
		printf( esc_html__( '%s chunks indexed', 'talkwyn' ), esc_html( number_format_i18n( Talkwyn_Indexer::count() ) ) );
		echo '</span></div><div class="twa-progress" aria-hidden="true"><span></span></div></section>';

		self::form_open( 'knowledge' );
		self::card( __( 'What to scan', 'talkwyn' ) );
		echo '<div class="twa-checks">';
		$selected = (array) Talkwyn_Settings::get( 'index_post_types', array() );
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			echo '<label><input type="checkbox" name="talkwyn[index_post_types][]" value="' . esc_attr( $type->name ) . '"' . checked( in_array( $type->name, $selected, true ), true, false ) . '> ' . esc_html( $type->labels->name ) . '</label>';
		}
		echo '</div><input type="hidden" name="talkwyn[index_post_types][]" value="">';
		self::toggle( 'auto_index_on_save', __( 'Update the knowledge when content is saved, unpublished or deleted', 'talkwyn' ) );
		echo '</section>';

		self::card( __( 'Custom fields', 'talkwyn' ), __( 'Off by default. Turn it on and list only the fields that are safe to show visitors.', 'talkwyn' ) );
		self::toggle( 'index_custom_fields', __( 'Include allowed custom fields', 'talkwyn' ) );
		self::field( 'custom_field_allowlist', __( 'Allowed field keys', 'talkwyn' ), 'textarea', __( 'One key per line or comma separated. Use <code>spec_*</code> to allow every key that starts with spec_. Keys that start with an underscore are always skipped.', 'talkwyn' ) );
		echo '</section>';
		self::form_close( __( 'Save knowledge settings', 'talkwyn' ) );
		do_action( 'talkwyn_admin_knowledge' );
	}

	/**
	 * Providers.
	 *
	 * @return void
	 */
	private static function tab_providers() {
		$registry = Talkwyn_Providers::registry();
		self::form_open( 'providers' );
		self::card( __( 'How answers are generated', 'talkwyn' ), __( 'Talkwyn tries your providers in order and moves to the next one if a provider fails or hits its limit. Add two or more free keys for the best uptime.', 'talkwyn' ) );
		self::field( 'provider_order', __( 'Fallback order', 'talkwyn' ), 'text', sprintf( /* translators: %s: provider IDs */ __( 'Provider IDs separated by commas. Available: %s', 'talkwyn' ), '<code>' . esc_html( implode( ',', array_keys( $registry ) ) ) . '</code>' ) );
		echo '</section><div class="twa-grid">';
		$labels = array(
			'key'        => __( 'API key', 'talkwyn' ),
			'model'      => __( 'Model', 'talkwyn' ),
			'account_id' => __( 'Account ID', 'talkwyn' ),
			'token'      => __( 'API token', 'talkwyn' ),
		);
		foreach ( $registry as $id => $p ) {
			$badge = ! empty( $p['free'] ) ? __( 'Free tier', 'talkwyn' ) : __( 'Paid', 'talkwyn' );
			echo '<section class="twa-card twa-provider" data-provider="' . esc_attr( $id ) . '"><div class="twa-provider__head"><h2>' . esc_html( $p['label'] ) . ' <span class="twa-badge' . ( ! empty( $p['free'] ) ? ' twa-badge--free' : '' ) . '">' . esc_html( $badge ) . '</span></h2>';
			if ( ! empty( $p['signup'] ) ) {
				echo '<a class="button" href="' . esc_url( $p['signup'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get a key', 'talkwyn' ) . '</a>';
			}
			echo '</div>';
			foreach ( (array) $p['fields'] as $field ) {
				$suffix = preg_replace( '/^' . preg_quote( $id, '/' ) . '_/', '', $field );
				$label  = $labels[ $suffix ] ?? ucwords( str_replace( '_', ' ', $suffix ) );
				if ( ( $p['model_field'] ?? '' ) === $field ) {
					self::field( $field, $label, 'text', '', array( 'list' => 'twa-models-' . $id, 'class' => 'twa-model', 'autocomplete' => 'off' ) );
					echo '<datalist id="twa-models-' . esc_attr( $id ) . '"></datalist><p class="twa-models-row"><button type="button" class="button-link twa-load-models">' . esc_html__( 'Load models from the provider', 'talkwyn' ) . '</button> <span class="twa-models-status" aria-live="polite"></span></p>';
				} else {
					$secret = ( $p['key_field'] ?? '' ) === $field;
					self::field( $field, $label, $secret ? 'password' : 'text', '', array( 'autocomplete' => 'off', 'spellcheck' => 'false' ) );
				}
			}
			echo '<div class="twa-provider__test"><button type="button" class="button twa-test">' . esc_html__( 'Test connection', 'talkwyn' ) . '</button> <span class="twa-test-status" aria-live="polite"></span></div></section>';
		}
		echo '</div>';
		do_action( 'talkwyn_admin_providers' );
		self::form_close( __( 'Save AI providers', 'talkwyn' ) );
	}

	/**
	 * Appearance.
	 *
	 * @return void
	 */
	private static function tab_appearance() {
		$s     = Talkwyn_Settings::all();
		$brand = (string) $s['brand_color'];
		self::form_open( 'appearance' );
		echo '<div class="twa-grid">';
		self::card( __( 'Identity', 'talkwyn' ) );
		self::toggle( 'enabled', __( 'Show the chat on my site', 'talkwyn' ) );
		self::field( 'bot_name', __( 'Assistant name', 'talkwyn' ) );
		self::field( 'online_label', __( 'Status text', 'talkwyn' ) );
		self::field( 'welcome_message', __( 'Welcome message', 'talkwyn' ), 'textarea' );
		self::field( 'placeholder', __( 'Message box placeholder', 'talkwyn' ) );
		self::field( 'suggested_questions', __( 'Suggested questions', 'talkwyn' ), 'textarea', __( 'One per line, up to four.', 'talkwyn' ) );
		echo '</section>';

		self::card( __( 'Colour and style', 'talkwyn' ) );
		echo '<div class="twa-field"><label for="twa-brand_color">' . esc_html__( 'Brand colour', 'talkwyn' ) . '</label><input type="color" id="twa-brand_color" name="talkwyn[brand_color]" value="' . esc_attr( $brand ) . '" class="twa-color"></div>';
		$on    = Talkwyn_Contrast::text_on( $brand );
		$ratio = Talkwyn_Contrast::ratio( $brand, $on );
		echo '<div class="twa-contrast" data-ink="' . esc_attr( Talkwyn_Contrast::INK ) . '"><span class="twa-contrast__sample" style="background:' . esc_attr( $brand ) . ';color:' . esc_attr( $on ) . '">' . esc_html__( 'Visitor message', 'talkwyn' ) . '</span><p class="twa-contrast__note">';
		echo esc_html( sprintf( /* translators: 1: text colour name, 2: contrast ratio */ __( 'Smart Contrast uses %1$s text on this colour (contrast %2$s:1).', 'talkwyn' ), '#FFFFFF' === $on ? __( 'white', 'talkwyn' ) : __( 'dark', 'talkwyn' ), number_format_i18n( $ratio, 1 ) ) );
		echo '</p><p class="twa-contrast__warn"' . ( $ratio >= 4.5 ? ' hidden' : '' ) . '>' . esc_html__( 'Text on this colour is hard to read. Pick a darker or lighter shade.', 'talkwyn' ) . '</p></div>';
		self::select( 'position', __( 'Position', 'talkwyn' ), array( 'right' => __( 'Bottom right', 'talkwyn' ), 'left' => __( 'Bottom left', 'talkwyn' ) ) );
		self::select( 'launcher_icon', __( 'Launcher icon', 'talkwyn' ), array( 'talkwyn' => __( 'Talkwyn bubble', 'talkwyn' ), 'chat_dots' => __( 'Chat dots', 'talkwyn' ), 'spark_chat' => __( 'Spark', 'talkwyn' ), 'headset' => __( 'Support headset', 'talkwyn' ), 'question' => __( 'Help', 'talkwyn' ) ) );
		self::field( 'launcher_label', __( 'Launcher text', 'talkwyn' ), 'text', __( 'Leave empty to show only the icon.', 'talkwyn' ) );
		self::select( 'avatar_type', __( 'Avatar', 'talkwyn' ), array( 'talkwyn' => __( 'Talkwyn bubble', 'talkwyn' ), 'initials' => __( 'First letter of the name', 'talkwyn' ), 'headset' => __( 'Support headset', 'talkwyn' ), 'chat' => __( 'Chat dots', 'talkwyn' ), 'custom' => __( 'Your image', 'talkwyn' ) ) );
		echo '<div class="twa-field"><label for="twa-avatar_url">' . esc_html__( 'Avatar image', 'talkwyn' ) . '</label><div class="twa-inline"><input type="url" id="twa-avatar_url" name="talkwyn[avatar_url]" value="' . esc_attr( (string) $s['avatar_url'] ) . '"><button type="button" class="button" id="twa-avatar-media">' . esc_html__( 'Choose image', 'talkwyn' ) . '</button></div></div>';
		echo '</section>';

		self::card( __( 'Chat window', 'talkwyn' ) );
		self::field( 'chat_width', __( 'Width on desktop (px)', 'talkwyn' ), 'number', '', array( 'min' => 320, 'max' => 720 ) );
		self::field( 'chat_height', __( 'Height on desktop (px)', 'talkwyn' ), 'number', '', array( 'min' => 480, 'max' => 900 ) );
		self::toggle( 'full_screen_enabled', __( 'Full screen button', 'talkwyn' ) );
		self::toggle( 'mobile_full_screen', __( 'Open full screen on phones', 'talkwyn' ) );
		self::toggle( 'show_minimize_button', __( 'Minimize button', 'talkwyn' ) );
		self::toggle( 'show_reset_button', __( 'New chat button', 'talkwyn' ) );
		self::toggle( 'show_sound_button', __( 'Sound button', 'talkwyn' ) );
		self::toggle( 'sound_default', __( 'Sound on by default', 'talkwyn' ) );
		self::toggle( 'show_timestamps', __( 'Message times', 'talkwyn' ) );
		self::toggle( 'show_message_tools', __( 'Copy button under answers', 'talkwyn' ) );
		self::toggle( 'feedback_enabled', __( 'Helpful and not helpful buttons', 'talkwyn' ) );
		self::toggle( 'show_sources', __( 'Source links under answers', 'talkwyn' ) );
		self::toggle( 'show_powered_by', __( 'Show a small "Powered by Talkwyn" link in the chat', 'talkwyn' ), __( 'Off by default. Thank you if you turn it on.', 'talkwyn' ) );
		echo '</section>';

		self::card( __( 'Where the chat shows', 'talkwyn' ), __( 'The chat shows on every page except the ones below. Use the [talkwyn_chat] shortcode or the Talkwyn Chat block to place it inside a page.', 'talkwyn' ) );
		$hidden = array_map( 'absint', (array) $s['hidden_page_ids'] );
		echo '<div class="twa-field"><label for="twa-hidden">' . esc_html__( 'Hide on these pages', 'talkwyn' ) . '</label><select id="twa-hidden" name="talkwyn[hidden_page_ids][]" multiple size="8">';
		foreach ( get_pages( array( 'sort_column' => 'post_title' ) ) as $page ) {
			echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( in_array( (int) $page->ID, $hidden, true ), true, false ) . '>' . esc_html( '' !== $page->post_title ? $page->post_title : __( '(no title)', 'talkwyn' ) ) . '</option>';
		}
		echo '</select><input type="hidden" name="talkwyn[hidden_page_ids][]" value=""><p class="description">' . esc_html__( 'Hold Ctrl (Windows) or Command (Mac) to pick several.', 'talkwyn' ) . '</p></div>';
		self::field( 'hidden_url_paths', __( 'Also hide on these paths', 'talkwyn' ), 'textarea', __( 'One per line, for example /checkout/', 'talkwyn' ) );
		echo '</section>';

		self::card( __( 'Interface text', 'talkwyn' ), __( 'WPML String Translation and Polylang can translate all of these per language.', 'talkwyn' ), 'twa-card--wide' );
		echo '<div class="twa-cols">';
		$texts = array(
			'typing_label'        => __( 'Typing text ({bot} is the assistant name)', 'talkwyn' ),
			'expand_label'        => __( 'Full screen', 'talkwyn' ),
			'minimize_label'      => __( 'Minimize', 'talkwyn' ),
			'reset_label'         => __( 'New chat', 'talkwyn' ),
			'reset_confirm'       => __( 'New chat question', 'talkwyn' ),
			'sound_label'         => __( 'Sound', 'talkwyn' ),
			'close_label'         => __( 'Close', 'talkwyn' ),
			'copy_label'          => __( 'Copy', 'talkwyn' ),
			'copied_label'        => __( 'Copied', 'talkwyn' ),
			'helpful_label'       => __( 'Helpful', 'talkwyn' ),
			'not_helpful_label'   => __( 'Not helpful', 'talkwyn' ),
			'sources_label'       => __( 'Sources', 'talkwyn' ),
			'name_label'          => __( 'Name field', 'talkwyn' ),
			'email_label'         => __( 'Email field', 'talkwyn' ),
			'phone_label'         => __( 'Phone field', 'talkwyn' ),
			'sending_label'       => __( 'Sending', 'talkwyn' ),
			'success_title'       => __( 'Lead saved chip', 'talkwyn' ),
			'reference_label'     => __( 'Reference label', 'talkwyn' ),
			'continue_chat_label' => __( 'After the lead form', 'talkwyn' ),
			'generic_error'       => __( 'General error', 'talkwyn' ),
			'connection_error'    => __( 'Connection error', 'talkwyn' ),
			'lead_error'          => __( 'Lead form error', 'talkwyn' ),
		);
		foreach ( $texts as $key => $label ) {
			self::field( $key, $label );
		}
		echo '</div></section></div>';
		do_action( 'talkwyn_admin_appearance' );
		self::form_close( __( 'Save appearance', 'talkwyn' ) );
	}

	/**
	 * Answers and leads.
	 *
	 * @return void
	 */
	private static function tab_behavior() {
		self::form_open( 'behavior' );
		echo '<div class="twa-grid">';
		self::card( __( 'Answers', 'talkwyn' ) );
		self::toggle( 'multilingual_enabled', __( 'Reply in the visitor\'s language', 'talkwyn' ), __( 'Detects the language of each message, follows switches, and supports right-to-left scripts.', 'talkwyn' ) );
		self::field( 'system_prompt', __( 'Instructions for the assistant', 'talkwyn' ), 'textarea', __( 'Tone, what to focus on, what to avoid. Website facts come from the scan.', 'talkwyn' ), array( 'rows' => 6 ) );
		self::field( 'fallback_message', __( 'Reply when no provider answers', 'talkwyn' ), 'textarea' );
		self::field( 'max_history', __( 'Messages remembered per conversation', 'talkwyn' ), 'number', '', array( 'min' => 0, 'max' => 20 ) );
		self::field( 'max_context_chunks', __( 'Knowledge chunks per answer', 'talkwyn' ), 'number', '', array( 'min' => 2, 'max' => 12 ) );
		echo '</section>';

		self::card( __( 'Lead capture', 'talkwyn' ), __( 'The assistant answers first, then offers a follow-up after a few real questions, or sooner when the visitor asks for prices, a call or a quote.', 'talkwyn' ) );
		self::toggle( 'lead_enabled', __( 'Offer to collect contact details', 'talkwyn' ) );
		self::toggle( 'lead_ask_first', __( 'Ask before showing the form', 'talkwyn' ) );
		self::toggle( 'smart_lead_intent', __( 'Offer sooner when the visitor shows buying or contact intent', 'talkwyn' ) );
		self::field( 'lead_after_messages', __( 'Offer after this many questions', 'talkwyn' ), 'number', '', array( 'min' => 1, 'max' => 20 ) );
		self::field( 'lead_prompt', __( 'Offer question', 'talkwyn' ) );
		self::field( 'lead_yes_label', __( 'Yes button', 'talkwyn' ) );
		self::field( 'lead_no_label', __( 'No button', 'talkwyn' ) );
		self::field( 'lead_decline_reply', __( 'Reply after "No"', 'talkwyn' ) );
		self::field( 'lead_form_intro', __( 'Reply before the form', 'talkwyn' ) );
		self::field( 'lead_title', __( 'Form title', 'talkwyn' ) );
		self::field( 'lead_button', __( 'Form button', 'talkwyn' ) );
		self::field( 'lead_success', __( 'Thank you message', 'talkwyn' ), 'textarea' );
		self::toggle( 'lead_require_name', __( 'Name is required', 'talkwyn' ) );
		self::toggle( 'lead_require_email', __( 'Email is required', 'talkwyn' ) );
		self::toggle( 'lead_require_phone', __( 'Phone is required', 'talkwyn' ) );
		self::field( 'notification_email', __( 'Send new leads to', 'talkwyn' ), 'email' );
		echo '</section>';

		self::card( __( 'Spam protection', 'talkwyn' ), __( 'Every lead form has a hidden honeypot field and a rate limit. Cloudflare Turnstile is optional.', 'talkwyn' ) );
		self::toggle( 'lead_consent_enabled', __( 'Ask for consent with a checkbox', 'talkwyn' ) );
		self::field( 'lead_consent_text', __( 'Consent text', 'talkwyn' ), 'textarea' );
		self::field( 'lead_rate_limit_per_hour', __( 'Lead forms per visitor per hour', 'talkwyn' ), 'number', '', array( 'min' => 1, 'max' => 100 ) );
		self::field( 'turnstile_site_key', __( 'Turnstile site key (optional)', 'talkwyn' ), 'text', '', array( 'autocomplete' => 'off' ) );
		self::field( 'turnstile_secret', __( 'Turnstile secret key (optional)', 'talkwyn' ), 'password', __( 'When both keys are set, the lead form shows a Turnstile check and Talkwyn verifies it with Cloudflare.', 'talkwyn' ), array( 'autocomplete' => 'off' ) );
		echo '</section>';

		self::card( __( 'Talk to a person', 'talkwyn' ) );
		self::toggle( 'handoff_enabled', __( 'Show a link to a person', 'talkwyn' ) );
		self::field( 'handoff_label', __( 'Link text', 'talkwyn' ) );
		self::field( 'handoff_url', __( 'Link', 'talkwyn' ), 'url', __( 'A WhatsApp, booking, contact or support link.', 'talkwyn' ) );
		echo '</section></div>';
		do_action( 'talkwyn_admin_behavior' );
		self::form_close();
	}

	/**
	 * Privacy.
	 *
	 * @return void
	 */
	private static function tab_privacy() {
		self::form_open( 'privacy' );
		echo '<div class="twa-grid">';
		self::card( __( 'Chat logs', 'talkwyn' ) );
		self::toggle( 'logs_enabled', __( 'Store conversations', 'talkwyn' ), __( 'Needed for the Conversations tab and feedback.', 'talkwyn' ) );
		self::field( 'retention_days', __( 'Delete conversations after (days)', 'talkwyn' ), 'number', '', array( 'min' => 1, 'max' => 3650 ) );
		self::field( 'privacy_notice', __( 'Notice under the chat', 'talkwyn' ), 'textarea' );
		echo '<p class="description">' . wp_kses_post( sprintf( /* translators: 1: export link, 2: erase link */ __( 'Visitors can ask for their data. Use <a href="%1$s">Export Personal Data</a> or <a href="%2$s">Erase Personal Data</a> with their email.', 'talkwyn' ), esc_url( admin_url( 'export-personal-data.php' ) ), esc_url( admin_url( 'erase-personal-data.php' ) ) ) ) . '</p>';
		echo '</section>';
		self::card( __( 'Protection', 'talkwyn' ) );
		self::field( 'rate_limit_per_hour', __( 'Messages per visitor per hour', 'talkwyn' ), 'number', '', array( 'min' => 5, 'max' => 2000 ) );
		self::toggle( 'trusted_proxy', __( 'My site is behind Cloudflare or a proxy', 'talkwyn' ), __( 'Reads the visitor IP from proxy headers. Turn it on only if your site really is behind a proxy, because visitors can fake these headers otherwise.', 'talkwyn' ) );
		echo '</section>';
		self::card( __( 'When you uninstall', 'talkwyn' ) );
		self::toggle( 'delete_data_on_uninstall', __( 'Delete all Talkwyn data when the plugin is deleted', 'talkwyn' ), __( 'Removes knowledge, leads, conversations and settings. Leave it off to keep your data.', 'talkwyn' ) );
		echo '</section></div>';
		self::form_close();
	}

	/**
	 * Leads.
	 *
	 * @return void
	 */
	private static function tab_leads() {
		global $wpdb;
		$t    = Talkwyn_DB::tables();
		$rows = (array) $wpdb->get_results( "SELECT * FROM {$t['leads']} ORDER BY id DESC LIMIT 250" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		self::card( __( 'Leads', 'talkwyn' ), __( 'The latest 250 people who asked the team to follow up.', 'talkwyn' ) );
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_export_leads' ), 'talkwyn_export_leads' ) ) . '">' . esc_html__( 'Export CSV', 'talkwyn' ) . '</a></p>';
		echo '<div class="twa-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'talkwyn' ) . '</th><th>' . esc_html__( 'Name', 'talkwyn' ) . '</th><th>' . esc_html__( 'Email', 'talkwyn' ) . '</th><th>' . esc_html__( 'Phone', 'talkwyn' ) . '</th><th>' . esc_html__( 'Question', 'talkwyn' ) . '</th><th>' . esc_html__( 'Page', 'talkwyn' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'talkwyn' ) . '</span></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No leads yet.', 'talkwyn' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$del = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_delete_lead&id=' . absint( $r->id ) ), 'talkwyn_delete_lead_' . absint( $r->id ) );
			echo '<tr><td>' . esc_html( get_date_from_gmt( $r->created_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</td><td>' . esc_html( $r->name ) . '</td><td>' . ( $r->email ? '<a href="mailto:' . esc_attr( $r->email ) . '">' . esc_html( $r->email ) . '</a>' : '' ) . '</td><td>' . esc_html( $r->phone ) . '</td><td>' . esc_html( wp_trim_words( (string) $r->message, 18 ) ) . '</td><td>' . ( $r->page_url ? '<a href="' . esc_url( $r->page_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'talkwyn' ) . '</a>' : '' ) . '</td><td><a class="twa-danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this lead?', 'talkwyn' ) ) . '\')">' . esc_html__( 'Delete', 'talkwyn' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div></section>';
	}

	/**
	 * Conversations.
	 *
	 * @return void
	 */
	private static function tab_conversations() {
		global $wpdb;
		$t        = Talkwyn_DB::tables();
		$sessions = (array) $wpdb->get_results( "SELECT session_id, MAX(created_gmt) AS last_time, COUNT(*) AS messages, MAX(page_url) AS page_url FROM {$t['logs']} GROUP BY session_id ORDER BY last_time DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		self::card( __( 'Conversations', 'talkwyn' ), __( 'The latest 100 conversations, stored in your WordPress database.', 'talkwyn' ) );
		echo '<p><a class="button twa-danger-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_clear_logs' ), 'talkwyn_clear_logs' ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete all conversations?', 'talkwyn' ) ) . '\')">' . esc_html__( 'Delete all conversations', 'talkwyn' ) . '</a></p>';
		if ( ! $sessions ) {
			echo '<p>' . esc_html__( 'No conversations yet.', 'talkwyn' ) . '</p></section>';
			return;
		}
		foreach ( $sessions as $sess ) {
			$messages = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['logs']} WHERE session_id = %s ORDER BY id ASC LIMIT 80", $sess->session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			echo '<details class="twa-convo"><summary><strong>' . esc_html( substr( (string) $sess->session_id, 0, 8 ) ) . '</strong><span>' . esc_html( sprintf( /* translators: %d: message count */ _n( '%d message', '%d messages', (int) $sess->messages, 'talkwyn' ), (int) $sess->messages ) ) . '</span><span>' . esc_html( get_date_from_gmt( $sess->last_time, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</span></summary><div class="twa-convo__body">';
			foreach ( $messages as $m ) {
				$meta = $m->meta ? json_decode( (string) $m->meta, true ) : array();
				echo '<div class="twa-log twa-log--' . esc_attr( $m->role ) . '"><b>' . esc_html( 'user' === $m->role ? __( 'Visitor', 'talkwyn' ) : __( 'Assistant', 'talkwyn' ) ) . '</b><p>' . nl2br( esc_html( (string) $m->message ) ) . '</p>';
				if ( 'assistant' === $m->role ) {
					echo '<small>' . esc_html( (string) $m->provider ) . ( $m->response_ms ? ' · ' . esc_html( number_format_i18n( $m->response_ms / 1000, 1 ) ) . 's' : '' ) . ( $m->feedback ? ' · ' . esc_html( 'helpful' === $m->feedback ? __( 'Helpful', 'talkwyn' ) : __( 'Not helpful', 'talkwyn' ) ) : '' ) . '</small>';
				}
				if ( is_array( $meta ) && ! empty( $meta['error'] ) ) {
					echo '<div class="twa-log__error">' . esc_html__( 'Provider fallback:', 'talkwyn' ) . ' ' . esc_html( (string) $meta['error'] ) . '</div>';
				}
				echo '</div>';
			}
			echo '</div></details>';
		}
		echo '</section>';
	}

	/* ---------- Actions ---------- */

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn' ) );
		}
		check_admin_referer( 'talkwyn_save' );
		$input = isset( $_POST['talkwyn'] ) && is_array( $_POST['talkwyn'] ) ? wp_unslash( $_POST['talkwyn'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Talkwyn_Settings::sanitize().
		$bools = isset( $_POST['talkwyn_bools'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['talkwyn_bools'] ) ) : array();
		$tab   = isset( $_POST['return_tab'] ) ? sanitize_key( wp_unslash( $_POST['return_tab'] ) ) : 'dashboard';
		Talkwyn_Settings::update( Talkwyn_Settings::sanitize( $input, $bools ) );
		do_action( 'talkwyn_settings_saved', $tab );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . ( 'dashboard' === $tab ? '' : '&tab=' . $tab ) . '&updated=1' ) );
		exit;
	}

	/**
	 * Provider overrides from an AJAX request.
	 *
	 * @param string $id Provider ID.
	 * @return array
	 */
	private static function overrides( $id ) {
		$registry = Talkwyn_Providers::registry();
		$raw      = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by the caller; sanitized below.
		$out      = array();
		foreach ( (array) ( $registry[ $id ]['fields'] ?? array() ) as $field ) {
			if ( isset( $raw[ $field ] ) ) {
				$out[ $field ] = trim( sanitize_text_field( (string) $raw[ $field ] ) );
			}
		}
		return $out;
	}

	/**
	 * Test a provider.
	 *
	 * @return void
	 */
	public static function ajax_test() {
		check_ajax_referer( 'talkwyn_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'talkwyn' ) ), 403 );
		}
		$id     = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$result = Talkwyn_Providers::test( $id, self::overrides( $id ) );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error( $result, 400 );
	}

	/**
	 * Load models.
	 *
	 * @return void
	 */
	public static function ajax_models() {
		check_ajax_referer( 'talkwyn_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'talkwyn' ) ), 403 );
		}
		$id     = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$models = Talkwyn_Providers::models( $id, self::overrides( $id ), true );
		if ( ! $models ) {
			wp_send_json_error( array( 'message' => __( 'No models came back. Check the key, then try again.', 'talkwyn' ) ), 400 );
		}
		wp_send_json_success( array( 'models' => $models ) );
	}

	/**
	 * Export leads as CSV.
	 *
	 * @return void
	 */
	public static function export_leads() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn' ) );
		}
		check_admin_referer( 'talkwyn_export_leads' );
		global $wpdb;
		$t    = Talkwyn_DB::tables();
		$rows = (array) $wpdb->get_results( "SELECT * FROM {$t['leads']} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=talkwyn-leads-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'ID', 'Date GMT', 'Name', 'Email', 'Phone', 'Question', 'Page URL', 'Consent', 'Status' ) );
		foreach ( $rows as $r ) {
			$cells = array( $r['id'], $r['created_gmt'], $r['name'], $r['email'], $r['phone'], $r['message'], $r['page_url'], $r['consent'], $r['status'] );
			// Stop spreadsheet formula injection.
			$cells = array_map(
				static function ( $v ) {
					$v = (string) $v;
					return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
				},
				$cells
			);
			fputcsv( $out, $cells );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Delete a lead.
	 *
	 * @return void
	 */
	public static function delete_lead() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn' ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'talkwyn_delete_lead_' . $id );
		global $wpdb;
		$t = Talkwyn_DB::tables();
		if ( $id ) {
			$wpdb->delete( $t['leads'], array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=leads' ) );
		exit;
	}

	/**
	 * Delete all logs.
	 *
	 * @return void
	 */
	public static function clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn' ) );
		}
		check_admin_referer( 'talkwyn_clear_logs' );
		global $wpdb;
		$t = Talkwyn_DB::tables();
		$wpdb->query( "TRUNCATE TABLE {$t['logs']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=conversations' ) );
		exit;
	}
}
