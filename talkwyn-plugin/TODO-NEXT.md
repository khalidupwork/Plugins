# Saved to-do list (start only after the user says "go ahead")

## Plugin admin UI (website style)
- Website-style header band, Plus Jakarta Sans and Figtree (bundled locally), pill buttons.
- Header: red "Start free trial" (only when Pro is not active, links to talkwyn.com/pricing/#trial), dark "Upgrade to Pro", light "Setup wizard".
- One-line pill tabs with icons (no wrapping), rounded 24px cards with icons, switch toggles, 44px fields with red focus ring.
- "Saved." notice as a toast under the header (it currently lands between title and subtitle).
- Dashboard: stats with icons, getting-started progress ("2 of 4 done"), local weekly report, dark ink Pro trial card with feature list and "Start free 15-day trial".
- Pro tabs in the free build: hide (recommended) or show a preview card. Ask the user.
- License tab: "Don't have a key? Start a free trial" link.
- Providers: status badges ("Connected", "Not set"), drag-to-reorder fallback list.
- Appearance: live widget preview; friendlier label than "{bot} is typing".
- Conversations: first question, page and time instead of session ID; chat bubbles like the widget.
- Leads: card style, copy email, designed empty state.
- Knowledge: red progress bar, breakdown of pages, posts and products after a scan.
- Setup wizard: full-screen centered card, progress line, icons, "Your chat is live" screen.
- Widget: launcher pulse, optional gradient header, Rising Dots.
- Phase 1 brief items: badge question in the wizard, email opt-in, review request, one Pro prompt at a time.

## Website fixes (talkwyn.com theme)
1. /pricing/: fix spacing around the "Compare every Talkwyn plan" section (heading and table area, plus the gap before it).
2. /multilingual-chatbot/: the last block looks odd; fix its layout.
3. Home (/): the industries and languages explore section. Add icons to the language cards and make both columns use the same layout (same card style as the industries list).
4. "PRO ADDS" block (one lonely item in the left column, empty space, "Compare plans" link floating in the middle): fix it on every page where it appears, for example /industries/small-business/. Make it a balanced, full-width layout.
5. /docs/: remove the "Getting started" heading and reduce the space above the cards.

## Questions still open
- Pro tabs in the free plugin: hide or preview card?
- "Start free trial" target: talkwyn.com/pricing/#trial or another page?

## Partner referrals (saved for later, do not start until asked)

How it works today (Talkwyn Hub 1.1.0+):
- A customer applies in My Account, Partner. The admin approves under Talkwyn Hub, Partners, and the partner gets an email with their link.
- After approval the Partner page shows their link (`talkwyn.com/r/CODE`, or `?ref=CODE` on any page, or `/r/CODE?to=/pricing/`), a link builder, a QR code, banners, clicks and conversions, and pending, approved and paid totals.
- A click stores a signed first-party cookie for 60 days (last click wins). A partner coupon also counts at checkout.
- A paid order creates a pending commission (20% on new sales). A trial started from the link pays when it upgrades. Refunds and chargebacks reject it. After 30 days the daily cron approves it, and partners above the payout threshold show under Payouts.
- The free plugin's "Powered by Talkwyn" badge and "Add chat to your website" menu item use the partner code from Appearance, so agencies earn from sites they build.

To do:
1. Full test on talkwyn.com: apply, approve, open the link in a private window, start a trial and upgrade, buy a plan, refund one order, run the cron, mark a payout paid.
2. Check the Partner page inside the new dashboard layout (approved state: link, QR, builder, charts, payouts) and polish its design.
3. Admin report: clicks and conversions per partner and per source (`utm_source=badge`, `widget_menu`, links, coupons), so we can see what the plugin badge brings in.
4. Record the `utm_source` of each referral visit (today only the partner is stored).
5. Docs page "How Talkwyn Partners works" with the link formats, cookie rules and payout schedule.
6. Check cookie consent mode with a consent plugin (the referral cookie waits for consent when that setting is on).

## Plugin Check report (done in 2.3.2)

Report from the Plugin Check tool on talkwyn.com, all categories plus AI analysis, run on 2026-10-09 against the free plugin 2.3.1. Full CSV: `plugin-check/talkwyn-2.3.1-plugin-check-2026-10-09.csv` (53 rows: 13 errors, 40 warnings).

Errors:
- [x] `class-talkwyn-frontend.php:225` wp_enqueue_script() with an external resource (offloaded script).
- [x] `class-talkwyn-frontend.php:321` __() with placeholders has no "translators:" comment.
- [x] `class-talkwyn-providers.php:76` and `class-talkwyn-leads.php:77` flagged as offloading content to a remote service.
- [x] `class-talkwyn-providers.php:527, 543` exception messages not escaped ($res, $code, $msg).
- [x] `admin/class-talkwyn-admin.php:255` output not escaped ('self').
- [x] `class-talkwyn-indexer.php:94` suppress_filters set to true.
- [x] `readme.txt` Tested up to 6.8, current WordPress is 7.1.

Warnings:
- [x] Direct DB queries with table names from `$t[...]` not escaped: admin, indexer, privacy, retriever, db, migration (about 25 rows).
- [x] `class-talkwyn-privacy.php:122` $wpdb->prepare() gets 1 replacement, expects 2.
- [x] `class-talkwyn-retriever.php:72, 99` interpolated SQL.
- [x] (2.3.4: phpcs note added, providers kept) Direct AI provider calls (OpenRouter, Groq, Gemini): checker suggests the WordPress 7.0 AI Client (wp_ai_client_prompt()). Decide: keep, or add the AI Client as an extra provider.
- [x] WPML hook names (wpml_post_language_details, wpml_register_single_string, wpml_translate_single_string) not prefixed. These are WPML's own hooks; likely add a phpcs ignore comment.
- [x] readme plugin name differs from the plugin header name.
- [x] MIGRATION.md and HOOKS.md in the plugin root: leave them out of the release ZIP.

## Open decision from the October 2026 audit
- The chat is on by default right after activation, before an AI key is added. Without a key it answers from the site's own text (local answers). Options: keep it, or show the chat only after the wizard's "Go live" step. Waiting for the owner's choice.

## Launch playbook items still open
- Gap post 8, the "ai chatbot for customer service" pillar guide: write once the site has 30+ referring domains (playbook 5.2). Posts 1 to 7 are in theme 2.14.0 as scheduled posts.
- Optionally restore /urdu with a Roman Urdu demo after launch.
