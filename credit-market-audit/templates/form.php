<?php
/**
 * Audit form template.
 *
 * Override by copying to yourtheme/credit-market-audit/form.php.
 *
 * Available: $args (see CMA_Frontend::defaults() plus `id`), $accent, $on_accent.
 *
 * @package CreditMarketAudit
 */

defined( 'ABSPATH' ) || exit;

$id = $args['id'];
?>
<div id="<?php echo esc_attr( $id ); ?>" class="cma-audit cma-layout-<?php echo esc_attr( $args['layout'] ); ?> <?php echo esc_attr( $args['class'] ); ?>" style="--cma-brand: <?php echo esc_attr( $accent ); ?>; --cma-on-brand: <?php echo esc_attr( $on_accent ); ?>;">
	<div class="cma-form-wrap">
		<?php if ( '' !== $args['heading'] ) : ?>
			<<?php echo tag_escape( $args['heading_tag'] ); ?> class="cma-heading"><?php echo esc_html( $args['heading'] ); ?></<?php echo tag_escape( $args['heading_tag'] ); ?>>
		<?php endif; ?>
		<?php if ( '' !== $args['subheading'] ) : ?>
			<p class="cma-subheading"><?php echo esc_html( $args['subheading'] ); ?></p>
		<?php endif; ?>

		<form class="cma-form" novalidate>
			<div class="cma-fields">
				<?php if ( $args['show_name'] ) : ?>
					<div class="cma-field cma-field--name">
						<label class="screen-reader-text cma-sr" for="<?php echo esc_attr( $id ); ?>-name"><?php esc_html_e( 'Name', 'credit-market-audit' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $id ); ?>-name" name="name" autocomplete="name" placeholder="<?php echo esc_attr( $args['name_placeholder'] ); ?>">
					</div>
				<?php endif; ?>
				<div class="cma-field cma-field--email">
					<label class="screen-reader-text cma-sr" for="<?php echo esc_attr( $id ); ?>-email"><?php esc_html_e( 'Email', 'credit-market-audit' ); ?></label>
					<input type="email" id="<?php echo esc_attr( $id ); ?>-email" name="email" autocomplete="email" required placeholder="<?php echo esc_attr( $args['email_placeholder'] ); ?>">
				</div>
				<div class="cma-field cma-field--url">
					<label class="screen-reader-text cma-sr" for="<?php echo esc_attr( $id ); ?>-url"><?php esc_html_e( 'Website URL', 'credit-market-audit' ); ?></label>
					<input type="text" inputmode="url" id="<?php echo esc_attr( $id ); ?>-url" name="url" autocomplete="url" required placeholder="<?php echo esc_attr( $args['url_placeholder'] ); ?>">
				</div>
				<div class="cma-field cma-field--submit">
					<button type="submit" class="cma-btn"><span class="cma-btn__text"><?php echo esc_html( $args['button_text'] ); ?></span></button>
				</div>
			</div>

			<?php if ( $args['show_consent'] ) : ?>
				<input type="hidden" name="consent_shown" value="1">
				<label class="cma-consent">
					<input type="checkbox" name="consent" value="1" required>
					<span><?php echo wp_kses_post( $args['consent_text'] ); ?></span>
				</label>
			<?php endif; ?>

			<div class="cma-hp" aria-hidden="true">
				<label><?php esc_html_e( 'Leave this field empty', 'credit-market-audit' ); ?> <input type="text" name="cma_hp" tabindex="-1" autocomplete="off"></label>
			</div>

			<div class="cma-error" role="alert" hidden></div>
		</form>

		<div class="cma-progress" hidden aria-live="polite">
			<div class="cma-progress__bar"><span></span></div>
			<div class="cma-progress__meta">
				<span class="cma-progress__text"></span>
				<span class="cma-progress__pct">0%</span>
			</div>
			<ul class="cma-progress__steps">
				<li data-step="start"><?php esc_html_e( 'SEO & design check', 'credit-market-audit' ); ?></li>
				<li data-step="mobile"><?php esc_html_e( 'PageSpeed – mobile', 'credit-market-audit' ); ?></li>
				<li data-step="desktop"><?php esc_html_e( 'PageSpeed – desktop', 'credit-market-audit' ); ?></li>
				<li data-step="finalize"><?php esc_html_e( 'Report & email', 'credit-market-audit' ); ?></li>
			</ul>
		</div>
	</div>

	<div class="cma-result" hidden>
		<div class="cma-result__bar">
			<div class="cma-result__msg"></div>
			<div class="cma-result__actions"></div>
		</div>
		<div class="cma-result__report"></div>
	</div>
</div>
