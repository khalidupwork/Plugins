<?php
/**
 * /result/ — shareable outcome. Everything is rendered client-side from the ?r= payload,
 * so results need no database and no login.
 *
 * @package ClickMatKar
 */

get_header();
?>
<section class="cmk-result-page" data-result-page>
	<div class="cmk-wrap cmk-result-page__grid">

		<div class="cmk-result-page__card">
			<div class="cmk-result-card cmk-result-card--live" data-result-card hidden>
				<span class="cmk-result-card__logo">CLICK MAT KAR.</span>
				<span class="cmk-result-card__game" data-r-game></span>
				<span class="cmk-result-card__label" data-r-label><?php esc_html_e( 'I spent', 'click-mat-kar' ); ?></span>
				<span class="cmk-result-card__big" data-r-spent></span>
				<span class="cmk-result-card__sub" data-r-sub></span>
				<span class="cmk-result-card__cmp" data-r-cmp></span>
				<span class="cmk-result-card__items" data-r-items aria-hidden="true"></span>
				<span class="cmk-result-card__top" data-r-top></span>
				<span class="cmk-result-card__iq"><span data-r-score-label></span> <b data-r-iq></b></span>
				<span class="cmk-result-card__foot"><?php esc_html_e( 'Beat me.', 'click-mat-kar' ); ?> clickmatkar.com</span>
				<span class="cmk-result-card__stamp" data-r-stamp></span>
			</div>
		</div>

		<div class="cmk-result-page__copy" data-result-copy hidden>
			<?php cmk_pill( __( 'The damage report', 'click-mat-kar' ) ); ?>
			<h1 class="cmk-h1" data-r-title></h1>
			<p class="cmk-lead" data-r-verdict></p>

			<div class="cmk-share" data-share-owner>
				<p class="cmk-share__label"><?php esc_html_e( 'Send the damage to a friend', 'click-mat-kar' ); ?></p>
				<div class="cmk-share__row">
					<button type="button" class="cmk-btn cmk-btn--lime" data-share="native" hidden>📤 <?php esc_html_e( 'Share', 'click-mat-kar' ); ?></button>
					<a class="cmk-btn cmk-btn--mint" data-share="whatsapp" target="_blank" rel="noopener">💬 WhatsApp</a>
					<a class="cmk-btn cmk-btn--white" data-share="x" target="_blank" rel="noopener">𝕏 Post</a>
					<button type="button" class="cmk-btn cmk-btn--white" data-share="copy">🔗 <?php esc_html_e( 'Copy link', 'click-mat-kar' ); ?></button>
					<button type="button" class="cmk-btn cmk-btn--white" data-share="download">⬇️ <?php esc_html_e( 'Story card', 'click-mat-kar' ); ?></button>
				</div>
				<button type="button" class="cmk-btn cmk-btn--pink cmk-btn--lg cmk-share__challenge" data-challenge>⚔️ <?php esc_html_e( 'Challenge a friend to do worse', 'click-mat-kar' ); ?></button>
			</div>

			<div class="cmk-share cmk-share--visitor" data-share-visitor hidden>
				<p class="cmk-share__label"><?php esc_html_e( 'Your friend did this. Think you can do worse?', 'click-mat-kar' ); ?></p>
				<a class="cmk-btn cmk-btn--lime cmk-btn--lg" data-beat><?php esc_html_e( 'Accept the challenge', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			</div>

			<div class="cmk-result-page__next">
				<a class="cmk-btn cmk-btn--ink" data-again><?php esc_html_e( 'Phir se? Fine.', 'click-mat-kar' ); ?></a>
				<a class="cmk-btn cmk-btn--white" href="<?php echo esc_url( get_post_type_archive_link( 'cmk_game' ) ); ?>" data-play-another><?php esc_html_e( 'Another bad idea', 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			</div>
		</div>

		<div class="cmk-result-page__empty cmk-center" data-result-empty hidden>
			<p class="cmk-cooking__emoji" aria-hidden="true">🫥</p>
			<h1 class="cmk-h1"><?php esc_html_e( 'No damage here. Yet.', 'click-mat-kar' ); ?></h1>
			<p class="cmk-lead cmk-narrow"><?php esc_html_e( 'Results appear after you finish a game. Go make a terrible decision first.', 'click-mat-kar' ); ?></p>
			<a class="cmk-btn cmk-btn--lime cmk-btn--lg" href="<?php echo esc_url( cmk_first_game_url() ); ?>"><?php esc_html_e( "I'm clicking anyway", 'click-mat-kar' ); ?> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		</div>
	</div>
</section>
<?php
get_footer();
