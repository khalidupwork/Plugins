# Talkwyn launch checklist

Work through this on the live server, in order. Tick each box when done.

## 1. Server and WordPress

- [ ] HTTPS works on `talkwyn.com`, and `www` redirects to the bare domain (or the other way round, but pick one).
- [ ] PHP 8.0 or newer, WordPress 6.5 or newer, WooCommerce current.
- [ ] Gzip or Brotli is on, `/wp-content/` files have long cache headers, and a page cache is active (it must skip cart, checkout and My Account).
- [ ] Settings → General: site title "Talkwyn", the timezone, and the admin email.
- [ ] Settings → Reading: "Discourage search engines" is **off**.
- [ ] Settings → Permalinks: "Post name".
- [ ] Daily backups of the database and `wp-content/uploads` are running. Also back up the Hub release folder (`TWH_RELEASES_DIR`, outside the web root).

## 1b. Launch playbook (theme 2.13.0)

- [ ] Open the dashboard once after updating the theme: the copy fixes run. If a yellow notice lists pages, compare them with `setup/content/pages.json` and fix them by hand.
- [ ] Settings → General: site title "Talkwyn", tagline "AI chatbot for WordPress that answers in every language".
- [ ] Rank Math → Titles and Meta → Local SEO: organization name "Talkwyn", logo, URL https://talkwyn.com.
- [ ] Site Settings → Legal pages: business name, address, governing law, payment, hosting and email services, reply time. A lawyer reviews the three pages, then tick "A lawyer has reviewed the legal pages".
- [ ] Prices: WooCommerce regular prices $79, $179, $399 and sale prices $49, $109, $239 for the founding offer (or the manual fields). Pricing shows the struck-through regular price, "Founding price" and the monthly equivalent.
- [ ] Founding seats remaining is the real number.
- [ ] Rank Math noindex on shop, cart, checkout and My Account; WooCommerce's sample refund draft is in the Trash; empty the Trash.
- [ ] Menus: the theme draws its own curated header (mega menu in `inc/header.php`) and footer with the legal pages, so the page-list navigation block in the export is not used. Check that no other theme or template part shows it.
- [ ] Talkwyn Hub → Settings: From name "Talkwyn", partner commission for the launch year (the playbook suggests 30%, then 20%).
- [ ] Theme 2.14.0: Posts → Scheduled lists 7 new posts (10 Nov to 22 Dec). Read each before it publishes. The 8th gap post ("ai chatbot for customer service" pillar guide) waits until the site has 30+ referring domains.
- [ ] Live demo on the home page: with the Talkwyn plugin set up on talkwyn.com, put `[talkwyn_chat mode="inline"]` in Site Settings → Live demo. Empty keeps the scripted sample clinic.
- [ ] Theme 2.15.1: on a phone, open the menu. Every group is closed; tapping one opens it and closes the others.
- [ ] Theme 2.15.0: open the site in a private window. The cookie banner shows if GA4 or Partners is on; "Accept all" loads GA4, "Only essential" does not. Scroll the homepage to the middle: the trial popup opens once.
- [ ] Talkwyn Hub → Products: upload the free plugin ZIP as a stable release of the product with slug `talkwyn`. The download page then serves it without a ZIP URL in Site Settings.
- [ ] When the WordPress.org listing goes live: set its URL in Site Settings and switch the install steps back to "search Talkwyn".

## 2. Talkwyn Hub

- [ ] `TWH_SECRET_KEY` is set in `wp-config.php` and stored in your password manager.
- [ ] Talkwyn Hub → Settings: signing key generated. The public key is copied into the Talkwyn plugin's SDK config.
- [ ] Products: Personal, Business and Agency each have a plan slug, site limit and duration on the Hub product tab.
- [ ] Renewal discount and reminder days are set (Talkwyn Hub → Settings).
- [ ] A release ZIP is uploaded and marked stable.
- [ ] Email templates read well. Send one of each to yourself with "Resend email" on a test license:
  - license
  - 30-day reminder
  - 7-day reminder
  - expired
  - renewed
- [ ] WP-Cron runs. Use a real cron job hitting `wp-cron.php` if traffic is low.

## 3. WooCommerce

- [ ] A live payment gateway is connected and tested. Remove the "Check payments" test gateway.
- [ ] Currency is USD. Taxes are set up as your accountant advises.
- [ ] Store address is filled in (it appears in emails and invoices).
- [ ] Terms and Privacy pages are selected under Settings → Advanced (setup links them). Check their text is final.
- [ ] Settings → Accounts & Privacy: guest checkout on, and "Allow customers to create an account during checkout" on, so buyers can see licenses in My Account. (Guests can also add a key later with the claim form.)
- [ ] WooCommerce emails have the store name as sender and a reply-to address that you read.

## 4. Theme and content

- [ ] Run setup: Appearance → Talkwyn Site Settings → **Create or update site pages**.
- [ ] Site Settings:
  - [ ] refund days (14)
  - [ ] founding seats (100) and seats remaining
  - [ ] contact email
  - [ ] social links
- [ ] Free plugin download:
  - [ ] While WordPress.org review is pending, set the **Free ZIP URL** and check that `/download/` downloads it.
  - [ ] When the plugin is live, paste the **WordPress.org URL**. Every "Install free" button switches over by itself.
