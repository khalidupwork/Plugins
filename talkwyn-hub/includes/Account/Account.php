<?php
/**
 * WooCommerce My Account: licenses, downloads, invoices.
 *
 * @package TalkwynHub
 */

namespace TWH\Account;

use TWH\Api\RateLimiter;
use TWH\Domain\DownloadToken;
use TWH\Domain\KeyGenerator;
use TWH\Domain\Markdown;
use TWH\LicenseService;
use TWH\Repository\Activations;
use TWH\Repository\Events;
use TWH\Repository\Licenses;
use TWH\Repository\Products;
use TWH\Repository\Releases;
use TWH\Support\Secrets;
use TWH\Woo\Cart;
use TWH\Woo\Subscriptions;

defined( 'ABSPATH' ) || exit;

/**
 * Registers endpoints and handles customer actions.
 */
final class Account {

	public const EP_LICENSES  = 'licenses';
	public const EP_DOWNLOADS = 'software-downloads';
	public const EP_INVOICE   = 'twh-invoice';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'add_endpoints' ) );
		add_filter( 'woocommerce_get_query_vars', array( self::class, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( self::class, 'menu' ) );
		add_action( 'woocommerce_account_' . self::EP_LICENSES . '_endpoint', array( self::class, 'licenses_endpoint' ) );
		add_action( 'woocommerce_account_' . self::EP_DOWNLOADS . '_endpoint', array( self::class, 'downloads_endpoint' ) );
		add_filter( 'woocommerce_endpoint_' . self::EP_LICENSES . '_title', array( self::class, 'title_licenses' ) );
		add_filter( 'woocommerce_endpoint_' . self::EP_DOWNLOADS . '_title', array( self::class, 'title_downloads' ) );
		add_action( 'template_redirect', array( self::class, 'handle_post' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_render_invoice' ) );
		add_action( 'wp_ajax_twh_reveal_key', array( self::class, 'ajax_reveal' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( self::class, 'invoice_action' ), 10, 2 );
	}

	/**
	 * Rewrite endpoints (also called on activation before flushing rules).
	 */
	public static function add_endpoints(): void {
		add_rewrite_endpoint( self::EP_LICENSES, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::EP_DOWNLOADS, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::EP_INVOICE, EP_ROOT | EP_PAGES );
	}

	/**
	 * WooCommerce query vars.
	 *
	 * @param array<string, string> $vars Vars.
	 * @return array<string, string>
	 */
	public static function query_vars( $vars ) {
		$vars[ self::EP_LICENSES ]  = self::EP_LICENSES;
		$vars[ self::EP_DOWNLOADS ] = self::EP_DOWNLOADS;
		$vars[ self::EP_INVOICE ]   = self::EP_INVOICE;
		return $vars;
	}

	/**
	 * Menu items, inserted before "Logout".
	 *
	 * @param array<string, string> $items Items.
	 * @return array<string, string>
	 */
	public static function menu( $items ) {
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );
		$items[ self::EP_LICENSES ]  = __( 'Licenses', 'talkwyn-hub' );
		$items[ self::EP_DOWNLOADS ] = __( 'Software downloads', 'talkwyn-hub' );
		if ( null !== $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/**
	 * Endpoint titles.
	 */
	public static function title_licenses(): string {
		return __( 'Licenses', 'talkwyn-hub' );
	}

	/**
	 * Endpoint titles.
	 */
	public static function title_downloads(): string {
		return __( 'Software downloads', 'talkwyn-hub' );
	}

	/**
	 * Front-end assets on account pages.
	 */
	public static function assets(): void {
		if ( ! function_exists( 'is_account_page' ) || ( ! is_account_page() && ! is_order_received_page() ) ) {
			return;
		}
		wp_enqueue_style( 'twh-account', TWH_URL . 'assets/css/account.css', array(), TWH_VERSION );
		wp_enqueue_script( 'twh-account', TWH_URL . 'assets/js/account.js', array(), TWH_VERSION, true );
		wp_localize_script(
			'twh-account',
			'twhAccount',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'twh_reveal' ),
				'i18n'    => array(
					'copied' => __( 'Copied!', 'talkwyn-hub' ),
					'copy'   => __( 'Copy', 'talkwyn-hub' ),
					'reveal' => __( 'Reveal', 'talkwyn-hub' ),
					'hide'   => __( 'Hide', 'talkwyn-hub' ),
					'error'  => __( 'Could not load the key. Please reload the page.', 'talkwyn-hub' ),
				),
			)
		);
	}

	/**
	 * Licenses of the current customer.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function my_licenses(): array {
		return Licenses::for_customer( get_current_user_id() );
	}

	/**
	 * Licenses endpoint (list or detail).
	 *
	 * @param string $value Endpoint value (license id for detail).
	 */
	public static function licenses_endpoint( $value = '' ): void {
		$id = absint( $value );
		if ( $id > 0 ) {
			$license = Licenses::find( $id );
			if ( ! $license || ! Licenses::is_owned_by( $license, get_current_user_id() ) ) {
				wc_print_notice( __( 'License not found.', 'talkwyn-hub' ), 'error' );
				return;
			}
			self::template(
				'account/license-detail.php',
				array(
					'license'     => $license,
					'key'         => (string) Licenses::plain_key( $license ),
					'activations' => Activations::active_for( $id ),
					'product'     => Products::find( (int) $license['product_id'] ),
					'upgrades'    => Cart::upgrade_targets( $license ),
					'renew_url'   => Cart::can_renew( $license ) ? Cart::renew_url( $license ) : '',
					'auto_renews' => Subscriptions::auto_renews( $license ),
					'back_url'    => wc_get_account_endpoint_url( self::EP_LICENSES ),
				)
			);
			return;
		}

		$rows = array();
		foreach ( self::my_licenses() as $license ) {
			$rows[] = array(
				'license'    => $license,
				'product'    => LicenseService::product_name( $license ),
				'status'     => Licenses::effective_status( $license ),
				'sites_used' => Activations::count_used( (int) $license['id'] ),
				'renew_url'  => Cart::can_renew( $license ) ? Cart::renew_url( $license ) : '',
				'detail_url' => self::detail_url( (int) $license['id'] ),
			);
		}
		self::template( 'account/licenses.php', array( 'rows' => $rows ) );
	}

	/**
	 * Downloads endpoint.
	 */
	public static function downloads_endpoint(): void {
		$items = array();
		$seen  = array();
		foreach ( self::my_licenses() as $license ) {
			if ( 'active' !== Licenses::effective_status( $license ) || isset( $seen[ (int) $license['product_id'] ] ) ) {
				continue;
			}
			$release = Releases::latest( (int) $license['product_id'], 'stable' );
			if ( ! $release ) {
				continue;
			}
			$seen[ (int) $license['product_id'] ] = true;

			$token   = DownloadToken::create( (int) $license['id'], (int) $release['id'], Secrets::token_secret(), time() );
			$items[] = array(
				'product'   => LicenseService::product_name( $license ),
				'version'   => (string) $release['version'],
				'date'      => (string) $release['released_at'],
				'size'      => (int) $release['file_size'],
				'url'       => add_query_arg( 'token', rawurlencode( $token ), rest_url( 'talkwyn-hub/v1/download' ) ),
				'changelog' => wp_kses_post( Markdown::to_html( (string) $release['changelog'] ) ),
			);
		}
		self::template( 'account/downloads.php', array( 'items' => $items ) );
	}

	/**
	 * Handle customer POST actions (site deactivation).
	 */
	public static function handle_post(): void {
		if ( empty( $_POST['twh_action'] ) || ! is_user_logged_in() ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['twh_action'] ) );
		if ( 'claim_license' === $action ) {
			self::handle_claim();
			return;
		}
		if ( 'deactivate_site' !== $action ) {
			return;
		}
		$license_id = absint( $_POST['license_id'] ?? 0 );
		if ( ! isset( $_POST['_twh_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_twh_nonce'] ) ), 'twh_deactivate_' . $license_id ) ) {
			wc_add_notice( __( 'Your session expired. Please try again.', 'talkwyn-hub' ), 'error' );
			return;
		}
		$license = Licenses::find( $license_id );
		if ( ! $license || ! Licenses::is_owned_by( $license, get_current_user_id() ) ) {
			wc_add_notice( __( 'License not found.', 'talkwyn-hub' ), 'error' );
			return;
		}
		if ( LicenseService::deactivate_site( $license, absint( $_POST['activation_id'] ?? 0 ), 'customer' ) ) {
			wc_add_notice( __( 'The site was deactivated. Its slot is free again.', 'talkwyn-hub' ) );
		} else {
			wc_add_notice( __( 'That site could not be deactivated.', 'talkwyn-hub' ), 'error' );
		}
		wp_safe_redirect( self::detail_url( $license_id ) );
		exit;
	}

	/**
	 * Link a guest/manual license to the current account by its full key.
	 */
	private static function handle_claim(): void {
		if ( ! isset( $_POST['_twh_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_twh_nonce'] ) ), 'twh_claim' ) ) {
			wc_add_notice( __( 'Your session expired. Please try again.', 'talkwyn-hub' ), 'error' );
			return;
		}
		if ( ! RateLimiter::hit( 'claim', (string) get_current_user_id() ) ) {
			wc_add_notice( __( 'Too many attempts. Please try again later.', 'talkwyn-hub' ), 'error' );
			return;
		}
		$key     = KeyGenerator::normalize( sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );
		$license = KeyGenerator::is_valid_format( $key ) ? Licenses::claim( $key, get_current_user_id() ) : null;
		if ( ! $license ) {
			wc_add_notice( __( 'This license key is not valid or already belongs to another account.', 'talkwyn-hub' ), 'error' );
			return;
		}
		Events::log( 'admin_edit', (int) $license['id'], array( 'claimed_by' => get_current_user_id() ) );
		wc_add_notice( __( 'The license was added to your account.', 'talkwyn-hub' ) );
		wp_safe_redirect( self::detail_url( (int) $license['id'] ) );
		exit;
	}

	/**
	 * AJAX: reveal a full key to its owner.
	 */
	public static function ajax_reveal(): void {
		check_ajax_referer( 'twh_reveal', 'nonce' );
		$license = Licenses::find( absint( $_POST['license_id'] ?? 0 ) );
		if ( ! $license || ! Licenses::is_owned_by( $license, get_current_user_id() ) ) {
			wp_send_json_error( null, 403 );
		}
		$key = Licenses::plain_key( $license );
		if ( null === $key ) {
			wp_send_json_error( null, 500 );
		}
		wp_send_json_success( array( 'key' => $key ) );
	}

	/**
	 * Badge for a license: Active (success), Expiring soon (warning, within 30 days),
	 * Expired (error), Lifetime (Plum), plus Suspended and Revoked.
	 *
	 * @param array<string, mixed> $license License.
	 * @return array{0: string, 1: string} [modifier class, label].
	 */
	public static function badge( array $license ): array {
		$status = Licenses::effective_status( $license );
		if ( 'active' === $status ) {
			$expires = Licenses::expires_ts( $license );
			if ( null === $expires ) {
				return array( 'lifetime', __( 'Lifetime', 'talkwyn-hub' ) );
			}
			if ( $expires - time() <= 30 * DAY_IN_SECONDS ) {
				return array( 'expiring', __( 'Expiring soon', 'talkwyn-hub' ) );
			}
			return array( 'active', __( 'Active', 'talkwyn-hub' ) );
		}
		return array( $status, LicenseService::status_label( $status ) );
	}

	/**
	 * URL of a license detail page.
	 *
	 * @param int $license_id License id.
	 */
	public static function detail_url( int $license_id ): string {
		return wc_get_endpoint_url( self::EP_LICENSES, (string) $license_id, wc_get_page_permalink( 'myaccount' ) );
	}

	/**
	 * Whether a dedicated invoice plugin is active.
	 */
	public static function has_invoice_plugin(): bool {
		$detected = class_exists( 'WPO_WCPDF' ) || class_exists( 'WooCommerce_PDF_Invoices' ) || class_exists( 'BEWPI_Invoice' ) || defined( 'WCPDF_VERSION' ) || class_exists( 'Sliced_Invoices' );
		return (bool) apply_filters( 'twh_has_invoice_plugin', $detected );
	}

	/**
	 * Add an "Invoice" action to the orders table.
	 *
	 * @param array<string, array<string, string>> $actions Actions.
	 * @param \WC_Order                            $order   Order.
	 * @return array<string, array<string, string>>
	 */
	public static function invoice_action( $actions, $order ) {
		if ( self::has_invoice_plugin() || ! $order instanceof \WC_Order || ! $order->is_paid() ) {
			return $actions;
		}
		$actions['twh_invoice'] = array(
			'url'  => wc_get_endpoint_url( self::EP_INVOICE, (string) $order->get_id(), wc_get_page_permalink( 'myaccount' ) ),
			'name' => __( 'Invoice', 'talkwyn-hub' ),
		);
		return $actions;
	}

	/**
	 * Render a printable invoice and exit.
	 */
	public static function maybe_render_invoice(): void {
		global $wp;
		if ( ! isset( $wp->query_vars[ self::EP_INVOICE ] ) || ! is_user_logged_in() ) {
			return;
		}
		$order = wc_get_order( absint( $wp->query_vars[ self::EP_INVOICE ] ) );
		$owner = $order instanceof \WC_Order && ( $order->get_customer_id() === get_current_user_id() || current_user_can( 'manage_woocommerce' ) );
		if ( ! $owner || ! $order->is_paid() ) {
			wp_die( esc_html__( 'Invoice not found.', 'talkwyn-hub' ), '', array( 'response' => 404 ) );
		}
		self::template( 'account/invoice.php', array( 'order' => $order ) );
		exit;
	}

	/**
	 * Load an overridable template (theme/talkwyn-hub/...).
	 *
	 * @param string               $name Template name.
	 * @param array<string, mixed> $args Variables.
	 */
	private static function template( string $name, array $args ): void {
		wc_get_template( $name, $args, 'talkwyn-hub/', TWH_DIR . 'templates/' );
	}
}
