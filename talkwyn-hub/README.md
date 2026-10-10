# Talkwyn Hub

A license, activation and update server for Talkwyn commercial products, built as a WordPress + WooCommerce plugin. It runs on talkwyn.com.

A customer buys on talkwyn.com and gets a license key immediately. They paste the key into Talkwyn on their own site: Pro features turn on and updates arrive in their WordPress dashboard. Licenses, sites, downloads, invoices and renewals are managed from **My Account** on talkwyn.com.

- **Requirements:** WordPress 6.4+, PHP 8.0+ with `sodium` and `zip`, WooCommerce 8+ (HPOS compatible). MySQL/MariaDB with InnoDB.
- **Payments:** gateway-agnostic. The hub only reacts to WooCommerce order events. Tested flows assume the official *WooCommerce Stripe Payment Gateway*.
- **Optional:** WooCommerce Subscriptions for automatic renewals. Without it, the built-in manual renewal flow is used.

Docs: [API.md](API.md) · [QA-CHECKLIST.md](QA-CHECKLIST.md) · [client SDK](../client-sdk/README.md)

---

## 1. Install

1. Zip the `talkwyn-hub` folder, or copy it to `wp-content/plugins/`. Then activate it under **Plugins**.
2. Add a secret to `wp-config.php`, above the "That's all, stop editing!" line:

   ```php
   // 64 hex chars. Generate with: php -r "echo bin2hex(random_bytes(32));"
   define( 'TWH_SECRET_KEY', 'put-a-long-random-value-here' );

   // Recommended: store release ZIPs outside the web root.
   define( 'TWH_RELEASES_DIR', '/home/talkwyn/private/talkwyn-releases' );
   ```

   `TWH_SECRET_KEY` encrypts stored license keys and the signing key, and signs download tokens. **Never change or lose it.** If you add it after licenses already exist, existing data is re-encrypted automatically on the next page load.
   Without it, the hub falls back to your WordPress auth salt and shows a warning.
3. On activation the hub:
   - creates its tables,
   - generates the Ed25519 signing key,
   - creates the default software product `talkwyn-pro`,
   - registers the My Account endpoints,
   - schedules the daily cron.
4. Go to **Settings → Permalinks** and click **Save** once if `/my-account/licenses/` returns a 404.
5. Make sure WP-Cron runs. On low-traffic sites, set a real cron that calls `wp-cron.php` every 5 to 15 minutes.

### Nginx

`.htaccess` files are ignored by Nginx. If you don't use `TWH_RELEASES_DIR`, deny direct access:

```nginx
location ^~ /wp-content/uploads/talkwyn-hub-releases/ { deny all; }
```

The file names are random 128-bit strings anyway, but defense in depth is cheap.

## 2. Configure WooCommerce + Stripe

1. Install **WooCommerce** and **WooCommerce Stripe Payment Gateway**. Connect your Stripe account under **WooCommerce → Settings → Payments → Stripe**.
2. Configure the Stripe webhook as the Stripe plugin instructs. The hub relies on WooCommerce order statuses that the webhook sets:

   | Event | Order status | Hub action |
   |---|---|---|
   | Payment succeeded | `processing` / `completed` | Issue licenses (idempotent) |
   | Full refund | `refunded` | Revoke licenses; roll back renewals/upgrades; notify admin |
   | Partial refund | unchanged | Log only |
   | Cancelled | `cancelled` | Revoke |
   | Dispute opened | paid → `on-hold` | **Suspend** licenses (reversible) |
   | Dispute won | `on-hold` → `processing`/`completed` | Reactivate the suspended licenses |
   | Dispute lost | → `failed` | Revoke |

3. Virtual + downloadable is not needed. Mark license products as **Virtual** so no shipping is asked. Downloads are delivered by the hub.
4. In **WooCommerce → Settings → Accounts & Privacy**, allow customers to create an account during checkout. With the default settings guest checkout is turned off for license products (see 5c-2), so every buyer has an account. If you turn on **Keys in emails**, guests can buy too and get their full key by email and on the thank-you page. If a guest creates an account later, they add the license to it with **My Account → Licenses → Add license** by entering the full key. Licenses are deliberately not linked by email alone, because WordPress doesn't always verify email ownership.

## 3. Map products to licenses

