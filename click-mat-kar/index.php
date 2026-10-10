<?php
/**
 * Fallback template.
 *
 * @package ClickMatKar
 */

get_header();
?>
<section class="cmk-page cmk-wrap">
	<header class="cmk-page__head">
		<?php cmk_pill( __( 'Reading material', 'click-mat-kar' ) ); ?>
		<h1 class="cmk-h1"><?php echo is_home() ? esc_html__( 'Latest nonsense', 'click-mat-kar' ) : wp_kses_post( get_the_archive_title() ); ?></h1>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="cmk-post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'cmk-card cmk-post-item' ); ?>>
					<h2 class="cmk-h3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php the_excerpt(); ?>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing here. Even we are surprised.', 'click-mat-kar' ); ?></p>
	<?php endif; ?>
</section>
<?php
get_footer();
