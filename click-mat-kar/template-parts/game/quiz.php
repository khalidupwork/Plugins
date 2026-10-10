<?php
/**
 * Game shell: quiz-style games (Bad Decisions, Red Flags, What's Your Price).
 * Questions live in assets/js/games-data.js; assets/js/game-quiz.js drives it.
 *
 * @package ClickMatKar
 */

$cmk_color = get_post_meta( get_the_ID(), 'cmk_color', true );
$cmk_emoji = get_post_meta( get_the_ID(), 'cmk_emoji', true );
?>
<div class="cmk-game cmk-game--quiz" data-quiz-game data-game-id="<?php echo esc_attr( get_post_field( 'post_name' ) ); ?>">

	<?php get_template_part( 'template-parts/game/challenge-banner' ); ?>

	<section class="cmk-quiz cmk-wrap">
		<div class="cmk-quiz__card cmk-bg-<?php echo esc_attr( $cmk_color ? $cmk_color : 'lavender' ); ?>" data-quiz-card>

			<div class="cmk-quiz__start" data-quiz-start>
				<span class="cmk-quiz__big-emoji" aria-hidden="true"><?php echo esc_html( $cmk_emoji ? $cmk_emoji : '🎲' ); ?></span>
				<?php cmk_pill( __( 'Quick game', 'click-mat-kar' ), 'white' ); ?>
				<h1 class="cmk-h1"><?php the_title(); ?></h1>
				<p class="cmk-lead" data-intro><?php echo esc_html( get_the_excerpt() ); ?></p>
				<button type="button" class="cmk-btn cmk-btn--ink cmk-btn--lg" data-quiz-go><span data-quiz-cta><?php esc_html_e( "Let's go", 'click-mat-kar' ); ?></span> <?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				<p class="cmk-quiz__fine"><?php esc_html_e( 'No signup. Takes about 2 minutes. Answers stay in your browser.', 'click-mat-kar' ); ?></p>
			</div>

			<div class="cmk-quiz__play" data-quiz-play hidden>
				<div class="cmk-quiz__top">
					<span class="cmk-quiz__step" data-quiz-step>1 / 7</span>
					<span class="cmk-quiz__bar" aria-hidden="true"><span data-quiz-bar></span></span>
				</div>
				<span class="cmk-quiz__q-emoji" data-quiz-emoji aria-hidden="true"></span>
				<h2 class="cmk-quiz__q" data-quiz-q tabindex="-1"></h2>
				<div class="cmk-quiz__options" data-quiz-options role="group"></div>
				<p class="cmk-quiz__react" data-quiz-react aria-live="polite"></p>
			</div>
		</div>
	</section>
</div>
