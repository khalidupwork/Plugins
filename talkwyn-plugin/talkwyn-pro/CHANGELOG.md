# Talkwyn Pro changelog

## 1.2.4

Fixes from the WordPress Plugin Check report on 1.2.3:
- License client strings use the `talkwyn-pro` text domain (was `talkwyn`). The copy in `client-sdk/` is updated to match.
- Database queries in Insights, Search and Knowledge pass table names through `$wpdb->prepare()` with `%i`.
- Errors thrown by file extraction and streaming are escaped, then decoded again before they show in the admin.
- The `detail` value in admin notices is sanitized straight from `$_GET` (no extra URL decode).
- AI provider addresses (OpenAI, Anthropic, Mistral, DeepSeek, Groq, OpenRouter, Gemini) carry a phpcs note: the site owner picks the provider and adds their own key.
- readme: Tested up to 7.1; Groq and OpenRouter added to External services.
- `HOOKS-USED.md` stays in the source but is left out of the release ZIP.

Not changed on purpose:
- The update checker and the `Update URI` header. Talkwyn Pro is sold and updated from talkwyn.com, not WordPress.org, so these stay. Plugin Check reports them because it assumes every plugin is hosted on WordPress.org.
- "Requires Plugins: talkwyn" warning. It goes away once the free Talkwyn plugin is live on WordPress.org.

## 1.2.3

- Pro widget styles (product cards and more) use the same ID-level weight as Talkwyn 2.3.0, so themes and page builders cannot restyle them.

## 1.2.2

- Includes the active talkwyn.com Hub public key in `includes/hub-keys.php`. License responses and updates are verified without any wp-config setting.

## 1.2.1

- License tab: key field and Activate button are 44px, matching the Talkwyn admin buttons.

## 1.2.0

- License: a paid license that expires keeps Pro features on. Only updates and support stop until renewal. A trial that ended, or a key that is revoked or suspended, turns Pro features off. The 7-day grace period still covers times when talkwyn.com cannot be reached.
- Admin: without a license only the License tab is added, with a "Start a free 15-day trial" link. No locked feature tabs are shown.
- Admin: buttons and cards use the new Talkwyn admin style.
- White label: admin logo URL, and a chat menu link (text and URL) that replaces "Add chat to your website".
- Pro settings: "Coming soon" card for live human takeover, booking integrations, WhatsApp channel, multiple bots, lead scoring and AI conversation summaries.
- Uses the renamed free plugin hooks (see HOOKS-USED.md). Needs Talkwyn 2.1.0 or newer and shows a notice otherwise.
- License client: the expired message now says Pro features keep working.

## 1.0.0

- First release.
