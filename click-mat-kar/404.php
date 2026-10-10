<?php
/**
 * 404.
 *
 * @package ClickMatKar
 */

get_header();
?>
<section class="cmk-404 cmk-wrap">
	<p class="cmk-404__num" aria-hidden="true">4<span>0</span>4</p>
	<h1 class="cmk-h1"><?php esc_html_e( 'Mana kiya tha.', 'click-mat-kar' ); ?></h1>
	<p class="cmk-lead"><?php esc_html_e( 'This page does not exist. Which, honestly, is the most responsible thing on this site.', 'click-mat-kar' ); ?></p>
	<a class="cmk-btn cmk-btn--lime" href="<?php echo esc_url( cmk_first_game_url() ); ?>"><?php esc_html_e( 'Make a better bad decision', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
</section>
<?php
get_footer();
