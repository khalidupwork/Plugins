<?php
/**
 * Chaos level: self-select session length.
 *
 * @package ClickMatKar
 */

$cmk_play  = cmk_first_game_url();
$cmk_cards = cmk_game_cards();
$cmk_quick = ! empty( $cmk_cards['red-flag-check']['playable'] ) ? $cmk_cards['red-flag-check']['url'] : $cmk_play;
$cmk_games = get_post_type_archive_link( 'cmk_game' );
$cmk_level = array(
	array( '1 min', __( 'Quick regret.', 'click-mat-kar' ), __( 'Eight questions. Zero filter.', 'click-mat-kar' ), 'blue', $cmk_quick, '⚡' ),
	array( '2 min', __( 'Just one game.', 'click-mat-kar' ), __( "That's what everybody says.", 'click-mat-kar' ), 'pink', $cmk_play, '🎯' ),
	array( '20+ min', __( 'I have time.', 'click-mat-kar' ), __( 'Concerning, but welcome.', 'click-mat-kar' ), 'lime', $cmk_games, '🫠' ),
);
?>
<section class="cmk-section" aria-labelledby="cmk-chaos-title">
	<div class="cmk-wrap cmk-chaos">
		<div class="cmk-chaos__intro">
			<?php cmk_pill( __( 'What are you feeling?', 'click-mat-kar' ) ); ?>
			<h2 id="cmk-chaos-title" class="cmk-h2"><?php esc_html_e( 'Choose your chaos level.', 'click-mat-kar' ); ?></h2>
			<p><?php esc_html_e( 'Not every bad decision needs a 20-minute commitment. Pick how much time you are willing to lose.', 'click-mat-kar' ); ?></p>
		</div>
		<div class="cmk-chaos__cards">
			<?php foreach ( $cmk_level as $cmk_l ) : ?>
				<a class="cmk-chaos-card cmk-bg-<?php echo esc_attr( $cmk_l[3] ); ?>" href="<?php echo esc_url( $cmk_l[4] ); ?>" data-track="cta_click" data-label="chaos_<?php echo esc_attr( sanitize_title( $cmk_l[0] ) ); ?>">
					<span class="cmk-chaos-card__time"><?php echo esc_html( $cmk_l[0] ); ?></span>
					<span class="cmk-chaos-card__emoji" aria-hidden="true"><?php echo esc_html( $cmk_l[5] ); ?></span>
					<span class="cmk-chaos-card__title"><?php echo esc_html( $cmk_l[1] ); ?></span>
					<span class="cmk-chaos-card__sub"><?php echo esc_html( $cmk_l[2] ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
