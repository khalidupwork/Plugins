<?php
/**
 * My Account → Licenses list.
 *
 * Override by copying to yourtheme/talkwyn-hub/account/licenses.php.
 *
 * @package TalkwynHub
 * @var array<int, array<string, mixed>> $rows
 */

use TWH\Account\Account;
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
		<div class="twh-claim__row">
			<label class="screen-reader-text" for="twh-claim-key"><?php esc_html_e( 'License key', 'talkwyn-hub' ); ?></label>
			<input id="twh-claim-key" name="license_key" type="text" class="input-text" placeholder="TALK-XXXX-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" required>
			<button type="submit" class="button twh-btn twh-btn--ghost"><?php esc_html_e( 'Add license', 'talkwyn-hub' ); ?></button>
		</div>
	</form>
	<?php
};

if ( empty( $rows ) ) :
	?>
	<div class="twh-empty">
		<p class="twh-empty__title"><?php esc_html_e( 'You have no licenses yet.', 'talkwyn-hub' ); ?></p>
		<p><?php esc_html_e( 'Talkwyn Pro adds paid AI models, analytics, and more sites.', 'talkwyn-hub' ); ?></p>
		<a class="button twh-btn" href="<?php echo esc_url( apply_filters( 'twh_shop_url', home_url( '/pricing/' ) ) ); ?>"><?php esc_html_e( 'See plans', 'talkwyn-hub' ); ?></a>
	</div>
	<?php
	$twh_claim_form();
	return;
endif;
?>
<div class="twh-license-list">
<?php foreach ( $rows as $row ) : ?>
	<?php
	$license = $row['license'];
	$badge   = Account::badge( $license );
	$masked  = KeyGenerator::mask( (string) $license['key_last4'] );
	?>
	<article class="twh-license-card">
		<header class="twh-license-card__head">
			<div>
				<h3 class="twh-license-card__title"><?php echo esc_html( $row['product'] ); ?> <span class="twh-plan"><?php echo esc_html( LicenseService::plan_label( (string) $license['plan_slug'] ) ); ?></span></h3>
				<span class="twh-badge twh-badge--<?php echo esc_attr( $badge[0] ); ?>"><?php echo esc_html( $badge[1] ); ?></span>
			</div>
		</header>
		<div class="twh-key" data-license="<?php echo (int) $license['id']; ?>" data-masked="<?php echo esc_attr( $masked ); ?>">
			<code class="twh-key-value"><?php echo esc_html( $masked ); ?></code>
			<button type="button" class="twh-copy-btn twh-reveal"><?php esc_html_e( 'Reveal', 'talkwyn-hub' ); ?></button>
			<button type="button" class="twh-copy-btn twh-copy"><?php esc_html_e( 'Copy', 'talkwyn-hub' ); ?></button>
		</div>
		<dl class="twh-meta">
			<div><dt><?php esc_html_e( 'Expires', 'talkwyn-hub' ); ?></dt><dd><?php echo esc_html( Time::human( $license['expires_at'], __( 'Never', 'talkwyn-hub' ) ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Sites', 'talkwyn-hub' ); ?></dt><dd><?php echo esc_html( $row['sites_used'] . ' / ' . LicenseService::limit_label( (int) $license['activation_limit'] ) ); ?></dd></div>
		</dl>
		<div class="twh-actions">
			<?php if ( $row['renew_url'] ) : ?>
				<a class="button twh-btn" href="<?php echo esc_url( $row['renew_url'] ); ?>"><?php esc_html_e( 'Renew', 'talkwyn-hub' ); ?></a>
			<?php endif; ?>
			<a class="button twh-btn twh-btn--ghost" href="<?php echo esc_url( $row['detail_url'] ); ?>"><?php esc_html_e( 'Manage sites and upgrades', 'talkwyn-hub' ); ?></a>
		</div>
	</article>
<?php endforeach; ?>
</div>
<?php $twh_claim_form(); ?>
