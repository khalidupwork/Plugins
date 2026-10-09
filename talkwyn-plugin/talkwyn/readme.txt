=== Talkwyn: AI Chatbot, Lead Generation & Multilingual Support ===
Contributors: talkwyn
Tags: chatbot, ai chatbot, lead generation, multilingual, live chat
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.3.3
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
* **Chat menu.** Visitors can set their name, email themselves the transcript, pick a reply language, turn sound off, open the chat in its own window, or start a new chat.
* **Who sees it.** Show the chat to everyone, only logged in or only logged out visitors, and on all devices, desktop only or phones only.
* **Weekly summary** on the dashboard: chats, leads and unanswered questions compared with the week before.
* **Setup wizard.** Add a key and test it, scan, pick a colour, go live.
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

**talkwyn.com (optional email updates).** The setup wizard has an unchecked box for product news and tips. Only if you tick it, Talkwyn sends your email address, site URL and site language to talkwyn.com once. [Privacy](https://talkwyn.com/privacy/)

Otherwise Talkwyn does not contact talkwyn.com. The "Start free trial" and "Upgrade to Pro" links in the admin, and the optional "Powered by Talkwyn" badge and "Add chat to your website" menu item in the chat (both off by default), are plain links that send nothing until someone clicks them.

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

= 2.3.3 =
* Changed: The LIKE patterns in two knowledge cleanup queries are passed as prepared values (WordPress Plugin Check).

= 2.3.2 =
* Changed: Code cleanup from the WordPress Plugin Check report: database table names go through prepared statements, error messages are escaped, and a missing translator note was added.
* Changed: Tested up to WordPress 7.1. The plugin name in the plugin list now matches the WordPress.org listing.

= 2.3.1 =
* Changed: Sources under an answer fold behind a small "Sources (n)" button. Click it to see the links.
* Changed: At most three sources per answer, one per page. Small talk, "I don't know" and off-topic replies show no sources.

= 2.3.0 =
* Changed: The chat keeps its own look on any theme or page builder: every widget style has ID-level weight, and the send icon, inputs, text and scrollbar are locked against theme rules (including theme-wide scrollbar colours and arrows).

= 2.2.3 =
* New: the installed Talkwyn version (and the Pro version, when Pro is installed) shows next to the title on Talkwyn screens, so you can confirm an update at a glance.

= 2.2.2 =
* Changed: Buttons use three fixed sizes everywhere: small 36px, normal 44px, large 52px. Buttons in the same row match, and the header buttons all have icons.

= 2.2.1 =
* Changed: the admin tab bar has no scrollbar. When the tabs do not fit, left and right arrow buttons appear and the open tab is kept in view. The admin area is a little wider, so all tabs fit on most desktop screens.

= 2.2.0 =
* New: a "Pro features" tab that lists what Talkwyn Pro adds, grouped by area, plus what is on the roadmap. It is information only: nothing in the free plugin is locked. The tab hides once Pro is licensed.
* New: Pro tips link to the full list.

= 2.1.3 =
* Fixed: the lead form in the chat showed "We could not save your details" and did not save the lead. Introduced in 2.1.0.

= 2.1.2 =
* Changed: the Save button is a tall tab on the right edge of the screen on desktop, so it no longer covers settings or the live preview. A red dot shows when there are unsaved changes.

= 2.1.1 =
* Fixed: the Appearance live preview now follows every setting as you change it: launcher icon, avatar (including your image and first letter), header buttons, chat menu, message times, copy and feedback buttons, source links, typing text, badge, colours, position and header style.
* Fixed: no blue focus ring on Talkwyn tabs and buttons after a click. Keyboard focus shows a red outline.

= 2.1.0 =
* New: admin screens in the Talkwyn style, with a dashboard checklist, weekly summary and live appearance preview.
* New: chat menu with name, email transcript, language picker, sound, pop out and new chat.
* New: show the chat by device and by logged in state; optional gradient header and launcher pulse.
* New: "Powered by Talkwyn" badge is off by default and asked about in the wizard, with an optional referral code.
* New: a time check on the lead and transcript forms to stop bots that submit instantly.
* New: hooks `talkwyn_widget_menu`, `talkwyn_languages`, `talkwyn_admin_logo` and `talkwyn_hub_url`.
* New: Start free trial links in the admin and wizard (hidden when Pro is active). Pro tabs are no longer shown in the free plugin.
* Changed: requires PHP 8.0.
* Changed: hooks renamed: `talkwyn_retrieve` to `talkwyn_sources`, `talkwyn_pre_reply` to `talkwyn_before_answer`, `talkwyn_after_reply` to `talkwyn_after_answer`, `talkwyn_lead_saved` to `talkwyn_lead_created`, `talkwyn_show_powered_by` to `talkwyn_show_badge`.
* Changed: leads get a Source column (chat or transcript). The database updates on its own.

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

= 2.3.3 =
One more code cleanup for the WordPress Plugin Check.

= 2.3.2 =
Code cleanup for the WordPress Plugin Check. No settings change.

= 2.3.1 =
Sources under chat answers are folded and limited to three.

= 2.3.0 =
The chat no longer picks up colours, fonts or scrollbars from your theme.

= 2.2.3 =
Shows the installed version in the Talkwyn header.

= 2.2.2 =
Consistent button sizes in the admin.

= 2.2.1 =
Cleaner admin tab bar.

= 2.2.0 =
Adds a Pro features overview tab.

= 2.1.3 =
Important: fixes leads not saving from the chat form. Update now.

= 2.1.2 =
Save button moved to the right edge so it never covers settings.

= 2.1.1 =
Live preview fixes on the Appearance tab.

= 2.1.0 =
New admin design, chat menu and visibility rules. Needs PHP 8.0. If you use Talkwyn Pro, update it to 1.2.0 too.

= 2.0.0 =
Nabia AI Chatbot is now Talkwyn. Your data is copied over automatically. Deactivate Nabia afterwards so visitors see one chat.
