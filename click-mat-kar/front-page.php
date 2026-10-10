<?php
/**
 * Homepage: the lobby for the games.
 *
 * @package ClickMatKar
 */

get_header();

$cmk_sections = array( 'hero', 'ticker', 'games', 'featured', 'chaos', 'how', 'lab', 'results', 'manifesto', 'challenge', 'final' );
foreach ( $cmk_sections as $cmk_section ) {
	get_template_part( 'template-parts/home/' . $cmk_section );
}

get_footer();
