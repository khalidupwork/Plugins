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

	const SLUG        = 'talkwyn';
	const TRIAL_URL   = 'https://talkwyn.com/pricing/#trial';
	const PRICING_URL = 'https://talkwyn.com/pricing/';
	const REVIEW_URL  = 'https://wordpress.org/support/plugin/talkwyn/reviews/#new-post';

	/**
	 * Checkbox keys printed on the current form.
	 *
	 * @var string[]
	 */
	private static $bools = array();

	/**
	 * Whether a Pro prompt was already shown on this page.
	 *
	 * @var bool
	 */
	private static $prompt_shown = false;

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
		add_action( 'admin_post_talkwyn_dismiss', array( __CLASS__, 'dismiss' ) );
		add_action( 'wp_ajax_talkwyn_test_provider', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_talkwyn_models', array( __CLASS__, 'ajax_models' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TALKWYN_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
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
	 * Body class on Talkwyn screens.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, 'talkwyn' ) ) {
			$classes .= ' talkwyn-admin';
		}
		return $classes;
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
	 * Icon for a tab or card.
	 *
	 * @param string $name Icon name.
	 * @return string SVG.
	 */
	public static function icon( $name ) {
		$paths = array(
			'dashboard'     => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
			'knowledge'     => '<path d="M4 19.5V5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2Z"/><path d="M8 7h7M8 11h5"/>',
			'providers'     => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M10 10h4v4h-4z"/>',
			'appearance'    => '<circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><path d="M12 2a10 10 0 0 0 0 20c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2h2.4A4.6 4.6 0 0 0 22 10.8C22 5.9 17.5 2 12 2Z"/>',
			'behavior'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8M8 13h5"/>',
			'leads'         => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
			'conversations' => '<path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2z"/><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/>',
			'privacy'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
			'license'       => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
			'extra'         => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><path d="M14 2v6h6M12 18v-6M9 15h6"/>',
			'analytics'     => '<path d="M3 3v18h18"/><path d="M7 16v-4M12 16V8M17 16v-7"/>',
			'inbox'         => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.1Z"/>',
			'pro'           => '<path d="m12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.4 6.8 19.1l1-5.8L3.5 9.2l5.9-.9Z"/>',
			'chats'         => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
			'chunks'        => '<path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
			'live'          => '<circle cx="12" cy="12" r="3"/><path d="M5.6 5.6a9 9 0 0 0 0 12.8M18.4 18.4a9 9 0 0 0 0-12.8M8.5 8.5a5 5 0 0 0 0 7M15.5 15.5a5 5 0 0 0 0-7"/>',
			'rocket'        => '<path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2.1-.1-2.9a2.2 2.2 0 0 0-2.9-.1Z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.9A12.9 12.9 0 0 1 22 2c0 2.7-.8 7.5-6 11a22.4 22.4 0 0 1-4 2Z"/><path d="M9 12H4s.6-3 2-4c1.6-1.1 5 0 5 0M12 15v5s3-.6 4-2c1.1-1.6 0-5 0-5"/>',
			'report'        => '<path d="M8 2v4M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
			'scan'          => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
			'fields'        => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h6"/>',
			'order'         => '<path d="m3 16 4 4 4-4M7 20V4M21 8l-4-4-4 4M17 4v16"/>',
			'window'        => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/>',
			'eye'           => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
			'text'          => '<path d="M4 7V4h16v3M9 20h6M12 4v16"/>',
			'menu'          => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
			'person'        => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
			'shield'        => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
			'trash'         => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
			'star'          => '<path d="m12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.4 6.8 19.1l1-5.8L3.5 9.2l5.9-.9Z"/>',
			'check'         => '<path d="M20 6 9 17l-5-5"/>',
			'x'             => '<path d="M18 6 6 18M6 6l12 12"/>',
			'arrow'         => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'copy'          => '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
			'globe'         => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18"/>',
		);
		$path  = $paths[ $name ] ?? $paths['star'];
		return '<svg class="twa-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
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
		wp_add_inline_style( 'talkwyn-admin', self::font_faces() );
		wp_enqueue_style( 'talkwyn-widget-preview', TALKWYN_URL . 'assets/css/widget.css', array(), TALKWYN_VERSION );
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
	 * Brand fonts, loaded from the plugin (no outside requests).
	 *
	 * @return string CSS.
	 */
	private static function font_faces() {
		$css   = '';
		$fonts = array(
			array( 'Plus Jakarta Sans', 'plus-jakarta-sans-latin-600-normal', 600 ),
			array( 'Plus Jakarta Sans', 'plus-jakarta-sans-latin-700-normal', 700 ),
			array( 'Plus Jakarta Sans', 'plus-jakarta-sans-latin-800-normal', 800 ),
			array( 'Figtree', 'figtree-latin-400-normal', 400 ),
			array( 'Figtree', 'figtree-latin-500-normal', 500 ),
			array( 'Figtree', 'figtree-latin-600-normal', 600 ),
			array( 'Figtree', 'figtree-latin-700-normal', 700 ),
		);
		foreach ( $fonts as $f ) {
			$css .= '@font-face{font-family:"' . $f[0] . '";src:url(' . esc_url( TALKWYN_URL . 'assets/fonts/' . $f[1] . '.woff2' ) . ') format("woff2");font-weight:' . (int) $f[2] . ';font-style:normal;font-display:swap}';
		}
		return $css;
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
	 * Page header used by the main screen and the wizard.
	 *
	 * @param string $title    Title.
	 * @param string $subtitle Subtitle.
	 * @param string $actions  Escaped HTML for the right side.
	 * @return void
	 */
	public static function hero( $title, $subtitle, $actions = '' ) {
		/**
		 * Filters the logo in the admin header (white label).
		 *
		 * @param string $url Logo URL.
		 */
		$logo = (string) apply_filters( 'talkwyn_admin_logo', TALKWYN_URL . 'assets/img/talkwyn-mark.svg' );
		echo '<header class="twa-hero"><div class="twa-hero__brand"><span class="twa-hero__mark"><img src="' . esc_url( $logo ) . '" alt="" width="44" height="44"></span><div><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $subtitle ) . '</p></div></div>';
		if ( '' !== $actions ) {
			echo '<div class="twa-hero__actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		echo '</header><hr class="wp-header-end">';
	}

	/**
	 * Header buttons.
	 *
	 * @return string
	 */
	private static function hero_actions() {
		$html = '<a class="twa-btn twa-btn--light" href="' . esc_url( admin_url( 'admin.php?page=talkwyn-setup' ) ) . '">' . self::icon( 'rocket' ) . esc_html__( 'Setup wizard', 'talkwyn' ) . '</a>';
		if ( ! self::pro_active() ) {
			$html .= '<a class="twa-btn twa-btn--red" href="' . esc_url( self::TRIAL_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Start free trial', 'talkwyn' ) . '</a>';
			$html .= '<a class="twa-btn twa-btn--ink" href="' . esc_url( self::PRICING_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Upgrade to Pro', 'talkwyn' ) . '</a>';
		}
		return $html;
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
		self::hero( (string) apply_filters( 'talkwyn_admin_menu_title', 'Talkwyn' ), __( 'Your website assistant: answers visitors in their language and captures leads.', 'talkwyn' ), self::hero_actions() );

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible twa-notice"><p>' . esc_html__( 'Changes saved.', 'talkwyn' ) . '</p></div>';
		}
		self::review_request();

		echo '<nav class="twa-tabs" aria-label="' . esc_attr__( 'Talkwyn sections', 'talkwyn' ) . '">';
		foreach ( self::tabs() as $key => $label ) {
			$url = admin_url( 'admin.php?page=' . self::SLUG . ( 'dashboard' === $key ? '' : '&tab=' . $key ) );
			echo '<a class="twa-tab' . ( $tab === $key ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $tab === $key ? ' aria-current="page"' : '' ) . '>' . self::icon( $key ) . '<span>' . esc_html( $label ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() returns static SVG.
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
		echo '<div class="twa-savebar"><button class="twa-btn twa-btn--ink twa-btn--lg"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3h11l3 3v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2Z"/><path d="M8 3v5h7V3"/><path d="M8 21v-7h8v7"/></svg><span>' . esc_html( '' !== $button ? $button : __( 'Save changes', 'talkwyn' ) ) . '</span><span class="twa-savebar__dot" title="' . esc_attr__( 'Unsaved changes', 'talkwyn' ) . '"></span></button></div></form>';
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
			echo '<p class="description">' . wp_kses(
				$help,
				array(
					'code'   => array(),
					'a'      => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'strong' => array(),
				)
			) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Switch.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 * @return void
	 */
	public static function toggle( $key, $label, $help = '' ) {
		self::$bools[] = $key;
		echo '<div class="twa-toggle"><label><input type="checkbox" role="switch" name="talkwyn[' . esc_attr( $key ) . ']" value="1"' . checked( (bool) Talkwyn_Settings::get( $key ), true, false ) . '> <span class="twa-toggle__text"><span class="twa-toggle__label">' . esc_html( $label ) . '</span>';
		if ( '' !== $help ) {
			echo '<span class="twa-toggle__help">' . esc_html( $help ) . '</span>';
		}
		echo '</span></label></div>';
	}

	/**
	 * Select.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Label.
	 * @param array  $options Options.
	 * @param string $help    Help.
	 * @return void
	 */
	public static function select( $key, $label, array $options, $help = '' ) {
		$value = (string) Talkwyn_Settings::get( $key );
		echo '<div class="twa-field"><label for="twa-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><select id="twa-' . esc_attr( $key ) . '" name="talkwyn[' . esc_attr( $key ) . ']">';
		foreach ( $options as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $value, (string) $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Card open.
	 *
	 * @param string $title Title.
	 * @param string $intro Intro text.
	 * @param string $class Extra class.
	 * @param string $icon  Icon name.
	 * @return void
	 */
	public static function card( $title, $intro = '', $class = '', $icon = '' ) {
		echo '<section class="twa-card ' . esc_attr( $class ) . '"><div class="twa-card__head">';
		if ( '' !== $icon ) {
			echo '<span class="twa-chip-icon">' . self::icon( $icon ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '<div><h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $intro ) {
			echo '<p class="twa-intro">' . esc_html( $intro ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Empty state.
	 *
	 * @param string $icon   Icon.
	 * @param string $title  Title.
	 * @param string $text   Text.
	 * @param string $button Escaped button HTML.
	 * @return void
	 */
	private static function empty_state( $icon, $title, $text, $button = '' ) {
		echo '<div class="twa-empty"><span class="twa-empty__icon">' . self::icon( $icon ) . '</span><strong>' . esc_html( $title ) . '</strong><p>' . esc_html( $text ) . '</p>' . $button . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG and escaped button.
	}

	/* ---------- Growth: Pro prompts and review request ---------- */

	/**
	 * Dismissed prompt IDs for the current user.
	 *
	 * @return string[]
	 */
	private static function dismissed() {
		return (array) get_user_meta( get_current_user_id(), 'talkwyn_dismissed', true );
	}

	/**
	 * One contextual, dismissible Pro prompt (admin only, never on the front end,
	 * at most one per screen, hidden when Pro is active).
	 *
	 * @param string $id    Prompt ID.
	 * @param string $title Title.
	 * @param string $text  Text.
	 * @return void
	 */
	public static function pro_prompt( $id, $title, $text ) {
		if ( self::$prompt_shown || self::pro_active() || in_array( 'pro_' . $id, self::dismissed(), true ) ) {
			return;
		}
		self::$prompt_shown = true;
		$dismiss            = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_dismiss&id=pro_' . $id ), 'talkwyn_dismiss' );
		echo '<aside class="twa-prompt"><span class="twa-prompt__badge">PRO</span><div class="twa-prompt__text"><strong>' . esc_html( $title ) . '</strong><p>' . esc_html( $text ) . '</p></div><div class="twa-prompt__actions"><a class="twa-btn twa-btn--red twa-btn--sm" href="' . esc_url( self::TRIAL_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Try free for 15 days', 'talkwyn' ) . '</a><a class="twa-prompt__close" href="' . esc_url( $dismiss ) . '" aria-label="' . esc_attr__( 'Dismiss', 'talkwyn' ) . '">' . self::icon( 'x' ) . '</a></div></aside>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	}

	/**
	 * Review request after 14 days of use and 20 chats.
	 *
	 * @return void
	 */
	private static function review_request() {
		if ( get_option( 'talkwyn_review_done' ) ) {
			return;
		}
		$snooze = (int) get_option( 'talkwyn_review_snooze', 0 );
		$since  = (int) get_option( 'talkwyn_installed_at', time() );
		if ( time() < $snooze || ( time() - $since ) < 14 * DAY_IN_SECONDS || self::chat_count() < 20 ) {
			return;
		}
		$done  = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_dismiss&id=review_done' ), 'talkwyn_dismiss' );
		$later = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_dismiss&id=review_later' ), 'talkwyn_dismiss' );
		echo '<aside class="twa-review"><span class="twa-chip-icon">' . self::icon( 'star' ) . '</span><div><strong>' . esc_html__( 'Is Talkwyn helping your visitors?', 'talkwyn' ) . '</strong><p>' . esc_html__( 'A short review on WordPress.org helps other site owners find it. It takes a minute.', 'talkwyn' ) . '</p></div><div class="twa-review__actions"><a class="twa-btn twa-btn--ink twa-btn--sm" href="' . esc_url( self::REVIEW_URL ) . '" target="_blank" rel="noopener" data-twa-review="done" data-twa-href="' . esc_url( $done ) . '">' . esc_html__( 'Leave a review', 'talkwyn' ) . '</a><a class="twa-btn twa-btn--light twa-btn--sm" href="' . esc_url( $later ) . '">' . esc_html__( 'Maybe later', 'talkwyn' ) . '</a><a class="twa-link" href="' . esc_url( $done ) . '">' . esc_html__( 'Do not ask again', 'talkwyn' ) . '</a></div></aside>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	}

	/**
	 * Number of conversations ever logged.
	 *
	 * @return int
	 */
	private static function chat_count() {
		global $wpdb;
		$t = Talkwyn_DB::tables();
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$t['logs']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Dismiss a prompt or the review request.
	 *
	 * @return void
	 */
	public static function dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'talkwyn' ) );
		}
		check_admin_referer( 'talkwyn_dismiss' );
		$id = isset( $_GET['id'] ) ? sanitize_key( wp_unslash( $_GET['id'] ) ) : '';
		if ( 'review_done' === $id ) {
			update_option( 'talkwyn_review_done', time(), false );
		} elseif ( 'review_later' === $id ) {
			update_option( 'talkwyn_review_snooze', time() + 30 * DAY_IN_SECONDS, false );
		} elseif ( 0 === strpos( $id, 'pro_' ) ) {
			$list   = self::dismissed();
			$list[] = $id;
			update_user_meta( get_current_user_id(), 'talkwyn_dismissed', array_values( array_unique( $list ) ) );
		}
		if ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	/* ---------- Weekly report ---------- */

	/**
	 * Local weekly numbers (nothing is sent anywhere).
	 *
	 * @return array
	 */
	public static function weekly() {
		global $wpdb;
		$t     = Talkwyn_DB::tables();
		$now   = time();
		$w1    = gmdate( 'Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS );
		$w2    = gmdate( 'Y-m-d H:i:s', $now - 14 * DAY_IN_SECONDS );
		$chats = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT session_id) FROM {$t['logs']} WHERE role = 'user' AND created_gmt >= %s", $w1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$prev  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT session_id) FROM {$t['logs']} WHERE role = 'user' AND created_gmt >= %s AND created_gmt < %s", $w2, $w1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$leads = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['leads']} WHERE created_gmt >= %s", $w1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$lprev = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['leads']} WHERE created_gmt >= %s AND created_gmt < %s", $w2, $w1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows  = (array) $wpdb->get_col( $wpdb->prepare( "SELECT message FROM {$t['logs']} WHERE role = 'user' AND created_gmt >= %s ORDER BY id DESC LIMIT 2000", $w1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$top   = array();
		foreach ( $rows as $q ) {
			$q = trim( (string) $q );
			if ( '' === $q || 0 === strpos( Talkwyn_Conversation::intent( $q ), 'social_' ) ) {
				continue;
			}
			$key = trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', Talkwyn_Text::lower( $q ) ) );
			if ( ! isset( $top[ $key ] ) ) {
				$top[ $key ] = array(
					'text'  => Talkwyn_Text::sub( $q, 0, 120 ),
					'count' => 0,
				);
			}
			++$top[ $key ]['count'];
		}
		uasort(
			$top,
			static function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);
		return array(
			'chats'      => $chats,
			'chats_prev' => $prev,
			'leads'      => $leads,
			'leads_prev' => $lprev,
			'top'        => array_slice( array_values( $top ), 0, 5 ),
		);
	}

	/**
	 * Change label (+3 or -2 versus last week).
	 *
	 * @param int $now  This week.
	 * @param int $prev Last week.
	 * @return string HTML.
	 */
	private static function delta( $now, $prev ) {
		$d = $now - $prev;
		if ( 0 === $d ) {
			return '<span class="twa-delta">' . esc_html__( 'same as last week', 'talkwyn' ) . '</span>';
		}
		/* translators: %s: signed number */
		return '<span class="twa-delta ' . ( $d > 0 ? 'is-up' : 'is-down' ) . '">' . esc_html( sprintf( __( '%s vs last week', 'talkwyn' ), ( $d > 0 ? '+' : '' ) . number_format_i18n( $d ) ) ) . '</span>';
	}

	/* ---------- Tabs ---------- */

	/**
	 * Dashboard.
	 *
	 * @return void
	 */
	private static function tab_dashboard() {
		global $wpdb;
		$t      = Talkwyn_DB::tables();
		$s      = Talkwyn_Settings::all();
		$since  = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$chunks = Talkwyn_Indexer::count();
		$leads  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['leads']} WHERE created_gmt >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$chats  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT session_id) FROM {$t['logs']} WHERE created_gmt >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$ready  = Talkwyn_Providers::order( $s );
		$live   = ! empty( $s['enabled'] );
		$stats  = array(
			array( 'chats', __( 'Chats, last 30 days', 'talkwyn' ), number_format_i18n( $chats ), '' ),
			array( 'leads', __( 'Leads, last 30 days', 'talkwyn' ), number_format_i18n( $leads ), '' ),
			array( 'chunks', __( 'Knowledge chunks', 'talkwyn' ), number_format_i18n( $chunks ), '' ),
			array( 'live', __( 'Chat status', 'talkwyn' ), $live ? __( 'Live', 'talkwyn' ) : __( 'Off', 'talkwyn' ), $live ? 'is-live' : 'is-off' ),
		);
		echo '<div class="twa-stats">';
		foreach ( $stats as $stat ) {
			echo '<div class="twa-stat ' . esc_attr( $stat[3] ) . '"><span class="twa-chip-icon">' . self::icon( $stat[0] ) . '</span><span class="twa-stat__label">' . esc_html( $stat[1] ) . '</span><strong>' . esc_html( $stat[2] ) . '</strong></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</div>';

		$steps = array(
			array( (bool) $ready, __( 'Add a free AI key', 'talkwyn' ), admin_url( 'admin.php?page=talkwyn&tab=providers' ) ),
			array( $chunks > 0, __( 'Scan your site', 'talkwyn' ), admin_url( 'admin.php?page=talkwyn&tab=knowledge' ) ),
			array( count( $ready ) > 1, __( 'Add a second provider for automatic fallback', 'talkwyn' ), admin_url( 'admin.php?page=talkwyn&tab=providers' ) ),
			array( $live, __( 'Turn the chat on', 'talkwyn' ), admin_url( 'admin.php?page=talkwyn&tab=appearance' ) ),
		);
		$done  = count( array_filter( wp_list_pluck( $steps, 0 ) ) );
		echo '<div class="twa-grid twa-grid--dash">';
		self::card( __( 'Getting started', 'talkwyn' ), __( 'Four steps and visitors can chat with your website.', 'talkwyn' ), '', 'rocket' );
		/* translators: 1: done steps, 2: all steps */
		echo '<div class="twa-progress-label">' . esc_html( sprintf( __( '%1$d of %2$d done', 'talkwyn' ), $done, count( $steps ) ) ) . '</div><div class="twa-progress twa-progress--steps"><span style="width:' . esc_attr( (string) round( 100 * $done / count( $steps ) ) ) . '%"></span></div><ol class="twa-steps">';
		foreach ( $steps as $step ) {
			echo '<li class="' . ( $step[0] ? 'is-done' : '' ) . '"><span class="twa-steps__mark">' . ( $step[0] ? self::icon( 'check' ) : '' ) . '</span><a href="' . esc_url( $step[2] ) . '">' . esc_html( $step[1] ) . '</a>' . ( $step[0] ? '<span class="screen-reader-text">' . esc_html__( '(done)', 'talkwyn' ) . '</span>' : '' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</ol><div class="twa-actions"><a class="twa-btn twa-btn--ink" href="' . esc_url( admin_url( 'admin.php?page=talkwyn-setup' ) ) . '">' . esc_html__( 'Open the setup wizard', 'talkwyn' ) . '</a><a class="twa-btn twa-btn--light" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Test it on your site', 'talkwyn' ) . '</a></div></section>';

		$w = self::weekly();
		self::card( __( 'This week', 'talkwyn' ), __( 'Your last 7 days, calculated on this site. Nothing is sent anywhere.', 'talkwyn' ), '', 'report' );
		echo '<div class="twa-week"><div><span>' . esc_html__( 'Chats', 'talkwyn' ) . '</span><strong>' . esc_html( number_format_i18n( $w['chats'] ) ) . '</strong>' . self::delta( $w['chats'], $w['chats_prev'] ) . '</div><div><span>' . esc_html__( 'Leads', 'talkwyn' ) . '</span><strong>' . esc_html( number_format_i18n( $w['leads'] ) ) . '</strong>' . self::delta( $w['leads'], $w['leads_prev'] ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- delta() escapes.
		echo '<h3 class="twa-subhead">' . esc_html__( 'Top questions', 'talkwyn' ) . '</h3>';
		if ( $w['top'] ) {
			echo '<ol class="twa-rank">';
			foreach ( $w['top'] as $row ) {
				echo '<li><span dir="auto">' . esc_html( $row['text'] ) . '</span><b>' . esc_html( number_format_i18n( $row['count'] ) ) . '</b></li>';
			}
			echo '</ol>';
		} else {
			echo '<p class="twa-muted">' . esc_html__( 'Questions appear here once visitors start chatting.', 'talkwyn' ) . '</p>';
		}
		echo '</section></div>';

		do_action( 'talkwyn_admin_dashboard' );

		if ( ! self::pro_active() ) {
			$features = array(
				__( 'Paid AI models: OpenAI, Claude, Mistral, DeepSeek', 'talkwyn' ),
				__( 'Smart search that finds answers by meaning', 'talkwyn' ),
				__( 'PDF, DOCX and web pages as knowledge', 'talkwyn' ),
				__( 'Analytics and an unanswered questions inbox', 'talkwyn' ),
				__( 'WooCommerce product cards and order lookup', 'talkwyn' ),
				__( 'Slack and Telegram lead alerts, white label', 'talkwyn' ),
			);
			echo '<section class="twa-procard"><div class="twa-procard__text"><span class="twa-eyebrow">' . esc_html__( 'Talkwyn Pro', 'talkwyn' ) . '</span><h2>' . esc_html__( 'Answer more, learn more, sell more', 'talkwyn' ) . '</h2><p>' . esc_html__( 'Everything in the free plugin keeps working. Pro adds:', 'talkwyn' ) . '</p><ul>';
			foreach ( $features as $f ) {
				echo '<li>' . self::icon( 'check' ) . esc_html( $f ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			}
			echo '</ul></div><div class="twa-procard__cta"><a class="twa-btn twa-btn--red twa-btn--lg" href="' . esc_url( self::TRIAL_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Start free 15-day trial', 'talkwyn' ) . '</a><a class="twa-btn twa-btn--ghost-light" href="' . esc_url( self::PRICING_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'See plans', 'talkwyn' ) . '</a><span>' . esc_html__( 'No card needed for the trial.', 'talkwyn' ) . '</span></div></section>';
		}
	}

	/**
	 * Knowledge.
	 *
	 * @return void
	 */
	private static function tab_knowledge() {
		global $wpdb;
		$t     = Talkwyn_DB::tables();
		$types = (array) $wpdb->get_results( "SELECT source_type, COUNT(DISTINCT source_key) AS n FROM {$t['chunks']} GROUP BY source_type ORDER BY n DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		self::card( __( 'Scan your website', 'talkwyn' ), __( 'Talkwyn reads your published pages, posts and products and keeps the text in your WordPress database. Password protected, private and draft content is never included.', 'talkwyn' ), '', 'scan' );
		echo '<div class="twa-scan"><button type="button" class="twa-btn twa-btn--ink" id="twa-scan">' . self::icon( 'scan' ) . esc_html__( 'Scan entire site', 'talkwyn' ) . '</button><button type="button" class="twa-btn twa-btn--light" id="twa-clear">' . esc_html__( 'Clear knowledge', 'talkwyn' ) . '</button><span id="twa-scan-status" aria-live="polite">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		/* translators: %s: number of chunks */
		printf( esc_html__( '%s chunks indexed', 'talkwyn' ), esc_html( number_format_i18n( Talkwyn_Indexer::count() ) ) );
		echo '</span></div><div class="twa-progress" aria-hidden="true"><span></span></div>';
		if ( $types ) {
			echo '<ul class="twa-breakdown">';
			foreach ( $types as $row ) {
				$obj   = get_post_type_object( (string) $row['source_type'] );
				$label = $obj ? $obj->labels->name : ucfirst( (string) $row['source_type'] );
				echo '<li><b>' . esc_html( number_format_i18n( (int) $row['n'] ) ) . '</b> ' . esc_html( $label ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</section>';

		self::form_open( 'knowledge' );
		echo '<div class="twa-grid">';
		self::card( __( 'What to scan', 'talkwyn' ), '', '', 'knowledge' );
		echo '<div class="twa-checks">';
		$selected = (array) Talkwyn_Settings::get( 'index_post_types', array() );
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			echo '<label class="twa-check"><input type="checkbox" name="talkwyn[index_post_types][]" value="' . esc_attr( $type->name ) . '"' . checked( in_array( $type->name, $selected, true ), true, false ) . '> <span>' . esc_html( $type->labels->name ) . '</span></label>';
		}
		echo '</div><input type="hidden" name="talkwyn[index_post_types][]" value="">';
		self::toggle( 'auto_index_on_save', __( 'Keep the knowledge up to date', 'talkwyn' ), __( 'Updates when content is saved, and removes it when it is unpublished, trashed or deleted.', 'talkwyn' ) );
		echo '</section>';

		self::card( __( 'Custom fields', 'talkwyn' ), __( 'Off by default. Turn it on and list only the fields that are safe to show visitors.', 'talkwyn' ), '', 'fields' );
		self::toggle( 'index_custom_fields', __( 'Include allowed custom fields', 'talkwyn' ) );
		self::field( 'custom_field_allowlist', __( 'Allowed field keys', 'talkwyn' ), 'textarea', __( 'One key per line or comma separated. Use <code>spec_*</code> to allow every key that starts with spec_. Keys that start with an underscore are always skipped.', 'talkwyn' ) );
		echo '</section></div>';
		self::form_close( __( 'Save knowledge settings', 'talkwyn' ) );
		do_action( 'talkwyn_admin_knowledge' );
		self::pro_prompt( 'knowledge', __( 'Add PDFs, documents and web pages', 'talkwyn' ), __( 'Talkwyn Pro reads PDF, DOCX and TXT files, crawls pages and sitemaps, and lets you write answers that always win.', 'talkwyn' ) );
	}

	/**
	 * Providers.
	 *
	 * @return void
	 */
	private static function tab_providers() {
		$registry = Talkwyn_Providers::registry();
		$s        = Talkwyn_Settings::all();
		$order    = array_values( array_unique( array_merge( array_filter( array_map( 'trim', explode( ',', (string) $s['provider_order'] ) ) ), array_keys( $registry ) ) ) );
		$order    = array_values( array_intersect( $order, array_keys( $registry ) ) );
		self::form_open( 'providers' );
		self::card( __( 'Fallback order', 'talkwyn' ), __( 'Talkwyn asks the first provider that has a key, and moves down the list if it fails or hits its limit. Drag to reorder. Two or more free keys give the best uptime.', 'talkwyn' ), '', 'order' );
		echo '<ol class="twa-order" data-twa-order>';
		foreach ( $order as $id ) {
			$ready = is_callable( $registry[ $id ]['ready'] ) && call_user_func( $registry[ $id ]['ready'], $s );
			echo '<li draggable="true" data-id="' . esc_attr( $id ) . '"><span class="twa-order__grip" aria-hidden="true">' . self::icon( 'menu' ) . '</span><span class="twa-order__name">' . esc_html( $registry[ $id ]['label'] ) . '</span><span class="twa-status ' . ( $ready ? 'is-on' : '' ) . '">' . esc_html( $ready ? __( 'Key added', 'talkwyn' ) : __( 'No key', 'talkwyn' ) ) . '</span><span class="twa-order__move"><button type="button" class="twa-icon-btn" data-move="up" aria-label="' . esc_attr__( 'Move up', 'talkwyn' ) . '">&#8593;</button><button type="button" class="twa-icon-btn" data-move="down" aria-label="' . esc_attr__( 'Move down', 'talkwyn' ) . '">&#8595;</button></span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</ol><input type="hidden" name="talkwyn[provider_order]" id="twa-provider_order" value="' . esc_attr( implode( ',', $order ) ) . '"></section><div class="twa-grid">';
		$labels = array(
			'key'        => __( 'API key', 'talkwyn' ),
			'model'      => __( 'Model', 'talkwyn' ),
			'account_id' => __( 'Account ID', 'talkwyn' ),
			'token'      => __( 'API token', 'talkwyn' ),
		);
		foreach ( $registry as $id => $p ) {
			$ready = is_callable( $p['ready'] ) && call_user_func( $p['ready'], $s );
			echo '<section class="twa-card twa-provider" data-provider="' . esc_attr( $id ) . '"><div class="twa-provider__head"><span class="twa-avatar">' . esc_html( strtoupper( substr( (string) $p['label'], 0, 1 ) ) ) . '</span><div><h2>' . esc_html( $p['label'] ) . '</h2><span class="twa-badge' . ( ! empty( $p['free'] ) ? ' twa-badge--free' : '' ) . '">' . esc_html( ! empty( $p['free'] ) ? __( 'Free tier', 'talkwyn' ) : __( 'Paid', 'talkwyn' ) ) . '</span> <span class="twa-status ' . ( $ready ? 'is-on' : '' ) . '">' . esc_html( $ready ? __( 'Key added', 'talkwyn' ) : __( 'Not set', 'talkwyn' ) ) . '</span></div>';
			if ( ! empty( $p['signup'] ) ) {
				echo '<a class="twa-btn twa-btn--light twa-btn--sm" href="' . esc_url( $p['signup'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get a key', 'talkwyn' ) . '</a>';
			}
			echo '</div>';
			foreach ( (array) $p['fields'] as $field ) {
				$suffix = preg_replace( '/^' . preg_quote( $id, '/' ) . '_/', '', $field );
				$label  = $labels[ $suffix ] ?? ucwords( str_replace( '_', ' ', $suffix ) );
				if ( ( $p['model_field'] ?? '' ) === $field ) {
					self::field(
						$field,
						$label,
						'text',
						'',
						array(
							'list'         => 'twa-models-' . $id,
							'class'        => 'twa-model',
							'autocomplete' => 'off',
						)
					);
					echo '<datalist id="twa-models-' . esc_attr( $id ) . '"></datalist><p class="twa-models-row"><button type="button" class="twa-link twa-load-models">' . esc_html__( 'Load models from the provider', 'talkwyn' ) . '</button> <span class="twa-models-status" aria-live="polite"></span></p>';
				} else {
					$secret = ( $p['key_field'] ?? '' ) === $field;
					self::field(
						$field,
						$label,
						$secret ? 'password' : 'text',
						'',
						array(
							'autocomplete' => 'off',
							'spellcheck'   => 'false',
						)
					);
				}
			}
			echo '<div class="twa-provider__test"><button type="button" class="twa-btn twa-btn--light twa-btn--sm twa-test">' . esc_html__( 'Test connection', 'talkwyn' ) . '</button> <span class="twa-test-status" aria-live="polite"></span></div></section>';
		}
		echo '</div>';
		do_action( 'talkwyn_admin_providers' );
		self::form_close( __( 'Save AI providers', 'talkwyn' ) );
		self::pro_prompt( 'providers', __( 'Want OpenAI, Claude, Mistral or DeepSeek?', 'talkwyn' ), __( 'Talkwyn Pro adds paid models for the best language quality, and streams answers word by word.', 'talkwyn' ) );
	}

	/**
	 * Appearance, with a live preview.
	 *
	 * @return void
	 */
	private static function tab_appearance() {
		$s     = Talkwyn_Settings::all();
		$brand = (string) $s['brand_color'];
		$on    = Talkwyn_Contrast::text_on( $brand );
		$ratio = Talkwyn_Contrast::ratio( $brand, $on );
		self::form_open( 'appearance' );
		echo '<div class="twa-split"><div class="twa-split__main">';

		self::card( __( 'Identity', 'talkwyn' ), '', '', 'person' );
		self::toggle( 'enabled', __( 'Show the chat on my site', 'talkwyn' ) );
		echo '<div class="twa-row">';
		self::field( 'bot_name', __( 'Assistant name', 'talkwyn' ), 'text', '', array( 'data-preview' => 'name' ) );
		self::field( 'online_label', __( 'Status text', 'talkwyn' ), 'text', '', array( 'data-preview' => 'status' ) );
		echo '</div>';
		self::field( 'welcome_message', __( 'Welcome message', 'talkwyn' ), 'textarea', '', array( 'data-preview' => 'welcome' ) );
		self::field( 'placeholder', __( 'Message box placeholder', 'talkwyn' ), 'text', '', array( 'data-preview' => 'placeholder' ) );
		self::field( 'suggested_questions', __( 'Suggested questions', 'talkwyn' ), 'textarea', __( 'One per line, up to four.', 'talkwyn' ), array( 'data-preview' => 'suggestions' ) );
		echo '</section>';

		self::card( __( 'Colour and style', 'talkwyn' ), __( 'Smart Contrast picks readable text for your colour automatically.', 'talkwyn' ), '', 'appearance' );
		echo '<div class="twa-field"><label for="twa-brand_color">' . esc_html__( 'Brand colour', 'talkwyn' ) . '</label><div class="twa-colorrow"><input type="color" id="twa-brand_color" name="talkwyn[brand_color]" value="' . esc_attr( $brand ) . '" class="twa-color"><div class="twa-contrast" data-ink="' . esc_attr( Talkwyn_Contrast::INK ) . '"><p class="twa-contrast__note">';
		echo esc_html( sprintf( /* translators: 1: text colour name, 2: contrast ratio */ __( 'Text on this colour: %1$s (contrast %2$s:1).', 'talkwyn' ), '#FFFFFF' === $on ? __( 'white', 'talkwyn' ) : __( 'dark', 'talkwyn' ), number_format_i18n( $ratio, 1 ) ) );
		echo '</p><p class="twa-contrast__warn"' . ( $ratio >= 4.5 ? ' hidden' : '' ) . '>' . esc_html__( 'Text on this colour is hard to read. Pick a darker or lighter shade.', 'talkwyn' ) . '</p></div></div></div>';
		echo '<div class="twa-row">';
		self::select( 'position', __( 'Position', 'talkwyn' ), array( 'right' => __( 'Bottom right', 'talkwyn' ), 'left' => __( 'Bottom left', 'talkwyn' ) ) );
		self::select( 'launcher_icon', __( 'Launcher icon', 'talkwyn' ), array( 'talkwyn' => __( 'Talkwyn bubble', 'talkwyn' ), 'chat_dots' => __( 'Chat dots', 'talkwyn' ), 'spark_chat' => __( 'Spark', 'talkwyn' ), 'headset' => __( 'Support headset', 'talkwyn' ), 'question' => __( 'Help', 'talkwyn' ) ) );
		self::select( 'header_style', __( 'Chat header', 'talkwyn' ), array( 'plain' => __( 'White', 'talkwyn' ), 'gradient' => __( 'Brand gradient', 'talkwyn' ) ) );
		self::toggle( 'launcher_pulse', __( 'Gentle pulse on the chat button until the first open', 'talkwyn' ) );
		echo '</div>';
		self::field( 'launcher_label', __( 'Launcher text', 'talkwyn' ), 'text', __( 'Leave empty to show only the icon.', 'talkwyn' ), array( 'data-preview' => 'launcher' ) );
		echo '<div class="twa-row">';
		self::select( 'avatar_type', __( 'Avatar', 'talkwyn' ), array( 'talkwyn' => __( 'Talkwyn bubble', 'talkwyn' ), 'initials' => __( 'First letter of the name', 'talkwyn' ), 'headset' => __( 'Support headset', 'talkwyn' ), 'chat' => __( 'Chat dots', 'talkwyn' ), 'custom' => __( 'Your image', 'talkwyn' ) ) );
		echo '<div class="twa-field"><label for="twa-avatar_url">' . esc_html__( 'Avatar image', 'talkwyn' ) . '</label><div class="twa-inline"><input type="url" id="twa-avatar_url" name="talkwyn[avatar_url]" value="' . esc_attr( (string) $s['avatar_url'] ) . '"><button type="button" class="twa-btn twa-btn--light twa-btn--sm" id="twa-avatar-media">' . esc_html__( 'Choose', 'talkwyn' ) . '</button></div></div>';
		echo '</div></section>';

		self::card( __( 'Chat window', 'talkwyn' ), '', '', 'window' );
		echo '<div class="twa-row">';
		self::field(
			'chat_width',
			__( 'Width on desktop (px)', 'talkwyn' ),
			'number',
			'',
			array(
				'min' => 320,
				'max' => 720,
			)
		);
		self::field(
			'chat_height',
			__( 'Height on desktop (px)', 'talkwyn' ),
			'number',
			'',
			array(
				'min' => 480,
				'max' => 900,
			)
		);
		echo '</div><div class="twa-toggles">';
		self::toggle( 'full_screen_enabled', __( 'Full screen button', 'talkwyn' ) );
		self::toggle( 'mobile_full_screen', __( 'Open full screen on phones', 'talkwyn' ) );
		self::toggle( 'show_minimize_button', __( 'Minimize button', 'talkwyn' ) );
		self::toggle( 'show_timestamps', __( 'Message times', 'talkwyn' ) );
		self::toggle( 'show_message_tools', __( 'Copy button under answers', 'talkwyn' ) );
		self::toggle( 'feedback_enabled', __( 'Helpful and not helpful buttons', 'talkwyn' ) );
		self::toggle( 'show_sources', __( 'Source links under answers', 'talkwyn' ) );
		self::toggle( 'sound_default', __( 'Sound on by default', 'talkwyn' ) );
		echo '</div></section>';

		self::card( __( 'Chat menu', 'talkwyn' ), __( 'The menu in the chat header. Visitors can change their name, get the chat by email, pick a language, pop the chat out or start over.', 'talkwyn' ), '', 'menu' );
		echo '<div class="twa-toggles">';
		self::toggle( 'widget_menu', __( 'Show the chat menu', 'talkwyn' ) );
		self::toggle( 'transcript_enabled', __( 'Email transcript', 'talkwyn' ), __( 'With consent; the email is saved as a lead with source "transcript".', 'talkwyn' ) );
		self::toggle( 'language_menu', __( 'Language picker', 'talkwyn' ) );
		self::toggle( 'show_sound_button', __( 'Sound on or off', 'talkwyn' ) );
		self::toggle( 'show_reset_button', __( 'New chat', 'talkwyn' ) );
		echo '</div></section>';

		self::card( __( 'Powered by Talkwyn', 'talkwyn' ), __( 'Off by default. When on, a small Linen badge links to talkwyn.com and the menu shows "Add chat to your website". Add your partner code to earn on signups.', 'talkwyn' ), '', 'star' );
		self::toggle( 'show_badge', __( 'Show the "Powered by Talkwyn" badge', 'talkwyn' ) );
		self::field( 'badge_ref', __( 'Partner code (optional)', 'talkwyn' ), 'text', __( 'Your talkwyn.com partner code. It is added to the link as ?ref=CODE.', 'talkwyn' ) );
		echo '</section>';

		self::card( __( 'Where the chat shows', 'talkwyn' ), __( 'Use the [talkwyn_chat] shortcode or the Talkwyn Chat block to place it inside a page.', 'talkwyn' ), '', 'eye' );
		echo '<div class="twa-row">';
		self::select( 'devices', __( 'Devices', 'talkwyn' ), array( 'all' => __( 'Desktop and phones', 'talkwyn' ), 'desktop' => __( 'Desktop only', 'talkwyn' ), 'mobile' => __( 'Phones and tablets only', 'talkwyn' ) ) );
		self::select( 'show_to', __( 'Visitors', 'talkwyn' ), array( 'all' => __( 'Everyone', 'talkwyn' ), 'logged_out' => __( 'Logged out visitors only', 'talkwyn' ), 'logged_in' => __( 'Logged in users only', 'talkwyn' ) ) );
		echo '</div>';
		$hidden = array_map( 'absint', (array) $s['hidden_page_ids'] );
		echo '<div class="twa-field"><label for="twa-hidden">' . esc_html__( 'Hide on these pages', 'talkwyn' ) . '</label><select id="twa-hidden" name="talkwyn[hidden_page_ids][]" multiple size="8">';
		foreach ( get_pages( array( 'sort_column' => 'post_title' ) ) as $page ) {
			echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( in_array( (int) $page->ID, $hidden, true ), true, false ) . '>' . esc_html( '' !== $page->post_title ? $page->post_title : __( '(no title)', 'talkwyn' ) ) . '</option>';
		}
		echo '</select><input type="hidden" name="talkwyn[hidden_page_ids][]" value=""><p class="description">' . esc_html__( 'Hold Ctrl (Windows) or Command (Mac) to pick several.', 'talkwyn' ) . '</p></div>';
		self::field( 'hidden_url_paths', __( 'Also hide on these paths', 'talkwyn' ), 'textarea', __( 'One per line, for example /checkout/', 'talkwyn' ) );
		echo '</section>';

		self::card( __( 'Interface text', 'talkwyn' ), __( 'WPML String Translation and Polylang can translate all of these per language.', 'talkwyn' ), '', 'text' );
		echo '<div class="twa-cols">';
		$texts = array(
			'typing_label'        => __( 'While the assistant writes', 'talkwyn' ),
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
			self::field( $key, $label, 'text', 'typing_label' === $key ? __( '{bot} becomes the assistant name, for example "Talkwyn is typing".', 'talkwyn' ) : '' );
		}
		echo '</div></section>';
		do_action( 'talkwyn_admin_appearance' );
		echo '</div><aside class="twa-split__side">' . self::preview( $s, $on ) . '</aside></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview() escapes.
		self::form_close( __( 'Save appearance', 'talkwyn' ) );
	}

	/**
	 * Live widget preview for the Appearance tab.
	 *
	 * @param array  $s  Settings.
	 * @param string $on Text colour on the brand colour.
	 * @return string HTML.
	 */
	private static function preview( array $s, $on ) {
		$icons = array();
		foreach ( array( 'talkwyn', 'chat_dots', 'spark_chat', 'headset', 'question' ) as $icon ) {
			$icons[ $icon ] = Talkwyn_Frontend::icon( $icon );
		}
		// Everything below is drawn by admin.js from the form, so every change shows at once.
		$data  = array(
			'icons'      => $icons,
			'poweredBy'  => __( 'Powered by Talkwyn', 'talkwyn' ),
			'question'   => __( 'Do you deliver on Saturdays?', 'talkwyn' ),
			'answer'     => __( 'Yes. Saturday delivery runs from 9 am to 2 pm in the city area.', 'talkwyn' ),
			'source'     => __( 'Delivery', 'talkwyn' ),
			'chatMenu'   => __( 'Chat menu', 'talkwyn' ),
			'changeName' => __( 'Change name', 'talkwyn' ),
			'transcript' => __( 'Email transcript', 'talkwyn' ),
			'language'   => __( 'Language', 'talkwyn' ),
			'popout'     => __( 'Pop out', 'talkwyn' ),
			'addChat'    => __( 'Add chat to your website', 'talkwyn' ),
			'privacy'    => (string) $s['privacy_notice'],
			/* translators: 1: width in pixels, 2: height in pixels */
			'size'       => __( 'Desktop size: %1$s x %2$s px', 'talkwyn' ),
		);
		$style = '--twc-brand:' . esc_attr( (string) $s['brand_color'] ) . ';--twc-on-brand:' . esc_attr( $on );
		return '<div class="twa-preview"><div class="twa-preview__label">' . esc_html__( 'Live preview', 'talkwyn' ) . '<span class="twa-preview__size"></span></div>'
			. '<div class="twc twa-preview__chat" style="' . $style . '" data-twa-preview="' . esc_attr( (string) wp_json_encode( $data ) ) . '">'
			. '<div class="twc-panel"><header class="twc-header"><div class="twc-avatar"></div><div class="twc-identity"><strong class="twc-name"></strong><span class="twc-status"></span></div><div class="twc-tools"></div></header>'
			. '<div class="twc-menu" role="menu" hidden></div>'
			. '<div class="twc-messages"></div><div class="twc-suggestions"></div>'
			. '<div class="twc-compose"><span class="twa-preview__ph"></span><span class="twc-send"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13"/><path d="m13 6 6 6-6 6"/></svg></span></div><div class="twc-foot"></div></div>'
			. '<div class="twc-launcher-wrap"><span class="twc-launcher-label"></span><span class="twc-launcher"></span></div></div>'
			. '<p class="twa-preview__note">' . esc_html__( 'Updates as you type. Save to put it live.', 'talkwyn' ) . '</p></div>';
	}

	/**
	 * Answers and leads.
	 *
	 * @return void
	 */
	private static function tab_behavior() {
		self::form_open( 'behavior' );
		echo '<div class="twa-grid">';
		self::card( __( 'Answers', 'talkwyn' ), '', '', 'behavior' );
		self::toggle( 'multilingual_enabled', __( 'Reply in the visitor\'s language', 'talkwyn' ), __( 'Detects the language of each message, follows switches, and supports right-to-left scripts and Roman Urdu.', 'talkwyn' ) );
		self::field( 'system_prompt', __( 'Instructions for the assistant', 'talkwyn' ), 'textarea', __( 'Tone, what to focus on, what to avoid. Website facts come from the scan.', 'talkwyn' ), array( 'rows' => 6 ) );
		self::field( 'fallback_message', __( 'Reply when no provider answers', 'talkwyn' ), 'textarea' );
		echo '<div class="twa-row">';
		self::field(
			'max_history',
			__( 'Messages remembered', 'talkwyn' ),
			'number',
			'',
			array(
				'min' => 0,
				'max' => 20,
			)
		);
		self::field(
			'max_context_chunks',
			__( 'Knowledge chunks per answer', 'talkwyn' ),
			'number',
			'',
			array(
				'min' => 2,
				'max' => 12,
			)
		);
		echo '</div></section>';

		self::card( __( 'Lead capture', 'talkwyn' ), __( 'The assistant answers first, then offers a follow-up after a few real questions, or sooner when the visitor asks for prices, a call or a quote.', 'talkwyn' ), '', 'leads' );
		self::toggle( 'lead_enabled', __( 'Offer to collect contact details', 'talkwyn' ) );
		self::toggle( 'lead_ask_first', __( 'Ask before showing the form', 'talkwyn' ) );
		self::toggle( 'smart_lead_intent', __( 'Offer sooner when the visitor shows buying or contact intent', 'talkwyn' ) );
		self::field(
			'lead_after_messages',
			__( 'Offer after this many questions', 'talkwyn' ),
			'number',
			'',
			array(
				'min' => 1,
				'max' => 20,
			)
		);
		self::field( 'lead_prompt', __( 'Offer question', 'talkwyn' ) );
		echo '<div class="twa-row">';
		self::field( 'lead_yes_label', __( 'Yes button', 'talkwyn' ) );
		self::field( 'lead_no_label', __( 'No button', 'talkwyn' ) );
		echo '</div>';
		self::field( 'lead_decline_reply', __( 'Reply after "No"', 'talkwyn' ) );
		self::field( 'lead_form_intro', __( 'Reply before the form', 'talkwyn' ) );
		echo '<div class="twa-row">';
		self::field( 'lead_title', __( 'Form title', 'talkwyn' ) );
		self::field( 'lead_button', __( 'Form button', 'talkwyn' ) );
		echo '</div>';
		self::field( 'lead_success', __( 'Thank you message', 'talkwyn' ), 'textarea' );
		echo '<div class="twa-toggles">';
		self::toggle( 'lead_require_name', __( 'Name is required', 'talkwyn' ) );
		self::toggle( 'lead_require_email', __( 'Email is required', 'talkwyn' ) );
		self::toggle( 'lead_require_phone', __( 'Phone is required', 'talkwyn' ) );
		echo '</div>';
		self::field( 'notification_email', __( 'Send new leads to', 'talkwyn' ), 'email' );
		echo '</section>';

		self::card( __( 'Spam protection', 'talkwyn' ), __( 'Every form has a hidden honeypot field, a time trap that rejects forms sent faster than a person can type, a rate limit and email validation. Cloudflare Turnstile is optional.', 'talkwyn' ), '', 'shield' );
		self::toggle( 'lead_consent_enabled', __( 'Ask for consent with a checkbox', 'talkwyn' ) );
		self::field( 'lead_consent_text', __( 'Consent text', 'talkwyn' ), 'textarea' );
		self::field(
			'lead_rate_limit_per_hour',
			__( 'Forms per visitor per hour', 'talkwyn' ),
			'number',
			'',
			array(
				'min' => 1,
				'max' => 100,
			)
		);
		self::field( 'turnstile_site_key', __( 'Turnstile site key (optional)', 'talkwyn' ), 'text', '', array( 'autocomplete' => 'off' ) );
		self::field( 'turnstile_secret', __( 'Turnstile secret key (optional)', 'talkwyn' ), 'password', __( 'When both keys are set, the lead form shows a Turnstile check and Talkwyn verifies it with Cloudflare.', 'talkwyn' ), array( 'autocomplete' => 'off' ) );
		echo '</section>';

		self::card( __( 'Talk to a person', 'talkwyn' ), '', '', 'person' );
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
		self::card( __( 'Chat logs', 'talkwyn' ), '', '', 'conversations' );
		self::toggle( 'logs_enabled', __( 'Store conversations', 'talkwyn' ), __( 'Needed for Conversations, feedback and email transcripts.', 'talkwyn' ) );
		self::field(
			'retention_days',
			__( 'Delete conversations after (days)', 'talkwyn' ),
			'number',
			'',
			array(
				'min' => 1,
				'max' => 3650,
			)
		);
		self::field( 'privacy_notice', __( 'Notice under the chat', 'talkwyn' ), 'textarea' );
		echo '<p class="description">' . wp_kses_post( sprintf( /* translators: 1: export link, 2: erase link */ __( 'Visitors can ask for their data. Use <a href="%1$s">Export Personal Data</a> or <a href="%2$s">Erase Personal Data</a> with their email.', 'talkwyn' ), esc_url( admin_url( 'export-personal-data.php' ) ), esc_url( admin_url( 'erase-personal-data.php' ) ) ) ) . '</p>';
		echo '</section>';
		self::card( __( 'Protection', 'talkwyn' ), '', '', 'shield' );
		self::field(
			'rate_limit_per_hour',
			__( 'Messages per visitor per hour', 'talkwyn' ),
			'number',
			'',
			array(
				'min' => 5,
				'max' => 2000,
			)
		);
		self::toggle( 'trusted_proxy', __( 'My site is behind Cloudflare or a proxy', 'talkwyn' ), __( 'Reads the visitor IP from proxy headers. Turn it on only if your site really is behind a proxy, because visitors can fake these headers otherwise.', 'talkwyn' ) );
		echo '</section>';
		self::card( __( 'When you uninstall', 'talkwyn' ), '', 'twa-card--wide', 'trash' );
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
		self::card( __( 'Leads', 'talkwyn' ), __( 'People who asked the team to follow up, newest first.', 'talkwyn' ), '', 'leads' );
		if ( ! $rows ) {
			self::empty_state( 'leads', __( 'No leads yet', 'talkwyn' ), __( 'When a visitor shares their details in the chat, they show up here and you get an email.', 'talkwyn' ), '<a class="twa-btn twa-btn--ink" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open your site to test the chat', 'talkwyn' ) . '</a>' );
			echo '</section>';
			self::pro_prompt( 'leads', __( 'Get leads in Slack or Telegram', 'talkwyn' ), __( 'Talkwyn Pro sends every new lead to Slack and Telegram the moment it arrives.', 'talkwyn' ) );
			return;
		}
		echo '<div class="twa-actions twa-actions--top"><a class="twa-btn twa-btn--light twa-btn--sm" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_export_leads' ), 'talkwyn_export_leads' ) ) . '">' . esc_html__( 'Export CSV', 'talkwyn' ) . '</a></div>';
		echo '<div class="twa-table"><table class="widefat"><thead><tr><th>' . esc_html__( 'Person', 'talkwyn' ) . '</th><th>' . esc_html__( 'Question', 'talkwyn' ) . '</th><th>' . esc_html__( 'Source', 'talkwyn' ) . '</th><th>' . esc_html__( 'Date', 'talkwyn' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'talkwyn' ) . '</span></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$del = wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_delete_lead&id=' . absint( $r->id ) ), 'talkwyn_delete_lead_' . absint( $r->id ) );
			echo '<tr><td><div class="twa-person"><span class="twa-avatar">' . esc_html( strtoupper( substr( (string) ( $r->name ? $r->name : $r->email ), 0, 1 ) ) ) . '</span><div><strong>' . esc_html( (string) $r->name ) . '</strong>';
			if ( $r->email ) {
				echo '<span class="twa-person__line"><a href="mailto:' . esc_attr( $r->email ) . '">' . esc_html( $r->email ) . '</a><button type="button" class="twa-icon-btn twa-copy" data-copy="' . esc_attr( $r->email ) . '" aria-label="' . esc_attr__( 'Copy email', 'talkwyn' ) . '">' . self::icon( 'copy' ) . '</button></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			}
			if ( $r->phone ) {
				echo '<span class="twa-person__line">' . esc_html( (string) $r->phone ) . '</span>';
			}
			echo '</div></div></td><td class="twa-q">' . esc_html( wp_trim_words( (string) $r->message, 22 ) ) . ( $r->page_url ? '<a class="twa-page" href="' . esc_url( $r->page_url ) . '" target="_blank" rel="noopener">' . esc_html( (string) wp_parse_url( $r->page_url, PHP_URL_PATH ) ) . '</a>' : '' ) . '</td><td><span class="twa-badge' . ( 'transcript' === ( $r->source ?? 'chat' ) ? '' : ' twa-badge--free' ) . '">' . esc_html( 'transcript' === ( $r->source ?? 'chat' ) ? __( 'Transcript', 'talkwyn' ) : __( 'Chat', 'talkwyn' ) ) . '</span></td><td class="twa-muted">' . esc_html( get_date_from_gmt( $r->created_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</td><td><a class="twa-danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this lead?', 'talkwyn' ) ) . '\')">' . esc_html__( 'Delete', 'talkwyn' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div></section>';
		self::pro_prompt( 'leads', __( 'Get leads in Slack or Telegram', 'talkwyn' ), __( 'Talkwyn Pro sends every new lead to Slack and Telegram the moment it arrives.', 'talkwyn' ) );
	}

	/**
	 * Conversations.
	 *
	 * @return void
	 */
	private static function tab_conversations() {
		global $wpdb;
		$t        = Talkwyn_DB::tables();
		$sessions = (array) $wpdb->get_results( "SELECT session_id, MAX(created_gmt) AS last_time, COUNT(*) AS messages, MIN(id) AS first_id FROM {$t['logs']} GROUP BY session_id ORDER BY last_time DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		self::card( __( 'Conversations', 'talkwyn' ), __( 'The latest 100 conversations, stored in your WordPress database.', 'talkwyn' ), '', 'conversations' );
		if ( ! $sessions ) {
			self::empty_state( 'conversations', __( 'No conversations yet', 'talkwyn' ), __( 'Chats with visitors show up here with the answers and the provider that replied.', 'talkwyn' ), '<a class="twa-btn twa-btn--ink" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open your site to test the chat', 'talkwyn' ) . '</a>' );
			echo '</section>';
			return;
		}
		echo '<div class="twa-actions twa-actions--top"><a class="twa-btn twa-btn--light twa-btn--sm twa-danger" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=talkwyn_clear_logs' ), 'talkwyn_clear_logs' ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete all conversations?', 'talkwyn' ) ) . '\')">' . esc_html__( 'Delete all conversations', 'talkwyn' ) . '</a></div>';
		foreach ( $sessions as $sess ) {
			$messages = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['logs']} WHERE session_id = %s ORDER BY id ASC LIMIT 80", $sess->session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$first    = '';
			$page     = '';
			foreach ( $messages as $m ) {
				if ( 'user' === $m->role ) {
					$first = (string) $m->message;
					$page  = (string) $m->page_url;
					break;
				}
			}
			echo '<details class="twa-convo"><summary><span class="twa-avatar">' . self::icon( 'chats' ) . '</span><span class="twa-convo__q" dir="auto">' . esc_html( '' !== $first ? wp_trim_words( $first, 14 ) : __( '(no question)', 'talkwyn' ) ) . '</span><span class="twa-convo__meta">' . ( '' !== $page ? esc_html( (string) wp_parse_url( $page, PHP_URL_PATH ) ) . ' · ' : '' ) . esc_html( sprintf( /* translators: %d: message count */ _n( '%d message', '%d messages', (int) $sess->messages, 'talkwyn' ), (int) $sess->messages ) ) . ' · ' . esc_html( human_time_diff( strtotime( $sess->last_time . ' UTC' ) ) ) . '</span></summary><div class="twa-convo__body">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			foreach ( $messages as $m ) {
				$meta = $m->meta ? json_decode( (string) $m->meta, true ) : array();
				echo '<div class="twa-bubble twa-bubble--' . esc_attr( $m->role ) . '"><p dir="auto">' . nl2br( esc_html( (string) $m->message ) ) . '</p>';
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
		self::pro_prompt( 'conversations', __( 'See what visitors ask most', 'talkwyn' ), __( 'Talkwyn Pro adds analytics and an inbox of unanswered questions with one-click answers.', 'talkwyn' ) );
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
		fputcsv( $out, array( 'ID', 'Date GMT', 'Name', 'Email', 'Phone', 'Question', 'Page URL', 'Consent', 'Source', 'Status' ) );
		foreach ( $rows as $r ) {
			$cells = array( $r['id'], $r['created_gmt'], $r['name'], $r['email'], $r['phone'], $r['message'], $r['page_url'], $r['consent'], $r['source'] ?? 'chat', $r['status'] );
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
