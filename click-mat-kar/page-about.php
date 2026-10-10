<?php
/**
 * /about/ — WTF is this?
 *
 * @package ClickMatKar
 */

get_header();
while ( have_posts() ) :
	the_post();
	$cmk_rules = array(
		array( '🙅', __( 'No signup', 'click-mat-kar' ), __( 'Play first. We never ask who you are.', 'click-mat-kar' ), 'lime' ),
		array( '💸', __( 'No real money', 'click-mat-kar' ), __( 'Every rupee, dollar and yacht is fake.', 'click-mat-kar' ), 'pink' ),
		array( '📤', __( 'Made to share', 'click-mat-kar' ), __( 'Every game ends with a card for Stories and WhatsApp.', 'click-mat-kar' ), 'blue' ),
		array( '🤡', __( 'Zero seriousness', 'click-mat-kar' ), __( 'Not advice. Not productive. Just fun.', 'click-mat-kar' ), 'lavender' ),
	);
	?>
	<section class="cmk-about-hero">
		<div class="cmk-wrap">
			<?php cmk_pill( __( 'Good question', 'click-mat-kar' ) ); ?>
			<h1 class="cmk-display"><?php the_title(); ?></h1>
			<p class="cmk-lead"><?php esc_html_e( 'A global playground with Desi attitude. Fake shopping, ridiculous choices and results worth screenshotting.', 'click-mat-kar' ); ?> <span class="cmk-roman">Mana kiya tha.</span></p>
		</div>
	</section>
	<section class="cmk-section">
		<div class="cmk-wrap">
			<div class="cmk-about-grid">
				<?php foreach ( $cmk_rules as $cmk_rule ) : ?>
					<div class="cmk-card cmk-about-rule cmk-bg-<?php echo esc_attr( $cmk_rule[3] ); ?>" data-reveal>
						<span class="cmk-about-rule__emoji" aria-hidden="true"><?php echo esc_html( $cmk_rule[0] ); ?></span>
						<h2 class="cmk-h3"><?php echo esc_html( $cmk_rule[1] ); ?></h2>
						<p><?php echo esc_html( $cmk_rule[2] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="cmk-prose cmk-about-prose"><?php the_content(); ?></div>
			<a class="cmk-btn cmk-btn--lime cmk-btn--lg" href="<?php echo esc_url( cmk_first_game_url() ); ?>"><?php esc_html_e( 'Fine. Show me.', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		</div>
	</section>
	<?php
endwhile;
get_footer();
