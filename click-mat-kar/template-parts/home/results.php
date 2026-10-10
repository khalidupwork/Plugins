<?php
/**
 * Shareable results: explains the viral loop with an example card.
 *
 * @package ClickMatKar
 */

$cmk_play = cmk_first_game_url();
?>
<section class="cmk-section cmk-section--ink" id="results" aria-labelledby="cmk-results-title">
	<div class="cmk-wrap cmk-results">
		<div class="cmk-results__card-wrap">
			<div class="cmk-result-card cmk-result-card--demo" data-reveal>
				<span class="cmk-result-card__example"><?php esc_html_e( 'Example result', 'click-mat-kar' ); ?></span>
				<span class="cmk-result-card__logo">CLICK MAT KAR.</span>
				<span class="cmk-result-card__label"><?php esc_html_e( 'I spent', 'click-mat-kar' ); ?></span>
				<span class="cmk-result-card__big" data-money="87429200">Rs 8,74,29,200</span>
				<span class="cmk-result-card__sub"><?php esc_html_e( "on things I definitely don't need.", 'click-mat-kar' ); ?></span>
				<span class="cmk-result-card__items" aria-hidden="true">🛥️ 👟 ⌚ 🦒 👜</span>
				<span class="cmk-result-card__top"><?php esc_html_e( 'Worst buy: Emotional Support Giraffe', 'click-mat-kar' ); ?></span>
				<span class="cmk-result-card__iq"><?php esc_html_e( 'Financial IQ', 'click-mat-kar' ); ?> <b>3/100</b></span>
				<span class="cmk-result-card__foot"><?php esc_html_e( 'Beat me.', 'click-mat-kar' ); ?> clickmatkar.com</span>
				<span class="cmk-result-card__stamp"><?php esc_html_e( 'Regret', 'click-mat-kar' ); ?><br>97%</span>
			</div>
		</div>
		<div class="cmk-results__copy">
			<?php cmk_pill( __( 'Screenshot this', 'click-mat-kar' ) ); ?>
			<h2 id="cmk-results-title" class="cmk-h1"><?php esc_html_e( 'Bad decisions. Good screenshots.', 'click-mat-kar' ); ?></h2>
			<p class="cmk-lead"><?php esc_html_e( 'Every game ends with a vertical result card built for Stories, WhatsApp and challenge links. Your result becomes the invitation for the next player.', 'click-mat-kar' ); ?></p>
			<ul class="cmk-chips cmk-chips--dark">
				<li>↗ <?php esc_html_e( 'Stories', 'click-mat-kar' ); ?></li>
				<li>↗ WhatsApp</li>
				<li>↗ <?php esc_html_e( 'Challenge link', 'click-mat-kar' ); ?></li>
			</ul>
			<a class="cmk-btn cmk-btn--lime cmk-btn--lg" href="<?php echo esc_url( $cmk_play ); ?>" data-track="cta_click" data-label="results"><?php esc_html_e( 'Make a bad decision', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		</div>
	</div>
</section>