**One click (since 1.6.7):** Talkwyn Hub → Products → **Store setup** → **Create the Talkwyn plans**. It creates the software products `talkwyn-pro` and `talkwyn`, and a hidden, virtual WooCommerce product "Talkwyn Pro" with a Plan attribute and three variations: Personal (1 site), Business (5 sites) and Agency (unlimited, `pro,white_label`), each for 365 days. Prices come from the Talkwyn theme's Site Settings (regular price, and the founding price as the sale price), or $79/$49, $179/$109 and $399/$239. It also writes the variation IDs into the theme's Site Settings, so the pricing buttons go straight to checkout. It also sets the Talkwyn app icon (red) as the product image, so the cart, checkout and emails do not show WooCommerce's default picture. For a product created with 1.6.7, the card offers "Add the Talkwyn product image". Running it again creates nothing new. The manual way is below.

Edit a WooCommerce product → **Product data → Talkwyn Hub** tab:

| Field | Meaning |
|---|---|
| Issue license keys | Turn licensing on for this product |
| Software product | e.g. `talkwyn-pro` (manage under **Talkwyn Hub → Products**) |
| Activation limit | Production sites. `0` = unlimited. Dev/staging sites never count. |
| License duration (days) | `365` for yearly, `0` = lifetime |
| Plan slug | `personal`, `business`, `agency`, `lifetime`… The client plugin receives it. |
| Features | Comma-separated flags sent to the client, e.g. `pro` or `pro,white_label` |

**Recommended setup:** create one **variable product** "Talkwyn Pro" with a "Plan" attribute. Set the software product on the parent, and give each variation its own limit, duration, plan and features:

| Variation | Limit | Duration | Plan | Price (example) |
|---|---|---|---|---|
| Personal | 1 | 365 | personal | $49 |
| Business | 5 | 365 | business | $99 |
| Agency | 0 | 365 | agency | $199 |
| Lifetime | 5 | 0 | lifetime | $399 |

Empty variation fields inherit the parent values. One license is created per unit purchased.

### Renewals

- **Without WooCommerce Subscriptions:** the "Renew" button (My Account and reminder emails) adds the license's own product or variation to the cart at its current price minus the **renewal discount** (Settings, default 20%). Paying extends the license from the later of now and its current expiry. Renewal links in emails work without logging in.
- **With WooCommerce Subscriptions:** sell the plans as subscription products. Licenses are linked to the subscription, and every paid renewal extends them. Switching plans (upgrade/downgrade) keeps the same key. Reminder emails are skipped for licenses that renew automatically.

### Upgrades

On the license detail page in My Account, customers see more expensive plans of the same product with the same term type (yearly → yearly, lifetime → lifetime). The price is `(target price − current price) × remaining days / term days`, charged as a custom cart price. After payment, the **same key** gets the new plan, limit and features, and the expiry stays the same.

## 4. Upload a release

Upload a stable release for every paid product (Talkwyn Pro) before you sell it or open trials. Without one, customers see no Pro download in their account and get no updates. Since 1.6.1 the Hub screens show a warning while a product has no stable release.

1. Build the Talkwyn Pro ZIP. It must contain the plugin folder, e.g. `talkwyn/talkwyn.php`.
2. **Talkwyn Hub → Releases → Upload a release**:
   - Pick the product, choose the ZIP and enter the version.
   - The hub opens the ZIP, finds the main plugin file and **rejects the upload if its `Version:` header differs** from what you typed.
   - "Requires WP", "Requires PHP" and "Tested up to" are read from the plugin header and `readme.txt` if left empty.
   - Write the changelog in Markdown, pick the channel (`stable`/`beta`) and keep "Active" checked.
3. Sites with an active license see the update within a few hours. Clicking "Check again" under **Dashboard → Updates** shows it immediately.

To pull a broken release, uncheck **Active**. Clients then see the previous active version again.

## 5. Connect the client plugin

1. Copy `client-sdk/class-talkwyn-license-client.php` into the Talkwyn plugin.
2. Copy the **active public key** from **Talkwyn Hub → Settings → Signing keys** into the client config. See [`client-sdk/example-integration.php`](../client-sdk/example-integration.php).
3. Render `talkwyn_license()->render_settings()` on the plugin's License tab.

## 5a. Free trial (since 1.1.0)

A 15-day Pro trial that needs no card. Settings live under **Talkwyn Hub → Settings → Trial**: on/off, length, the plan it includes, the reminder days (default 5 and 2 days left), the disposable email list, and the card mode.

