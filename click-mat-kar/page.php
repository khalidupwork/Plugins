<?php
/**
 * Default page.
 *
 * @package ClickMatKar
 */

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'cmk-page cmk-wrap cmk-wrap--narrow' ); ?>>
		<header class="cmk-page__head">
			<?php cmk_pill( __( 'The fine print', 'click-mat-kar' ), 'lavender' ); ?>
			<h1 class="cmk-h1"><?php the_title(); ?></h1>
		</header>
		<div class="cmk-prose"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;
get_footer();
