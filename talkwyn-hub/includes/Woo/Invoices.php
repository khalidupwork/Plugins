<?php
/**
 * Invoices: a sequential invoice number for every paid order, a printable invoice
 * (print or save as PDF from the browser), and links in My Account, order emails and the admin.
 *
 * @package TalkwynHub
 */

namespace TWH\Woo;

use TWH\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Invoice numbers and the printable invoice.
 */
final class Invoices {

	public const META_NUMBER = '_twh_invoice_number';
	public const META_DATE   = '_twh_invoice_date';
	public const COUNTER     = 'twh_invoice_counter';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_order_status_processing', array( self::class, 'assign' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'assign' ), 20 );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( self::class, 'order_actions' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( self::class, 'order_button' ) );
		add_action( 'woocommerce_email_after_order_table', array( self::class, 'email_link' ), 20, 4 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( self::class, 'admin_link' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_render' ), 1 );
	}

	/**
	 * Format an invoice number from a counter value.
	 *
	 * @param int    $n      Counter value.
	 * @param string $prefix Prefix from settings.
	 */
	public static function format_number( int $n, string $prefix ): string {
		return $prefix . str_pad( (string) max( 1, $n ), 5, '0', STR_PAD_LEFT );
	}

	/**
	 * Give a paid order its invoice number (once).
	 *
	 * @param int $order_id Order ID.
	 */
	public static function assign( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || '' !== (string) $order->get_meta( self::META_NUMBER ) || ! $order->is_paid() ) {
			return;
		}
		$next = (int) get_option( self::COUNTER, 0 ) + 1;
		update_option( self::COUNTER, $next, false );
		$order->update_meta_data( self::META_NUMBER, self::format_number( $next, (string) Settings::get( 'invoice_prefix' ) ) );
		$order->update_meta_data( self::META_DATE, gmdate( 'Y-m-d H:i:s' ) );
		$order->save();
	}

	/**
	 * Invoice number of an order, assigning one first for paid orders that predate this feature.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function number( \WC_Order $order ): string {
		if ( '' === (string) $order->get_meta( self::META_NUMBER ) && $order->is_paid() ) {
			self::assign( $order->get_id() );
			$order = wc_get_order( $order->get_id() );
		}
		return (string) $order->get_meta( self::META_NUMBER );
	}

	/**
	 * Link to the printable invoice. The order key lets the buyer open it from an email.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function url( \WC_Order $order ): string {
		return add_query_arg(
			array(
				'twh_invoice' => $order->get_id(),
				'key'         => $order->get_order_key(),
			),
			home_url( '/' )
		);
	}

	/**
	 * Who may open an invoice: shop managers, the customer, or anyone holding the order key.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $key   Key from the link.
	 */
	public static function can_view( \WC_Order $order, string $key ): bool {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		if ( is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id() ) {
			return true;
		}
		return '' !== $key && hash_equals( $order->get_order_key(), $key );
	}

	/**
	 * "Invoice" button in My Account, Orders.
	 *
	 * @param array<string, array<string, string>> $actions Actions.
	 * @param \WC_Order                            $order   Order.
	 * @return array<string, array<string, string>>
	 */
	public static function order_actions( $actions, $order ) {
		if ( $order instanceof \WC_Order && $order->is_paid() && ! \TWH\Account\Account::has_invoice_plugin() ) {
			$actions['twh-invoice'] = array(
				'url'  => self::url( $order ),
				'name' => __( 'Invoice', 'talkwyn-hub' ),
			);
		}
		return $actions;
	}

	/**
	 * Button under the order table on View order and Thank you pages.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function order_button( $order ): void {
		if ( ! $order instanceof \WC_Order || ! $order->is_paid() || \TWH\Account\Account::has_invoice_plugin() ) {
			return;
		}
		echo '<p class="twh-invoice-link"><a class="button twh-btn" href="' . esc_url( self::url( $order ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View and download invoice', 'talkwyn-hub' ) . '</a></p>';
	}

	/**
	 * Invoice link in customer order emails.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Admin email.
	 * @param bool      $plain_text    Plain text email.
	 * @param mixed     $email         Email object.
	 */
	public static function email_link( $order, $sent_to_admin, $plain_text, $email = null ): void {
		unset( $email );
		if ( $sent_to_admin || ! $order instanceof \WC_Order || ! $order->is_paid() || \TWH\Account\Account::has_invoice_plugin() ) {
			return;
		}
		$label = __( 'View and download your invoice', 'talkwyn-hub' );
		if ( $plain_text ) {
			echo "\n" . esc_html( $label ) . ': ' . esc_url_raw( self::url( $order ) ) . "\n";
			return;
		}
		echo '<p style="margin:16px 0"><a href="' . esc_url( self::url( $order ) ) . '">' . esc_html( $label ) . '</a></p>';
	}

