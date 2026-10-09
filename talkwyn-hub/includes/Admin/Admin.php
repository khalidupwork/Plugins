<?php
/**
 * Admin menu, assets and notices.
 *
 * @package TalkwynHub
 */

namespace TWH\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level "Talkwyn Hub" menu.
 */
final class Admin {

	/**
	 * Capability required for all admin screens.
	 */
	public static function cap(): string {
		return (string) apply_filters( 'twh_admin_capability', 'manage_woocommerce' );
	}

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
		add_action( 'in_admin_header', array( self::class, 'header' ) );

		LicensesPage::init();
		ReleasesPage::init();
		ProductsPage::init();
		SettingsPage::init();
		PartnersPage::init();
		Export::init();
	}

	/**
	 * Menu.
	 */
	public static function menu(): void {
		$cap = self::cap();
		add_menu_page( __( 'Talkwyn Hub', 'talkwyn-hub' ), __( 'Talkwyn Hub', 'talkwyn-hub' ), $cap, 'twh-dashboard', array( Dashboard::class, 'render' ), 'dashicons-admin-network', 56 );
		add_submenu_page( 'twh-dashboard', __( 'Dashboard', 'talkwyn-hub' ), __( 'Dashboard', 'talkwyn-hub' ), $cap, 'twh-dashboard', array( Dashboard::class, 'render' ) );
		add_submenu_page( 'twh-dashboard', __( 'Licenses', 'talkwyn-hub' ), __( 'Licenses', 'talkwyn-hub' ), $cap, 'twh-licenses', array( LicensesPage::class, 'render' ) );
		add_submenu_page( 'twh-dashboard', __( 'Releases', 'talkwyn-hub' ), __( 'Releases', 'talkwyn-hub' ), $cap, 'twh-releases', array( ReleasesPage::class, 'render' ) );
		add_submenu_page( 'twh-dashboard', __( 'Products', 'talkwyn-hub' ), __( 'Products', 'talkwyn-hub' ), $cap, 'twh-products', array( ProductsPage::class, 'render' ) );
		add_submenu_page( 'twh-dashboard', __( 'Partners', 'talkwyn-hub' ), __( 'Partners', 'talkwyn-hub' ), $cap, 'twh-partners', array( PartnersPage::class, 'render' ) );
		add_submenu_page( 'twh-dashboard', __( 'Subscribers', 'talkwyn-hub' ), __( 'Subscribers', 'talkwyn-hub' ), $cap, 'twh-subscribers', array( SubscribersPage::class, 'render' ) );
		add_submenu_page( 'twh-dashboard', __( 'Logs', 'talkwyn-hub' ), __( 'Logs', 'talkwyn-hub' ), $cap, 'twh-logs', array( LogsPage::class, 'render' ) );
		add_submenu_page( 'twh-dashboard', __( 'Settings', 'talkwyn-hub' ), __( 'Settings', 'talkwyn-hub' ), $cap, 'twh-settings', array( SettingsPage::class, 'render' ) );
	}

	/**
	 * Whether the current admin screen belongs to the Hub.
	 */
	public static function is_hub_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return 0 === strpos( $page, 'twh-' );
	}

	/**
	 * Body class for Hub screens.
	 *
	 * @param string $classes Classes.
	 */
	public static function body_class( $classes ): string {
		return self::is_hub_screen() ? $classes . ' twh-admin' : (string) $classes;
	}

	/**
	 * Hub pages for the top navigation.
	 *
	 * @return array<string, array{0: string, 1: string}> Slug => label, icon.
	 */
	public static function pages(): array {
		return array(
			'twh-dashboard'   => array( __( 'Dashboard', 'talkwyn-hub' ), 'grid' ),
			'twh-licenses'    => array( __( 'Licenses', 'talkwyn-hub' ), 'key' ),
			'twh-releases'    => array( __( 'Releases', 'talkwyn-hub' ), 'box' ),
			'twh-products'    => array( __( 'Products', 'talkwyn-hub' ), 'tag' ),
			'twh-partners'    => array( __( 'Partners', 'talkwyn-hub' ), 'users' ),
			'twh-subscribers' => array( __( 'Subscribers', 'talkwyn-hub' ), 'mail' ),
			'twh-logs'        => array( __( 'Logs', 'talkwyn-hub' ), 'list' ),
			'twh-settings'    => array( __( 'Settings', 'talkwyn-hub' ), 'cog' ),
		);
	}

	/**
	 * Small stroke icon.
	 *
	 * @param string $name Icon name.
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'grid'   => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
			'key'    => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.8-9.8M17 6l3 3M14.5 8.5l2 2"/>',
			'box'    => '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
			'tag'    => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
			'users'  => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.5a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/>',
			'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'list'   => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
			'cog'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
			'plus'   => '<path d="M12 5v14M5 12h14"/>',
			'upload' => '<path d="M12 15V3M7 8l5-5 5 5M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>',
		);
		return '<svg class="twh-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
	}

	/**
	 * Brand header and page navigation on every Hub screen.
	 */
	public static function header(): void {
		if ( ! self::is_hub_screen() || ! current_user_can( self::cap() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$current = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		echo '<div class="twh-shell"><header class="twh-hero"><div class="twh-hero__brand"><span class="twh-hero__mark"><img src="' . esc_url( TWH_URL . 'assets/img/talkwyn-mark.svg' ) . '" alt="" width="40" height="40"></span><div><strong class="twh-hero__title">' . esc_html__( 'Talkwyn Hub', 'talkwyn-hub' ) . ' <span class="twh-ver">v' . esc_html( TWH_VERSION ) . '</span></strong><span class="twh-hero__sub">' . esc_html__( 'Licenses, trials, releases and partners for talkwyn.com', 'talkwyn-hub' ) . '</span></div></div>';
		echo '<div class="twh-hero__actions"><a class="twh-btn twh-btn--light" href="' . esc_url( admin_url( 'admin.php?page=twh-releases' ) ) . '">' . self::icon( 'upload' ) . esc_html__( 'Upload release', 'talkwyn-hub' ) . '</a><a class="twh-btn twh-btn--ink" href="' . esc_url( admin_url( 'admin.php?page=twh-licenses&action=new' ) ) . '">' . self::icon( 'plus' ) . esc_html__( 'Create license', 'talkwyn-hub' ) . '</a></div></header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		echo '<nav class="twh-nav" aria-label="' . esc_attr__( 'Talkwyn Hub sections', 'talkwyn-hub' ) . '">';
		foreach ( self::pages() as $slug => $page ) {
			echo '<a class="twh-nav__item' . ( $slug === $current ? ' is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"' . ( $slug === $current ? ' aria-current="page"' : '' ) . '>' . self::icon( $page[1] ) . '<span>' . esc_html( $page[0] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</nav></div>';
	}

	/**
	 * Brand fonts from the plugin (no outside requests).
	 */
	private static function font_css(): string {
		$css = '';
		foreach ( array(
			array( 'Figtree', 'figtree-latin-400-normal', 400 ),
			array( 'Figtree', 'figtree-latin-500-normal', 500 ),
			array( 'Figtree', 'figtree-latin-600-normal', 600 ),
			array( 'Figtree', 'figtree-latin-700-normal', 700 ),
			array( 'Plus Jakarta Sans', 'plus-jakarta-sans-latin-700-normal', 700 ),
			array( 'Plus Jakarta Sans', 'plus-jakarta-sans-latin-800-normal', 800 ),
		) as $f ) {
			$css .= '@font-face{font-family:"' . $f[0] . '";src:url(' . esc_url( TWH_URL . 'assets/fonts/' . $f[1] . '.woff2' ) . ') format("woff2");font-weight:' . (int) $f[2] . ';font-style:normal;font-display:swap}';
		}
		return $css;
	}

	/**
	 * Assets on our screens.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function assets( $hook ): void {
		if ( false === strpos( (string) $hook, 'twh-' ) ) {
			return;
		}
		wp_enqueue_style( 'twh-admin', TWH_URL . 'assets/css/admin.css', array(), TWH_VERSION );
		wp_add_inline_style( 'twh-admin', self::font_css() );
		wp_enqueue_script( 'twh-admin', TWH_URL . 'assets/js/admin.js', array(), TWH_VERSION, true );
		wp_localize_script(
			'twh-admin',
			'twhAdmin',
			array(
				'i18n' => array(
					'copied'  => __( 'Copied!', 'talkwyn-hub' ),
					'confirm' => __( 'Are you sure?', 'talkwyn-hub' ),
				),
			)
		);
	}

	/**
	 * Redirect back to a page with a notice code.
	 *
	 * @param string               $page   Page slug.
	 * @param string               $notice Notice code.
	 * @param array<string, mixed> $args   Extra args.
	 */
	public static function redirect( string $page, string $notice, array $args = array() ): void {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array(
						'page'       => $page,
						'twh_notice' => $notice,
					),
					$args
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Verify capability and nonce for an admin-post handler.
	 *
	 * @param string $action Nonce action.
	 */
	public static function guard( string $action ): void {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'talkwyn-hub' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Show notices after redirects.
	 */
	public static function notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$code = isset( $_GET['twh_notice'] ) ? sanitize_key( wp_unslash( $_GET['twh_notice'] ) ) : '';
		if ( '' === $code ) {
			return;
		}
		$messages = array(
			'saved'            => array( 'success', __( 'Saved.', 'talkwyn-hub' ) ),
			'created'          => array( 'success', __( 'License created.', 'talkwyn-hub' ) ),
			'emailed'          => array( 'success', __( 'License email sent.', 'talkwyn-hub' ) ),
			'email_failed'     => array( 'error', __( 'The email could not be sent.', 'talkwyn-hub' ) ),
			'deactivated'      => array( 'success', __( 'Site deactivated.', 'talkwyn-hub' ) ),
			'bulk_done'        => array( 'success', __( 'Bulk action applied.', 'talkwyn-hub' ) ),
			'status_invalid'   => array( 'error', __( 'Status not changed. To reactivate an expired license, set a future expiry date first.', 'talkwyn-hub' ) ),
			'uploaded'         => array( 'success', __( 'Release uploaded.', 'talkwyn-hub' ) ),
			'deleted'          => array( 'success', __( 'Deleted.', 'talkwyn-hub' ) ),
			'delete_blocked'   => array( 'error', __( 'This product still has licenses or releases and cannot be deleted.', 'talkwyn-hub' ) ),
			'upload_error'     => array( 'error', __( 'Upload failed. Please choose a valid ZIP file.', 'talkwyn-hub' ) ),
			'zip_invalid'      => array( 'error', __( 'The ZIP does not contain a WordPress plugin with a "Plugin Name" header.', 'talkwyn-hub' ) ),
			'version_mismatch' => array( 'error', __( 'The version you entered does not match the "Version" header of the plugin inside the ZIP.', 'talkwyn-hub' ) ),
			'version_exists'   => array( 'error', __( 'This version already exists for this product and channel.', 'talkwyn-hub' ) ),
			'storage_error'    => array( 'error', __( 'The release directory is not writable.', 'talkwyn-hub' ) ),
			'invalid'          => array( 'error', __( 'Please check the form and try again.', 'talkwyn-hub' ) ),
			'slug_exists'      => array( 'error', __( 'A product with this slug already exists.', 'talkwyn-hub' ) ),
			'key_next'         => array( 'success', __( 'A new signing key was generated. Add its public key to your client plugin before promoting it.', 'talkwyn-hub' ) ),
			'key_promoted'     => array( 'success', __( 'The next signing key is now active.', 'talkwyn-hub' ) ),
			'paid'             => array( 'success', __( 'Payout recorded and the partner was emailed.', 'talkwyn-hub' ) ),
			'reason_required'  => array( 'error', __( 'Add a reason before rejecting a referral. The partner sees it.', 'talkwyn-hub' ) ),
		);
		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] )
		);
	}
}
