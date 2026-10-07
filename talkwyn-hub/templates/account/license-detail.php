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

use TWH\Account\Account;
use TWH\LicenseService;
use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

$twh_badge = Account::badge( $license );
$twh_used  = 0;
foreach ( $activations as $twh_a ) {
	$twh_used += (int) $twh_a['is_dev_site'] ? 0 : 1;
}
?>
<p class="twh-back"><a href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'All licenses', 'talkwyn-hub' ); ?></a></p>

<section class="twh-license-card twh-license-card--detail" aria-labelledby="twh-license-title">
	<header class="twh-license-card__head">
		<div>
			<h2 id="twh-license-title" class="twh-license-card__title"><?php echo esc_html( $product ? (string) $product['name'] : '' ); ?> <span class="twh-plan"><?php echo esc_html( LicenseService::plan_label( (string) $license['plan_slug'] ) ); ?></span></h2>
			<span class="twh-badge twh-badge--<?php echo esc_attr( $twh_badge[0] ); ?>"><?php echo esc_html( $twh_badge[1] ); ?></span>
		</div>
	</header>

	<div class="twh-keypanel">
		<p class="twh-keypanel__label"><?php esc_html_e( 'License key', 'talkwyn-hub' ); ?></p>
		<div class="twh-key twh-key--full" data-license="<?php echo (int) $license['id']; ?>">
			<code class="twh-key-value" id="twh-key-<?php echo (int) $license['id']; ?>"><?php echo esc_html( $key ); ?></code>
			<button type="button" class="twh-copy-btn twh-copy"><?php esc_html_e( 'Copy', 'talkwyn-hub' ); ?></button>
		</div>
		<p class="twh-keypanel__hint"><?php esc_html_e( 'Paste it into Talkwyn → Settings → License on your website.', 'talkwyn-hub' ); ?></p>
	</div>

	<dl class="twh-meta">
		<div><dt><?php esc_html_e( 'Expires', 'talkwyn-hub' ); ?></dt><dd>
			<?php echo esc_html( Time::human( $license['expires_at'], __( 'Never (lifetime)', 'talkwyn-hub' ) ) ); ?>
			<?php if ( $auto_renews ) : ?>
				<small>(<?php esc_html_e( 'renews automatically', 'talkwyn-hub' ); ?>)</small>
			<?php endif; ?>
		</dd></div>
		<div><dt><?php esc_html_e( 'Sites', 'talkwyn-hub' ); ?></dt><dd><?php echo esc_html( $twh_used . ' / ' . LicenseService::limit_label( (int) $license['activation_limit'] ) ); ?></dd></div>
	</dl>

	<?php if ( $renew_url ) : ?>
		<div class="twh-actions">
			<a class="button twh-btn" href="<?php echo esc_url( $renew_url ); ?>"><?php echo esc_html( $auto_renews ? __( 'Manage subscription', 'talkwyn-hub' ) : __( 'Renew license', 'talkwyn-hub' ) ); ?></a>
		</div>
	<?php endif; ?>
</section>

<section class="twh-section" aria-labelledby="twh-sites-title">
	<h2 id="twh-sites-title"><?php esc_html_e( 'Activated sites', 'talkwyn-hub' ); ?></h2>
	<?php if ( ! $activations ) : ?>
		<p><?php esc_html_e( 'This license is not active on any site yet. Paste the key into Talkwyn → Settings → License on your website.', 'talkwyn-hub' ); ?></p>
	<?php else : ?>
		<ul class="twh-sites">
		<?php foreach ( $activations as $twh_a ) : ?>
			<li class="twh-site">
				<div class="twh-site__main">
					<strong class="twh-site__domain"><?php echo esc_html( (string) $twh_a['domain_normalized'] ); ?></strong>
					<?php if ( (int) $twh_a['is_dev_site'] ) : ?>
						<span class="twh-badge twh-badge--dev"><?php esc_html_e( 'Staging, free', 'talkwyn-hub' ); ?></span>
					<?php endif; ?>
					<span class="twh-site__meta">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: plugin version, 2: last check date */
								__( 'Version %1$s · last check %2$s', 'talkwyn-hub' ),
								'' !== (string) $twh_a['plugin_version'] ? (string) $twh_a['plugin_version'] : '?',
								Time::human( $twh_a['last_check_at'], __( 'never', 'talkwyn-hub' ) )
							)
						);
						?>
					</span>
				</div>
				<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Deactivate this site? Pro features will stop working there.', 'talkwyn-hub' ) ); ?>');">
					<input type="hidden" name="twh_action" value="deactivate_site">
					<input type="hidden" name="license_id" value="<?php echo (int) $license['id']; ?>">
					<input type="hidden" name="activation_id" value="<?php echo (int) $twh_a['id']; ?>">
					<?php wp_nonce_field( 'twh_deactivate_' . (int) $license['id'], '_twh_nonce' ); ?>
					<button type="submit" class="button twh-btn twh-btn--ghost twh-btn--sm">
						<?php esc_html_e( 'Deactivate', 'talkwyn-hub' ); ?><span class="screen-reader-text"> <?php echo esc_html( (string) $twh_a['domain_normalized'] ); ?></span>
					</button>
				</form>
			</li>
		<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php if ( $upgrades ) : ?>
	<section class="twh-section" aria-labelledby="twh-upgrade-title">
		<h2 id="twh-upgrade-title"><?php esc_html_e( 'Upgrade', 'talkwyn-hub' ); ?></h2>
		<p><?php esc_html_e( 'Move to a bigger plan and keep the same license key. You only pay the difference for the time left on your license.', 'talkwyn-hub' ); ?></p>
		<form method="post" class="twh-upgrade">
			<input type="hidden" name="twh_upgrade_license" value="<?php echo (int) $license['id']; ?>">
			<?php wp_nonce_field( 'twh_upgrade_' . (int) $license['id'], '_twh_nonce' ); ?>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Choose a plan', 'talkwyn-hub' ); ?></legend>
				<?php foreach ( $upgrades as $twh_i => $twh_u ) : ?>
					<label class="twh-upgrade-option">
						<input type="radio" name="twh_target_product" value="<?php echo (int) $twh_u['wc_product']->get_id(); ?>" <?php checked( 0, $twh_i ); ?>>
						<span class="twh-upgrade-option__name"><?php echo esc_html( LicenseService::plan_label( (string) $twh_u['mapping']['plan_slug'] ) ); ?></span>
						<span class="twh-upgrade-option__sites"><?php echo esc_html( sprintf( /* translators: %s: number of sites */ __( '%s sites', 'talkwyn-hub' ), LicenseService::limit_label( (int) $twh_u['mapping']['activation_limit'] ) ) ); ?></span>
						<span class="twh-upgrade-option__price"><?php echo wp_kses_post( wc_price( $twh_u['price'] ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p><button type="submit" class="button twh-btn"><?php esc_html_e( 'Upgrade now', 'talkwyn-hub' ); ?></button></p>
		</form>
	</section>
<?php endif; ?>
