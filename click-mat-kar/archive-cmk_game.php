<?php
/**
 * /games/ — the library of bad ideas.
 *
 * @package ClickMatKar
 */

get_header();
$cmk_cards    = cmk_game_cards();
$cmk_ready    = array_filter( $cmk_cards, function ( $c ) { return $c['playable']; } );
$cmk_cooking  = array_filter( $cmk_cards, function ( $c ) { return ! $c['playable']; } );
?>
<section class="cmk-library-hero">
	<div class="cmk-wrap">
		<?php cmk_pill( __( 'The library', 'click-mat-kar' ), 'pink' ); ?>
		<h1 class="cmk-display"><?php esc_html_e( 'All bad ideas.', 'click-mat-kar' ); ?></h1>
		<p class="cmk-lead"><?php esc_html_e( 'One engine, many ways to question your judgment. Pick one. Regret it. Share it.', 'click-mat-kar' ); ?></p>
		<?php if ( $cmk_cooking ) : ?>
		<div class="cmk-library-filter" role="toolbar" aria-label="<?php esc_attr_e( 'Filter games', 'click-mat-kar' ); ?>" data-lib-filter>
			<button type="button" class="cmk-filter is-on" data-lib="all" aria-pressed="true"><?php esc_html_e( 'Everything', 'click-mat-kar' ); ?></button>
			<button type="button" class="cmk-filter" data-lib="ready" aria-pressed="false">🎮 <?php esc_html_e( 'Play now', 'click-mat-kar' ); ?> <span class="cmk-filter__n"><?php echo esc_html( count( $cmk_ready ) ); ?></span></button>
			<button type="button" class="cmk-filter" data-lib="cooking" aria-pressed="false">🧪 <?php esc_html_e( 'Cooking', 'click-mat-kar' ); ?> <span class="cmk-filter__n"><?php echo esc_html( count( $cmk_cooking ) ); ?></span></button>
		</div>
		<?php else : ?>
		<p class="cmk-library-count"><b><?php echo esc_html( count( $cmk_ready ) ); ?></b> <?php esc_html_e( 'games. All playable. Zero signups.', 'click-mat-kar' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="cmk-section cmk-section--library">
	<div class="cmk-wrap">
		<div class="cmk-game-grid cmk-game-grid--home" data-lib-grid>
			<?php
			foreach ( array_merge( $cmk_ready, $cmk_cooking ) as $cmk_card ) {
				cmk_game_card( $cmk_card );
			}
			?>
		</div>
		<?php if ( $cmk_cooking ) : ?>
			<p class="cmk-library-note cmk-scribble"><?php esc_html_e( 'Cooking games are not live yet. We would rather build one great bad idea than six broken ones.', 'click-mat-kar' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
