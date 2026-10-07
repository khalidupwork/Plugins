<?php
/**
 * Title: Home: pricing teaser and founding customers
 * Slug: talkwyn/home-pricing
 * Categories: talkwyn
 * Inserter: no
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","className":"tw-section tw-pricing-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull tw-section tw-pricing-section"><!-- wp:group {"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"className":"tw-section-head tw-center","layout":{"type":"default"}} -->
<div class="wp-block-group tw-section-head tw-center"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Try Pro free for [tw_value key="trial_days"] days. Keep the free plan forever.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tw-lede"} -->
<p class="tw-lede">Every Pro feature, free for [tw_value key="trial_days"] days. [tw_value key="trial_card_policy"] When the trial ends you can upgrade or simply stay on the free plan.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:shortcode -->
[tw_pricing_teaser]
<!-- /wp:shortcode -->

<!-- wp:html -->
<div class="tw-actions tw-actions--center"><a class="tw-pill tw-pill--red" href="/pricing/#trial" data-tw-event="trial_click" data-tw-location="home_pricing">Start free trial</a><a class="tw-pill tw-pill--white" href="/pricing/">Compare plans</a></div>
<!-- /wp:html -->

<!-- wp:paragraph {"align":"center","className":"tw-partners-line"} -->
<p class="has-text-align-center tw-partners-line">Recommend Talkwyn to others? <a href="/partners/">Join Talkwyn Partners</a> and earn on every paid plan you refer.</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[tw_founding style="band"]
<!-- /wp:shortcode --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
