<?php
/**
 * Site footer.
 *
 * @package ClickMatKar
 */
?>
</main>

<footer class="cmk-footer">
	<div class="cmk-wrap cmk-footer__grid">
		<div class="cmk-footer__brand">
			<a class="cmk-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php cmk_wordmark( 'sm' ); ?><span class="screen-reader-text">Click Mat Kar</span></a>
			<p><?php esc_html_e( 'Games, bad decisions and absolutely unnecessary internet stuff.', 'click-mat-kar' ); ?></p>
		</div>

		<nav class="cmk-footer__nav" aria-label="<?php esc_attr_e( 'Footer', 'click-mat-kar' ); ?>">
			<p class="cmk-footer__label"><?php esc_html_e( 'Explore', 'click-mat-kar' ); ?></p>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'cmk-footer__list',
					'depth'          => 1,
					'fallback_cb'    => 'cmk_primary_menu_fallback',
				)
			);
			?>
		</nav>

		<div class="cmk-footer__still cmk-card cmk-bg-lime">
			<p class="cmk-footer__still-title"><?php esc_html_e( 'Still here?', 'click-mat-kar' ); ?></p>
			<p><?php esc_html_e( "That's concerning. You should probably make one more bad decision.", 'click-mat-kar' ); ?></p>
			<a class="cmk-btn cmk-btn--ink cmk-btn--sm" href="<?php echo esc_url( cmk_first_game_url() ); ?>" data-track="cta_click" data-label="footer"><?php esc_html_e( 'One more', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		</div>
	</div>
	<div class="cmk-wrap cmk-footer__base">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Click Mat Kar. <?php esc_html_e( 'All money is fake. Judgement is real.', 'click-mat-kar' ); ?></p>
		<p><?php esc_html_e( 'Built for boredom. Not financial advice.', 'click-mat-kar' ); ?></p>
	</div>
</footer>

<div class="cmk-toast" role="status" aria-live="polite" data-toast></div>
<?php wp_footer(); ?>
</body>
</html>
