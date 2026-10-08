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
