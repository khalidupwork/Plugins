<?php
/**
 * Featured game: Shop Like You're Rich, with a swipe-style demo.
 *
 * @package ClickMatKar
 */

$cmk_play = cmk_first_game_url();
?>
<section class="cmk-section cmk-section--tight" aria-labelledby="cmk-featured-title">
	<div class="cmk-wrap">
		<div class="cmk-featured cmk-card cmk-bg-lavender">
			<span class="cmk-featured__blob" aria-hidden="true"></span>
			<div class="cmk-featured__stage">
				<div class="cmk-phone cmk-phone--tilt" data-swipe>
					<div class="cmk-phone__notch" aria-hidden="true"></div>
					<div class="cmk-phone__screen">
						<div class="cmk-phone__bar"><span class="cmk-phone__brand">CLICK MAT KAR.</span><span class="cmk-phone__cart">🛒 <b data-swipe-count>0</b></span></div>
						<div class="cmk-swipe-card" data-swipe-card>
							<span class="cmk-swipe-card__emoji" data-swipe-emoji aria-hidden="true">👜</span>
							<span class="cmk-swipe-card__label"><?php esc_html_e( 'Holds almost nothing', 'click-mat-kar' ); ?></span>
							<span class="cmk-swipe-card__name" data-swipe-name>Gucchi Mini Bag (Holds 1 Mint)</span>
							<span class="cmk-swipe-card__price" data-swipe-price></span>
						</div>
						<div class="cmk-swipe-actions">
							<button type="button" class="cmk-btn cmk-btn--white cmk-btn--sm" data-swipe-nope><?php esc_html_e( 'Nope', 'click-mat-kar' ); ?></button>
							<button type="button" class="cmk-btn cmk-btn--lime cmk-btn--sm" data-swipe-add><?php esc_html_e( 'Add to cart', 'click-mat-kar' ); ?></button>
						</div>
					</div>
				</div>
				<span class="cmk-tag cmk-tag--resp cmk-bg-lime" aria-hidden="true"><?php esc_html_e( 'Financially responsible? Tell the cart.', 'click-mat-kar' ); ?></span>
			</div>
			<div class="cmk-featured__copy">
				<?php cmk_pill( __( 'First drop', 'click-mat-kar' ), 'white' ); ?>
				<h2 id="cmk-featured-title" class="cmk-h1 cmk-featured__title"><?php esc_html_e( 'You have', 'click-mat-kar' ); ?> <span class="cmk-mark" data-budget-label>Rs 10 Crore.</span></h2>
				<p class="cmk-lead"><?php esc_html_e( 'Terrible news: you have to spend it. Build the most unnecessary cart possible and see what your Financial IQ says about you.', 'click-mat-kar' ); ?></p>
				<ul class="cmk-chips cmk-chips--light">
					<li>✓ <?php esc_html_e( 'No real money', 'click-mat-kar' ); ?></li>
					<li>✓ <?php esc_html_e( 'No checkout', 'click-mat-kar' ); ?></li>
					<li>✓ <?php esc_html_e( 'Questionable taste', 'click-mat-kar' ); ?></li>
				</ul>
				<a class="cmk-btn cmk-btn--ink cmk-btn--lg" href="<?php echo esc_url( $cmk_play ); ?>" data-track="cta_click" data-label="featured"><?php esc_html_e( 'Start shopping', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			</div>
		</div>
	</div>
</section>
