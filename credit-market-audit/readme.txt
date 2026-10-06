=== Credit Market Free Audit ===
Contributors: creditmarket
Tags: seo audit, pagespeed, lead generation, website audit, elementor
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Free website audit lead-generation tool: Google PageSpeed + basic SEO + design check, downloadable report, emailed to the visitor. Includes an Elementor widget and a shortcode.

== Description ==

Visitors enter their email and website URL. The plugin then:

1. Fetches the page and runs on-page SEO checks (title, meta description, H1, headings, image alt, canonical, noindex, lang, Open Graph, schema, content length, internal links, robots.txt, XML sitemap, response time, compression, mixed content).
2. Runs design / UX checks (responsive viewport, favicon, touch icon & theme colour, web fonts, modern image formats, image dimensions, outdated HTML, inline styles, CSS/JS count, call to action, plus colour contrast, font size, tap targets and layout shift from Lighthouse).
3. Runs Google PageSpeed Insights for mobile and desktop (scores, Core Web Vitals, screenshots, opportunities, accessibility issues).
4. Shows a short "quick wins" report on the page with "Download PDF", "Print" and "Open online" buttons.
5. Emails the report (summary + attached branded PDF + online link) to the visitor and notifies the site owner of the new lead.

= Short "quick wins" report =

By default the report only lists the top 5 issues that are easy to fix (missing meta description, alt text, Open Graph tags, favicon, sitemap, image formats…). Large jobs such as redesigns, server speed or rewriting content are left out, so prospects get an achievable to-do list instead of a long list of everything. Switch to "Full report" or change the number of issues in Settings.

= Automatic branding =

The report, PDF and email automatically use this website's own logo (Customizer → Site Identity, Elementor site logo, or site icon) and Elementor global colours. Override them in Settings if needed.

All leads are stored under Free Audit → Leads (search, view, download, resend, delete, CSV export).

== Installation ==

1. Upload the `credit-market-audit` folder to `/wp-content/plugins/` (or zip it and use Plugins → Add New → Upload) and activate.
2. Go to Free Audit → Settings and paste a Google PageSpeed Insights API key (Google Cloud Console → enable "PageSpeed Insights API" → Credentials → Create API key). Without a key Google's shared quota is usually exhausted.
3. Set branding, email texts and call-to-action.
4. Install an SMTP plugin (e.g. WP Mail SMTP) so emails are delivered reliably.
5. Add the form: Elementor widget "Free Website Audit" (category "Credit Market"), or the shortcode `[credit_market_audit]`.

== Shortcode ==

`[credit_market_audit heading="Free SEO Audit" subheading="..." button_text="Check my site" show_name="no" show_consent="yes" layout="inline" heading_tag="h2"]`

== Developers ==

* Templates can be overridden by copying `templates/form.php`, `templates/report.php`, `templates/pdf.php` or `templates/email.php` to `yourtheme/credit-market-audit/`.
* Filters: `cma_brand` (logo/colours), `cma_audit_lead` (validate/modify or reject a lead with WP_Error), `cma_client_ip` (e.g. behind a trusted proxy/CDN).
* Action: `cma_audit_started` (send the lead to a CRM, etc.).

== Changelog ==

= 1.1.0 =
* Real PDF reports (bundled Dompdf) for download and email attachment.
* Short "quick wins" report mode (default) showing only easy fixes.
* Automatic branding from the site's logo and Elementor global colours.

= 1.0.0 =
* Initial release.
