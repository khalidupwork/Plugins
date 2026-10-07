<?php
/**
 * Title: Home: Hero
 * Slug: talkwyn/home-hero
 * Categories: talkwyn
 * Description: Plum hero band with rotating language word and a sample chat window.
 * Keywords: hero, header
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","className":"is-style-band tw-hero","ariaLabel":"Introduction","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull is-style-band tw-hero" aria-label="Introduction"><!-- wp:group {"align":"wide","className":"tw-hero__grid","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide tw-hero__grid"><!-- wp:group {"className":"tw-hero__text","layout":{"type":"default"}} -->
<div class="wp-block-group tw-hero__text"><!-- wp:paragraph {"className":"tw-eyebrow"} -->
<p class="tw-eyebrow">AI chatbot for WordPress and Shopify</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<h1 class="wp-block-heading tw-hero__title">Your website, answering every visitor in their <span class="screen-reader-text">language</span><span class="tw-rotator" data-tw-rotator aria-hidden="true"><span class="tw-rotator__word is-active">language</span><span class="tw-rotator__word" lang="en">English</span><span class="tw-rotator__word" lang="ar" dir="rtl">العربية</span><span class="tw-rotator__word" lang="ur" dir="rtl">اردو</span><span class="tw-rotator__word" lang="es">Español</span><span class="tw-rotator__word" lang="hi">हिन्दी</span><span class="tw-rotator__word" lang="fr">Français</span></span>.</h1>
<!-- /wp:html -->

<!-- wp:paragraph {"className":"tw-lede"} -->
<p class="tw-lede">Talkwyn reads your pages, products, and FAQs in one click. Then it answers questions day and night, in whatever language your visitor writes in, and hands your team the leads.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"tw-install"} -->
<div class="wp-block-button tw-install"><a class="wp-block-button__link wp-element-button" href="/download/" data-tw-event="install_click" data-tw-location="hero">Install free for WordPress</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-secondary"} -->
<div class="wp-block-button is-style-secondary"><a class="wp-block-button__link wp-element-button" href="#live-demo">See it answer live</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:paragraph {"className":"tw-hero__fine"} -->
<p class="tw-hero__fine">Free plan forever. Works with free AI keys. No credit card.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tw-hero__visual","layout":{"type":"default"}} -->
<div class="wp-block-group tw-hero__visual"><!-- wp:shortcode -->
[tw_hero_chat]
<!-- /wp:shortcode --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
