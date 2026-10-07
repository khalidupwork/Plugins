<?php
/**
 * My Account → License detail.
 *
 * Override by copying to yourtheme/talkwyn-hub/account/license-detail.php.
 *
 * @package TalkwynHub
 * @var array<string, mixed>             $license
 * @var string                           $key
 * @var array<int, array<string, mixed>> $activations
 * @var array<string, mixed>|null        $product
 * @var array<int, array<string, mixed>> $upgrades
 * @var string                           $renew_url
 * @var bool                             $auto_renews
 * @var string                           $back_url
 */

use TWH\LicenseService;
use TWH\Repository\Licenses;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

$twh_status = Licenses::effective_status( $license );
$twh_used   = 0;
foreach ( $activations as $twh_a ) {
	$twh_used += (int) $twh_a['is_dev_site'] ? 0 : 1;
}
?>
<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'All licenses', 'talkwyn-hub' ); ?></a></p>

<div class="twh-card">
	<h3>
		<?php echo esc_html( $product ? $product['name'] : '' ); ?>
		<span class="twh-badge twh-badge--<?php echo esc_attr( $twh_status ); ?>"><?php echo esc_html( LicenseService::status_label( $twh_status ) ); ?></span>
	</h3>

	<div class="twh-key twh-key--full" data-license="<?php echo (int) $license['id']; ?>">
		<code class="twh-key-value"><?php echo esc_html( $key ); ?></code>
		<button type="button" class="button twh-copy"><?php esc_html_e( 'Copy', 'talkwyn-hub' ); ?></button>
	</div>

	<dl class="twh-meta">
		<dt><?php esc_html_e( 'Plan', 'talkwyn-hub' ); ?></dt>
		<dd><?php echo esc_html( LicenseService::plan_label( (string) $license['plan_slug'] ) ); ?></dd>
		<dt><?php esc_html_e( 'Expires', 'talkwyn-hub' ); ?></dt>
		<dd>
			<?php echo esc_html( Time::human( $license['expires_at'], __( 'Never (lifetime)', 'talkwyn-hub' ) ) ); ?>
			<?php if ( $auto_renews ) : ?>
				<small>(<?php esc_html_e( 'renews automatically', 'talkwyn-hub' ); ?>)</small>
			<?php endif; ?>
		</dd>
		<dt><?php esc_html_e( 'Sites', 'talkwyn-hub' ); ?></dt>
		<dd><?php echo esc_html( $twh_used . ' / ' . LicenseService::limit_label( (int) $license['activation_limit'] ) ); ?></dd>
	</dl>

	<?php if ( $renew_url ) : ?>
		<p><a class="button alt" href="<?php echo esc_url( $renew_url ); ?>"><?php echo esc_html( $auto_renews ? __( 'Manage subscription', 'talkwyn-hub' ) : __( 'Renew license', 'talkwyn-hub' ) ); ?></a></p>
	<?php endif; ?>
</div>

<h3><?php esc_html_e( 'Activated sites', 'talkwyn-hub' ); ?></h3>
<?php if ( ! $activations ) : ?>
	<p><?php esc_html_e( 'This license is not active on any site yet. Paste the key into Talkwyn → Settings → License on your website.', 'talkwyn-hub' ); ?></p>
<?php else : ?>
	<table class="shop_table shop_table_responsive twh-sites">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Site', 'talkwyn-hub' ); ?></th>
				<th><?php esc_html_e( 'Plugin version', 'talkwyn-hub' ); ?></th>
				<th><?php esc_html_e( 'Last check', 'talkwyn-hub' ); ?></th>
				<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'talkwyn-hub' ); ?></span></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $activations as $twh_a ) : ?>
			<tr>
				<td data-title="<?php esc_attr_e( 'Site', 'talkwyn-hub' ); ?>">
					<?php echo esc_html( (string) $twh_a['domain_normalized'] ); ?>
					<?php if ( (int) $twh_a['is_dev_site'] ) : ?>
						<span class="twh-badge twh-badge--dev"><?php esc_html_e( 'Dev / staging – free', 'talkwyn-hub' ); ?></span>
					<?php endif; ?>
				</td>
				<td data-title="<?php esc_attr_e( 'Plugin version', 'talkwyn-hub' ); ?>"><?php echo esc_html( (string) $twh_a['plugin_version'] ); ?></td>
				<td data-title="<?php esc_attr_e( 'Last check', 'talkwyn-hub' ); ?>"><?php echo esc_html( Time::human( $twh_a['last_check_at'], '—' ) ); ?></td>
				<td>
					<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Deactivate this site? Pro features will stop working there.', 'talkwyn-hub' ) ); ?>');">
						<input type="hidden" name="twh_action" value="deactivate_site">
						<input type="hidden" name="license_id" value="<?php echo (int) $license['id']; ?>">
						<input type="hidden" name="activation_id" value="<?php echo (int) $twh_a['id']; ?>">
						<?php wp_nonce_field( 'twh_deactivate_' . (int) $license['id'], '_twh_nonce' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Deactivate', 'talkwyn-hub' ); ?></button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $upgrades ) : ?>
	<h3><?php esc_html_e( 'Upgrade', 'talkwyn-hub' ); ?></h3>
	<p><?php esc_html_e( 'Move to a bigger plan and keep the same license key. You only pay the difference for the time left on your license.', 'talkwyn-hub' ); ?></p>
	<form method="post" class="twh-upgrade">
		<input type="hidden" name="twh_upgrade_license" value="<?php echo (int) $license['id']; ?>">
		<?php wp_nonce_field( 'twh_upgrade_' . (int) $license['id'], '_twh_nonce' ); ?>
		<?php foreach ( $upgrades as $twh_i => $twh_u ) : ?>
			<label class="twh-upgrade-option">
				<input type="radio" name="twh_target_product" value="<?php echo (int) $twh_u['wc_product']->get_id(); ?>" <?php checked( 0, $twh_i ); ?>>
				<strong><?php echo esc_html( LicenseService::plan_label( (string) $twh_u['mapping']['plan_slug'] ) ); ?></strong>
				— <?php echo esc_html( sprintf( /* translators: %s: number of sites */ __( '%s sites', 'talkwyn-hub' ), LicenseService::limit_label( (int) $twh_u['mapping']['activation_limit'] ) ) ); ?>
				— <?php echo wp_kses_post( wc_price( $twh_u['price'] ) ); ?>
			</label>
		<?php endforeach; ?>
		<p><button type="submit" class="button alt"><?php esc_html_e( 'Upgrade now', 'talkwyn-hub' ); ?></button></p>
	</form>
<?php endif; ?>
