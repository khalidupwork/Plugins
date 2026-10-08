=== Talkwyn: AI Chatbot, Lead Generation & Multilingual Support ===
Contributors: talkwyn
Tags: chatbot, ai chatbot, lead generation, multilingual, live chat
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI chatbot that learns your website in one click, answers in your visitor's language, and captures leads. Works with free AI tiers.

== Description ==

Talkwyn adds a chat assistant to your WordPress site. It reads your pages, posts and products, answers visitors from that content, replies in the language they write in, and offers to collect their details when they are ready to talk to you.

You bring your own AI key. Groq, OpenRouter, Google Gemini and Cloudflare Workers AI all offer free tiers, and Talkwyn moves to the next provider automatically when one fails or hits its limit.

= What it does =

* **One-click site scan.** Published pages, posts and WooCommerce products (price, stock, attributes), Elementor content and your menus. Custom fields are off by default and use an allow list.
* **Always up to date.** Content is refreshed when you save it and removed as soon as it is unpublished, trashed, deleted or password protected.
* **Free AI providers with fallback.** Groq, OpenRouter, Google Gemini and Cloudflare Workers AI. Talkwyn remembers the provider that worked last and falls back to answers built from your pages if every provider is down.
* **Model lists from each provider.** Load the current models from the provider's API or type your own.
* **Replies in your visitor's language.** Detects the language of each message, follows switches, supports right-to-left scripts, and works with WPML and Polylang string translation.
* **Source links** under answers, and **safe formatting** (bold, lists, links) through an allow-list renderer.
* **Conversations that survive page changes.** History is kept on your server by session, so a visitor can browse while they chat.
* **Smart lead capture.** Talkwyn answers first, then asks whether the visitor wants a follow-up after a few real questions, or sooner when they ask about prices, a call or a quote. Required fields, a consent checkbox, a honeypot, a rate limit and optional Cloudflare Turnstile. Leads are emailed to you and can be exported as CSV.
* **Chat logs** with a retention setting (30 days by default), helpful or not helpful feedback, and copy buttons.
* **Your look.** Brand colour with Smart Contrast (readable text picked for you, with a warning for colours that are hard to read), avatar, launcher icon and text, position, size, full screen, full screen on phones, and rules for pages where the chat is hidden.
* **Privacy tools.** A notice under the chat, rate limiting by session and IP, personal data export and erase by email, and data removal on uninstall only when you choose it.
* **Setup wizard.** Scan, add a key, test it, pick a colour, go live.
* **Shortcode and block.** `[talkwyn_chat]` or the Talkwyn Chat block to place the chat inside a page.

= Talkwyn Pro =

Talkwyn works on its own. Talkwyn Pro is a separate add-on from talkwyn.com with a free 15-day trial. It adds paid AI providers (OpenAI, Anthropic Claude, Mistral, DeepSeek), smart search by meaning, PDF, DOCX and URL knowledge, custom answers, streaming replies, analytics, an unanswered questions inbox, WooCommerce product cards and order lookup, Slack and Telegram lead alerts, proactive messages, business hours and white label. If Pro stops, the free plugin keeps working with all your data.

== External services ==

Talkwyn sends data to an outside service only when you configure it.

**AI providers (to answer visitors).** When a visitor sends a message, Talkwyn sends the message, the recent conversation, the relevant text from your website and your instructions to the AI providers you added a key for, in your fallback order. When you press "Load models", Talkwyn asks that provider for its model list with your key. When you press "Test connection", a short test message is sent.

* Groq: https://groq.com/ ([terms](https://groq.com/terms-of-use/), [privacy](https://groq.com/privacy-policy/))
* OpenRouter: https://openrouter.ai/ ([terms](https://openrouter.ai/terms), [privacy](https://openrouter.ai/privacy))
* Google Gemini API: https://ai.google.dev/ ([terms](https://ai.google.dev/gemini-api/terms), [privacy](https://policies.google.com/privacy))
* Cloudflare Workers AI: https://developers.cloudflare.com/workers-ai/ ([terms](https://www.cloudflare.com/website-terms/), [privacy](https://www.cloudflare.com/privacypolicy/))

