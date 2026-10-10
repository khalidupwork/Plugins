<?php
/**
 * Fallback for a game without a playable engine yet.
 *
 * @package ClickMatKar
 */

$cmk_emoji = get_post_meta( get_the_ID(), 'cmk_emoji', true );
?>
<section class="cmk-page cmk-wrap cmk-wrap--narrow cmk-center">
	<p class="cmk-cooking__emoji" aria-hidden="true"><?php echo esc_html( $cmk_emoji ? $cmk_emoji : '🧪' ); ?></p>
	<?php cmk_pill( __( 'Still cooking', 'click-mat-kar' ), 'pink' ); ?>
	<h1 class="cmk-h1"><?php the_title(); ?></h1>
	<?php if ( has_excerpt() ) : ?>
		<p class="cmk-lead cmk-narrow"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
	<div class="cmk-prose cmk-narrow"><?php the_content(); ?></div>
	<a class="cmk-btn cmk-btn--lime cmk-btn--lg" href="<?php echo esc_url( cmk_first_game_url() ); ?>"><?php esc_html_e( 'Play something ready', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
</section>
