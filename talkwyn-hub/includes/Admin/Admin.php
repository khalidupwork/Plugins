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
		add_submenu_page( 'twh-dashboard', __( 'Logs', 'talkwyn-hub' ), __( 'Logs', 'talkwyn-hub' ), $cap, 'twh-logs', array( LogsPage::class, 'render' ) );
		add_submenu_page( 'twh-dashboard', __( 'Settings', 'talkwyn-hub' ), __( 'Settings', 'talkwyn-hub' ), $cap, 'twh-settings', array( SettingsPage::class, 'render' ) );
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
