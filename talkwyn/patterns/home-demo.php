<?php
/**
 * Title: Home: Live demo
 * Slug: talkwyn/home-demo
 * Categories: talkwyn
 * Description: Interactive demo. Embeds the real Talkwyn widget when configured in Site Settings; otherwise a clearly labeled scripted preview.
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","anchor":"live-demo","align":"full","className":"tw-section tw-section--demo","layout":{"type":"constrained"}} -->
<section id="live-demo" class="wp-block-group alignfull tw-section tw-section--demo"><!-- wp:group {"align":"wide","className":"tw-demo-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide tw-demo-wrap"><!-- wp:shortcode -->
[tw_live_demo]
<!-- /wp:shortcode --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