**Cloudflare Turnstile (optional spam check).** If you add Turnstile keys, the lead form loads the Turnstile script from challenges.cloudflare.com and Talkwyn sends the visitor's check token and IP address to Cloudflare to verify it. [Privacy](https://www.cloudflare.com/turnstile-privacy-policy/)

Talkwyn does not contact talkwyn.com. The "Upgrade to Pro" link in the admin and the optional "Powered by Talkwyn" link in the chat (off by default) are plain links.

== Installation ==

1. Install Talkwyn from Plugins, Add New, or upload the `talkwyn` folder to `/wp-content/plugins/`.
2. Activate it. The setup wizard opens.
3. Scan your site, add a free AI key (Groq is the quickest), test it, choose your colour and go live.

To place the chat inside a page, use the `[talkwyn_chat]` shortcode or the Talkwyn Chat block.

== Frequently Asked Questions ==

= Is it free? =

Yes. The plugin is free. AI answers are paid to the provider you choose, and Groq, OpenRouter, Google Gemini and Cloudflare Workers AI have free tiers that suit many small sites. Check each provider's current limits.

= What happens when a provider hits its limit? =

Talkwyn tries the next provider in your fallback order. If none answers, it replies with the most relevant parts of your pages and offers to collect the visitor's details.

= Which languages does it answer in? =

It replies in the language the visitor writes in, including right-to-left scripts such as Arabic and Urdu. Interface text can be translated per language with WPML or Polylang.

= Does it use my private content? =

No. Only published, public content of the types you choose is scanned. Password protected, draft, private and trashed content is never used, and is removed from the knowledge as soon as it changes.

= Does it work with page caching? =

Yes. The chat fetches a fresh session token from an uncached endpoint when it opens, so cached pages do not break it.

= My site is behind Cloudflare. Is the rate limit per visitor? =

Turn on "My site is behind Cloudflare or a proxy" under Talkwyn, Privacy. Talkwyn then reads the visitor IP from the proxy headers.

= I used Nabia AI Chatbot. Do I lose anything? =

No. Talkwyn copies your settings, knowledge, leads and chat logs on first run and then offers to delete the old data.

= Where is my data stored? =

In your WordPress database. Chats, leads and knowledge stay on your site. Messages are sent to your AI provider to generate answers.

== Screenshots ==

1. The chat on a website, with source links and a lead offer.
2. Lead form with consent, then the "Lead saved" confirmation.
3. The setup wizard.
4. AI providers with fallback order, model lists and connection tests.
5. Appearance settings with Smart Contrast.
6. Leads and conversations.

== Changelog ==

= 2.0.0 =
* Renamed from Nabia AI Chatbot to Talkwyn, with a one-time migration of settings, knowledge, leads and logs.
* New: setup wizard, Gutenberg block, `[talkwyn_chat]` shortcode.
* New: chat endpoints moved to the REST API with a fresh token on open, so cached pages work.
* New: conversation history stored on the server by session; the chat continues across pages. Browser-sent history is no longer trusted.
* New: Markdown answers rendered with an allow-list sanitizer.
* New: Smart Contrast, consent checkbox, honeypot, lead rate limit and optional Cloudflare Turnstile.
* New: model lists loaded from each provider's API.
* New: personal data exporter and eraser, privacy policy text, uninstall that removes data only when you opt in.
* New: all admin text is translatable (text domain `talkwyn`).
* Fixed: unpublished, trashed, deleted and password protected content is removed from the knowledge.
* Fixed: retrieval ranks inside MySQL (FULLTEXT plus keyword score) before the limit, so older relevant pages are found.
* Fixed: rate limit by session plus IP; proxy headers only when you allow them.
* Fixed: Gemini key sent in a header instead of the URL.
* Fixed: custom fields are opt-in with an allow list.
* Fixed: a full scan removes knowledge of posts that no longer exist.

== Upgrade Notice ==

= 2.0.0 =
Nabia AI Chatbot is now Talkwyn. Your data is copied over automatically. Deactivate Nabia afterwards so visitors see one chat.