- **Start it:** put `[twh_trial_form]` on a page (the website uses `/pricing/#trial`; the header popup uses `[twh_trial_form labels="hidden"]` for placeholders only). The form asks for first name, email and website and sends over AJAX (it still works without JavaScript). It has a honeypot, a minimum fill time, a fresh nonce per submit and a limit of 5 starts per IP per hour.
- **Confirm first (since 1.4.0):** the form only sends a "Confirm your email" message. Nothing is created until the link in it is opened (valid 48 hours, single use, one email per address every 2 minutes). Opening it creates the customer account if the email has none (role `customer`), issues the trial license, signs a brand-new account in and opens **My Account → Licenses**. The second email carries the key plus a "Set your password" link (24 hours; "Lost password" works any time). Existing accounts get the key added and a "log in any time" line instead, and are never signed in by the link.
- **One per person and per site:** the same email (Gmail dots and `+tags` ignored), the same domain, disposable emails, `localhost` and dev hosts are refused.
- **What they get:** a real key for 1 site with the plan's features, in the email and in My Account.
- **During the trial:** "Trial: X days left" in My Account and in the plugin, reminder emails, and an "Upgrade now" box on the license page.
- **At the end:** the daily cron expires it and sends "Your trial has ended". The plugin falls back to the free plan.
- **Upgrade:** keeps the same key. The checkout line is "Upgrade trial to Business" and the term starts on the payment day. A refund turns it back into an ended trial.
- **Card mode (optional):** set **Card on file** and pick a WooCommerce Subscriptions product with a free trial period. The trial is then a subscription that charges on day 16 unless cancelled. Until that product exists, the no-card form is used and an admin notice says why.
- **Reporting:** the Dashboard shows trials started, active, trial-to-paid rate and average days to convert.

### Spam check on the trial form (since 1.6.2)

Two filters let a theme or plugin add a captcha to the trial form:
- `twh_trial_form_extra` (string): markup printed before the button.
- `twh_trial_verify` (bool, submitted fields): return anything other than `true` to refuse the request. The visitor sees "Please complete the spam check and try again."

On a failed request `trial.js` fires the `twh:trial_error` event (`detail.form`), so the widget can be reset. The Talkwyn theme 2.10.0 uses these for Cloudflare Turnstile (Appearance, Talkwyn Site Settings, Spam protection).

## 5b. Partners (referral program, since 1.1.0)

Settings live under **Talkwyn Hub → Settings → Partners**: on/off, commission on new sales (default 20%), on renewals (default 0, first payment only), cookie days (60), approval delay (30 days, the refund window), payout threshold (50) and payout methods.

1. A logged-in customer applies from **My Account → Partners** (website, how they will promote, payout method and details, terms). The admin gets an email.
2. Approve them under **Talkwyn Hub → Partners**. They get an email with their link.
3. Their link is `/r/their-code` (or `?ref=their-code` on any page; `/r/code?to=/pricing/` lands on a chosen page). A visit stores a signed first-party cookie for the cookie window. Last click wins. A coupon linked to the partner also counts, at checkout time.
4. A paid order creates a **pending** commission. A trial started from their link pays when it upgrades. Refunds, cancellations and chargebacks reject it; partial refunds scale it. Self-referrals (same account, email or payment fingerprint) are rejected.
5. After the approval delay the daily cron marks it **approved**. When a partner reaches the threshold, the Payouts tab lists them (CSV export). Pay them with their method and click **Mark paid** with the reference. They get an email.

Partners see clicks, trials, referrals, pending, approved and paid totals, a chart, a link builder with a QR code, marketing banners and copy in **My Account → Partners**. Trial users are never named to partners.

If a consent plugin that supports the WP Consent API is active, the cookie is only set with marketing consent (the WooCommerce session still keeps the referral for that visit). Filter: `twh_referral_cookie_consent`.

## 5c. Invoices (since 1.2.0)

Every paid order gets the next invoice number (prefix plus five digits, for example `TW-00001`) when it moves to Processing or Completed. Set the prefix and your company name, address, tax or VAT ID, billing email and a footer note under **Talkwyn Hub, Settings, Invoices**.

Customers open a printable invoice from **My Account, Orders and invoices** (the Invoice button), from the order page, and from the link in their order email. The page has a "Print or save as PDF" button. A link only opens for the customer, a shop manager, or someone holding the order key from the email. Older `/my-account/twh-invoice/ID/` links redirect to the new invoice. If a dedicated PDF invoice plugin is active, Talkwyn Hub leaves invoices to it.

## 5c-2. Keys stay in the account (since 1.6.0)

**Talkwyn Hub → Settings → General → Keys in emails** is off by default:

