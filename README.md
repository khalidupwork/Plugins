# Plugins

## Credit Market Free Audit (`credit-market-audit/`)

WordPress plugin: a free website audit lead-generation tool. Visitors enter their email + website URL and get a report with Google PageSpeed (mobile + desktop), a basic SEO check and a design/UX check. The report is shown on the page, can be downloaded / saved as PDF, and is emailed to the visitor. Includes an Elementor widget and the `[credit_market_audit]` shortcode.

See [`credit-market-audit/readme.txt`](credit-market-audit/readme.txt) for installation and options.

**Install:** zip the `credit-market-audit` folder → WordPress → Plugins → Add New → Upload, then activate and add your PageSpeed API key under **Free Audit → Settings**.

## Talkwyn Hub (`talkwyn-hub/`)

WordPress + WooCommerce plugin that sells, licenses and delivers updates for Talkwyn Pro from talkwyn.com:

- license keys on purchase
- signed REST API: activation, checks, updates and downloads
- dev/staging sites free
- renewals, upgrades, refunds and chargebacks
- My Account licenses, downloads and invoices
- admin dashboard, logs and CSV export

See [`talkwyn-hub/README.md`](talkwyn-hub/README.md), [`talkwyn-hub/API.md`](talkwyn-hub/API.md) and [`talkwyn-hub/QA-CHECKLIST.md`](talkwyn-hub/QA-CHECKLIST.md).

## Talkwyn License Client SDK (`client-sdk/`)

Drop-in class for the Talkwyn plugin: activation, daily checks, Ed25519 signature verification, a 7-day offline grace period, and automatic updates. See [`client-sdk/README.md`](client-sdk/README.md).
