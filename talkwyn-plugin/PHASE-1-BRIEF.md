# Phase 1 brief (saved, not started)

Source of truth: MASTER-PROMPT-plugin.md in talkwyn-site-kit.zip (read it in full first).
Phase 1 = FREE plugin "Talkwyn" from Nabia AI Chatbot v1.9.0. No Pro features.

## A. Rebrand and migrate
- Slug "talkwyn", text domain "talkwyn", class prefix Talkwyn_, option and table prefix talkwyn_.
- On activation, migrate every nac_* option, table and setting once, keep a "migrated" flag. Never lose settings, leads or logs.
- Talkwyn Red tokens (brand/tokens.css) on widget and admin. Light admin UI only.

## B. Fix all 16 audit issues (section 4)
1. Remove chunks on unpublish, trash, delete. Re-index on save.
2. Never index password-protected, private or draft content.
3. Rank across all chunks, not ORDER BY id DESC LIMIT 32.
4. Nonce works on cached pages (REST nonce refresh, or nonce-free public endpoint with other protections).
5. Rate limit with hashed IP plus session token; trusted proxy headers only when enabled.
6. Lead spam: honeypot, time trap, rate limit, email validation.
7. Chat history survives page changes (server-side session).
8. Safe sanitized Markdown, not raw textContent.
9. Never trust client history; load from server.
10. Gemini key in a header, never in the URL.
11. Only allow-listed custom fields (opt-in).
12. Full re-scan deletes chunks for posts that no longer exist.
13. uninstall.php with "delete all data on uninstall" option.
14. Full i18n, .pot file.
15. GDPR exporter and eraser for leads and logs.
16. Model lists fetched from provider APIs.

## C. Free features
- Widget, shortcode [talkwyn_chat], Gutenberg block.
- One-click scan: pages, posts, Woo products, Elementor, menus, opt-in custom fields.
- Providers: Groq, Gemini, OpenRouter, Cloudflare; fallback, last-good memory, local fallback answer.
- Multilingual incl. Roman Urdu, RTL, WPML, Polylang.
- Sources under answers.
- Lead capture with consent checkbox, email notification, CSV export.
- Chat logs (30 days default, configurable), thumbs up/down.
- Appearance with Smart Contrast; visibility rules (pages, devices, logged in or out).
- Onboarding wizard: provider key, scan, colors, badge question.

## D. Growth features (sections 5b, 5c, 5d)
- "Powered by Talkwyn" badge: opt-in, default OFF, asked in onboarding. Link talkwyn.com ?ref=CODE&utm_source=badge&utm_medium=widget, rel="nofollow noopener". Linen pill.
- Widget menu: Change name, Email transcript (consent, saved as lead source "transcript"), Sound on/off, Language, Pop out, New chat, "Add chat to your website" (only when badge is on).
- Unchecked email opt-in in onboarding, posts to Hub /subscribe.
- readme.txt "External services" listing every API called.
- Local weekly report on dashboard (chats, leads, top questions). Nothing sent anywhere.
- Contextual dismissible Pro prompts (max one at a time, never on frontend).
- Review request after 14 days AND 20 chats, dismissible forever.

## E. Rules
- WordPress.org guidelines: no trialware, no locked features, no forced links, no tracking without consent.
- Hooks: talkwyn_providers, talkwyn_sources, talkwyn_before_answer, talkwyn_after_answer, talkwyn_lead_created, talkwyn_widget_menu, talkwyn_show_badge, talkwyn_admin_tabs.
- PHP 8.0 to 8.3, WordPress 6.4+, PHPCS WordPress clean, escape all output, prepare all SQL.
- No long dashes.
- Deliver: talkwyn.zip, readme.txt, CHANGELOG.md, test checklist. Then stop and wait for phase 2.

## Notes from the screenshot review (to fix in this phase)
- "Saved." notice lands between the title and subtitle.
- Tabs wrap to two lines.
- Pro tabs show "paused" without a license; decide whether to hide them in the free build.
- Conversations list shows a session ID instead of the first question.
- "{bot} is typing" label is technical for users.