- [ ] **Tidio comparison:** check tidio.com/pricing, then fill in the price note and the date you checked. Until then the page links to their pricing instead of quoting a number.
- [ ] **Live demo:** when the Talkwyn plugin runs on this site, paste its inline shortcode into Site Settings. Then ask the homepage demo a real question.
- [ ] Read every page once on a phone and once on a desktop.
- [ ] Every not-yet-shipped feature shows a "Coming soon" badge.
- [ ] Replace the docs' "Screenshot coming soon" boxes with real screenshots. Each needs width, height and alt text.
- [ ] Placeholder pages stay noindex until they are written. These are still placeholders:
  - `/woocommerce-chatbot/`
  - `/features/lead-generation/`, `/features/knowledge-base/`
  - `/solutions/clinics/`, `/solutions/real-estate/`, `/solutions/ecommerce/`, `/solutions/education/`
  - `/multilingual-chatbot/arabic/`, `/multilingual-chatbot/urdu/`, `/multilingual-chatbot/hindi/`, `/multilingual-chatbot/spanish/`
  - `/compare/chatbase-alternative/`, `/compare/ai-engine-alternative/`, `/compare/intercom-alternative/`
- [ ] Testimonials, ratings, customer logos or usage numbers are added only once they are real. The site has none today on purpose.

## 5. SEO

- [ ] Rank Math is installed and its wizard finished:
  - [ ] its breadcrumbs are off
  - [ ] its sitemap is on
  - [ ] Docs and Doc categories are set to index
- [ ] View source on the homepage. There is exactly one `<title>`, one meta description, one canonical and one set of Open Graph tags.
- [ ] Sitemap: submit `/sitemap_index.xml` (Rank Math) or `/wp-sitemap.xml` to Google Search Console and Bing Webmaster Tools.
- [ ] Check `robots.txt`. It should allow the site, disallow cart, checkout, My Account and search, and list the sitemap.
- [ ] Run the homepage, `/pricing/` and one doc through Google's Rich Results Test. Expect:
  - SoftwareApplication
  - Product with Offers
  - FAQ
  - Article
  - Breadcrumbs
- [ ] Share the homepage link in a private chat or a social post preview. The Talkwyn social image and title should appear.

## 6. Analytics

- [ ] Site Settings → Analytics: GA4 Measurement ID or Plausible domain.
- [ ] In a private window, click "Install free" and pricing Buy buttons, and ask the demo a question. Confirm these events arrive:
  - `install_click`
  - `pricing_cta`
  - `demo_question`
  - `checkout_start`
- [ ] Mark `checkout_start` (and the WooCommerce purchase, if you track it) as conversions.

## 7. Test purchases (live mode, then refund)

- [ ] Buy Personal as a guest. Check each of these:
  - the thank-you page shows "You're in." with the key and a working Copy button
  - the email arrives with the key
  - the account is created
  - the license appears in My Account → Licenses
- [ ] Activate the key on a real WordPress site. It should take a slot.
- [ ] Activate on `staging.yoursite.com`. It should not take a slot.
- [ ] Deactivate a site from My Account.
- [ ] Download from My Account → Software downloads. The ZIP should install.
- [ ] Upgrade Personal to Business from the license page. You should pay only the difference.
- [ ] Refund the order. The license should be revoked and the site should lose Pro at its next check.

## 8. Quality checks

These were run before handover on the test site:

- [x] No em dash or en dash characters in theme, Hub, SDK or rendered pages.
- [x] No placeholder Latin text, no invented numbers or testimonials. Prices come from WooCommerce.
- [x] Every internal link resolves, and every page has at least 2 links pointing to it.
- [x] Every image has width, height and alt.
- [x] Each page has one H1 and no skipped heading levels.
- [x] Keyboard checks:
  - every focus stop shows a visible ring
  - the skip link works
  - the mobile menu opens with Enter and closes with Escape, and focus returns
- [x] JSON-LD parses on every page type.

Rerun the Lighthouse check on the live server (mobile, private window):

- [ ] Every indexable page: Performance 90+, Accessibility 100, Best Practices 100, SEO 100, LCP under 2.5 s, CLS under 0.1.

Results on the test site (mobile, simulated slow 4G, gzip and caching on). Each row is a single run unless it says otherwise.

| Page | Perf | A11y | Best Pr. | SEO | LCP | CLS |
|---|---|---|---|---|---|---|
| `/` (3 runs) | 96 to 97 | 100 | 100 | 100 | 1.9 to 2.3 s | 0.02 |
| `/pricing/` | 99 | 100 | 100 | 100 | 1.9 s | 0 |
| `/download/` | 99 | 100 | 100 | 100 | 1.9 s | 0.055 |
| `/features/` | 99 | 100 | 100 | 100 | 1.7 s | 0 |
| `/multilingual-chatbot/` (5 runs) | 95 to 100 | 100 | 100 | 100 | 1.5 to 2.7 s | 0 |
| `/wordpress-ai-chatbot/` | 99 | 100 | 100 | 100 | 1.7 s | 0 |
| `/shopify-ai-chatbot/` | 99 | 100 | 100 | 100 | 1.7 s | 0 |
| `/solutions/` | 99 | 100 | 100 | 100 | 1.7 s | 0 |
| `/agencies/` | 99 | 100 | 100 | 100 | 1.7 s | 0 |
| `/docs/`, `/docs/getting-started/` | 99 | 100 | 100 | 100 | 1.9 s | 0 |
| `/blog/`, `/about/`, `/contact/`, `/refund-policy/` | 99 | 100 | 100 | 100 | 1.7 to 1.9 s | 0 |
| Placeholder (noindex on purpose) | 99 | 100 | 100 | 66 | 1.9 s | 0 |
| `/checkout/` (noindex, WooCommerce block checkout) | 55 | 100 | 100 | 58 | 5.6 s | 0.21 |

Notes on the misses:

- **Placeholder pages** score SEO 66 only because they are noindex, which is the intent.
- **Checkout** is WooCommerce's own React checkout: about 55 scripts that WooCommerce loads in the head. Moving them was tested and gained nothing. It is noindex and reached only after a Buy click.
- **The 404 page** returns status 404, which Lighthouse doesn't score.
