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
