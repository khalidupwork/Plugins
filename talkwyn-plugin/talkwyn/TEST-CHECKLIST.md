# Talkwyn 2.3.3 test checklist

The full run, including Pro and migration, is in `../QA-CHECKLIST.md`. This list covers what changed in 2.1.0.

## Plugin Check (2.3.2, 2.3.3)
- [ ] Knowledge, Clear knowledge empties the page and site entries; Rescan fills them again.
- [ ] Run Tools, Plugin Check on Talkwyn with all categories: no errors. The only warnings left are the AI provider suggestions.
- [ ] Dashboard, Knowledge, Leads and Conversations tabs show the same numbers as before. Rescan, Clear knowledge, Export leads and Clear conversations work.
- [ ] Test connection with a wrong key: the error text shows quotes normally, not `&#039;`.
- [ ] Tools, Export Personal Data for an email with a lead: the file holds the lead and its chat.

## Install
- [ ] PHP 7.4 site: WordPress refuses to activate (needs PHP 8.0).
- [ ] PHP 8.0+ site: activation opens the setup wizard once. No PHP notices with WP_DEBUG on.

## Wizard
- [ ] Step 1 Test buttons report Connected or the provider error.
- [ ] Step 4 badge is off and the email box is unticked by default. Nothing is sent to talkwyn.com unless the box is ticked.
- [ ] Step 6 shows "Your chat is live" with links to the site and the dashboard.

## Admin
- [ ] Every tab loads without notices: Dashboard, Knowledge, AI providers, Appearance, Answers and leads, Leads, Conversations, Privacy.
- [ ] Without a Pro license, a red "Pro features" tab lists six groups and the roadmap; both buttons open talkwyn.com. With a Pro license the tab is gone. Pro tips show an "All Pro features" link to it.
- [ ] Tab bar has no scrollbar. On a wide screen all tabs fit and no arrows show. On a narrow screen (or with Pro tabs) a right arrow shows; clicking it scrolls and a left arrow appears. Opening the last tab keeps it in view.
- [ ] Every button is 36, 44 or 52px tall, and buttons side by side are the same height (header, wizard Back and Continue, Pro card, Pro features, License).
- [ ] Start free trial opens talkwyn.com/pricing/#trial. With Pro active, both trial and upgrade buttons hide.
- [ ] Drag providers to reorder, save, reload: order kept. Arrow buttons work with the keyboard.
- [ ] Appearance preview follows every setting without saving: launcher icon, avatar type and image, name, status, welcome, placeholder, suggestions, typing text, colour, position, header style, pulse, header buttons, menu items (click the three dots), message times, copy and feedback buttons, source links and badge.
- [ ] Desktop: the Save tab sits on the right edge, centred, and covers nothing. Change any field: a red dot appears on it. Phone width: the Save button sits at the bottom.
- [ ] Click a tab or button: no blue ring. Press Tab: a red outline shows.
- [ ] Only one Pro prompt per screen; Dismiss keeps it hidden for that user only.
- [ ] Review request: set `talkwyn_installed_at` to 15 days ago and log 20 chats; the card shows. Maybe later hides it for 30 days.

## Widget
- [ ] Ask a question about the site: a "Sources (n)" button shows under the answer, n is 3 or less, and the list stays closed until clicked. No page title shows twice.
- [ ] Say "thanks", ask something off-topic, or ask something the site does not cover: no Sources button.
- [ ] Menu items: Change name, Email transcript, Sound, Language, Pop out, New chat. Turning the menu off brings back the header sound and reset buttons.
- [ ] Email transcript needs consent, saves a lead with Source "Transcript" and sends the email. If mail fails, the visitor sees that the request was saved.
- [ ] Language picker forces the reply language; Arabic and Urdu switch the chat to right to left.
- [ ] Pop out opens `?talkwyn_popout=1` with only the chat.
- [ ] Badge on with a referral code: badge and "Add chat to your website" link carry `ref` and `utm_source`, with `rel="nofollow noopener"`.
- [ ] Devices and logged in rules hide the chat as set.
- [ ] Gradient header is readable with a light brand colour (text switches to dark).
- [ ] Launcher pulse runs a few times, stops after the first open, and does not run with reduced motion.

## Leads
- [ ] Ask about a quote in the chat, choose "Yes, contact me", fill name, email, phone and consent, send: "Lead saved" shows with a reference, and the lead appears under Leads with Source "Chat".

## Theme conflicts
- [ ] On a theme with heavy button, input, paragraph and scrollbar styles (for example a purple Elementor kit), the chat looks the same as on a plain theme: arrow icon in the send button, slim grey scrollbar without arrows, chat font in answers.

## Spam
- [ ] Lead or transcript form sent in under 2.5 seconds is ignored; a normal fill is saved.

## Update to a new version
- [ ] Upload `talkwyn-<version>.zip` from Plugins, Add New, Upload. WordPress offers "Replace current with uploaded" and shows the old and new version. After replacing, the Talkwyn header shows the new version label.

## Update from 2.0.0
- [ ] Install 2.0.0, add a lead, then upload 2.1.0 (Plugins, Add New, Upload, Replace current). Plugins shows 2.1.0, the leads table gets the `source` column, existing leads read "Chat", settings stay.
- [ ] New admin CSS and widget JS load without a hard refresh (file URLs end in `ver=2.1.0`).

## Tests
- [ ] `phpunit` in this folder passes.
