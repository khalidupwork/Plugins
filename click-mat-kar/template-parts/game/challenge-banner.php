<?php
/**
 * "You have been challenged" banner, filled by CMKUI.gameView() from ?challenge=.
 *
 * @package ClickMatKar
 */
?>
<div class="cmk-challenge-banner" data-challenge-banner hidden>
	<div class="cmk-wrap">
		<span class="cmk-challenge-banner__emoji" aria-hidden="true">⚔️</span>
		<p><strong><?php esc_html_e( 'You have been challenged.', 'click-mat-kar' ); ?></strong> <span data-challenge-text></span></p>
	</div>
</div>
