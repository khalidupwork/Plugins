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

if ( empty( $items ) ) : ?>
	<div class="woocommerce-info"><?php esc_html_e( 'Downloads appear here for every active license.', 'talkwyn-hub' ); ?></div>
	<?php
	return;
endif;
?>
<p class="twh-muted"><?php esc_html_e( 'Download links are valid for 10 minutes. Reload this page to get a fresh link. Updates are also delivered automatically in your WordPress dashboard.', 'talkwyn-hub' ); ?></p>
<?php foreach ( $items as $twh_item ) : ?>
	<div class="twh-card twh-download">
		<h3><?php echo esc_html( $twh_item['product'] ); ?> <small><?php echo esc_html( $twh_item['version'] ); ?></small></h3>
		<p>
			<a class="button alt" href="<?php echo esc_url( $twh_item['url'] ); ?>"><?php esc_html_e( 'Download ZIP', 'talkwyn-hub' ); ?></a>
			<span class="twh-muted"><?php echo esc_html( Time::human( $twh_item['date'] ) . ( $twh_item['size'] ? ' · ' . size_format( $twh_item['size'] ) : '' ) ); ?></span>
		</p>
		<?php if ( '' !== $twh_item['changelog'] ) : ?>
			<details>
				<summary><?php esc_html_e( 'Changelog', 'talkwyn-hub' ); ?></summary>
				<div class="twh-changelog"><?php echo wp_kses_post( $twh_item['changelog'] ); ?></div>
			</details>
		<?php endif; ?>
	</div>
<?php endforeach; ?>