- Emails (license issued, trial started, WooCommerce order emails) show only `TALK-****-****-****-ABCD` and a button to the customer dashboard, where the full key is shown (Licenses page, thank-you page).
- Because the key is not emailed, buying a license product needs an account: guest checkout is turned off for carts with a license product, and WooCommerce asks for an account at checkout.
- Turn the setting on to email full keys again.

**Trial → paid keeps the key:** when a customer who has a trial buys a plan of the same product (any route, not only the upgrade link), the trial license itself becomes the paid license: same key, new plan, sites and term from the payment day. No second license is created.

## 5d. Customer dashboard (since 1.5.0)

Logged-in customers see My Account as a full-screen dashboard: a sidebar (Overview, Downloads, Licenses, Billing, Account, Partner, Support, a "Need more sites?" box and their name), a top bar with the page title and **Log out**, and the WooCommerce pages inside it. Logged-out visitors still get the normal login page.

- **Overview:** welcome line; a download banner (free plugin, plus **Talkwyn Pro .zip** with an active license); Pro features included, connected sites (used/limit) and licenses; "Features in your plan" (or "Unlock with Pro" with a trial button); the current plan with site slots and end date; the latest sites with Live or Dev.
- **Free plugin download:** create a product with the slug `talkwyn` under **Talkwyn Hub → Products** and upload the free plugin ZIP as its release. Logged-in customers download it from the dashboard and the Downloads page. Without that release the button goes to `/download/` (filter `twh_free_download_fallback`).
- **Billing** holds Orders and invoices plus Billing address; **Account** is the WooCommerce account details form; **Support** links to `/contact/` (filter `twh_account_support_url`).
- Override the layout from the theme with `talkwyn-hub/account/app.php` and `talkwyn-hub/account/overview.php`, change the menu with `twh_account_nav`, the feature list with `twh_account_plan_features`, or turn the layout off with `add_filter( 'twh_account_app', '__return_false' )`.

## 6. Admin

| Screen | What you can do |
|---|---|
| Dashboard | Active licenses, new this month, expiring in 30 days, expired not renewed, renewal rate, revenue, active sites, plugin version distribution, SVG charts |
| Licenses | Search by last 4 / full key / email / domain; filter by status, plan, product; bulk suspend/reactivate/revoke/export; CSV export of licenses and activations |
| License detail | Edit expiry, limit, plan, features, status (suspend/revoke/reactivate); view and deactivate activations; event log; linked orders; resend the license email; private notes |
| Create license | Manual licenses for giveaways and partners. Linked to an existing user with that email; otherwise the recipient adds it with the key. |
| Releases | Upload, edit, deactivate, delete |
| Products | Software products, icons, banners and the description shown in "View details" |
| Partners | Approve or suspend partners, rate override, coupon, code; approve or reject referrals (a reason is required); payouts due, CSV export, mark paid, history; fraud signals (many sales from one IP, buys under 60 seconds after the click, high refund rate) |
| Logs | Filter by type, license and date |
| Settings | Tabs: General (reminders, renewal discount, rate limits, dev domains, log retention, uninstall), Trial, Partners, Invoices, Emails (one card per template, placeholders click to copy), Signing keys (view, rotate), System status |

All screens require `manage_woocommerce` (filter: `twh_admin_capability`).

## 7. Emails

Emails are branded HTML with a plain-text version for clients that don't show HTML. The layout has:

- a white header with the logo
- the message
- the key in a Blush panel
- one Ink pill button
- a footer

### Sender name and address (since 1.6.1)

Under **Talkwyn Hub → Settings → Emails**:
- **From name**, for example `Talkwyn`. Empty uses the site title, never "WordPress".
- **From email**, for example `hello@talkwyn.com`. Use a mailbox on your own domain, the same one your SMTP plugin logs in with. Empty keeps the WordPress default `wordpress@yourdomain`.

These apply to Hub emails only. WooCommerce has its own sender under WooCommerce → Settings → Emails. To change every WordPress email (password resets too), set the same name and address in your SMTP plugin and turn on its "force from" options.

Subjects and message text are edited under **Talkwyn Hub → Settings**. They are plain text with placeholders; line breaks become paragraphs.

To change the layout, copy `templates/emails/branded.php` to `yourtheme/talkwyn-hub/emails/branded.php`. To change one email before it is sent, use the `twh_email` filter (subject, HTML, text, recipient).

