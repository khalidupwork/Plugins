<?php
/**
 * More nonsense is cooking: upcoming experiences (never fake-live).
 *
 * @package ClickMatKar
 */

$cmk_upcoming = array_filter(
	cmk_game_cards(),
	function ( $card ) {
		return ! $card['playable'];
	}
);
if ( ! $cmk_upcoming ) {
	return;
}
?>
<section class="cmk-section" aria-labelledby="cmk-lab-title">
	<div class="cmk-wrap">
		<div class="cmk-section__head cmk-section__head--split">
			<div>
				<?php cmk_pill( __( 'In the lab', 'click-mat-kar' ), 'pink' ); ?>
				<h2 id="cmk-lab-title" class="cmk-h2"><?php esc_html_e( 'More nonsense is cooking.', 'click-mat-kar' ); ?></h2>
			</div>
			<p class="cmk-scribble"><?php esc_html_e( 'One engine. Lots of ridiculous ways to use it. These are the next experiences we are designing.', 'click-mat-kar' ); ?></p>
		</div>
		<div class="cmk-lab-grid">
			<?php
			foreach ( $cmk_upcoming as $cmk_card ) {
				cmk_game_card( $cmk_card, 'small' );
			}
			?>
		</div>
	</div>
</section>
