# Talkwyn changelog

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
