<?php
/**
 * Printable invoice (used when no invoice plugin is active).
 *
 * Override by copying to yourtheme/talkwyn-hub/account/invoice.php.
 *
 * @package TalkwynHub
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

$twh_store = array_filter(
	array(
		get_option( 'woocommerce_store_address' ),
		get_option( 'woocommerce_store_address_2' ),
		trim( get_option( 'woocommerce_store_postcode' ) . ' ' . get_option( 'woocommerce_store_city' ) ),
		WC()->countries ? WC()->countries->get_base_country() : '',
	)
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Invoice %s', 'talkwyn-hub' ), $order->get_order_number() ) ); ?></title>
	<style>
		body{font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#111827;margin:0;background:#f3f4f6}
		.sheet{max-width:800px;margin:24px auto;background:#fff;padding:40px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
		h1{margin:0 0 4px;font-size:26px}
		.row{display:flex;justify-content:space-between;gap:24px;margin:24px 0}
		table{width:100%;border-collapse:collapse;margin-top:16px}
		th,td{text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;vertical-align:top}
		td.num,th.num{text-align:right}
		tfoot th{text-align:right;border:0}
		tfoot td{text-align:right;border:0}
		.muted{color:#6b7280}
		.actions{max-width:800px;margin:16px auto 0;text-align:right}
		.actions button{padding:8px 16px;font-size:14px;cursor:pointer}
		@media print{body{background:#fff}.sheet{box-shadow:none;margin:0;padding:0}.actions{display:none}}
	</style>
</head>
<body>
	<div class="actions"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'talkwyn-hub' ); ?></button></div>
	<div class="sheet">
		<div class="row">
			<div>
				<h1><?php esc_html_e( 'Invoice', 'talkwyn-hub' ); ?></h1>
				<div class="muted">
					<?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Invoice no. %s', 'talkwyn-hub' ), $order->get_order_number() ) ); ?><br>
					<?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Date: %s', 'talkwyn-hub' ), wc_format_datetime( $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created() ) ) ); ?><br>
					<?php echo esc_html( sprintf( /* translators: %s: payment method */ __( 'Paid via: %s', 'talkwyn-hub' ), $order->get_payment_method_title() ) ); ?>
				</div>
			</div>
			<div style="text-align:right">
				<strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong><br>
				<?php echo wp_kses_post( implode( '<br>', array_map( 'esc_html', $twh_store ) ) ); ?>
			</div>
		</div>

		<div>
			<strong><?php esc_html_e( 'Billed to', 'talkwyn-hub' ); ?></strong><br>
			<?php echo wp_kses_post( $order->get_formatted_billing_address() ? $order->get_formatted_billing_address() : esc_html( $order->get_billing_email() ) ); ?><br>
			<?php echo esc_html( $order->get_billing_email() ); ?>
			<?php
			$twh_vat = $order->get_meta( '_billing_vat_number' ) ? $order->get_meta( '_billing_vat_number' ) : $order->get_meta( '_vat_number' );
			if ( $twh_vat ) :
				?>
				<br><?php echo esc_html( sprintf( /* translators: %s: VAT number */ __( 'VAT: %s', 'talkwyn-hub' ), $twh_vat ) ); ?>
			<?php endif; ?>
		</div>

		<table>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Item', 'talkwyn-hub' ); ?></th>
					<th class="num"><?php esc_html_e( 'Qty', 'talkwyn-hub' ); ?></th>
					<th class="num"><?php esc_html_e( 'Total', 'talkwyn-hub' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $order->get_items() as $twh_item ) : ?>
				<tr>
					<td><?php echo esc_html( $twh_item->get_name() ); ?></td>
					<td class="num"><?php echo esc_html( (string) $twh_item->get_quantity() ); ?></td>
					<td class="num"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $twh_item ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
			<?php foreach ( $order->get_order_item_totals() as $twh_total ) : ?>
				<tr>
					<th colspan="2"><?php echo wp_kses_post( $twh_total['label'] ); ?></th>
					<td><?php echo wp_kses_post( $twh_total['value'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tfoot>
		</table>
		<p class="muted" style="margin-top:32px"><?php esc_html_e( 'Thank you for your business.', 'talkwyn-hub' ); ?></p>
	</div>
</body>
</html>
