<?php
/**
 * Hero: wordmark + a phone you can actually play with.
 *
 * @package ClickMatKar
 */

$cmk_play = cmk_first_game_url();
?>
<section class="cmk-hero" aria-labelledby="cmk-hero-title">
	<div class="cmk-hero__stickers" aria-hidden="true">
		<span class="cmk-sticker cmk-sticker--a">💸</span>
		<span class="cmk-sticker cmk-sticker--b">👀</span>
		<span class="cmk-sticker cmk-sticker--c">⭐</span>
		<span class="cmk-sticker cmk-sticker--d">!?</span>
	</div>

	<div class="cmk-wrap cmk-hero__grid">
		<div class="cmk-hero__copy">
			<?php cmk_pill( __( 'Warning: fun ahead', 'click-mat-kar' ) ); ?>
			<h1 id="cmk-hero-title" class="cmk-hero__title">
				<span class="screen-reader-text">Click Mat Kar.</span>
				<?php cmk_wordmark( 'xl' ); ?>
			</h1>
			<p class="cmk-hero__lead"><?php esc_html_e( 'Seriously. There are better ways to waste your time.', 'click-mat-kar' ); ?> <span class="cmk-roman">Mana kiya tha.</span></p>
			<div class="cmk-hero__actions">
				<a class="cmk-btn cmk-btn--lime cmk-btn--lg cmk-nudge" href="<?php echo esc_url( $cmk_play ); ?>" data-track="cta_click" data-label="hero">
					<?php esc_html_e( "I'm clicking anyway", 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</a>
				<a class="cmk-btn cmk-btn--ghost-dark" href="#games"><?php esc_html_e( 'See all bad ideas', 'click-mat-kar' ); ?></a>
			</div>
			<ul class="cmk-chips" aria-label="<?php esc_attr_e( 'The deal', 'click-mat-kar' ); ?>">
				<li>🛍️ <?php esc_html_e( 'Fake shopping', 'click-mat-kar' ); ?></li>
				<li>💵 <?php esc_html_e( 'Fake money', 'click-mat-kar' ); ?></li>
				<li>🤡 <?php esc_html_e( 'Real bad choices', 'click-mat-kar' ); ?></li>
			</ul>
			<p class="cmk-hero__fine"><?php esc_html_e( 'No signup. No money. No regrets (some regrets).', 'click-mat-kar' ); ?></p>
		</div>

		<div class="cmk-hero__stage">
			<span class="cmk-tag cmk-tag--poke cmk-bg-lime" aria-hidden="true"><?php esc_html_e( 'Poke the shop 👇', 'click-mat-kar' ); ?></span>
			<span class="cmk-tag cmk-tag--bad cmk-bg-pink" aria-hidden="true"><?php esc_html_e( 'Bad idea', 'click-mat-kar' ); ?></span>
			<div class="cmk-phone" data-mini-shop data-budget-usd="972000">
				<div class="cmk-phone__notch" aria-hidden="true"></div>
				<div class="cmk-phone__screen">
					<div class="cmk-phone__bar">
						<span class="cmk-phone__brand">CLICK MAT KAR.</span>
						<span class="cmk-phone__cart" aria-label="<?php esc_attr_e( 'Items in cart', 'click-mat-kar' ); ?>">🛒 <b data-mini-count>0</b></span>
					</div>
					<p class="cmk-phone__budget"><span><?php esc_html_e( 'Left to waste', 'click-mat-kar' ); ?></span> <b data-mini-budget>Rs 9,72,00,000</b></p>
					<ul class="cmk-phone__grid">
						<?php
						$cmk_mini = array(
							array( '👟', 'Adibas', 1200 ),
							array( '👜', 'Gucchi', 3200 ),
							array( '⌚', 'Rolax', 45000 ),
							array( '🏎️', 'Lambo-Ghanta', 260000 ),
						);
						foreach ( $cmk_mini as $cmk_item ) :
							?>
							<li class="cmk-mini-item">
								<span class="cmk-mini-item__emoji" aria-hidden="true"><?php echo esc_html( $cmk_item[0] ); ?></span>
								<span class="cmk-mini-item__name"><?php echo esc_html( $cmk_item[1] ); ?></span>
								<span class="cmk-mini-item__price" data-usd="<?php echo esc_attr( $cmk_item[2] ); ?>"></span>
								<button type="button" class="cmk-mini-item__add" data-mini-add data-usd="<?php echo esc_attr( $cmk_item[2] ); ?>" data-name="<?php echo esc_attr( $cmk_item[1] ); ?>"><?php esc_html_e( 'Add', 'click-mat-kar' ); ?></button>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="cmk-phone__react" data-mini-react aria-live="polite"><?php esc_html_e( 'Go on. Tap something expensive.', 'click-mat-kar' ); ?></p>
					<a class="cmk-phone__go" href="<?php echo esc_url( $cmk_play ); ?>" data-track="cta_click" data-label="hero_phone"><?php esc_html_e( 'Play the full game', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				</div>
			</div>
			<span class="cmk-tag cmk-tag--real cmk-bg-lime" aria-hidden="true"><?php esc_html_e( '0 real money', 'click-mat-kar' ); ?></span>
		</div>
	</div>
</section>
