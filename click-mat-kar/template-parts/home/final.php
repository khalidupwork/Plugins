<?php
/**
 * Final CTA.
 *
 * @package ClickMatKar
 */
?>
<section class="cmk-final" aria-labelledby="cmk-final-title">
	<div class="cmk-wrap cmk-center">
		<p class="cmk-final__kicker"><?php esc_html_e( 'You made it this far?', 'click-mat-kar' ); ?></p>
		<h2 id="cmk-final-title" class="cmk-display"><?php esc_html_e( "That's concerning.", 'click-mat-kar' ); ?></h2>
		<p class="cmk-lead"><?php esc_html_e( 'At this point, you may as well make one bad decision.', 'click-mat-kar' ); ?></p>
		<a class="cmk-btn cmk-btn--ink cmk-btn--lg cmk-nudge" href="<?php echo esc_url( cmk_first_game_url() ); ?>" data-track="cta_click" data-label="final"><?php esc_html_e( 'Pick your poison', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</div>
</section>
