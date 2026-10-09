<?php
/**
 * Dashboard overview.
 *
 * Override by copying to yourtheme/talkwyn-hub/account/overview.php.
 *
 * @package TalkwynHub
 * @var array<string, mixed> $twh_o App::overview() data.
 */

use TWH\Account\Account;
use TWH\Account\App;

defined( 'ABSPATH' ) || exit;

$twh_user    = $twh_o['user'];
$twh_first   = '' !== $twh_user->first_name ? $twh_user->first_name : $twh_user->display_name;
$twh_has_pro = ! empty( $twh_o['active'] );
$twh_plan    = App::plan_card( $twh_o['main'] );
$twh_feat    = $twh_o['features'];
$twh_limit   = (int) $twh_o['limit'];
$twh_used    = (int) $twh_o['used'];
$twh_pct     = $twh_limit > 0 ? min( 100, (int) round( 100 * $twh_used / $twh_limit ) ) : ( $twh_has_pro ? 8 : 0 );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$twh_started = isset( $_GET['twh_trial'] ) && 'started' === sanitize_key( wp_unslash( $_GET['twh_trial'] ) );
?>
<div class="twh-ov">
	<div class="twh-ov__hello">
		<h2><?php echo esc_html( sprintf( /* translators: %s: first name */ __( 'Welcome back, %s', 'talkwyn-hub' ), $twh_first ) ); ?></h2>
		<p><?php esc_html_e( 'Here\'s what\'s happening across your sites today.', 'talkwyn-hub' ); ?></p>
	</div>

	<?php if ( $twh_started ) : ?>
		<div class="twh-ov__notice" role="status"><?php esc_html_e( 'Your Pro trial has started. Your key is below under Licenses and in your email.', 'talkwyn-hub' ); ?></div>
	<?php endif; ?>

	<section class="twh-ov__banner">
		<span class="twh-ov__banner-icon" aria-hidden="true"><img src="<?php echo esc_url( TWH_URL . 'assets/img/talkwyn-mark.svg' ); ?>" alt="" width="28" height="28"></span>
		<div class="twh-ov__banner-text">
			<strong><?php esc_html_e( 'Download the Talkwyn plugin', 'talkwyn-hub' ); ?></strong>
			<span><?php echo esc_html( $twh_has_pro ? __( 'Install Talkwyn and Talkwyn Pro on your site, then paste your license key under Talkwyn, License.', 'talkwyn-hub' ) : __( 'Install it on any WordPress site. It is free, and Pro turns on with a license key.', 'talkwyn-hub' ) ); ?></span>
		</div>
		<div class="twh-ov__banner-actions">
			<?php if ( '' !== $twh_o['pro_download'] ) : ?>
				<a class="twh-app__btn twh-app__btn--light" href="<?php echo esc_url( $twh_o['pro_download'] ); ?>"><?php echo App::icon( 'download', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Talkwyn Pro .zip', 'talkwyn-hub' ); ?></a>
			<?php endif; ?>
			<a class="twh-app__btn twh-app__btn--red twh-app__btn--lg" href="<?php echo esc_url( $twh_o['free_url'] ); ?>"><?php echo App::icon( 'download', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Download .zip', 'talkwyn-hub' ); ?></a>
		</div>
	</section>

	<div class="twh-ov__stats">
		<div class="twh-ov__stat">
			<span class="twh-ov__stat-icon"><?php echo App::icon( 'sliders', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<strong><?php echo esc_html( number_format_i18n( $twh_has_pro ? count( $twh_feat ) : 0 ) ); ?></strong>
			<span><?php esc_html_e( 'Pro features included', 'talkwyn-hub' ); ?></span>
		</div>
		<div class="twh-ov__stat">
			<span class="twh-ov__stat-icon"><?php echo App::icon( 'share', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<strong><?php echo esc_html( number_format_i18n( $twh_used ) ); ?><small>/<?php echo esc_html( $twh_limit > 0 ? number_format_i18n( $twh_limit ) : ( $twh_has_pro ? '∞' : '0' ) ); ?></small></strong>
			<span><?php esc_html_e( 'Connected sites', 'talkwyn-hub' ); ?></span>
		</div>
		<div class="twh-ov__stat">
			<span class="twh-ov__stat-icon twh-ov__stat-icon--green"><?php echo App::icon( 'shield', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<strong><?php echo esc_html( number_format_i18n( count( $twh_o['licenses'] ) ) ); ?></strong>
			<span><?php esc_html_e( 'Licenses', 'talkwyn-hub' ); ?></span>
		</div>
	</div>

	<div class="twh-ov__grid">
		<section class="twh-ov__card twh-ov__features">
			<header class="twh-ov__card-head">
				<h3><?php echo esc_html( $twh_has_pro ? __( 'Features in your plan', 'talkwyn-hub' ) : __( 'Unlock with Pro', 'talkwyn-hub' ) ); ?></h3>
				<a href="<?php echo esc_url( $twh_has_pro ? wc_get_account_endpoint_url( Account::EP_LICENSES ) : home_url( '/pricing/' ) ); ?>"><?php esc_html_e( 'View all', 'talkwyn-hub' ); ?> <?php echo App::icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
			</header>
			<ul>
				<?php foreach ( array_slice( $twh_feat, 0, 5 ) as $twh_f ) : ?>
					<li>
						<span class="twh-ov__tile twh-ov__tile--<?php echo esc_attr( $twh_f[3] ); ?>"><?php echo App::icon( $twh_f[2], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<span class="twh-ov__fname"><strong><?php echo esc_html( $twh_f[0] ); ?></strong><span><?php echo esc_html( $twh_f[1] ); ?></span></span>
						<span class="twh-ov__pill<?php echo $twh_has_pro ? '' : ' twh-ov__pill--pro'; ?>"><?php echo esc_html( $twh_has_pro ? __( 'Included', 'talkwyn-hub' ) : __( 'Pro', 'talkwyn-hub' ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! $twh_has_pro ) : ?>
				<p class="twh-ov__cta"><a class="twh-app__btn twh-app__btn--red" href="<?php echo esc_url( home_url( '/pricing/#trial' ) ); ?>"><?php esc_html_e( 'Start free 15-day trial', 'talkwyn-hub' ); ?></a></p>
			<?php endif; ?>
		</section>

		<div class="twh-ov__side">
			<section class="twh-ov__card twh-ov__plan">
				<div class="twh-ov__plan-head">
					<div>
						<span class="twh-ov__muted"><?php esc_html_e( 'Current plan', 'talkwyn-hub' ); ?></span>
						<strong class="twh-ov__plan-name"><?php echo esc_html( $twh_plan['plan'] ); ?></strong>
					</div>
					<span class="twh-ov__pill twh-ov__pill--<?php echo esc_attr( $twh_plan['tone'] ); ?>"><?php echo esc_html( $twh_plan['badge'] ); ?></span>
				</div>
				<span class="twh-ov__muted"><?php esc_html_e( 'Site slots', 'talkwyn-hub' ); ?></span>
				<div class="twh-ov__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $twh_pct; ?>"><span style="width:<?php echo (int) $twh_pct; ?>%"></span></div>
				<div class="twh-ov__row">
					<span><?php echo esc_html( $twh_limit > 0 ? sprintf( /* translators: 1: used, 2: limit */ __( '%1$d of %2$d used', 'talkwyn-hub' ), $twh_used, $twh_limit ) : sprintf( /* translators: %d: used */ __( '%d used', 'talkwyn-hub' ), $twh_used ) ); ?></span>
					<span><?php echo esc_html( $twh_limit > 0 ? sprintf( /* translators: %d: free slots */ __( '%d available', 'talkwyn-hub' ), max( 0, $twh_limit - $twh_used ) ) : ( $twh_has_pro ? __( 'Unlimited', 'talkwyn-hub' ) : '' ) ); ?></span>
				</div>
				<div class="twh-ov__row twh-ov__row--line">
					<span><?php echo esc_html( '' !== $twh_plan['ends'] ? $twh_plan['ends'] : __( 'No Pro license yet', 'talkwyn-hub' ) ); ?></span>
					<a href="<?php echo esc_url( $twh_plan['manage'] ); ?>"><?php echo esc_html( $twh_o['main'] ? __( 'Manage', 'talkwyn-hub' ) : __( 'See plans', 'talkwyn-hub' ) ); ?></a>
				</div>
			</section>

			<section class="twh-ov__card twh-ov__sites">
				<header class="twh-ov__card-head">
					<h3><?php esc_html_e( 'Your sites', 'talkwyn-hub' ); ?></h3>
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( Account::EP_LICENSES ) ); ?>"><?php esc_html_e( 'View all', 'talkwyn-hub' ); ?> <?php echo App::icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
				</header>
				<?php if ( ! $twh_o['sites'] ) : ?>
					<p class="twh-ov__empty"><?php esc_html_e( 'No sites yet. Paste your license key in Talkwyn on your site and it shows up here.', 'talkwyn-hub' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( array_slice( $twh_o['sites'], 0, 4 ) as $twh_s ) : ?>
							<?php $twh_d = (string) $twh_s['domain_normalized']; ?>
							<li>
								<span class="twh-ov__site-av" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $twh_d, 0, 2 ) ) ); ?></span>
								<span class="twh-ov__fname"><strong><?php echo esc_html( $twh_d ); ?></strong><span><?php echo esc_html( App::last_seen( $twh_s ) ); ?></span></span>
								<?php if ( ! empty( $twh_s['is_dev_site'] ) ) : ?>
									<span class="twh-ov__pill twh-ov__pill--amber"><?php esc_html_e( 'Dev', 'talkwyn-hub' ); ?></span>
								<?php else : ?>
									<span class="twh-ov__pill twh-ov__pill--green"><?php esc_html_e( 'Live', 'talkwyn-hub' ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>
	</div>
</div>
