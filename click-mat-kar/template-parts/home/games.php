<?php
/**
 * Choose a bad idea: the game grid.
 *
 * @package ClickMatKar
 */

$cmk_cards = array_slice( cmk_game_cards(), 0, 7 );
?>
<section class="cmk-section" id="games" aria-labelledby="cmk-games-title">
	<div class="cmk-wrap">
		<div class="cmk-section__head cmk-section__head--split">
			<div>
				<?php cmk_pill( __( 'Pick your poison', 'click-mat-kar' ) ); ?>
				<h2 id="cmk-games-title" class="cmk-h2"><?php esc_html_e( 'Choose a bad idea.', 'click-mat-kar' ); ?></h2>
			</div>
			<p class="cmk-scribble"><?php esc_html_e( 'Seven tiny ways to question your judgment. All of them are open. Mana kiya tha.', 'click-mat-kar' ); ?></p>
		</div>
		<div class="cmk-game-grid cmk-game-grid--home">
			<?php
			foreach ( $cmk_cards as $cmk_card ) {
				cmk_game_card( $cmk_card );
			}
			?>
		</div>
	</div>
</section>
