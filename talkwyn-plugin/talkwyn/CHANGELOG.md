# Talkwyn changelog

## 2.3.6

### Changed
- The chat widget bundles Inter 4.0 (400, 500, 600, Latin, OFL) in `assets/fonts` and loads it from `widget.css`. Before, it asked for Figtree without loading it, so most sites showed a system font.
- Widget type scale: messages 15px, buttons and chips 14px or smaller at weight 500, names and titles 600. Nothing uses 700 any more.

## 2.3.5

### Changed
- Plugin name is "Talkwyn" in both the plugin header and `readme.txt`. WordPress.org builds the plugin address (slug) from this name, so it becomes `talkwyn`, the same as the text domain. The names still match, so Plugin Check stays clean.

## 2.3.4

### Changed
- The seven Groq, OpenRouter and Gemini addresses that Plugin Check flagged ("consider the WordPress AI Client") carry a phpcs note: the site owner chooses the provider and adds their own key, and every provider is listed under External services in the readme. The calls themselves are unchanged.

## 2.3.3

### Changed
- Clear knowledge and the stale chunk cleanup pass their `LIKE 'post:%'` and `LIKE 'site:%'` patterns as prepared values with `$wpdb->esc_like()`, as the second Plugin Check run asked. Same rows are removed.
- The only Plugin Check items left are the AI provider suggestions (warnings, not errors).

## 2.3.2

### Changed
- Fixes from the WordPress Plugin Check report (run on 2.3.1):
  - Every database query passes table and column names through `$wpdb->prepare()` with `%i` (needs WordPress 6.2, the plugin already needs 6.4).
  - Fixed the placeholder count in the privacy export query.
  - Provider error messages are escaped when thrown and decoded again before they show in the admin, so quotes do not appear as `&#039;`.
  - The version labels in the admin header go through `wp_kses_post()`.
  - Added the missing "translators:" note for "Thanks, %s. Nice to meet you."
  - The scan no longer sets `suppress_filters` (get_posts already defaults to it, so results are the same).
  - WPML hook names and the Cloudflare Turnstile script and verify call carry a phpcs note with the reason. Turnstile is opt-in and listed under External services.
- Tested up to WordPress 7.1.
- The plugin header name matches the readme: "Talkwyn: AI Chatbot, Lead Generation & Multilingual Support".
- `HOOKS.md` and `MIGRATION.md` stay in the source but are no longer in the release ZIP.

### Not changed
- Plugin Check suggests the WordPress 7.0 AI Client instead of calling Groq, OpenRouter and Gemini directly. This is a suggestion, not an error, so the providers stay as they are.

## 2.3.1

### Changed
- Sources under an answer fold behind a small "Sources (n)" button with a count. Clicking it opens a short list of page links.
- At most three sources per answer, one per page title. Small talk, "I don't know" and off-topic replies (for example "I can only help with questions about this website") show no sources.

## 2.3.0

### Changed
- The chat keeps its own look on any theme or page builder: every widget style has ID-level weight, and the send icon, inputs, text and scrollbar are locked against theme rules (including theme-wide scrollbar colours and arrows). Tested against hostile rules such as `button svg { display: none !important }`, theme-wide `::-webkit-scrollbar` colours and arrows, and serif fonts on `p`.

## 2.2.3

### Added
- Installed version labels next to the title on Talkwyn screens ("v2.2.3", plus "Pro v1.2.1" when Pro is installed).
- Release ZIPs carry the version in the file name, for example `talkwyn-2.2.3.zip`. The folder inside is still `talkwyn`, so WordPress replaces the installed copy.

## 2.2.2

### Changed
- Buttons use three fixed sizes everywhere: small 36px, normal 44px, large 52px. Buttons in the same row match, and the header buttons all have icons. Wizard Back buttons match Continue, and plan links match the trial button next to them.

## 2.2.1

### Changed
- Admin tab bar: no scrollbar. Left and right arrow buttons with soft fades appear only when the tabs do not fit, and the open tab scrolls into view. The admin area is 1320px wide (was 1240px), so all tabs fit on most desktop screens.

## 2.2.0

### Added
- "Pro features" tab: what Talkwyn Pro adds (better answers, more knowledge, insights, WooCommerce, engage visitors, alerts and branding) and the roadmap, with trial and plan links. Information only; nothing in the free plugin is locked or disabled, as WordPress.org requires. The tab hides once a Pro license is active.
- Pro tips on Knowledge, AI providers, Leads and Conversations link to the full list.

## 2.1.3

### Fixed
- The chat lead form showed "We could not save your details. Please try again." and no lead was stored. Chat leads had an empty source value that the database refused. Introduced in 2.1.0.

## 2.1.2

### Changed
- Save button is a tall tab stuck to the right edge of the screen on desktop, so it never covers settings or the live preview. On phones it stays at the bottom. A red dot shows when there are unsaved changes.

## 2.1.1

### Fixed
- Appearance live preview follows every setting as you change it: launcher icon, avatar (your image or the first letter), header buttons, chat menu (opens from the three dots), message times, copy and feedback buttons, source links, typing text, badge, colours, position, header style and launcher pulse.
- No blue WordPress focus ring on Talkwyn tabs and buttons after a click. Keyboard focus shows a red outline.

## 2.1.0

### Added
- Setup wizard: AI key with a Test button, site scan, colours, badge choice and optional email updates, go live, and a "Your chat is live" screen.
- Admin screens in the Talkwyn website style: header with Setup wizard, Start free trial and Upgrade to Pro, pill tabs, dashboard checklist, weekly summary, live appearance preview, drag to reorder providers with status badges, lead cards with copy and source, conversation list with first question and page.
- Start free trial links go to talkwyn.com/pricing/#trial and show only while Pro is not active. Pro tabs are not shown in the free plugin.
- At most one Pro prompt per screen, dismissible per user. A review request after 14 days and 20 chats, with Maybe later and Do not ask again.
- Chat menu: change name, email transcript, language picker (16 languages, right to left for Arabic and Urdu), sound, pop out, new chat.
- Visibility by device (desktop, phones and tablets) and by logged in state.
- Optional brand gradient header and a launcher pulse until the first open (off with reduced motion).
- "Powered by Talkwyn" badge, off by default, with an optional referral code. Links use `rel="nofollow noopener"`.
- Lead source column (chat or transcript) in the table and CSV.
- Time check on the lead and transcript forms: forms sent in under 2.5 seconds are treated as spam.
- Hooks: `talkwyn_widget_menu`, `talkwyn_languages`, `talkwyn_admin_logo`, `talkwyn_hub_url`.
- Fonts for the admin (Plus Jakarta Sans, Figtree) are bundled; nothing loads from Google.

### Changed
- Requires PHP 8.0.
- Hooks renamed: `talkwyn_retrieve` to `talkwyn_sources`, `talkwyn_pre_reply` to `talkwyn_before_answer`, `talkwyn_after_reply` to `talkwyn_after_answer`, `talkwyn_lead_saved` to `talkwyn_lead_created`, `talkwyn_show_powered_by` to `talkwyn_show_badge`.

## 2.0.0

First release under the Talkwyn name (formerly Nabia AI Chatbot).

- One-time migration from Nabia AI Chatbot, REST chat endpoints with a fresh token on open, server-side history, Markdown allow list, Smart Contrast, consent, honeypot, rate limits, optional Turnstile, model lists, privacy exporter and eraser, opt-in data removal on uninstall. See readme.txt for the full list.
