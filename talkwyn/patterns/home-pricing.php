<?php
/**
 * Title: Home: Pricing teaser
 * Slug: talkwyn/home-pricing
 * Categories: talkwyn
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","className":"tw-section tw-section--soft","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull tw-section tw-section--soft"><!-- wp:group {"align":"wide","className":"tw-pricing-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide tw-pricing-wrap"><!-- wp:group {"className":"tw-section-head","layout":{"type":"default"}} -->
<div class="wp-block-group tw-section-head"><!-- wp:heading -->
<h2 class="wp-block-heading">Start free. Upgrade when it pays for itself.</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:shortcode -->
[tw_pricing_teaser]
<!-- /wp:shortcode -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/pricing/" data-tw-event="pricing_cta" data-tw-location="home_teaser">Compare plans</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
