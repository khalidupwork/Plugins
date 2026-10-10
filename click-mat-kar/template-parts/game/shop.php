<?php
/**
 * Game shell: Shop Like You're Rich.
 * Products and logic live in assets/js/game-shop.js; this is the accessible skeleton.
 *
 * @package ClickMatKar
 */
?>
<div class="cmk-game" data-shop-game data-game-id="<?php echo esc_attr( get_post_field( 'post_name' ) ); ?>">

	<div class="cmk-challenge-banner" data-challenge-banner hidden>
		<div class="cmk-wrap">
			<span class="cmk-challenge-banner__emoji" aria-hidden="true">⚔️</span>
			<p><strong><?php esc_html_e( 'You have been challenged.', 'click-mat-kar' ); ?></strong> <span data-challenge-text></span></p>
		</div>
	</div>

	<header class="cmk-game__intro cmk-wrap">
		<?php cmk_pill( __( 'Fake money. Real temptation.', 'click-mat-kar' ) ); ?>
		<h1 class="cmk-h1"><?php the_title(); ?></h1>
		<p class="cmk-lead"><?php esc_html_e( 'You have', 'click-mat-kar' ); ?> <b data-budget-label>Rs 10 Crore.</b> <?php esc_html_e( 'Spend it on things nobody needs, then see what your Financial IQ says about you.', 'click-mat-kar' ); ?></p>
	</header>

	<div class="cmk-hud" data-hud>
		<div class="cmk-wrap cmk-hud__inner">
			<div class="cmk-hud__money">
				<span class="cmk-hud__label"><?php esc_html_e( 'Left to waste', 'click-mat-kar' ); ?></span>
				<span class="cmk-hud__left" data-left aria-live="polite">—</span>
				<span class="cmk-hud__bar" aria-hidden="true"><span data-bar></span></span>
			</div>
			<button type="button" class="cmk-btn cmk-btn--lime cmk-hud__cart" data-cart-open aria-controls="cmk-cart" aria-expanded="false">
				🛒 <span><?php esc_html_e( 'Cart', 'click-mat-kar' ); ?></span> <b class="cmk-hud__count" data-count>0</b>
			</button>
		</div>
	</div>

	<section class="cmk-shop cmk-wrap" aria-label="<?php esc_attr_e( 'Shop', 'click-mat-kar' ); ?>">
		<div class="cmk-shop__filters" role="toolbar" aria-label="<?php esc_attr_e( 'Categories', 'click-mat-kar' ); ?>" data-filters></div>
		<p class="cmk-shop__react" data-react aria-live="polite"><?php esc_html_e( 'Pick something. Anything. Preferably something stupid.', 'click-mat-kar' ); ?></p>
		<ul class="cmk-shop__grid" data-grid></ul>
		<noscript><p class="cmk-lead"><?php esc_html_e( 'This game needs JavaScript. Mana kiya tha.', 'click-mat-kar' ); ?></p></noscript>
	</section>

	<div class="cmk-finish-bar" data-finish-bar hidden>
		<div class="cmk-wrap cmk-finish-bar__inner">
			<p data-finish-text></p>
			<button type="button" class="cmk-btn cmk-btn--ink" data-finish><?php esc_html_e( 'Checkout (fake)', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</div>
	</div>

	<aside class="cmk-cart" id="cmk-cart" data-cart aria-label="<?php esc_attr_e( 'Your cart', 'click-mat-kar' ); ?>" hidden>
		<div class="cmk-cart__panel" role="dialog" aria-modal="true" aria-labelledby="cmk-cart-title" tabindex="-1">
			<div class="cmk-cart__head">
				<h2 id="cmk-cart-title" class="cmk-h3"><?php esc_html_e( 'Your terrible cart', 'click-mat-kar' ); ?></h2>
				<button type="button" class="cmk-cart__close" data-cart-close aria-label="<?php esc_attr_e( 'Close cart', 'click-mat-kar' ); ?>">✕</button>
			</div>
			<ul class="cmk-cart__list" data-cart-list></ul>
			<p class="cmk-cart__empty" data-cart-empty><?php esc_html_e( 'Empty. Suspiciously responsible.', 'click-mat-kar' ); ?></p>
			<div class="cmk-cart__foot">
				<p class="cmk-cart__total"><span><?php esc_html_e( 'Damage', 'click-mat-kar' ); ?></span> <b data-cart-total>—</b></p>
				<button type="button" class="cmk-btn cmk-btn--lime cmk-btn--lg" data-finish><?php esc_html_e( 'Checkout (fake)', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</div>
		</div>
		<div class="cmk-cart__scrim" data-cart-close></div>
	</aside>
</div>
