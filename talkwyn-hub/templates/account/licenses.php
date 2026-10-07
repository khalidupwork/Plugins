<?php
/**
 * My Account → Licenses list.
 *
 * Override by copying to yourtheme/talkwyn-hub/account/licenses.php.
 *
 * @package TalkwynHub
 * @var array<int, array<string, mixed>> $rows
 */

use TWH\Domain\KeyGenerator;
use TWH\LicenseService;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

$twh_claim_form = static function () {
	?>
	<form method="post" class="twh-claim">
		<p><strong><?php esc_html_e( 'Bought as a guest or received a key?', 'talkwyn-hub' ); ?></strong> <?php esc_html_e( 'Enter it to add it to your account.', 'talkwyn-hub' ); ?></p>
		<input type="hidden" name="twh_action" value="claim_license">
		<?php wp_nonce_field( 'twh_claim', '_twh_nonce' ); ?>
		<label class="screen-reader-text" for="twh-claim-key"><?php esc_html_e( 'License key', 'talkwyn-hub' ); ?></label>
		<input id="twh-claim-key" name="license_key" type="text" class="input-text" placeholder="TALK-XXXX-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" required>
		<button type="submit" class="button"><?php esc_html_e( 'Add license', 'talkwyn-hub' ); ?></button>
	</form>
	<?php
};

if ( empty( $rows ) ) :
	?>
	<div class="woocommerce-info">
		<?php esc_html_e( 'You have no licenses yet.', 'talkwyn-hub' ); ?>
		<a class="button" href="<?php echo esc_url( apply_filters( 'twh_shop_url', wc_get_page_permalink( 'shop' ) ) ); ?>"><?php esc_html_e( 'Get Talkwyn Pro', 'talkwyn-hub' ); ?></a>
	</div>
	<?php
	$twh_claim_form();
	return;
endif;
?>
<table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders twh-licenses-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Product', 'talkwyn-hub' ); ?></th>
			<th><?php esc_html_e( 'License key', 'talkwyn-hub' ); ?></th>
			<th><?php esc_html_e( 'Status', 'talkwyn-hub' ); ?></th>
			<th><?php esc_html_e( 'Expires', 'talkwyn-hub' ); ?></th>
			<th><?php esc_html_e( 'Sites', 'talkwyn-hub' ); ?></th>
			<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'talkwyn-hub' ); ?></span></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $rows as $row ) : ?>
		<?php $license = $row['license']; ?>
		<tr>
			<td data-title="<?php esc_attr_e( 'Product', 'talkwyn-hub' ); ?>">
				<strong><?php echo esc_html( $row['product'] ); ?></strong><br>
				<small><?php echo esc_html( LicenseService::plan_label( (string) $license['plan_slug'] ) ); ?></small>
			</td>
			<td data-title="<?php esc_attr_e( 'License key', 'talkwyn-hub' ); ?>">
				<span class="twh-key" data-license="<?php echo (int) $license['id']; ?>" data-masked="<?php echo esc_attr( KeyGenerator::mask( (string) $license['key_last4'] ) ); ?>"><code class="twh-key-value"><?php echo esc_html( KeyGenerator::mask( (string) $license['key_last4'] ) ); ?></code>
					<button type="button" class="twh-link twh-reveal"><?php esc_html_e( 'Reveal', 'talkwyn-hub' ); ?></button>
					<button type="button" class="twh-link twh-copy"><?php esc_html_e( 'Copy', 'talkwyn-hub' ); ?></button>
				</span>
			</td>
			<td data-title="<?php esc_attr_e( 'Status', 'talkwyn-hub' ); ?>">
				<span class="twh-badge twh-badge--<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( LicenseService::status_label( $row['status'] ) ); ?></span>
			</td>
			<td data-title="<?php esc_attr_e( 'Expires', 'talkwyn-hub' ); ?>"><?php echo esc_html( Time::human( $license['expires_at'], __( 'Never', 'talkwyn-hub' ) ) ); ?></td>
			<td data-title="<?php esc_attr_e( 'Sites', 'talkwyn-hub' ); ?>">
				<?php echo esc_html( $row['sites_used'] . ' / ' . LicenseService::limit_label( (int) $license['activation_limit'] ) ); ?>
			</td>
			<td class="twh-actions">
				<a class="woocommerce-button button" href="<?php echo esc_url( $row['detail_url'] ); ?>"><?php esc_html_e( 'Manage', 'talkwyn-hub' ); ?></a>
				<?php if ( $row['renew_url'] ) : ?>
					<a class="woocommerce-button button twh-renew" href="<?php echo esc_url( $row['renew_url'] ); ?>"><?php esc_html_e( 'Renew', 'talkwyn-hub' ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php $twh_claim_form(); ?>
