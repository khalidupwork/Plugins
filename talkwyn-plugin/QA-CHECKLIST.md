# Talkwyn and Talkwyn Pro: manual QA checklist

Run on a clean WordPress 6.4 or later site with PHP 8.0 or later (both plugins). Tick each line.

## 1. Fresh install (free)

- [ ] Activate Talkwyn. The setup wizard opens once.
- [ ] Step 1: add a Groq key, **Test** shows "Connected".
- [ ] Step 2: **Scan my site** reaches 100% and shows a chunk count.
- [ ] Step 3: pick a light colour such as `#FFE066`. The sample switches to dark text. Pick `#797979`: the "hard to read" warning shows.
- [ ] Step 4: the "Powered by Talkwyn" badge switch is off and the email updates box is unticked. Leave both off: nothing is sent to talkwyn.com. Tick the email box on a second run: the address appears under Talkwyn Hub, Subscribers.
- [ ] Step 5: turn the chat on. Step 6 shows "Your chat is live". The dashboard checklist shows the steps done.
- [ ] Front end: the launcher shows bottom right. The panel opens with the welcome message and suggested questions.
- [ ] Ask a question your site answers. The reply is formatted (bold, lists, links) and shows source links.
- [ ] Ask "hi" and "thanks". Small talk is answered without source links.
- [ ] Write in Spanish, then Arabic. Replies follow the language; Arabic bubbles read right to left.
- [ ] Open another page. The conversation is still there.
- [ ] Ask about prices. The lead offer appears. **Yes, contact me**, submit without consent: an error shows. Tick consent and submit: the "Lead saved" chip shows and the admin email arrives.
- [ ] Leads tab lists the lead with Source "Chat"; the copy button copies the email; **Export CSV** downloads it with a Source column.
- [ ] Submit the lead form within 2 seconds of it opening (for example with a script): it is treated as spam and not saved.
- [ ] Chat menu (three dots): **Change name** is used in the next reply; **Email transcript** with consent saves a lead with Source "Transcript" and sends the email; **Language** forces replies in that language (Arabic and Urdu switch the chat to right to left); **Sound** toggles; **Pop out** opens the chat alone in a new window; **New chat** clears it.
- [ ] Appearance: the preview updates live. Turn the badge on with a referral code: the badge and the "Add chat to your website" menu item link to talkwyn.com with `ref` and `utm_source`, and have `rel="nofollow noopener"`.
- [ ] Appearance: "Brand gradient" header and the launcher pulse show; the pulse stops after the first open and with reduced motion.
- [ ] Visitors: "Logged out visitors only" hides the chat for an admin; "Phones and tablets only" hides it on desktop.
- [ ] After 14 days and 20 chats the review request shows once; **Maybe later** hides it for 30 days. Only one Pro prompt shows per screen and **Dismiss** keeps it hidden for that user.
- [ ] Without Pro, the header shows **Start free trial** (talkwyn.com/pricing/#trial) and **Upgrade to Pro**, and no Pro tabs.
- [ ] Conversations tab shows the chat with provider and reply time. Mark an answer "Not helpful": it shows on the log.
- [ ] Unpublish a page, then ask about it: it is no longer used. Add a password to a page: same.
- [ ] Remove all keys: questions get answers built from your pages, not an error.
- [ ] Hide the chat on one page and one path: it does not show there.
- [ ] Place `[talkwyn_chat]` and the Talkwyn Chat block on a page: the chat shows inline and the floating one is hidden on that page.
- [ ] Phone width: the chat opens full screen, inputs are 16px, the send button is 44px.
- [ ] With a page cache plugin on, open the chat after the cached page is a day old: chatting still works.
- [ ] Tools, Export Personal Data and Erase Personal Data for the lead's email: the lead and its chats are exported, then removed.
- [ ] Privacy: turn on "Delete all Talkwyn data", delete the plugin: tables and options are gone. With it off, they stay.

## 2. Migration from Nabia AI Chatbot 1.9.0

- [ ] On a site with Nabia data (settings, leads, logs, knowledge), activate Talkwyn.
- [ ] The notice reports the copied rows. Settings (name, colour, keys, lead texts) match.
- [ ] **Delete old Nabia data**: Nabia is deactivated and the `nac_*` tables and options are gone. Talkwyn data is untouched.
- [ ] Follow MIGRATION.md "After the move".

## 3. Pro trial to Pro features to expiry to free

- [ ] Start a trial on talkwyn.com and install Talkwyn Pro from the email link.
- [ ] Talkwyn, License: paste the key, **Activate**. Status shows "Trial: 15 days left" and the trial banner shows on Talkwyn screens.
- [ ] Paid providers appear under AI providers. Add an OpenAI or Anthropic key, **Load models**, pick one, **Test**.
- [ ] Pro settings: choose an embedding provider, **Build smart search now** reaches all chunks. A question phrased differently from the page still finds it.
- [ ] Extra knowledge: add a custom answer; asking it returns the answer word for word. Upload a PDF and a DOCX; add a sitemap URL. Each shows chunks and "Ready".
- [ ] Streaming on: replies appear word by word. Turn it off: replies arrive in one piece.
- [ ] Analytics shows chats per day, leads, lead rate, top questions, start pages, provider success and reply time.
- [ ] Ask something the site does not cover; mark another answer "Not helpful". Both appear under Unanswered. **Add answer** saves a custom answer and removes the item.
- [ ] WooCommerce: a product question shows product cards with price, **View product** and **Add to cart**. "Where is my order 1234?" asks for the email; the right email returns the status, a wrong one does not reveal anything. Six lookups in an hour are refused.
- [ ] Lead alerts: Slack webhook and Telegram bot receive **Send a test alert** and real leads. WhatsApp shows "Coming soon".
- [ ] Proactive message after 5 seconds on /pricing/ shows once per visit. Exit intent works on desktop.
- [ ] Business hours: outside hours the status shows the away text and the chat asks for contact details.
- [ ] White label: "Powered by Talkwyn" is hidden; a custom menu name replaces "Talkwyn".
- [ ] Export settings without keys, import on a second site: settings and custom answers arrive, keys do not.
- [ ] White label: admin logo URL replaces the Talkwyn mark; a chat menu link text and URL replace "Add chat to your website".
- [ ] Pro settings shows the "Coming soon" card with six roadmap items.
- [ ] End the trial on the Hub (or wait). After the daily check: only the License tab remains, paid providers and Pro features stop, the chat keeps answering with free providers, and no data is lost.
- [ ] Let a paid license expire on the Hub. After the daily check: Pro features keep working, the notice says updates and support stopped, and a new version shows "Renew your license to get this update".

## 4. Paid activation

- [ ] Upgrade the trial on talkwyn.com. **Check now** on the License tab: status Active, plan shown, trial banner gone, Pro features back with the same key and data.
- [ ] A new paid key on a second site within the site limit activates; one over the limit is refused with a clear message. A staging domain does not use a slot.
- [ ] Deactivate on the License tab: the site slot is released in My Account.
- [ ] Block talkwyn.com from the site: Pro stays on with a grace notice for 7 days, then pauses until the next good check.

## 5. Updates

- [ ] Publish a new Pro release on the Hub. Dashboard, Updates shows it; "View details" shows the changelog.
- [ ] Update: the download works and the version changes. Settings and data stay.
- [ ] Update the free plugin from 2.0.0 to a newer build: the database upgrade runs once, settings stay.

## 6. Final checks

- [ ] No em dash or en dash characters in UI text, readme or docs (a search for Unicode 2013 and 2014 finds nothing).
- [ ] Plugin Check (WordPress.org) on the free plugin: no errors.
- [ ] PHPUnit: `phpunit` in `talkwyn/` and in `talkwyn-pro/` passes.
- [ ] Browser console has no errors on pages with the chat.
