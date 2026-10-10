<?php
/**
 * Customer dashboard: My Account as a full-screen app (sidebar, top bar, overview).
 *
 * @package TalkwynHub
 */

namespace TWH\Account;

use TWH\Api\RestController;
use TWH\Domain\DownloadToken;
use TWH\LicenseService;
use TWH\Repository\Activations;
use TWH\Repository\Licenses;
use TWH\Repository\Products;
use TWH\Repository\Releases;
use TWH\Support\Secrets;
use TWH\Support\Settings;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

/**
 * Logged-in customers get the dashboard layout on every My Account page. The
 * WooCommerce endpoints (orders, account details, licenses, downloads, partners)
 * render inside it unchanged. Logged-out visitors keep the normal login page.
 */
final class App {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'template_include', array( self::class, 'template' ), 99 );
		add_action( 'template_redirect', array( self::class, 'free_download' ), 5 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ), 20 );
	}

	/**
	 * Whether the dashboard layout is used for this request.
	 */
	public static function active(): bool {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! is_user_logged_in() ) {
			return false;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( Account::EP_INVOICE ) ) {
			return false;
		}
		/**
		 * Turn the dashboard layout off (to use the theme's My Account page).
		 *
		 * @param bool $on Whether it is on.
		 */
		return (bool) apply_filters( 'twh_account_app', true );
	}

	/**
	 * Use the app template.
	 *
	 * @param string $template Template path.
	 */
	public static function template( $template ) {
		if ( ! self::active() ) {
			return $template;
		}
		$theme = locate_template( 'talkwyn-hub/account/app.php' );
		return '' !== $theme ? $theme : TWH_DIR . 'templates/account/app.php';
	}

	/**
	 * Styles.
	 */
	public static function assets(): void {
		if ( ! self::active() ) {
			return;
		}
		wp_enqueue_style( 'twh-app', TWH_URL . 'assets/css/app.css', array( 'twh-account' ), TWH_VERSION );
	}

	/**
	 * Current endpoint ('' on the overview).
	 */
	public static function endpoint(): string {
		return function_exists( 'WC' ) && WC()->query ? (string) WC()->query->get_current_endpoint() : '';
	}

	/**
	 * Page title for the top bar.
	 */
	public static function title(): string {
		$ep = self::endpoint();
		if ( '' === $ep ) {
			return __( 'Overview', 'talkwyn-hub' );
		}
		$items = self::nav();
		foreach ( $items as $item ) {
			if ( in_array( $ep, $item['endpoints'], true ) ) {
				return $item['label'];
			}
		}
		$title = WC()->query->get_endpoint_title( $ep );
		return '' !== $title ? $title : __( 'My account', 'talkwyn-hub' );
	}

	/**
	 * Sidebar items.
	 *
	 * @return array<int, array{key: string, label: string, icon: string, url: string, endpoints: string[], count: int, external: bool}>
	 */
	public static function nav(): array {
		$account  = wc_get_page_permalink( 'myaccount' );
		$licenses = Licenses::for_customer( get_current_user_id() );
		$items    = array(
			array( 'overview', __( 'Overview', 'talkwyn-hub' ), 'grid', $account, array( '' ), 0 ),
			array( 'downloads', __( 'Downloads', 'talkwyn-hub' ), 'download', wc_get_account_endpoint_url( Account::EP_DOWNLOADS ), array( Account::EP_DOWNLOADS, 'downloads' ), 0 ),
			array( 'licenses', __( 'Licenses', 'talkwyn-hub' ), 'shield', wc_get_account_endpoint_url( Account::EP_LICENSES ), array( Account::EP_LICENSES ), count( $licenses ) ),
			array( 'billing', __( 'Billing', 'talkwyn-hub' ), 'receipt', wc_get_account_endpoint_url( 'orders' ), array( 'orders', 'view-order', 'edit-address' ), 0 ),
			array( 'account', __( 'Account', 'talkwyn-hub' ), 'cog', wc_get_account_endpoint_url( 'edit-account' ), array( 'edit-account', 'payment-methods', 'add-payment-method' ), 0 ),
		);
		if ( (int) Settings::get( 'partners_enabled' ) ) {
			$items[] = array( 'partner', __( 'Partner', 'talkwyn-hub' ), 'user-plus', wc_get_account_endpoint_url( 'partners' ), array( 'partners' ), 0 );
		}
		$out = array();
		foreach ( $items as $i ) {
			$out[] = array(
				'key'       => $i[0],
				'label'     => $i[1],
				'icon'      => $i[2],
				'url'       => $i[3],
				'endpoints' => $i[4],
				'count'     => $i[5],
				'external'  => false,
			);
		}
		$out[] = array(
			'key'       => 'support',
			'label'     => __( 'Support', 'talkwyn-hub' ),
			'icon'      => 'chat',
			'url'       => (string) apply_filters( 'twh_account_support_url', home_url( '/contact/' ) ),
			'endpoints' => array(),
			'count'     => 0,
			'external'  => true,
		);
		/**
		 * Filter the dashboard sidebar.
		 *
		 * @param array $out Items.
		 */
		return (array) apply_filters( 'twh_account_nav', $out );
	}

	/**
	 * Small stroke icon.
	 *
	 * @param string $name Name.
	 * @param int    $size Size in px.
	 */
	public static function icon( string $name, int $size = 20 ): string {
		$p = array(
			'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
			'download'  => '<path d="M12 3v12M7 10l5 5 5-5M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
			'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
			'receipt'   => '<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2Z"/><path d="M9 8h6M9 12h6"/>',
			'cog'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
			'user-plus' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M19 8v6M16 11h6"/>',
			'chat'      => '<path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/><path d="M8 9h8M8 13h5"/>',
			'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
			'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'back'      => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
			'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
			'sliders'   => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
			'share'     => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
			'sparkles'  => '<path d="M12 3c.4 2.9 2.1 4.6 5 5-2.9.4-4.6 2.1-5 5-.4-2.9-2.1-4.6-5-5 2.9-.4 4.6-2.1 5-5Z"/><path d="M18.5 14c.2 1.4 1 2.2 2.5 2.5-1.5.3-2.3 1.1-2.5 2.5-.2-1.4-1-2.2-2.5-2.5 1.5-.3 2.3-1.1 2.5-2.5Z"/>',
			'brain'     => '<path d="M9 3a3 3 0 0 0-3 3 3 3 0 0 0-2 5 3 3 0 0 0 2 5 3 3 0 0 0 3 3h0a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3Z"/><path d="M15 3a3 3 0 0 1 3 3 3 3 0 0 1 2 5 3 3 0 0 1-2 5 3 3 0 0 1-3 3h0a3 3 0 0 1-3-3"/>',
			'search'    => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
			'file'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
			'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
			'inbox'     => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.7 4H7.3a2 2 0 0 0-1.8 1.1Z"/>',
			'cart'      => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.5L22 8H6"/>',
			'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
			'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'tag'       => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
			'message'   => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
		);
		return '<svg class="twh-app-icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}

	/**
	 * Pro features, shown as "Features in your plan".
	 *
	 * @return array<int, array{0: string, 1: string, 2: string, 3: string}> Name, category, icon, tone.
	 */
	public static function features(): array {
		$list = array(
			array( __( 'Paid AI models', 'talkwyn-hub' ), __( 'Answers', 'talkwyn-hub' ), 'brain', 'violet' ),
			array( __( 'Smart search', 'talkwyn-hub' ), __( 'Answers', 'talkwyn-hub' ), 'search', 'amber' ),
			array( __( 'Custom answers', 'talkwyn-hub' ), __( 'Knowledge', 'talkwyn-hub' ), 'message', 'rose' ),
			array( __( 'PDF, DOCX and TXT uploads', 'talkwyn-hub' ), __( 'Knowledge', 'talkwyn-hub' ), 'file', 'teal' ),
			array( __( 'Analytics', 'talkwyn-hub' ), __( 'Insights', 'talkwyn-hub' ), 'chart', 'blue' ),
			array( __( 'Unanswered questions inbox', 'talkwyn-hub' ), __( 'Insights', 'talkwyn-hub' ), 'inbox', 'violet' ),
			array( __( 'WooCommerce product cards', 'talkwyn-hub' ), __( 'Sales', 'talkwyn-hub' ), 'cart', 'amber' ),
			array( __( 'Order status lookup', 'talkwyn-hub' ), __( 'Sales', 'talkwyn-hub' ), 'tag', 'teal' ),
			array( __( 'Proactive messages', 'talkwyn-hub' ), __( 'Engage', 'talkwyn-hub' ), 'sparkles', 'rose' ),
			array( __( 'Business hours', 'talkwyn-hub' ), __( 'Engage', 'talkwyn-hub' ), 'clock', 'blue' ),
			array( __( 'Slack and Telegram alerts', 'talkwyn-hub' ), __( 'Alerts', 'talkwyn-hub' ), 'bell', 'amber' ),
			array( __( 'White label', 'talkwyn-hub' ), __( 'Branding', 'talkwyn-hub' ), 'shield', 'violet' ),
			array( __( 'Settings export and import', 'talkwyn-hub' ), __( 'Tools', 'talkwyn-hub' ), 'share', 'teal' ),
		);
		/**
		 * Filter the features listed on the dashboard.
		 *
		 * @param array $list Name, category, icon, tone.
		 */
		return (array) apply_filters( 'twh_account_plan_features', $list );
	}

	/**
	 * Everything the overview needs.
	 *
	 * @return array<string, mixed>
	 */
	public static function overview(): array {
		$user     = wp_get_current_user();
		$licenses = Licenses::for_customer( (int) $user->ID );
		$active   = array_values(
			array_filter(
				$licenses,
				static function ( $l ) {
					return 'active' === Licenses::effective_status( $l );
				}
			)
		);
		$main     = $active ? $active[0] : ( $licenses ? $licenses[0] : null );
		$used     = 0;
		$limit    = 0;
		$no_limit = false;
		$sites    = array();
		foreach ( $active as $l ) {
			$used += Activations::count_used( (int) $l['id'] );
			if ( 0 === (int) $l['activation_limit'] ) {
				$no_limit = true;
			}
			$limit += (int) $l['activation_limit'];
		}
		foreach ( $licenses as $l ) {
			foreach ( Activations::active_for( (int) $l['id'] ) as $a ) {
				$sites[] = $a;
			}
		}
		usort(
			$sites,
			static function ( $a, $b ) {
				return strcmp( (string) $b['last_check_at'], (string) $a['last_check_at'] );
			}
		);
		$pro_download = '';
		if ( $active ) {
			$release = Releases::latest( (int) $active[0]['product_id'], 'stable' );
			if ( $release ) {
				$token        = DownloadToken::create( (int) $active[0]['id'], (int) $release['id'], Secrets::token_secret(), time() );
				$pro_download = add_query_arg( 'token', rawurlencode( $token ), rest_url( 'talkwyn-hub/v1/download' ) );
			}
		}
		return array(
			'user'         => $user,
			'licenses'     => $licenses,
			'active'       => $active,
			'main'         => $main,
			'used'         => $used,
			'limit'        => $no_limit ? 0 : $limit,
			'sites'        => $sites,
			'features'     => self::features(),
			'free_url'     => self::free_download_url(),
			'pro_download' => $pro_download,
		);
	}

	/**
	 * Plan card details for a license.
	 *
	 * @param array<string, mixed>|null $license License.
	 * @return array<string, string>
	 */
	public static function plan_card( ?array $license ): array {
		if ( ! $license ) {
			return array(
				'plan'   => __( 'Free', 'talkwyn-hub' ),
				'badge'  => __( 'Free plan', 'talkwyn-hub' ),
				'tone'   => 'muted',
				'ends'   => '',
				'manage' => home_url( '/pricing/' ),
			);
		}
		$badge   = Account::badge( $license );
		$expires = Licenses::expires_ts( $license );
		return array(
			'plan'   => LicenseService::plan_label( (string) $license['plan_slug'] ),
			'badge'  => $badge[1],
			'tone'   => in_array( $badge[0], array( 'active', 'lifetime' ), true ) ? 'green' : ( 'trial' === $badge[0] || 'expiring' === $badge[0] ? 'amber' : 'muted' ),
			'ends'   => null === $expires ? __( 'Never expires', 'talkwyn-hub' ) : sprintf( /* translators: %s: date */ __( 'Ends %s', 'talkwyn-hub' ), wp_date( get_option( 'date_format' ), $expires ) ),
			'manage' => Account::detail_url( (int) $license['id'] ),
		);
	}

	/**
	 * "7 hours ago" for an activation.
	 *
	 * @param array<string, mixed> $site Activation.
	 */
	public static function last_seen( array $site ): string {
		$ts = Time::to_ts( $site['last_check_at'] ?? null );
		if ( null === $ts ) {
			$ts = Time::to_ts( $site['activated_at'] ?? null );
		}
		/* translators: %s: time difference, e.g. "7 hours" */
		return null === $ts ? '' : sprintf( __( '%s ago', 'talkwyn-hub' ), human_time_diff( $ts, time() ) );
	}

	/**
	 * Free plugin download link (account only).
	 */
	public static function public_free_download_url(): string {
		return add_query_arg( 'twh_free_zip', '1', home_url( '/' ) );
	}

	/**
	 * Whether a stable release of the free plugin is uploaded.
	 */
	public static function has_free_release(): bool {
		$product = Products::find_by_slug( (string) apply_filters( 'twh_free_product', 'talkwyn' ) );
		return $product && null !== Releases::latest( (int) $product['id'], 'stable' );
	}

	/**
	 * Free plugin download for a logged-in customer (account dashboard).
	 */
	public static function free_download_url(): string {
		return wp_nonce_url( add_query_arg( 'twh_free_download', '1', wc_get_page_permalink( 'myaccount' ) ), 'twh_free_download' );
	}

	/**
	 * Stream the latest free plugin release, or send people to its public page.
	 * Upload the free plugin as a release of a product with the slug "talkwyn"
	 * (Talkwyn Hub, Products) to serve it from here.
	 */
	public static function free_download(): void {
		// The free plugin is GPL and public: /?twh_free_zip=1 serves it to anyone (the
		// website's download page uses it). The account link keeps its nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public download.
		$public = ! empty( $_GET['twh_free_zip'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		if ( ! $public && empty( $_GET['twh_free_download'] ) ) {
			return;
		}
		if ( ! $public && ( ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'twh_free_download' ) ) ) {
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}
		$product = Products::find_by_slug( (string) apply_filters( 'twh_free_product', 'talkwyn' ) );
		$release = $product ? Releases::latest( (int) $product['id'], 'stable' ) : null;
		$path    = $release ? \TWH\Support\Storage::path( (string) $release['zip_path'] ) : null;
		if ( ! $release || null === $path || ! is_readable( $path ) ) {
			wp_safe_redirect( (string) apply_filters( 'twh_free_download_fallback', home_url( '/download/' ) ) );
			exit;
		}
		RestController::stream( $path, $product['slug'] . '-' . preg_replace( '/[^0-9A-Za-z.-]/', '', (string) $release['version'] ) . '.zip' );
		exit;
	}
}
