<?php
/**
 * Single game. Games with an engine get the shared game shell; others show a "cooking" page.
 *
 * @package ClickMatKar
 */

get_header();
while ( have_posts() ) :
	the_post();
	$cmk_engine = cmk_game_engine( get_the_ID() );
	if ( 'none' !== $cmk_engine && locate_template( 'template-parts/game/' . $cmk_engine . '.php' ) ) {
		get_template_part( 'template-parts/game/' . $cmk_engine );
	} else {
		get_template_part( 'template-parts/game/cooking' );
	}
endwhile;
get_footer();