	/**
	 * Link in the admin order screen.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function admin_link( $order ): void {
		if ( ! $order instanceof \WC_Order || ! $order->is_paid() ) {
			return;
		}
		echo '<p class="form-field form-field-wide"><a class="button" href="' . esc_url( self::url( $order ) ) . '" target="_blank" rel="noopener">'
			/* translators: %s: invoice number */
			. esc_html( sprintf( __( 'Invoice %s', 'talkwyn-hub' ), self::number( $order ) ) ) . '</a></p>';
	}

	/**
	 * Serve the printable invoice for ?twh_invoice=ID&key=KEY.
	 */
	public static function maybe_render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read only, guarded by the order key or ownership.
		if ( ! isset( $_GET['twh_invoice'] ) ) {
			return;
		}
		$order = wc_get_order( absint( $_GET['twh_invoice'] ) );
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:enable
		if ( ! $order instanceof \WC_Order || ! self::can_view( $order, $key ) ) {
			wp_die( esc_html__( 'This invoice link is not valid, or you need to log in to see it.', 'talkwyn-hub' ), esc_html__( 'Invoice', 'talkwyn-hub' ), array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo self::html( $order ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped while building.
		exit;
	}

	/**
	 * The invoice document.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function html( \WC_Order $order ): string {
		$paid    = $order->is_paid();
		$number  = $paid ? self::number( $order ) : '';
		$date    = (string) $order->get_meta( self::META_DATE );
		$date    = '' !== $date ? date_i18n( get_option( 'date_format' ), (int) strtotime( $date . ' UTC' ) ) : wc_format_datetime( $order->get_date_created() );
		$company = (string) Settings::get( 'invoice_company' );
		$company = '' !== $company ? $company : get_bloginfo( 'name' );
		$address = (string) Settings::get( 'invoice_address' );
		$tax_id  = (string) Settings::get( 'invoice_tax_id' );
		$email   = (string) Settings::get( 'invoice_email' );
		$email   = '' !== $email ? $email : (string) get_option( 'admin_email' );
		$note    = (string) Settings::get( 'invoice_note' );
		$title   = $paid
			/* translators: %s: invoice number */
			? sprintf( __( 'Invoice %s', 'talkwyn-hub' ), $number )
			/* translators: %s: order number */
			: sprintf( __( 'Order %s (not paid yet)', 'talkwyn-hub' ), $order->get_order_number() );

		$rows = '';
		foreach ( $order->get_items() as $item ) {
			$meta = wc_display_item_meta(
				$item,
				array(
					'echo'      => false,
					'before'    => '<div class="meta">',
					'after'     => '</div>',
					'separator' => ', ',
				)
			);
			$rows .= '<tr><td><strong>' . esc_html( $item->get_name() ) . '</strong>' . wp_kses_post( $meta ) . '</td>'
				. '<td class="num">' . esc_html( (string) $item->get_quantity() ) . '</td>'
				. '<td class="num">' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) . '</td></tr>';
		}
		$totals = '';
		foreach ( $order->get_order_item_totals() as $key => $t ) {
			if ( 'payment_method' === $key ) {
				continue;
			}
			$totals .= '<tr class="' . esc_attr( 'order_total' === $key ? 'grand' : '' ) . '"><th>' . wp_kses_post( $t['label'] ) . '</th><td class="num">' . wp_kses_post( $t['value'] ) . '</td></tr>';
		}
		$bill = $order->get_formatted_billing_address();
		$bill = $bill ? $bill : esc_html( $order->get_formatted_billing_full_name() );
		$css  = 'body{margin:0;background:#F7F3F3;color:#1A0F12;font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
			. '.bar{max-width:820px;margin:24px auto 0;padding:0 16px;display:flex;justify-content:space-between;align-items:center;gap:12px}'
			. '.bar a{color:#96172A;font-weight:600;text-decoration:none}.bar button{border:0;border-radius:999px;background:#D7263D;color:#fff;font:600 15px system-ui,sans-serif;padding:12px 22px;cursor:pointer}'
			. '.doc{max-width:820px;margin:16px auto 40px;background:#fff;border-radius:20px;box-shadow:0 0 0 1px #EEE7E8,0 18px 40px rgba(26,15,18,.08);padding:48px}'
			. '.top{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;border-bottom:2px solid #D7263D;padding-bottom:24px}'
			. '.brand{font:800 26px/1 system-ui,sans-serif;letter-spacing:-.02em;color:#D7263D}.seller{margin-top:10px;color:#5A4E51;font-size:14px;white-space:pre-line}'
			. 'h1{margin:0;font-size:28px;letter-spacing:-.02em;text-align:right}.stamp{display:inline-block;margin-top:8px;padding:4px 12px;border-radius:999px;font-size:13px;font-weight:700}'
			. '.stamp.paid{background:#E3F4EC;color:#146345}.stamp.unpaid{background:#FFF1F2;color:#96172A}'
			. '.grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:28px 0}.grid h2{margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#96172A}'
			. '.kv{margin:0}.kv div{display:flex;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:1px dashed #EEE7E8}.kv dt{color:#5A4E51}.kv dd{margin:0;font-weight:600}'
			. 'table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 10px;border-bottom:1px solid #EEE7E8;vertical-align:top}thead th{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#5A4E51;background:#F7F3F3}'
			. '.num{text-align:right;white-space:nowrap}.meta{color:#5A4E51;font-size:13px;margin-top:2px}.meta p{display:inline;margin:0}'
			. '.totals{width:auto;margin-left:auto;min-width:320px;margin-top:8px}.totals th{font-weight:500;color:#5A4E51}.totals tr.grand th,.totals tr.grand td{font-size:18px;font-weight:800;color:#1A0F12;border-bottom:0}'
			. '.note{margin-top:32px;padding-top:16px;border-top:1px solid #EEE7E8;color:#5A4E51;font-size:13px}'
			. '@media(max-width:640px){.doc{padding:24px}.top,.grid{display:block}h1{text-align:left;margin-top:16px}.grid>div+div{margin-top:20px}.totals{min-width:0;width:100%}}'
			. '@media print{body{background:#fff}.bar{display:none}.doc{box-shadow:none;margin:0;max-width:none;padding:0;border-radius:0}}';

		return '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">'
			. '<title>' . esc_html( $title . ' · ' . $company ) . '</title><style>' . $css . '</style></head><body>'
			. '<div class="bar"><a href="' . esc_url( wc_get_account_endpoint_url( 'orders' ) ) . '">&larr; ' . esc_html__( 'Back to your orders', 'talkwyn-hub' ) . '</a>'
			. '<button type="button" onclick="window.print()">' . esc_html__( 'Print or save as PDF', 'talkwyn-hub' ) . '</button></div>'
			. '<main class="doc"><div class="top"><div><div class="brand">' . esc_html( $company ) . '</div>'
			. '<div class="seller">' . esc_html( $address ) . ( '' !== $tax_id ? "\n" . esc_html__( 'Tax ID', 'talkwyn-hub' ) . ': ' . esc_html( $tax_id ) : '' ) . "\n" . esc_html( $email ) . '</div></div>'
			. '<div><h1>' . esc_html( $paid ? __( 'Invoice', 'talkwyn-hub' ) : __( 'Order summary', 'talkwyn-hub' ) ) . '</h1>'
			. '<div style="text-align:right"><span class="stamp ' . ( $paid ? 'paid' : 'unpaid' ) . '">' . esc_html( $paid ? __( 'Paid', 'talkwyn-hub' ) : __( 'Not paid', 'talkwyn-hub' ) ) . '</span></div></div></div>'
			. '<div class="grid"><div><h2>' . esc_html__( 'Billed to', 'talkwyn-hub' ) . '</h2><div>' . wp_kses_post( $bill ) . '<br>' . esc_html( $order->get_billing_email() ) . '</div></div>'
			. '<div><h2>' . esc_html__( 'Details', 'talkwyn-hub' ) . '</h2><dl class="kv">'
			. ( $paid ? '<div><dt>' . esc_html__( 'Invoice number', 'talkwyn-hub' ) . '</dt><dd>' . esc_html( $number ) . '</dd></div>' : '' )
			. '<div><dt>' . esc_html__( 'Invoice date', 'talkwyn-hub' ) . '</dt><dd>' . esc_html( $date ) . '</dd></div>'
			. '<div><dt>' . esc_html__( 'Order number', 'talkwyn-hub' ) . '</dt><dd>' . esc_html( $order->get_order_number() ) . '</dd></div>'
			. '<div><dt>' . esc_html__( 'Payment method', 'talkwyn-hub' ) . '</dt><dd>' . esc_html( $order->get_payment_method_title() ) . '</dd></div>'
			. '</dl></div></div>'
			. '<table><thead><tr><th>' . esc_html__( 'Item', 'talkwyn-hub' ) . '</th><th class="num">' . esc_html__( 'Qty', 'talkwyn-hub' ) . '</th><th class="num">' . esc_html__( 'Amount', 'talkwyn-hub' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
			. '<table class="totals"><tbody>' . $totals . '</tbody></table>'
			. ( '' !== $note ? '<p class="note">' . nl2br( esc_html( $note ) ) . '</p>' : '' )
			. '</main></body></html>';
	}
}
