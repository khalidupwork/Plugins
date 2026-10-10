<?php
/**
 * Site header.
 *
 * @package ClickMatKar
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text cmk-skip" href="#main"><?php esc_html_e( 'Skip to content', 'click-mat-kar' ); ?></a>

<header class="cmk-header" data-header>
	<div class="cmk-header__inner cmk-wrap">
		<?php cmk_site_logo(); ?>

		<nav class="cmk-nav" id="cmk-nav" aria-label="<?php esc_attr_e( 'Primary', 'click-mat-kar' ); ?>" data-nav>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'cmk-nav__list',
					'depth'          => 1,
					'fallback_cb'    => 'cmk_primary_menu_fallback',
				)
			);
			?>
			<a class="cmk-btn cmk-btn--lime cmk-nav__cta" href="<?php echo esc_url( cmk_first_game_url() ); ?>" data-track="cta_click" data-label="header">
				<?php esc_html_e( 'Make a bad decision', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
		</nav>

		<button class="cmk-burger" type="button" aria-controls="cmk-nav" aria-expanded="false" data-burger>
			<span class="cmk-burger__lines" aria-hidden="true"><span></span><span></span><span></span></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'click-mat-kar' ); ?></span>
		</button>
	</div>
</header>

<main id="main" class="cmk-main" tabindex="-1">
