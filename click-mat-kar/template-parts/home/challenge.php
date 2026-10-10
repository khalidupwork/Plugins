<?php
/**
 * Friend challenge.
 *
 * @package ClickMatKar
 */

$cmk_play = cmk_first_game_url();
?>
<section class="cmk-section cmk-section--ink" aria-labelledby="cmk-challenge-title">
	<div class="cmk-wrap cmk-challenge">
		<div class="cmk-challenge__copy">
			<?php cmk_pill( __( 'Bring a victim', 'click-mat-kar' ) ); ?>
			<h2 id="cmk-challenge-title" class="cmk-h1"><?php esc_html_e( "Don't click alone.", 'click-mat-kar' ); ?></h2>
			<p class="cmk-lead"><?php esc_html_e( 'Finish a game, send the result, and challenge somebody to beat your score, spend more, or make an even worse decision.', 'click-mat-kar' ); ?></p>
			<a class="cmk-btn cmk-btn--lime cmk-btn--lg" href="<?php echo esc_url( $cmk_play ); ?>" data-track="cta_click" data-label="challenge"><?php esc_html_e( 'Challenge a friend', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		</div>
		<div class="cmk-challenge__stage cmk-card cmk-bg-lavender" aria-hidden="true">
			<span class="cmk-bubble cmk-bubble--a cmk-bg-white"><?php esc_html_e( 'I got 7/100 😭', 'click-mat-kar' ); ?></span>
			<span class="cmk-bubble cmk-bubble--b cmk-bg-lime"><?php esc_html_e( 'Hold my chai', 'click-mat-kar' ); ?></span>
			<span class="cmk-bubble cmk-bubble--c cmk-bg-pink"><?php esc_html_e( 'Why did you buy a camel?', 'click-mat-kar' ); ?></span>
			<div class="cmk-phone cmk-phone--mini">
				<div class="cmk-phone__notch"></div>
				<div class="cmk-phone__screen cmk-phone__screen--center">
					<span class="cmk-phone__score-label"><?php esc_html_e( 'Financial IQ', 'click-mat-kar' ); ?></span>
					<span class="cmk-phone__score">7<small>/100</small></span>
					<span class="cmk-phone__score-sub"><?php esc_html_e( 'send help', 'click-mat-kar' ); ?></span>
				</div>
			</div>
		</div>
	</div>
</section>
