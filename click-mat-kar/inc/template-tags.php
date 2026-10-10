<?php
/**
 * Small render helpers shared by templates.
 *
 * @package ClickMatKar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Typography-led wordmark: CLICK / MAT KAR. on a lime swoosh, with the cursor.
 *
 * @param string $size sm|md|xl.
 */
function cmk_wordmark( $size = 'md' ) {
	?>
	<span class="cmk-wordmark cmk-wordmark--<?php echo esc_attr( $size ); ?>" aria-hidden="true">
		<span class="cmk-wordmark__click">Click</span>
		<span class="cmk-wordmark__mat">Mat Kar<span class="cmk-wordmark__dot">.</span></span>
		<svg class="cmk-wordmark__cursor" viewBox="0 0 24 24" focusable="false"><path d="M4 2l15 9-6.5 1.6L16 20l-3 1.4-3.4-7.5L4 18z"/></svg>
	</span>
	<?php
}

function cmk_site_logo() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	printf( '<a class="cmk-logo" href="%s" rel="home">', esc_url( home_url( '/' ) ) );
	cmk_wordmark( 'sm' );
	printf( '<span class="screen-reader-text">%s</span></a>', esc_html__( 'Click Mat Kar — home', 'click-mat-kar' ) );
}

/**
 * Arrow icon used inside buttons and cards.
 */
function cmk_arrow() {
	return '<svg class="cmk-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h13m-5-6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/**
 * One game card. Unplayable games render as a non-link with a "soon" badge.
 *
 * @param array  $game    Card from cmk_game_cards().
 * @param string $variant big|small.
 */
function cmk_game_card( $game, $variant = 'big' ) {
	$tag   = $game['playable'] ? 'a' : 'div';
	$class = sprintf( 'cmk-game-card cmk-game-card--%s cmk-bg-%s', $variant, $game['color'] );
	if ( ! $game['playable'] ) {
		$class .= ' is-soon';
	}
	printf(
		'<%1$s class="%2$s"%3$s data-game="%4$s">',
		esc_attr( $tag ),
		esc_attr( $class ),
		$game['playable'] ? ' href="' . esc_url( $game['url'] ) . '"' : '',
		esc_attr( $game['slug'] )
	);
	?>
		<span class="cmk-game-card__emoji" aria-hidden="true"><?php echo esc_html( $game['emoji'] ); ?></span>
		<span class="cmk-game-card__body">
			<span class="cmk-game-card__kicker"><?php echo esc_html( $game['playable'] ? $game['kicker'] : __( 'Cooking', 'click-mat-kar' ) ); ?></span>
			<span class="cmk-game-card__title"><?php echo esc_html( $game['title'] ); ?></span>
			<span class="cmk-game-card__hook"><?php echo esc_html( $game['hook'] ); ?></span>
		</span>
		<?php if ( $game['playable'] ) : ?>
			<span class="cmk-game-card__go"><?php echo cmk_arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<?php else : ?>
			<span class="cmk-game-card__soon"><?php esc_html_e( 'Soon', 'click-mat-kar' ); ?></span>
		<?php endif; ?>
	</<?php echo esc_attr( $tag ); ?>>
	<?php
}

/**
 * Small pill label above section titles.
 */
function cmk_pill( $text, $color = 'lime' ) {
	printf( '<span class="cmk-pill cmk-bg-%s">%s</span>', esc_attr( $color ), esc_html( $text ) );
}

/**
 * URL of the first playable game (falls back to the games archive).
 */
function cmk_first_game_url() {
	$cards = cmk_game_cards();
	foreach ( $cards as $card ) {
		if ( $card['playable'] ) {
			return $card['url'];
		}
	}
	return get_post_type_archive_link( 'cmk_game' );
}
