<?php
/**
 * Can't decide? Spin the wheel of bad ideas — sends you to a random playable game.
 *
 * @package ClickMatKar
 */

$cmk_playable = array_values(
	array_filter(
		cmk_game_cards(),
		function ( $card ) {
			return $card['playable'];
		}
	)
);
if ( count( $cmk_playable ) < 2 ) {
	return;
}
?>
<section class="cmk-section cmk-spin-section" aria-labelledby="cmk-spin-title">
	<div class="cmk-wrap cmk-spin">
		<div class="cmk-spin__copy">
			<?php cmk_pill( __( "Can't decide?", 'click-mat-kar' ), 'pink' ); ?>
			<h2 id="cmk-spin-title" class="cmk-h2"><?php esc_html_e( 'Let fate pick your bad idea.', 'click-mat-kar' ); ?></h2>
			<p class="cmk-lead"><?php esc_html_e( 'Spin it. Whatever it lands on, you play. No take-backs. Phir se? Fine, spin again.', 'click-mat-kar' ); ?></p>
			<button type="button" class="cmk-btn cmk-btn--ink cmk-btn--lg" data-spin-go>🎰 <?php esc_html_e( 'Spin the wheel', 'click-mat-kar' ); ?></button>
		</div>
		<div class="cmk-spin__machine" data-spin>
			<div class="cmk-spin__window" aria-live="polite">
				<?php foreach ( $cmk_playable as $cmk_i => $cmk_card ) : ?>
					<a class="cmk-spin__slot cmk-bg-<?php echo esc_attr( $cmk_card['color'] ); ?><?php echo 0 === $cmk_i ? ' is-on' : ''; ?>" href="<?php echo esc_url( $cmk_card['url'] ); ?>" data-spin-slot data-game="<?php echo esc_attr( $cmk_card['slug'] ); ?>"<?php echo 0 === $cmk_i ? '' : ' tabindex="-1" aria-hidden="true"'; ?>>
						<span class="cmk-spin__emoji" aria-hidden="true"><?php echo esc_html( $cmk_card['emoji'] ); ?></span>
						<span class="cmk-spin__name"><?php echo esc_html( $cmk_card['title'] ); ?></span>
						<span class="cmk-spin__go"><?php esc_html_e( 'Play this', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</a>
				<?php endforeach; ?>
			</div>
			<span class="cmk-spin__lever" aria-hidden="true"></span>
		</div>
	</div>
</section>