| Email | When |
|---|---|
| License issued | Order paid; manual creation (optional); "Resend" in admin |
| Reminder | N days before expiry (default 30 and 7), with a one-click renewal link |
| Expired | On expiry, with a one-click renewal link |
| Renewed | After a renewal payment |
| Admin notice | License revoked (refund, cancellation, chargeback); a renewal or upgrade rolled back |
| Trial: confirm email | Trial form submitted: one-time link to confirm the address |
| Trial started | Confirmation link opened: key, setup steps, account and set-password link (`{login_details}`) |
| Trial reminder | Days left in the trial (default 5 and 2) |
| Trial ended | Trial expired, with a one-click upgrade link |
| Trial upgraded | Trial converted to a paid plan |
| Partner application | To the admin, when someone applies |
| Partner approved | To the partner, with their link |
| New referral | To the partner, when a referral is recorded (pending) |
| Commission approved | To the partner, after the approval delay |
| Payout sent | To the partner, with amount and reference |

Keys are also shown in the WooCommerce order email, on the thank-you page and in the order view. In admin order emails they are masked.

## 8. Security notes

- Every REST response is signed with Ed25519. Clients verify the signature, the echoed nonce and `server_time` (±10 min).
- Keys are looked up by SHA-256 hash and stored encrypted (sodium secretbox) so they can be shown to the customer again. Logs and CSV exports only contain the last 4 characters.
- IP addresses are stored only as keyed HMAC hashes.
- Download tokens are HMAC-signed, bound to a license and a release, and expire after 10 minutes.
- Every admin action checks the capability and a nonce. Every query uses `$wpdb->prepare()`. All output is escaped.

## 9. Uninstall

Deleting the plugin keeps all data unless **Settings → Delete all data on uninstall** is checked. When it is checked, uninstalling removes tables (including newsletter subscribers), options, product mapping meta, signing keys and release ZIPs. A custom `TWH_RELEASES_DIR` folder itself is kept. Invoice numbers on orders and the invoice counter are kept, because invoices are accounting records and a reinstall must not reuse a number.

## 10. Development

```bash
cd talkwyn-hub
composer install
composer test   # PHPUnit: keys, domains/dev detection, activation limits, expiry/renewal math, signatures, download tokens, trial and referral rules
composer lint   # php -l on every file
```

### Layout

```
talkwyn-hub/
  talkwyn-hub.php          bootstrap, constants, HPOS declaration
  uninstall.php
  includes/
    Domain/                pure logic (unit tested): KeyGenerator, Domain, ActivationPolicy,
                           ExpiryCalculator, Signer, DownloadToken, Crypto, Markdown, ZipInspector,
                           TrialPolicy, ReferralPolicy
    Trial/                 Trial: form, start, reminders, upgrade link, conversion
    Partners/              Program, Tracking (links + cookie), Commissions, PartnerAccount (My Account)
    Install/               Schema (dbDelta + versioning), Installer
    Repository/            Products, Releases, Licenses, Activations, Events, Partners, Referrals
    Support/               Settings, Secrets, SigningKeys, Storage, Request, Time
    Woo/                   ProductTab, Mapping, OrderHandler, Cart (renew/upgrade), Subscriptions, OrderDisplay
    Api/                   RestController, Responder (signing), RateLimiter, ApiError
    Account/               My Account endpoints (licenses, software-downloads, invoice)
    Admin/                 Dashboard, Licenses, Releases, Products, Settings, Logs, Export
    Email/Mailer.php
    Cron/Daily.php         expiry, reminders, log pruning
    LicenseService.php     issue / renew / upgrade / revoke
  templates/account/       overridable from yourtheme/talkwyn-hub/account/
  assets/                  admin + account CSS/JS (no build step)
  tests/Unit/
```

### Tables

| Table | Purpose |
|---|---|
| `wp_twh_products` | Software products (`slug`, `name`, `latest_version`, meta) |
| `wp_twh_releases` | Versions per product and channel, protected ZIP path, changelog, requirements |
| `wp_twh_licenses` | Keys (hash + encrypted + last 4), plan, limit, duration, features, status, expiry, order/subscription links |
| `wp_twh_activations` | Sites per license (instance id, normalized domain, dev flag, versions, last check) |
| `wp_twh_events` | Event log (hashed IP, JSON meta), pruned daily |
| `wp_twh_partners` | Partners: user, status, referral code, rate override, coupon, payout method, encrypted payout details |
| `wp_twh_referral_visits` | Clicks on partner links (hashed IP, one per IP per day) |
| `wp_twh_referrals` | Commissions per order and license: amount, commission, status, reason, source |
| `wp_twh_payouts` | Payouts with method and reference |
