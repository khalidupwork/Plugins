<?php
/**
 * My Account → Software downloads.
 *
 * Override by copying to yourtheme/talkwyn-hub/account/downloads.php.
 *
 * @package TalkwynHub
 * @var array<int, array<string, mixed>> $items
 */

use TWH\Support\Time;

defined( 'ABSPATH' ) || exit;

if ( empty( $items ) ) :
	?>
	<div class="twh-empty"><p><?php esc_html_e( 'Downloads appear here for every active license.', 'talkwyn-hub' ); ?></p></div>
	<?php
	return;
endif;
?>
<p class="twh-muted"><?php esc_html_e( 'Download links are valid for 10 minutes. Reload this page to get a fresh link. Updates also arrive automatically in your WordPress dashboard.', 'talkwyn-hub' ); ?></p>
<?php foreach ( $items as $twh_item ) : ?>
	<article class="twh-license-card twh-download">
		<header class="twh-license-card__head">
			<h3 class="twh-license-card__title"><?php echo esc_html( $twh_item['product'] ); ?><?php if ( '' !== $twh_item['version'] ) : ?> <span class="twh-plan"><?php echo esc_html( $twh_item['version'] ); ?></span><?php endif; ?></h3>
		</header>
		<div class="twh-actions">
			<a class="button twh-btn" href="<?php echo esc_url( $twh_item['url'] ); ?>"><?php esc_html_e( 'Download ZIP', 'talkwyn-hub' ); ?></a>
			<?php if ( '' !== $twh_item['date'] ) : ?>
				<span class="twh-muted"><?php echo esc_html( Time::human( $twh_item['date'] ) . ( $twh_item['size'] ? ' · ' . size_format( $twh_item['size'] ) : '' ) ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $twh_item['changelog'] ) : ?>
			<details class="twh-changelog-toggle">
				<summary><?php esc_html_e( 'What changed', 'talkwyn-hub' ); ?></summary>
				<div class="twh-changelog"><?php echo wp_kses_post( $twh_item['changelog'] ); ?></div>
			</details>
		<?php endif; ?>
	</article>
<?php endforeach; ?>
