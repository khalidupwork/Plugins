# Phase 2 brief (saved, not started)

Build "Talkwyn Pro" as a SEPARATE add-on (slug talkwyn-pro). Requires the free Talkwyn plugin and extends it only through its hooks. Follow MASTER-PROMPT-plugin.md sections 3 and 6. Match Hub API.md endpoints exactly.

## A. License client
- Activate, deactivate, check against Hub REST API talkwyn-hub/v1.
- Verify Ed25519-signed responses with the bundled public key.
- Daily cron check, 7-day grace period if the Hub is unreachable.
- Trial: read is_trial, trial_ends_at, trial_days_left; trial banner with days left and upgrade link.
- Updates through the Hub update endpoint (normal WordPress update screen).
- Staging and local sites do not use an activation slot.
- License expired: features KEEP WORKING but no updates and support. Never break the site.
  (Differs from the current build, where Pro features pause on expiry.)

## B. Pro v1 features
1. Providers: OpenAI, Claude, Mistral, DeepSeek.
2. Embeddings smart search with keyword fallback.
3. Knowledge: custom Q&A, PDF/DOCX/TXT upload, URL and sitemap crawl.
4. Streaming (SSE) with non-streaming fallback.
5. Analytics dashboard plus "Unanswered questions" inbox with one-click add answer to Q&A.
6. WooCommerce: product cards, add to cart, order status lookup with email verification.
7. Lead alerts: email, Slack, Telegram. WhatsApp "Coming soon".
8. Proactive messages (page, time, scroll) and business hours.
9. White label: hide badge, rename widget menu item, custom branding.
10. Settings export and import (JSON).

## C. Coming soon only (do not build)
Live human takeover, booking, WhatsApp channel, multiple bots, lead scoring and AI summaries.

## D. Rules
- Free plugin works 100% without Pro. Pro only adds.
- Plans: Personal 1 site, Business 5, Agency unlimited. Read the site limit from the license response, never hardcode.
- Same coding standards as phase 1. No long dashes.
- Deliver: talkwyn-pro.zip, CHANGELOG.md, test checklist, short doc of every free-plugin hook used.
