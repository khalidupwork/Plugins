<?php
/**
 * License keys on the thank-you page, order view, order emails and admin order screen.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

use TWH\Domain\KeyGenerator;
use TWH\LicenseService;
use TWH\Repository\Licenses;
use TWH\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders keys under each order line item.
 */
final class OrderDisplay {

	/**
	 * True while rendering an email that goes to the admin.
	 *
	 * @var bool
	 */
	private static bool $admin_email = false;

	/**
	 * True while rendering any order email.
	 *
	 * @var bool
	 */
	private static bool $in_email = false;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_order_item_meta_end', array( self::class, 'item_keys' ), 10, 4 );
		add_action( 'woocommerce_email_before_order_table', array( self::class, 'email_start' ), 1, 4 );
		add_action( 'woocommerce_email_after_order_table', array( self::class, 'email_end' ), 99 );
		add_action( 'woocommerce_after_order_itemmeta', array( self::class, 'admin_item' ), 10, 2 );
		add_action( 'woocommerce_thankyou', array( self::class, 'thankyou_notice' ), 5 );
		add_filter( 'render_block_woocommerce/order-confirmation-status', array( self::class, 'thankyou_after_status' ) );
	}

	/**
	 * Track admin emails.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Whether to admin.
	 */
	public static function email_start( $order, $sent_to_admin = false ): void {
		self::$in_email    = true;
		self::$admin_email = (bool) $sent_to_admin;
	}

	/**
	 * Reset.
	 */
	public static function email_end(): void {
		self::$in_email    = false;
		self::$admin_email = false;
	}

	/**
	 * Keys under an order item (front end and customer emails).
	 *
	 * @param int                    $item_id    Item id.
	 * @param \WC_Order_Item_Product $item       Item.
	 * @param \WC_Order              $order      Order.
	 * @param bool                   $plain_text Plain text email.
	 */
	public static function item_keys( $item_id, $item, $order, $plain_text = false ): void {
		if ( ! $order instanceof \WC_Order || ! $item instanceof \WC_Order_Item_Product ) {
			return;
		}
		// Only the order owner (or a customer email) may see full keys.
		if ( ! self::$in_email && ! self::viewer_owns( $order ) ) {
			return;
		}
		// The thank-you page shows keys in the "You're in" panel instead.
		if ( ! self::$in_email && function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return;
		}

		$renew = (int) $item->get_meta( OrderHandler::ITEM_RENEW_LICENSE );
		$upg   = (int) $item->get_meta( OrderHandler::ITEM_UPGRADE );
		if ( $renew || $upg ) {
			$license = Licenses::find( $renew ? $renew : $upg );
			if ( $license ) {
				$label = $renew ? __( 'Renews license', 'talkwyn-hub' ) : __( 'Upgrades license', 'talkwyn-hub' );
				self::line( $label, KeyGenerator::mask( (string) $license['key_last4'] ), (bool) $plain_text );
			}
			return;
		}

		$licenses = Licenses::for_order_item( (int) $item_id );
		if ( ! $licenses ) {
			if ( $order->has_status( array( 'pending', 'on-hold' ) ) && $item->get_product() && Mapping::for_product( $item->get_product() ) ) {
				self::line( __( 'License key', 'talkwyn-hub' ), __( 'Issued as soon as your payment is confirmed.', 'talkwyn-hub' ), (bool) $plain_text );
			}
			return;
		}
		foreach ( $licenses as $license ) {
			// Emails show the full key only when Settings allow it; the account and thank-you page always do.
			$mask = self::$admin_email || ( self::$in_email && ! \TWH\Support\Settings::email_keys() );
			$key  = $mask ? KeyGenerator::mask( (string) $license['key_last4'] ) : (string) Licenses::plain_key( $license );
			self::line( __( 'License key', 'talkwyn-hub' ), $key, (bool) $plain_text, true );
		}
	}

	/**
	 * Print one line.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param bool   $plain Plain text.
	 * @param bool   $code  Monospace.
	 */
	private static function line( string $label, string $value, bool $plain, bool $code = false ): void {
		if ( $plain ) {
			echo "\n" . esc_html( $label ) . ': ' . esc_html( $value );
			return;
		}
		$style = $code ? 'font-family:Menlo,Consolas,monospace;font-size:14px;background:#f3f4f6;padding:2px 6px;border-radius:4px;user-select:all;' : '';
		printf(
			'<div class="twh-order-key" style="margin-top:6px"><strong>%s:</strong> <span style="%s">%s</span></div>',
			esc_html( $label ),
			esc_attr( $style ),
			esc_html( $value )
		);
	}

	/**
	 * Whether the current visitor owns the order (thank-you via order key, or logged in owner).
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function viewer_owns( \WC_Order $order ): bool {
		if ( is_admin() && current_user_can( 'edit_shop_orders' ) ) {
			return false; // Admin screens use admin_item() with masked keys.
		}
		$user_id = get_current_user_id();
		if ( $user_id && $order->get_customer_id() === $user_id ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- order key is the capability on the thank-you page.
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		return '' !== $key && hash_equals( $order->get_order_key(), (string) $key );
	}

	/**
	 * Masked keys and links on the admin order screen.
	 *
	 * @param int                    $item_id Item id.
	 * @param \WC_Order_Item_Product $item    Item.
	 */
	public static function admin_item( $item_id, $item ): void {
		if ( ! $item instanceof \WC_Order_Item_Product || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$ids = array();
		foreach ( Licenses::for_order_item( (int) $item_id ) as $license ) {
			$ids[] = $license;
		}
		foreach ( array( OrderHandler::ITEM_RENEW_LICENSE, OrderHandler::ITEM_UPGRADE ) as $meta ) {
			$id = (int) $item->get_meta( $meta );
			if ( $id ) {
				$license = Licenses::find( $id );
				if ( $license ) {
					$ids[] = $license;
				}
			}
		}
		foreach ( $ids as $license ) {
			printf(
				'<div class="twh-admin-key"><a href="%s">%s</a> · %s · %s</div>',
				esc_url( admin_url( 'admin.php?page=twh-licenses&action=edit&license=' . (int) $license['id'] ) ),
				esc_html( KeyGenerator::mask( (string) $license['key_last4'] ) ),
				esc_html( LicenseService::plan_label( (string) $license['plan_slug'] ) ),
				esc_html( LicenseService::status_label( (string) $license['status'] ) )
			);
		}
	}

	/**
	 * "You're in" panel on the thank-you page: keys with copy buttons and three next steps.
	 *
	 * @param int $order_id Order id.
	 */
	public static function thankyou_notice( $order_id ): void {
		static $done = array();
		if ( isset( $done[ (int) $order_id ] ) ) {
			return;
		}
		$done[ (int) $order_id ] = true;
		$order                   = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || ! self::viewer_owns( $order ) ) {
			return;
		}
		$licenses = Licenses::for_order( $order->get_id() );
		if ( ! $licenses ) {
			if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
				echo '<div class="twh-thanks"><p class="twh-thanks__title">' . esc_html__( 'Thanks for your order.', 'talkwyn-hub' ) . '</p><p class="twh-thanks__lede">' . esc_html__( 'Your license key appears here and in your email as soon as the payment is confirmed.', 'talkwyn-hub' ) . '</p></div>';
			}
			return;
		}
		$logged_in = is_user_logged_in();
		$download  = $logged_in ? wc_get_account_endpoint_url( 'software-downloads' ) : home_url( '/download/' );
		$setup     = (string) apply_filters( 'twh_setup_guide_url', home_url( '/docs/getting-started/' ) );
		?>
		<section class="twh-thanks" aria-labelledby="twh-thanks-title">
			<h2 class="twh-thanks__title" id="twh-thanks-title"><?php esc_html_e( 'You\'re in.', 'talkwyn-hub' ); ?></h2>
			<p class="twh-thanks__lede">
				<?php
				if ( Settings::email_keys() ) {
					echo esc_html( _n( 'Your license is ready. We\'ve also emailed the key to you.', 'Your licenses are ready. We\'ve also emailed the keys to you.', count( $licenses ), 'talkwyn-hub' ) );
				} else {
					echo esc_html( _n( 'Your license is ready. Copy the key below: for your security, the email shows only its last characters. It is always in My Account.', 'Your licenses are ready. Copy the keys below: for your security, the email shows only their last characters. They are always in My Account.', count( $licenses ), 'talkwyn-hub' ) );
				}
				?>
			</p>
			<div class="twh-keypanel">
				<p class="twh-keypanel__label"><?php echo esc_html( _n( 'Your license key', 'Your license keys', count( $licenses ), 'talkwyn-hub' ) ); ?></p>
				<?php foreach ( $licenses as $license ) : ?>
					<div class="twh-key twh-key--full" data-license="<?php echo (int) $license['id']; ?>">
						<code class="twh-key-value"><?php echo esc_html( (string) Licenses::plain_key( $license ) ); ?></code>
						<button type="button" class="twh-copy-btn twh-copy"><?php esc_html_e( 'Copy', 'talkwyn-hub' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<ol class="twh-steps">
				<li><strong><?php esc_html_e( 'Download Talkwyn', 'talkwyn-hub' ); ?></strong><p><a href="<?php echo esc_url( $download ); ?>"><?php echo esc_html( $logged_in ? __( 'Get the latest version from your account', 'talkwyn-hub' ) : __( 'Get the plugin from the download page', 'talkwyn-hub' ) ); ?></a></p></li>
				<li><strong><?php esc_html_e( 'Activate your license', 'talkwyn-hub' ); ?></strong><p><?php esc_html_e( 'In WordPress, open Talkwyn, go to the License tab, and paste your key.', 'talkwyn-hub' ); ?></p></li>
				<li><strong><?php esc_html_e( 'Follow the setup guide', 'talkwyn-hub' ); ?></strong><p><a href="<?php echo esc_url( $setup ); ?>"><?php esc_html_e( 'Scan your site and go live in about five minutes', 'talkwyn-hub' ); ?></a></p></li>
			</ol>
			<?php if ( $logged_in ) : ?>
				<p><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'licenses' ) ); ?>"><?php esc_html_e( 'Manage your licenses and sites', 'talkwyn-hub' ); ?></a></p>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Block themes: show the panel right under the order status heading instead of below the order details.
	 *
	 * @param string $html Block output.
	 */
	public static function thankyou_after_status( $html ): string {
		$order_id = absint( get_query_var( 'order-received' ) );
		if ( ! $order_id ) {
			return (string) $html;
		}
		ob_start();
		self::thankyou_notice( $order_id );
		return $html . ob_get_clean();
	}
}
