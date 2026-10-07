<?php
/**
 * Title: Home: story line (answers, languages, leads, fallback)
 * Slug: talkwyn/home-story
 * Categories: talkwyn
 * Inserter: no
 *
 * @package Talkwyn
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","align":"full","className":"tw-section tw-story-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull tw-section tw-story-section"><!-- wp:group {"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"className":"tw-story","layout":{"type":"default"}} -->
<div class="wp-block-group tw-story"><!-- wp:group {"className":"tw-story__item","layout":{"type":"default"}} -->
<div data-tw-reveal class="wp-block-group tw-story__item"><!-- wp:group {"className":"tw-row","layout":{"type":"default"}} -->
<div class="wp-block-group tw-row"><!-- wp:group {"className":"tw-row__text","layout":{"type":"default"}} -->
<div class="wp-block-group tw-row__text"><!-- wp:paragraph {"className":"tw-eyebrow tw-eyebrow--plain"} -->
<p class="tw-eyebrow tw-eyebrow--plain">Instant answers</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Answers that come from your website, not from guesswork.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Talkwyn only answers business questions from your own content. Ask about prices, opening hours, shipping, or services and it pulls the relevant pages, writes a clear reply, and shows the sources underneath so the visitor can read more.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>When it doesn't know, it says so. It will never invent a price or a policy. Instead it offers to connect the visitor with your team.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"tw-checklist"} -->
<ul class="wp-block-list tw-checklist"><!-- wp:list-item -->
<li>Learns pages, posts, WooCommerce products, menus, and custom fields</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Shows source links under every answer</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Understands follow-up questions like "and how much is that?"</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Handles greetings and small talk without wasting your AI quota</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Read more about <a href="/features/">how Talkwyn answers</a>.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tw-glow tw-story__visual","layout":{"type":"default"}} -->
<div class="wp-block-group tw-glow tw-story__visual"><!-- wp:html -->
<div class="tw-glow__ui"><div class="tw-chat" data-tw-play><div class="tw-chat__head"><span class="tw-chat__avatar">[tw_icon name="message-circle" size="20"]</span><div><p class="tw-chat__title">Sample online store</p><p class="tw-chat__status">Answers in your language</p></div></div><div class="tw-chat__body tw-chat__body--short"><div class="tw-msg tw-msg--user" lang="en"><p>Do you ship to Sharjah, and how long does it take?</p></div><div class="tw-msg tw-msg--bot" lang="en"><p>Yes. Orders to Sharjah arrive in 2 to 3 working days. Delivery is free over the amount shown on our shipping page.</p><div class="tw-msg__sources"><span class="tw-msg__sources-label">Sources</span><span class="tw-chip tw-chip--source">Shipping</span><span class="tw-chip tw-chip--source">Delivery zones</span></div></div><div class="tw-msg tw-msg--user" lang="en"><p>and how much is that?</p></div><div class="tw-msg tw-msg--bot" lang="en"><p>Free delivery starts at the order value listed on the Shipping page. Below that, a flat fee applies. Want the link?</p><div class="tw-msg__sources"><span class="tw-msg__sources-label">Sources</span><span class="tw-chip tw-chip--source">Shipping</span></div></div></div></div></div>
<!-- /wp:html --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tw-story__item","layout":{"type":"default"}} -->
<div data-tw-reveal class="wp-block-group tw-story__item"><!-- wp:group {"className":"tw-row tw-row--reverse","layout":{"type":"default"}} -->
<div class="wp-block-group tw-row tw-row--reverse"><!-- wp:group {"className":"tw-row__text","layout":{"type":"default"}} -->
<div class="wp-block-group tw-row__text"><!-- wp:paragraph {"className":"tw-eyebrow tw-eyebrow--plain"} -->
<p class="tw-eyebrow tw-eyebrow--plain">Every language</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">One chatbot. Every language your customers speak.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Talkwyn detects the language of each message and replies in the same language. If your visitor switches halfway through, Talkwyn switches with them. Names, prices, emails, and product names stay exactly as they are.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>It is built for the way people really type, including Roman Urdu and mixed messages like "price kya hai for the large one?" Arabic and Urdu flow right to left, with fonts that keep every letter readable.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Your interface text can be translated too, with built-in support for WPML and Polylang.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>See the full list on our <a href="/multilingual-chatbot/">multilingual chatbot</a> page.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tw-glow tw-story__visual","layout":{"type":"default"}} -->
<div class="wp-block-group tw-glow tw-story__visual"><!-- wp:html -->
<div class="tw-glow__ui"><div class="tw-chat" data-tw-play><div class="tw-chat__head"><span class="tw-chat__avatar">[tw_icon name="message-circle" size="20"]</span><div><p class="tw-chat__title">Sample clothing store</p><p class="tw-chat__status">Answers in your language</p></div></div><div class="tw-chat__body tw-chat__body--short"><div class="tw-msg tw-msg--user" lang="ur-Latn"><p>price kya hai for the large one?</p></div><div class="tw-msg tw-msg--bot" lang="ur-Latn"><p>Large size ki price Rs 4,500 hai. Delivery 2 se 3 din mein ho jati hai. Order karna hai?</p><div class="tw-msg__sources"><span class="tw-msg__sources-label">Sources</span><span class="tw-chip tw-chip--source">Products</span></div></div><div class="tw-msg tw-msg--user" lang="ar" dir="rtl"><p>هل يوجد مقاس أكبر؟</p></div><div class="tw-msg tw-msg--bot" lang="ar" dir="rtl"><p>نعم، يتوفر مقاس XL باللونين الأسود والأزرق.</p><div class="tw-msg__sources"><span class="tw-msg__sources-label">Sources</span><span class="tw-chip tw-chip--source">Products</span></div></div></div></div></div>
<!-- /wp:html --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tw-story__item","layout":{"type":"default"}} -->
<div data-tw-reveal class="wp-block-group tw-story__item"><!-- wp:group {"className":"tw-row","layout":{"type":"default"}} -->
<div class="wp-block-group tw-row"><!-- wp:group {"className":"tw-row__text","layout":{"type":"default"}} -->
<div class="wp-block-group tw-row__text"><!-- wp:paragraph {"className":"tw-eyebrow tw-eyebrow--plain"} -->
<p class="tw-eyebrow tw-eyebrow--plain">Real leads</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Turn conversations into customers.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Talkwyn answers first and sells second. After a few helpful replies, or the moment a visitor shows buying intent ("can I book?", "what's the price?", "call me"), it offers a follow-up from your team. One tap opens a short form inside the chat.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"tw-checklist"} -->
<ul class="wp-block-list tw-checklist"><!-- wp:list-item -->
<li>Smart intent detection in many languages</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Asks before showing a form, so it never feels pushy</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Every lead is saved on your site and emailed to you instantly</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Lead alerts on WhatsApp and Slack [tw_badge type="pro"]Pro[/tw_badge] [tw_badge]Coming soon[/tw_badge]</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Lead scoring and AI summaries of the conversation [tw_badge type="pro"]Pro[/tw_badge] [tw_badge]Coming soon[/tw_badge]</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Learn more about <a href="/features/lead-generation/">lead generation with Talkwyn</a>.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tw-glow tw-story__visual","layout":{"type":"default"}} -->
<div class="wp-block-group tw-glow tw-story__visual"><!-- wp:html -->
<div class="tw-glow__ui"><div class="tw-chat" data-tw-play><div class="tw-chat__head"><span class="tw-chat__avatar">[tw_icon name="message-circle" size="20"]</span><div><p class="tw-chat__title">Sample real estate agency</p><p class="tw-chat__status">Answers in your language</p></div></div><div class="tw-chat__body tw-chat__body--short"><div class="tw-msg tw-msg--user" lang="en"><p>Can I book a viewing on Saturday?</p></div><div class="tw-msg tw-msg--bot" lang="en"><p>Yes, Saturday viewings run from 10 am to 4 pm. Would you like our team to call you to confirm a time?</p></div><div class="tw-lead-form"><span class="tw-lead-form__field">Name<i>Daniel</i></span><span class="tw-lead-form__field">Phone<i>+44 7700 900123</i></span><span class="tw-lead-form__btn">Send</span></div><span class="tw-lead-chip">[tw_icon name="check" size="14"] New lead saved</span></div></div></div>
<!-- /wp:html --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tw-story__item","layout":{"type":"default"}} -->
<div data-tw-reveal class="wp-block-group tw-story__item"><!-- wp:group {"className":"tw-row tw-row--reverse","layout":{"type":"default"}} -->
<div class="wp-block-group tw-row tw-row--reverse"><!-- wp:group {"className":"tw-row__text","layout":{"type":"default"}} -->
<div class="wp-block-group tw-row__text"><!-- wp:paragraph {"className":"tw-eyebrow tw-eyebrow--plain"} -->
<p class="tw-eyebrow tw-eyebrow--plain">Never goes silent</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Free AI keys, with a backup plan.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Free AI tiers are great until one hits its daily limit. Talkwyn lets you add several providers and remembers which one is working. If every provider is busy, it still replies with the most relevant information from your site, so no visitor is left staring at a spinner.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Prefer a paid model for top quality? Talkwyn Pro adds OpenAI, Anthropic Claude, Mistral, and DeepSeek. [tw_badge type="pro"]Pro[/tw_badge]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Read our guide to <a href="/docs/ai-providers/">choosing an AI provider</a>.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"tw-glow tw-story__visual","layout":{"type":"default"}} -->
<div class="wp-block-group tw-glow tw-story__visual"><!-- wp:html -->
<div class="tw-glow__ui"><div class="tw-fallback" aria-hidden="true"><div class="tw-fallback__q">Visitor asks a question</div><ol class="tw-fallback__list"><li class="is-busy"><b>Groq</b><span>Daily limit reached</span></li><li class="is-on"><b>Google Gemini</b><span>Answering now</span></li><li><b>OpenRouter</b><span>Standing by</span></li><li class="is-local"><b>Your site content</b><span>Last resort: most relevant info</span></li></ol></div></div>
<!-- /wp:html --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
