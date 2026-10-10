<?php
/**
 * Chaos ticker.
 *
 * @package ClickMatKar
 */

$cmk_words = array( 'Fake shopping', 'Bad decisions', 'Dream weddings', 'Too much money', 'Red flags', 'Zero consequences', 'Mana kiya tha', 'Bad decisions. Good times.' );
?>
<div class="cmk-ticker" aria-hidden="true">
	<div class="cmk-ticker__track">
		<?php for ( $cmk_i = 0; $cmk_i < 2; $cmk_i++ ) : ?>
			<span class="cmk-ticker__group">
				<?php foreach ( $cmk_words as $cmk_word ) : ?>
					<span><?php echo esc_html( $cmk_word ); ?></span><span class="cmk-ticker__star">✦</span>
				<?php endforeach; ?>
			</span>
		<?php endfor; ?>
	</div>
</div>
