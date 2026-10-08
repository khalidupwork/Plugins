# Talkwyn 2.0.0 test checklist

The full run, including Pro and migration, is in `../QA-CHECKLIST.md`. This list covers what changed in 2.0.0.

## Install
- [ ] PHP 7.4 site: WordPress refuses to activate (needs PHP 8.0).
- [ ] PHP 8.0+ site: activation opens the setup wizard once. No PHP notices with WP_DEBUG on.

## Wizard
- [ ] Step 1 Test buttons report Connected or the provider error.
- [ ] Step 4 badge is off and the email box is unticked by default. Nothing is sent to talkwyn.com unless the box is ticked.
- [ ] Step 6 shows "Your chat is live" with links to the site and the dashboard.

## Admin
- [ ] Every tab loads without notices: Dashboard, Knowledge, AI providers, Appearance, Answers and leads, Leads, Conversations, Privacy.
- [ ] Start free trial opens talkwyn.com/pricing/#trial. With Pro active, both trial and upgrade buttons hide.
- [ ] Drag providers to reorder, save, reload: order kept. Arrow buttons work with the keyboard.
- [ ] Appearance preview follows colour, name, welcome text and header style without saving.
- [ ] Only one Pro prompt per screen; Dismiss keeps it hidden for that user only.
- [ ] Review request: set `talkwyn_installed_at` to 15 days ago and log 20 chats; the card shows. Maybe later hides it for 30 days.

## Widget
- [ ] Menu items: Change name, Email transcript, Sound, Language, Pop out, New chat. Turning the menu off brings back the header sound and reset buttons.
- [ ] Email transcript needs consent, saves a lead with Source "Transcript" and sends the email. If mail fails, the visitor sees that the request was saved.
- [ ] Language picker forces the reply language; Arabic and Urdu switch the chat to right to left.
- [ ] Pop out opens `?talkwyn_popout=1` with only the chat.
- [ ] Badge on with a referral code: badge and "Add chat to your website" link carry `ref` and `utm_source`, with `rel="nofollow noopener"`.
- [ ] Devices and logged in rules hide the chat as set.
- [ ] Gradient header is readable with a light brand colour (text switches to dark).
- [ ] Launcher pulse runs a few times, stops after the first open, and does not run with reduced motion.

## Spam
- [ ] Lead or transcript form sent in under 2.5 seconds is ignored; a normal fill is saved.

## Upgrade from an earlier 2.0.0 build
- [ ] The leads table gets the `source` column; existing leads read "Chat".

## Tests
- [ ] `phpunit` in this folder passes.
