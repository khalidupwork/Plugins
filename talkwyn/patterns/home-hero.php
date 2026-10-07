<?php
/**
 * Title: Home: hero
 * Slug: talkwyn/home-hero
 * Categories: talkwyn
 * Inserter: no
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","className":"tw-hero tw-blush tw-inset","ariaLabel":"Introduction","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull tw-hero tw-blush tw-inset" aria-label="Introduction"><!-- wp:group {"className":"tw-hero__text","layout":{"type":"default"}} -->
<div class="wp-block-group tw-hero__text"><!-- wp:paragraph {"className":"tw-eyebrow"} -->
<p class="tw-eyebrow"><span class="tw-dot" aria-hidden="true"></span>AI chatbot for any website · Live on WordPress today</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<h1 class="wp-block-heading tw-hero__title"><span class="screen-reader-text">Your website, answering every visitor in their language.</span><span aria-hidden="true">Your website, answering every visitor in <span class="tw-rotator" data-tw-rotator><span class="tw-rotator__static">their language.</span><span class="tw-rotator__word is-active"><span class="tw-rotator__u" lang="en">English</span>.</span><span class="tw-rotator__word"><span class="tw-rotator__u" lang="ar" dir="rtl">العربية</span>.</span><span class="tw-rotator__word"><span class="tw-rotator__u" lang="ur" dir="rtl">اردو</span>.</span><span class="tw-rotator__word"><span class="tw-rotator__u" lang="es">Español</span>.</span><span class="tw-rotator__word"><span class="tw-rotator__u" lang="hi">हिन्दी</span>.</span><span class="tw-rotator__word"><span class="tw-rotator__u" lang="fr">Français</span>.</span></span></span></h1>
<!-- /wp:html -->

<!-- wp:paragraph {"className":"tw-lede tw-hero__lede"} -->
<p class="tw-lede tw-hero__lede">Talkwyn reads your pages, products, and FAQs in one click. Then it answers questions day and night, in whatever language your visitor writes in, and hands your team the leads.</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="tw-actions tw-actions--center"><a class="tw-pill tw-pill--red tw-pill--lg" href="/pricing/#trial" data-tw-event="trial_click" data-tw-location="hero">Start free trial</a><a class="tw-pill tw-pill--white tw-pill--lg" href="#live-demo">See it answer live</a></div>
<!-- /wp:html -->

<!-- wp:paragraph {"className":"tw-hero__fine"} -->
<p class="tw-hero__fine">[tw_value key="trial_days"] days of Pro free. Free plan forever after. Works with free AI keys. Get the <a href="/integrations/wordpress/">WordPress chatbot plugin</a>, with Shopify and more on the way.</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<ul class="tw-hero__chips" aria-label="What Talkwyn does"><li class="tw-chip">[tw_icon name="scan-search" size="18"] Answers from your content</li><li class="tw-chip">[tw_icon name="languages" size="18"] Replies in every language</li><li class="tw-chip">[tw_icon name="user-check" size="18"] Turns chats into leads</li></ul>
<!-- /wp:html --></div>
<!-- /wp:group -->

<!-- wp:html -->
<figure class="tw-app-wrap" data-tw-rise>
<div class="tw-app" aria-hidden="true">
 <div class="tw-app__bar"><span class="tw-app__brand">[tw_logo_mark] Inbox</span><span class="tw-app__search">[tw_icon name="search" size="14"] Search conversations</span><span class="tw-app__pill">Brightside Dental (sample clinic)</span></div>
 <div class="tw-app__cols">
  <div class="tw-app__list">
   <p class="tw-app__label">Today</p>
   <div class="tw-app__row is-active"><span class="tw-app__avatar">S</span><span class="tw-app__who"><b>Sara</b><span>How much is teeth whitening?</span></span><span class="tw-app__tag">EN</span></div>
   <div class="tw-app__row"><span class="tw-app__avatar">O</span><span class="tw-app__who"><b>Omar</b><span lang="ar" dir="rtl">هل تفتحون يوم السبت؟</span></span><span class="tw-app__tag">AR</span></div>
   <div class="tw-app__row"><span class="tw-app__avatar">L</span><span class="tw-app__who"><b>Lucía</b><span lang="es">¿Aceptan mi seguro?</span></span><span class="tw-app__tag">ES</span></div>
   <div class="tw-app__row"><span class="tw-app__avatar">B</span><span class="tw-app__who"><b>Bilal</b><span lang="ur-Latn">Kal ka appointment mil sakta hai?</span></span><span class="tw-app__tag">UR</span></div>
  </div>
  <div class="tw-app__convo">
   <div class="tw-chat tw-chat--bare" data-tw-play="loop"><div class="tw-chat__body tw-chat__body--short">
    <div class="tw-msg tw-msg--user" lang="en"><p>How much is teeth whitening?</p></div>
    <div class="tw-msg tw-msg--bot" lang="en"><p>In-office whitening is $249 and takes about an hour. A take-home kit is $149. Would you like to book a free consultation first?</p><div class="tw-msg__sources"><span class="tw-msg__sources-label">Sources</span><span class="tw-chip tw-chip--source">Whitening</span><span class="tw-chip tw-chip--source">Prices</span></div></div>
    <div class="tw-msg tw-msg--user" lang="ar" dir="rtl"><p>هل تفتحون يوم السبت؟</p></div>
    <div class="tw-msg tw-msg--bot" lang="ar" dir="rtl"><p>نعم، نفتح يوم السبت من التاسعة صباحًا حتى الثانية ظهرًا. هل تريد أن نحجز لك موعدًا؟</p><div class="tw-msg__sources"><span class="tw-msg__sources-label">Sources</span><span class="tw-chip tw-chip--source">Opening hours</span></div></div>
    <span class="tw-lead-chip">[tw_icon name="check" size="14"] New lead saved</span>
   </div></div>
  </div>
  <div class="tw-app__aside">
   <p class="tw-app__label">Visitor</p>
   <div class="tw-app__card"><span class="tw-app__avatar tw-app__avatar--lg">S</span><b>Sara</b><span class="tw-status tw-status--available">New lead</span></div>
   <dl class="tw-app__dl"><div><dt>Came from</dt><dd>/teeth-whitening/</dd></div><div><dt>Languages</dt><dd>English, Arabic</dd></div><div><dt>Asked about</dt><dd>Whitening price, Saturday hours</dd></div><div><dt>Wants</dt><dd>A call back</dd></div></dl>
  </div>
 </div>
</div>
<figcaption class="screen-reader-text">Preview of the Talkwyn inbox for a sample dental clinic: a visitor asks about whitening in English, then about Saturday hours in Arabic, and is saved as a new lead.</figcaption>
</figure>
<!-- /wp:html --></section>
<!-- /wp:group -->
