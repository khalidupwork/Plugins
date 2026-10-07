# Talkwyn theme

Block theme for talkwyn.com. It works with WooCommerce and the Talkwyn Hub plugin (licenses, updates, My Account screens and emails).

- Requires WordPress 6.5+, PHP 8.0+.
- Light first, with an optional dark mode. The header toggle cycles System, Light and Dark and remembers the choice in the browser.
- Fonts are self-hosted, so the theme makes no Google Fonts requests. Licenses are in `assets/fonts/`.
- Layouts are RTL-ready: logical CSS properties throughout, and Arabic, Urdu and Hindi fonts are applied by `:lang()`.

## Install order

1. **WordPress** 6.5 or newer. Under Settings → Permalinks choose "Post name" (setup also does this).
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
   - creates all 34 pages from `setup/content/pages.json`, plus docs and blog categories
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
| Shopify waitlist target | Leave empty to keep sign-ups on this site (Tools → Shopify waitlist, with an email to you). Or paste a form endpoint URL. |
| Contact email | Where contact form messages and waitlist alerts go. |
| Social links | Only the filled ones appear in the footer. |
| Analytics | `none`, `ga4` (Measurement ID) or `plausible` (domain and script URL). Logged-in editors are never tracked. |
| Live demo shortcode | When the Talkwyn plugin runs on this site, paste its inline shortcode and the homepage demo becomes the real chatbot. Until then the demo is a scripted preview and says so. |
| Tidio price note and date checked | The `/compare/tidio-alternative/` page shows this text with the date you checked. While empty it links to Tidio's pricing page instead of quoting a number. |

Events sent when analytics is on:

- `install_click`
- `demo_question`
- `demo_lead_saved`
- `pricing_cta`
- `checkout_start`
- `doc_feedback`

## Editing pages

Pages are ordinary block content, so edit them in the block editor. Most sections are groups with Talkwyn block styles:

- **Band** is the Plum full-width section. Use it at most for the hero plus one more band per page.
- **Card** and **Panel** are the white card and the Lilac panel.
- **Buttons** come in Primary, Secondary and On band.

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
| `[tw_founding style="panel"]` | Founding member offer (`style="band"` for the Plum version) |
| `[tw_cost_explainer]` | Yearly license vs monthly tool comparison |
| `[tw_renewal_discount]` | Renewal discount sentence, read from the Hub |
| `[tw_download_box]` | Download steps and button for `/download/` |
| `[tw_changelog]` | Release notes from the Hub |
| `[tw_compare_tidio]` | Tidio comparison table |
| `[tw_hero_chat]`, `[tw_live_demo]` | Homepage chat sample and demo |
| `[tw_value key="refund_days"]` | Any Site Settings value inline (`suffix`, `fallback` optional) |
| `[tw_badge type="soon"]` | "Coming soon" badge. Use it on every feature that isn't shipped. |
| `[tw_contact_form]`, `[tw_waitlist_form]` | Forms with nonce, honeypot and rate limiting |
| `[tw_breadcrumbs]`, `[tw_toc]`, `[tw_docs_nav]` | Navigation helpers |

A paragraph can also read a setting through the `talkwyn/value` block binding.

### Header, footer and menus

The header, footer and checkout header are template parts. Edit them under Appearance → Editor → Patterns → Template parts.

- The header menu is a Navigation block. "Log in" and the "Install free" button inside the menu appear only in the mobile overlay.
- Anything with the class `tw-install` follows the WordPress.org URL setting automatically.

## Adding a doc

1. Go to Docs → Add New. Docs are their own post type, served at `/docs/your-slug/`.
2. Write the content. Every H2 and H3 gets an anchor and appears in the "On this page" list.
3. Pick a **Doc category** (for example Getting started). The left sidebar and `/docs/` group docs by category.
4. Use **Page attributes → Order** to set the position inside the category. Use **Parent** for a sub-page.
5. Write a summary in the Excerpt box. It shows on `/docs/` and becomes the meta description.

"Was this helpful?" answers are stored per doc. The Docs list shows a "Helpful (yes / no)" column.

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

The homepage (and any page using its patterns or the CTA band) uses the effects below. They live in `assets/css/home.css` and the "Motion" block in `assets/js/theme.js`.

**Hero and backgrounds**

- The hero and the final call to action have drifting light and floating language bubbles.
- The rotating hero word draws an underline under each new language.
- The hero chat plays itself on a loop, and bot replies "type" first.

**On scroll**

- Sections fade and rise in as they enter the screen. Cards and steps stagger.
- The night section is a scroll story: the chat on the right follows the time on the left, and the rail fills as you go.
- The other sample chats play once when scrolled into view.
- The founding seats count up.

**Pointer and buttons**

- Cards light up under the pointer.
- Buttons get a light sweep on hover.

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

- **Font subsets.** The fonts ship as small subsets with the full files as fallback. Headings use an English-only cut of Bricolage. The Arabic, Urdu and Hindi fonts are cut to the characters the site uses today. A browser downloads the full file only when a page needs a character outside the subset, so new text always renders. To regenerate the subsets after big copy changes, see `assets/fonts/README.md`.
- **Leaner marketing pages.** WooCommerce's jQuery, cart scripts and shop CSS load only on cart, checkout and My Account. Filter: `talkwyn_trim_woocommerce_assets`.
- **Hosting.** Use a host with gzip or Brotli, long cache headers for `/wp-content/` and a page cache. The Lighthouse numbers in the launch checklist assume that.

## Files

```
talkwyn/
  style.css, theme.json, functions.php, screenshot.png
  inc/          settings, components, homepage, pricing, docs, SEO, analytics, forms, WooCommerce, installer
  templates/    front page, pages (default, landing, pricing, no title, checkout), single, docs, archives, search, 404
  parts/        header, header-checkout, footer, cta-band
  patterns/     homepage sections and the full homepage
  assets/       css, js, fonts, brand (logo, icons, social images)
  setup/        setup.php (WP-CLI) and content/pages.json
```
