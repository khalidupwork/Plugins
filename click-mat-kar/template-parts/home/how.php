<?php
/**
 * How it works.
 *
 * @package ClickMatKar
 */

$cmk_steps = array(
	array( '👆', __( 'Pick a game', 'click-mat-kar' ), __( 'Choose your preferred method of wasting time.', 'click-mat-kar' ), 'lavender' ),
	array( '🤡', __( 'Make bad decisions', 'click-mat-kar' ), __( 'Spend fake money. Pick chaos. There are no real consequences.', 'click-mat-kar' ), 'pink' ),
	array( '📤', __( 'Send the damage', 'click-mat-kar' ), __( 'Get a result card and challenge a friend to do worse.', 'click-mat-kar' ), 'lime' ),
);
?>
<section class="cmk-section cmk-section--paper2" id="how-it-works" aria-labelledby="cmk-how-title">
	<div class="cmk-wrap">
		<div class="cmk-section__head cmk-center">
			<?php cmk_pill( __( "It's suspiciously simple", 'click-mat-kar' ) ); ?>
			<h2 id="cmk-how-title" class="cmk-h2"><?php esc_html_e( 'How it works', 'click-mat-kar' ); ?></h2>
		</div>
		<ol class="cmk-steps">
			<?php foreach ( $cmk_steps as $cmk_n => $cmk_step ) : ?>
				<li class="cmk-step cmk-card" data-reveal>
					<span class="cmk-step__num"><?php echo esc_html( $cmk_n + 1 ); ?></span>
					<span class="cmk-step__icon cmk-bg-<?php echo esc_attr( $cmk_step[3] ); ?>" aria-hidden="true"><?php echo esc_html( $cmk_step[0] ); ?></span>
					<h3 class="cmk-h3"><?php echo esc_html( $cmk_step[1] ); ?></h3>
					<p><?php echo esc_html( $cmk_step[2] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
