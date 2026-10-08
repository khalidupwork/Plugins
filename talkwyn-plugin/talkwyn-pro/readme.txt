=== Talkwyn Pro ===
Requires at least: 6.4
Requires PHP: 8.0
Requires Plugins: talkwyn
Stable tag: 1.1.0
License: GPLv2 or later

Pro add-on for the free Talkwyn plugin. Sold and delivered from talkwyn.com with a free 15-day trial.

== Description ==

Talkwyn Pro needs the free Talkwyn plugin and extends it through hooks. If a paid license expires, Pro features keep working; only updates and support stop until you renew. If a trial ends or a key is revoked, Pro features pause and Talkwyn keeps working with all your data.

* Paid AI providers: OpenAI, Anthropic Claude, Mistral, DeepSeek, with model lists from each API.
* Smart search: embeddings (OpenAI, Mistral or Google Gemini) stored in your database, mixed with keyword scoring.
* Extra knowledge: custom answers that always win, PDF, DOCX and TXT uploads, pages and sitemaps.
* Streaming replies (Server-Sent Events) with a normal reply fallback.
* Analytics: chats per day, leads and lead rate, top questions, start pages, provider success rate, reply time.
* Unanswered questions inbox with one-click "Add answer".
* WooCommerce: product cards with Add to cart, and order status lookup with order number plus billing email.
* Lead alerts: email (free plugin), Slack and Telegram. WhatsApp alerts: coming soon.
* Proactive messages: time on page, scroll depth, exit intent, per URL.
* Business hours with an away status and lead-only mode.
* White label: hide "Powered by Talkwyn", rename the admin menu, use your own admin logo, and put your own link in the chat menu.
* Coming soon (on the roadmap, not available yet): live human takeover, booking integrations, WhatsApp channel, multiple bots, lead scoring, AI conversation summaries.
* Settings export and import (JSON).

== License and updates ==

Activate your key under Talkwyn, License. The plugin checks the license daily with Talkwyn Hub, verifies every response with Ed25519 signatures, keeps Pro on for 7 days if talkwyn.com cannot be reached, and receives updates in Dashboard, Updates.

Before building a release, put the active Hub public key in `includes/hub-keys.php` (Talkwyn Hub, Settings, Signing keys).

== External services ==

* talkwyn.com (Talkwyn Hub): license activation, daily checks and updates. Sends the license key, site URL, an instance ID, and the WordPress, PHP and plugin versions.
* OpenAI, Anthropic, Mistral, DeepSeek: when you add a key, visitor messages and relevant site text are sent to answer them.
* OpenAI, Mistral or Google Gemini embeddings: when smart search is on, your site text and visitor questions are sent to create embeddings.
* Slack and Telegram: lead details are sent when you add a webhook or bot.
* Pages you add by URL or sitemap are fetched from their sites.

== Changelog ==

= 1.1.0 =
* A paid license that expires keeps Pro features on. Only updates and support stop.
* Without a license only the License tab is shown, with a free trial link.
* White label: admin logo and chat menu link.
* Coming soon card in Pro settings.
* Uses the renamed free plugin hooks (Talkwyn 2.0.0).

= 1.0.0 =
* First release.
