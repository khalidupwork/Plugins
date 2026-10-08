# Talkwyn Pro test checklist

Use a clean WordPress 6.4+ site on PHP 8.0+ with Talkwyn 2.1.0 active.

## Install and dependency
- [ ] Activate Pro without Talkwyn: notice "Talkwyn Pro needs the free Talkwyn plugin." and nothing breaks.
- [ ] Activate Pro with Talkwyn older than 2.1.0 (for example 2.0.0): notice "Talkwyn Pro needs Talkwyn 2.1.0 or newer."
- [ ] Activate with Talkwyn 2.1.0: no PHP notices on any Talkwyn tab.

## License states
- [ ] No key: only the License tab is added. "Start a free 15-day trial" opens talkwyn.com/pricing/#trial.
- [ ] Trial key: all Pro tabs show, trial banner shows days left.
- [ ] Paid key active: all Pro tabs show, no banner.
- [ ] Paid key expired: Pro features still work. Notice says updates and support stopped. A new version shows "Renew your license to get this update" with no download.
- [ ] Trial ended: Pro tabs hide, Pro features stop, free plugin keeps working, Pro data is kept.
- [ ] Revoked or suspended key: same as trial ended.
- [ ] Hub unreachable for under 7 days: Pro stays on. After 7 days: Pro turns off until the next good check.
- [ ] Deactivate the key: site slot is freed on the Hub.

## Features
- [ ] Paid providers: add an OpenAI or Anthropic key, Test works, model list loads.
- [ ] Smart search: build embeddings, chunk count rises, answers still work with the provider off.
- [ ] Custom answers win over normal answers.
- [ ] PDF, DOCX and TXT upload, page URL and sitemap import.
- [ ] Streaming replies, and fallback when the host buffers.
- [ ] Analytics and Unanswered inbox fill after some chats. "Add answer" works.
- [ ] WooCommerce: product cards with Add to cart; order lookup needs order number plus billing email.
- [ ] Slack and Telegram test alerts, and an alert on a real lead.
- [ ] Proactive message by seconds, scroll and exit intent, only on matching URLs.
- [ ] Business hours: away status and lead-only mode outside hours.

## White label
- [ ] Hide "Powered by Talkwyn" hides the badge even when the free badge setting is on.
- [ ] Admin menu name changes the menu and screen title.
- [ ] Admin logo URL shows in the admin header.
- [ ] Chat menu link text and URL replace "Add chat to your website". With either field empty the default item stays.

## Other
- [ ] Coming soon card lists 6 roadmap items, none clickable.
- [ ] Export and import settings, with and without API keys.
- [ ] Uninstall with "delete data" on removes Pro tables and options only.
