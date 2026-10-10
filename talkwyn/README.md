# Talkwyn theme

Block theme for talkwyn.com (v3, Talkwyn Red). It works with WooCommerce and the Talkwyn Hub plugin (licenses, free trial, partners, updates, My Account screens and emails).

- Requires WordPress 6.5+, PHP 8.0+.
- Light only. There is no dark mode and no theme switcher. Colors: Talkwyn Red `#D7263D`, Ink `#1A0F12`, Linen `#F7F3F3`, Blush gradient. Fonts: Plus Jakarta Sans (headings), Inter (body and every button, weight 500), JetBrains Mono (keys).
- Fonts are self-hosted, so the theme makes no Google Fonts requests. Licenses are in `assets/fonts/`.
- Layouts are RTL-ready: logical CSS properties throughout, and Arabic and Hindi fonts are applied by `:lang()`. Chinese, Japanese and Korean use system fonts.

## Install order

1. **WordPress** 6.5 or newer. Setup sets permalinks to `/blog/%postname%/` so posts live under `/blog/`.
2. **WooCommerce.** Finish or skip the onboarding wizard. In WooCommerce → Settings → Advanced → Features, keep "High-performance order storage" on.
3. **Talkwyn Hub** (`talkwyn-hub/`). Follow its README:
   - set `TWH_SECRET_KEY` in `wp-config.php`
   - create the Talkwyn Pro product with Personal, Business and Agency plans (the Hub's product tab sets each plan slug)
   - upload a release
4. **This theme.** Zip the `talkwyn` folder, then go to Appearance → Themes → Add New → Upload. Activate it.
5. **Rank Math** (recommended). Install and run its setup wizard. The theme detects it and steps aside. See "SEO" below.
6. **Create the pages.** Go to Appearance → Talkwyn Site Settings → **Create or update site pages**, or run this with WP-CLI:

   ```
   wp eval-file wp-content/themes/talkwyn/setup/setup.php
   wp eval-file wp-content/themes/talkwyn/setup/setup.php overwrite   # also replace content you edited
   ```

   Setup is safe to run again. Without `overwrite` it only creates missing pages and keeps your edits. It does the following:
   - creates every page, the 4 launch blog posts and the docs from `setup/content/pages.json`, plus blog categories
   - trashes the theme 1.x pages that v3 replaced (their URLs 301 to the new ones, for example `/wordpress-ai-chatbot/` to `/integrations/wordpress/`; see `inc/redirects.php`)
   - sets the homepage and the posts page
   - maps the plan products into Site Settings (by the Hub plan slug)
   - links the WooCommerce terms and privacy pages
   - trashes WordPress's untouched "Hello world!" post and "Sample Page"
7. **Fill in Site Settings** (next section). Then work through `LAUNCH-CHECKLIST.md`.

## Site Settings (Appearance → Talkwyn Site Settings)

| Setting | What it does |
|---|---|
| Price source | `woocommerce` reads prices from the mapped products. `manual` uses the price fields below. |
| Product IDs (Personal, Business, Agency, Lifetime) | Products the Buy buttons add to the cart. Setup fills these in. |
| Show lifetime plan | Adds the lifetime card to pricing. Leave it off until you sell one. |
| Founding offer on, seats, seats remaining | Shows the founding member panel on the homepage and pricing. Update "remaining" by hand as seats sell. At 0 the panel disappears. |
| Refund days | Used in the checkout trust line, the FAQ and the policy text (14). |
| WordPress.org URL | Leave empty until the free plugin is live. While empty, every "Install free" button goes to `/download/`. Once set, they all point to WordPress.org. |
| Free ZIP URL | Direct ZIP download shown on `/download/` until WordPress.org is live. |
| Free trial (days, card policy) | Used everywhere the trial is mentioned. When Talkwyn Hub runs on this site, its Trial settings win. |
| Partners (Hub URL and fallbacks) | Partner terms on `/partners/` come from Talkwyn Hub on this site, or from the Hub's `/wp-json/talkwyn-hub/v1/partners/terms` endpoint when you set the Hub URL. The fallback fields are used only without the Hub. |
| Integrations | Status of each integration: Available, In development or Planned. Pills in the mega menu, homepage and `/integrations/` follow it. |
| Show waitlist count | Shows "N people are waiting for Shopify" on integration pages. Off by default. |
| Waitlist | Sign-ups are stored under **Waitlist** in the admin menu (email, platform, website), with a platform filter and **Export CSV**. Each sign-up gets a confirmation email, and you get a notice. |
| Contact email | Where contact form messages and waitlist alerts go. |
| Contact messages | Since 2.10.0 every contact form message is also saved under **Contact messages** in the admin menu (name, email, website, topic, page, message), with a red count of unread messages and a **Reply by email** button. If an email does not arrive, the message is still there. |
| Content fixes | Since 2.12.0, `inc/content-fixes.php` replaces exact old sentences in imported pages once, after a theme update, when an admin opens the dashboard. 2.12.0 removes the "lead inbox per client" promise (not a feature) and describes white label as the Agency plan's logo and menu name. Edited pages keep the rest of their text. 2.13.0 (launch playbook) adds fix set 2 from `inc/content-fixes-2.json`, applied per page and all or nothing: upload install steps until the WordPress.org listing is live, the setup order (AI key, then Scan my site), Slack and Telegram alerts live in Pro, the full Pro feature lists, "smart search (meaning-based search)", the "not on your site" line, the language count (12 pages, interface in 16), the restaurant PDF answer, the /docs/ai-providers/ title and the legal page details. A page edited by hand since the import is skipped and listed in an admin notice. It also sets the site title to "Talkwyn" (only if it was "talkwyn"), the tagline (only if empty), the Rank Math organization name, noindex on the WooCommerce shop, cart, checkout and account pages, and moves WooCommerce's sample refund draft to the Trash. |
| Checkout (2.16.0) | A plain "Checkout" heading; the trust notes became one "Buy with confidence" card under the order summary (after the Place order button on phones): refund window, key right away, renewal, Pro keeps working, staging sites, secure payment (payment methods and HTTPS, when set up) and the numbered invoice. /cart/ is not a step: it goes to checkout when the cart has a product, else to /pricing/, and an empty checkout goes to /pricing/ too (return false from `talkwyn_skip_cart` to keep the cart page). |
| My Account notices (2.16.1) | No "has been added to your cart" notice: the pricing buttons go straight to checkout, so it only showed up later, twice, on another page. Other classic WooCommerce notices (My Account, password reset) now look like the block notices: soft green or panel background, a small icon, and the action as an underlined link on the right instead of a red pill. |
| Checkout header (2.16.2) | A "Back to pricing" pill above the Checkout heading (Back to homepage when there is no /pricing/ page), a one line note under it, and less empty space between the heading and the form. |
| Card pills (2.16.3) | The small value pills in the side cards (for example "Groq, Gemini, OpenRouter, Cloudflare" in "Before you start" on /download/) stay on one line on desktop and tablet instead of breaking in two right aligned lines. On phones each value sits under its label, lined up with it, as a chip with softer corners; a value too long for the card takes two lines inside the chip. |
| Checkout (2.15.4) | The close button on WooCommerce notices is a small round X (the theme's red button style no longer turns it into an empty pill). The trust notes show as a grid of small cards and add "Staging and local sites never count toward your plan" and "Help from the Talkwyn team by email". HTTPS is detected from the site address too, so it shows behind Cloudflare. |
| Checkout trust (2.15.3) | Above checkout, only facts the site can back: the refund window and renewal, where the key appears, numbered invoices, "If you do not renew, Pro keeps working", the payment methods WooCommerce has turned on ("We never see your full card number"), and "Encrypted connection (HTTPS)" when the site runs on HTTPS. No made-up seals. |
| Checkout notes (2.15.2) | The notes above checkout tell the truth about renewals ("No automatic renewal", or "Cancel renewal anytime" only with WooCommerce Subscriptions) and the key ("appears right after payment and stays in your account", or "arrives by email" only when the Hub's Keys in emails is on). |
| Mobile menu (2.15.1) | The groups in the phone menu (Product, Integrations, Industries, Languages, Resources) start closed and work as an accordion: opening one closes the others. Before, a CSS rule kept every group open. The homepage "How it works" and the WordPress menu item follow the playbook: install and add an AI key, then scan, with no "one click from your dashboard" until the WordPress.org listing is live. |
| Cookie banner (2.15.0) | Site Settings → Cookies and popups. "Automatic" shows the banner only when the site uses a cookie that needs consent: Google Analytics 4, or the Talkwyn Hub partner cookie. Plausible sets no cookies and needs no banner. "Only essential" and "Accept all" are equally easy; the choice is kept in the essential `tw_consent` cookie for 180 days and passed to the WP Consent API when that plugin is active. GA4 loads only after "Accept all", and the Hub's `twh_ref` cookie waits for it (the referral is kept in the shop session meanwhile). "Cookie settings" in the footer reopens the banner. |
| Trial popup (2.15.0) | On the homepage, the free trial popup opens once the visitor has scrolled 50% of the page (setting), then not again for 7 days (setting). It waits until the cookie banner is answered, never opens over another dialog, does not show to the team or to customers who already had a trial, and focuses the close button so phones do not open the keyboard. |
| Empty settings (2.15.0) | A field saved empty before it had a default (prices, regular prices, the hosted range) now uses the default. Empty fields show what they fall back to as grey placeholder text (contact email, privacy email, free ZIP from Talkwyn Hub, payment methods from WooCommerce). With no ZIP URL set, the download page offers the latest free release uploaded to Talkwyn Hub. |
| Launch content (2.14.0) | Fix set 3 (`inc/content-fixes-3.json`, same per-page, all-or-nothing rules) makes the 12 alternative pages at least 60% unique (tool-specific reasons, "what X does well", pricing model, a "Moving from X to Talkwyn" section, tool-specific FAQ, "Last reviewed October 2026"), adds the internal links from playbook 6.2, sets Rank Math focus keywords where a page has none, changes "offers to connect the visitor with your team" to "offers to have your team follow up", and creates 7 new posts from playbook 5.2 as scheduled posts (10 Nov to 22 Dec 2026) with covers in `assets/blog/`. Links to scheduled posts sit in `[tw_when_live path="/blog/slug/"]...[/tw_when_live]`, which shows its text only once that post is published. A page already fixed is not listed as skipped. |
| Prices | Since 2.13.0 a plan card shows the regular price struck through when the price is lower (a WooCommerce sale price, or the manual "regular price" fields), a "Founding price" label, and the monthly equivalent ("about $4.08 a month, billed yearly"). Manual defaults follow the playbook: founding $49, $109, $239, regular $79, $179, $399. `[tw_cost_chart]` and the cost explainer show 3 years of Personal and Business (first year at the regular price, then two renewals with the Hub renewal discount) against the hosted range in "3-year cost of a typical hosted AI chat plan". The old chart placeholder on the best plugins post shows this chart. |
| Legal pages | Since 2.13.0 the privacy, terms and refund pages read the business name, address, privacy email, governing law, payment, hosting and email services, minimum age, support reply time and "Last updated" date from **Legal pages** in Site Settings. Empty fields show in square brackets, and the draft notice (`[tw_legal_draft]`) stays until every business field is filled and "A lawyer has reviewed the legal pages" is ticked. |
| Spam protection | Since 2.10.0. Add a free Cloudflare Turnstile site key and secret key (dash.cloudflare.com, Turnstile, Add widget, add your domain) and tick the forms to protect: contact, waitlist, free trial form and popup (Talkwyn Hub 1.6.2 or newer), and the WooCommerce log in, register and lost password forms (including the Log in popup). Empty keys turn the check off everywhere. The Turnstile script loads only when a form with a check is on screen. On the contact, waitlist and trial forms the honeypot, fill time and rate limits keep running either way. |
| Social links | Only the filled ones appear in the footer. |
| Analytics | `none`, `ga4` (Measurement ID) or `plausible` (domain and script URL). Logged-in editors are never tracked. |
| Live demo shortcode | When the Talkwyn plugin runs on this site, paste its inline shortcode and the homepage demo becomes the real chatbot. Until then the demo is a scripted preview and says so. |
| Tidio, Chatbase, Intercom price notes and dates checked | The comparison pages show these notes with the date you checked. Check each vendor's pricing page before filling them in. |

Events sent when analytics is on:

- `trial_click` (every Start free trial button) and `trial_start` (trial form submitted)
- `install_click`
- `demo_question`, `demo_lead_saved`
- `waitlist_signup` (location = platform)
- `pricing_view`, `checkout_start`, `pricing_cta`
- `partner_application`, `partner_apply_click`
- `doc_feedback`

## Editing pages

Pages are ordinary block content, so edit them in the block editor. Most sections are groups with Talkwyn block styles:

- **Ink band** is the dark rounded band. Use it for one strong moment per page (the end-of-page CTA already is one).
- **Card** and **Linen card** are the soft rounded cards.
- **Buttons** are pills: Ink (primary), Red (`tw-trial` class, for Start free trial), White and Outline.

### Homepage sections are patterns

Each homepage section lives in `patterns/home-*.php`, and `patterns/page-home.php` lists them in order. Setup copies the patterns into the Home page, so you can:

- **Edit text in the editor:** open Home and change any block directly.
- **Add a section to another page:** in the inserter open Patterns → Talkwyn, then pick one (for example "Final call to action" or "FAQ").
- **Change a section for good (developers):**
  1. Edit the pattern file.
  2. Bump `Version` in `style.css`. WordPress caches theme patterns per version.
  3. Rerun setup with `overwrite`, or re-insert the pattern.

### Dynamic pieces are shortcodes

These keep prices, settings and data in one place. Use them in a Shortcode block.

| Shortcode | Output |
|---|---|
| `[tw_plans]` | Pricing cards with Buy buttons |
| `[tw_compare_plans]` | Plan comparison table |
| `[tw_pricing_teaser]` | Short price summary for the homepage |
| `[tw_founding style="panel"]` | Founding member offer (`style="band"` for the Ink version) |
| `[tw_trial_block]` | The free trial block (`#trial`) with the Hub's start form |
| `[tw_partner_terms]` | Live partner terms cards |
| `[tw_integrations set="home"]` | Integration cards with status pills (`set="all"` adds Elementor) |
| `[tw_waitlist platform="Shopify"]` | Waitlist form (email, platform, website) |
| `[tw_cost_explainer]` | Yearly license vs monthly tool comparison |
| `[tw_renewal_discount]` | Renewal discount sentence, read from the Hub |
| `[tw_download_box]` | Download steps and button for `/download/` |
| `[tw_changelog]` | Release notes from the Hub |
| `[tw_compare_tidio]` | Tidio comparison table |
| `[tw_hero_chat]`, `[tw_live_demo]` | Homepage chat sample and demo |
| `[tw_value key="refund_days"]` | Any Site Settings value inline (`suffix`, `fallback` optional) |
| `[tw_badge type="soon"]` | "Coming soon" badge. Use it on every feature that isn't shipped. |
| `[tw_contact_form]` | Contact form with nonce, honeypot and rate limiting |
| `[tw_breadcrumbs]`, `[tw_toc]`, `[tw_docs_nav]` | Navigation helpers |

A paragraph can also read a setting through the `talkwyn/value` block binding.

### Header, footer and menus

The header (mega menu), footer and the end-of-page CTA band are dynamic blocks rendered from `inc/header.php`, so they stay in sync with Site Settings.

- Edit menu items in `talkwyn_menu()` or with the `talkwyn_menu` filter. Panels open on hover and click, close on Escape, and work with the keyboard (arrow keys move between items). Under 1024px a full-screen menu with accordions replaces them.
- Footer columns are in `talkwyn_footer_columns()`.
- The CTA band shows the trial. A page with the custom field `_tw_cta` = `waitlist` (and `_tw_platform`) shows the waitlist instead; `none` hides it.
- Every "Install free" link follows the WordPress.org URL setting automatically.

## Adding a doc

1. Go to Docs → Add New. Docs are their own post type, served at `/docs/your-slug/`.
2. Write the content. Every H2 and H3 gets an anchor and appears in the "On this page" list.
3. Pick a **Doc category** (for example Getting started). The left sidebar and `/docs/` group docs by category.
4. Use **Page attributes → Order** to set the position inside the category. Use **Parent** for a sub-page.
5. Write a summary in the Excerpt box. It shows on `/docs/` and becomes the meta description.

"Was this helpful?" answers are stored per doc. The Docs list shows a "Helpful (yes / no)" column.

Since 2.11.0 a doc page is a full-width app: a sidebar with a search box (filters the guide list as you type) and a "Stuck on a step?" card, a 760px reading column with reading time and last update, and "On this page" on the right that highlights the section in view. On phones the sidebar folds into a "Browse the docs" button. An H2 that starts with a number ("1. Install the plugin") gets a round step badge.

### Screenshots and diagrams

`[tw_shot key="scan"]` prints a screenshot from `assets/docs` with its alt text and caption. Add `caption="..."` to change the caption. Keys:

| Key | Shows |
|---|---|
| `plugins`, `scan`, `providers`, `appearance`, `conversations`, `woo` | Real WordPress and Talkwyn admin screens |
| `lead-email` | A real lead email, with sample visitor details |
| `chat-sources`, `chat-offer`, `chat-delivery`, `chat-dental`, `chat-hotel`, `chat-spanish`, `chat-arabic`, `chat-viewing`, `chat-night` | The real chat widget with sample businesses, captioned as examples |
| `re-answer`, `re-leads`, `es-flow`, `hi-flow`, `faq-index` | Step diagrams drawn in HTML |
| `kinds`, `scan-scope` | Two-column comparisons drawn in HTML |
| `faq-page`, `question-log`, `shipping` | Outlines drawn in HTML |

Pages imported from an older theme still hold grey "Screenshot of ..." boxes in the database. They are swapped for the matching screenshot or diagram when the page is shown, so nothing needs re-importing. Boxes for third-party screens (Groq console, Cloudflare dashboard) and a cost chart without data are hidden. To refresh a screenshot, replace the file in `assets/docs` with one of the same name (WebP, about 1600px wide) and bump the theme version.

## Adding a blog post

1. Go to Posts → Add New. Pick one category (Guides, Multilingual, WordPress, Comparisons or Industries). Tags are noindex and not used.
2. Add a featured image of 1200 × 630 or larger. It is the card image and the social image.
3. Write a summary in the Excerpt box.
4. Link to at least one product page and one doc. Every page should have at least two links pointing to it.

Category archives with fewer than 3 posts are noindex until they fill up.

## SEO

- **With Rank Math active**, the theme stops printing its own:
  - title and meta description
  - canonical
  - Open Graph and Twitter tags
  - Organization, WebSite and Article schema
  - BreadcrumbList schema

  There are never duplicate tags.

  Setup writes each page's title, description and noindex flag into Rank Math's fields. Edit them later in Rank Math's box on each page.

  In Rank Math:
  - turn off its breadcrumbs (the theme shows its own)
  - keep its sitemap
  - in Titles & Meta, set Docs and Doc categories to index
- **Without an SEO plugin**, the theme's "Talkwyn SEO" box on each page and post sets the title, description, social image and noindex. Sitemaps are WordPress's own at `/wp-sitemap.xml`, with noindex pages, users, tags and WooCommerce pages left out.
- **The theme always adds:**
  - SoftwareApplication schema on the homepage
  - Product with Offers on pricing (prices from Site Settings)
  - FAQPage wherever a group has the `tw-faq` class
  - visible breadcrumbs
- **No rating or review schema.** Add it only once real reviews exist.
- **Placeholder pages** are noindex until you write them (setup lists them). When one is ready:
  1. Write it.
  2. Untick noindex, in Rank Math or the Talkwyn SEO box.
  3. Remove the placeholder notice.
- **WooCommerce pages:** the shop, product and product category pages redirect to `/pricing/` (filter `talkwyn_shop_redirect`, return false to keep them). Cart, checkout and My Account are noindex.

## Motion and visual effects

Effects live in `assets/css/home.css`, `assets/css/theme.css` and `assets/js/theme.js`.

- The hero word rotates through languages (only that word moves). With reduced motion it reads "in their language."
- The product inbox rises from the hero card, and sample chats play with Rising Dots typing.
- Sections fade and rise in on scroll; cards and steps stagger. Cards glow softly under the pointer.
- The language marquee scrolls and pauses on hover.

**Hooks for your own blocks** (add these attributes in a Custom HTML block):

| Attribute | Effect |
|---|---|
| `data-tw-reveal` | Fade in on scroll. Values `left`, `right` and `zoom` change the direction. |
| `data-tw-stagger` | Reveals the children one after another. |
| `data-tw-play` on a `.tw-chat` | Plays the chat once. Use `loop` to repeat it. |
| `data-tw-count="100"` | Counts up to the number. |
| `.tw-spot` | Pointer glow on a card. |

**Safety rules**

- With reduced motion turned on, or without JavaScript, everything is shown immediately and nothing moves.
- Looping effects pause when off screen.
- Hidden states keep their space, so nothing shifts the layout.

## Performance notes

- **Font subsets.** The fonts ship as small subsets with the full files as fallback. Headings use Plus Jakarta Sans (Latin). The Arabic and Hindi fonts are cut to the characters the site uses today. A browser downloads the full file only when a page needs a character outside the subset, so new text always renders. To regenerate the subsets after big copy changes, see `assets/fonts/README.md`.
- **Leaner marketing pages.** WooCommerce's jQuery, cart scripts and shop CSS load only on cart, checkout and My Account. Filter: `talkwyn_trim_woocommerce_assets`.
- **Hosting.** Use a host with gzip or Brotli, long cache headers for `/wp-content/` and a page cache. The Lighthouse numbers in the launch checklist assume that.

## Files

```
talkwyn/
  style.css, theme.json, functions.php, screenshot.png
  inc/          settings, header (mega menu, footer, CTA band), components, homepage, pricing, docs, SEO, analytics, forms, waitlist, redirects, WooCommerce, installer
  templates/    front page, pages (default, landing, pricing, no title, checkout), single, docs, archives, search, 404
  parts/        header, header-checkout, footer, cta-band
  patterns/     homepage sections and the full homepage
  assets/       css, js, fonts, brand (logo, icons, social images)
  setup/        setup.php (WP-CLI) and content/pages.json
```
