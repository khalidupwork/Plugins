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
4. In **WooCommerce → Settings → Accounts & Privacy**, allow customers to create an account during checkout. Guests still get their key by email and on the thank-you page. If they create an account later, they add the license to it with **My Account → Licenses → Add license** by entering the full key. Licenses are deliberately not linked by email alone, because WordPress doesn't always verify email ownership.

## 3. Map products to licenses

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

## 6. Admin

| Screen | What you can do |
|---|---|
| Dashboard | Active licenses, new this month, expiring in 30 days, expired not renewed, renewal rate, revenue, active sites, plugin version distribution, SVG charts |
| Licenses | Search by last 4 / full key / email / domain; filter by status, plan, product; bulk suspend/reactivate/revoke/export; CSV export of licenses and activations |
| License detail | Edit expiry, limit, plan, features, status (suspend/revoke/reactivate); view and deactivate activations; event log; linked orders; resend the license email; private notes |
| Create license | Manual licenses for giveaways and partners. Linked to an existing user with that email; otherwise the recipient adds it with the key. |
| Releases | Upload, edit, deactivate, delete |
| Products | Software products, icons, banners and the description shown in "View details" |
| Logs | Filter by type, license and date |
| Settings | Email templates (placeholders), reminder days, renewal discount, rate limits, dev domains, log retention, signing keys (view, rotate), delete data on uninstall, system status |

All screens require `manage_woocommerce` (filter: `twh_admin_capability`).

## 7. Emails

Emails are branded HTML with a plain-text version for clients that don't show HTML. The layout has:

- a Plum header with the logo
- the message
- the key in a Lilac panel
- one Plum button
- a footer

Subjects and message text are edited under **Talkwyn Hub → Settings**. They are plain text with placeholders; line breaks become paragraphs.

To change the layout, copy `templates/emails/branded.php` to `yourtheme/talkwyn-hub/emails/branded.php`. To change one email before it is sent, use the `twh_email` filter (subject, HTML, text, recipient).

| Email | When |
|---|---|
| License issued | Order paid; manual creation (optional); "Resend" in admin |
| Reminder | N days before expiry (default 30 and 7), with a one-click renewal link |
| Expired | On expiry, with a one-click renewal link |
| Renewed | After a renewal payment |
| Admin notice | License revoked (refund, cancellation, chargeback); a renewal or upgrade rolled back |

Keys are also shown in the WooCommerce order email, on the thank-you page and in the order view. In admin order emails they are masked.

## 8. Security notes

- Every REST response is signed with Ed25519. Clients verify the signature, the echoed nonce and `server_time` (±10 min).
- Keys are looked up by SHA-256 hash and stored encrypted (sodium secretbox) so they can be shown to the customer again. Logs and CSV exports only contain the last 4 characters.
- IP addresses are stored only as keyed HMAC hashes.
- Download tokens are HMAC-signed, bound to a license and a release, and expire after 10 minutes.
- Every admin action checks the capability and a nonce. Every query uses `$wpdb->prepare()`. All output is escaped.

## 9. Uninstall

Deleting the plugin keeps all data unless **Settings → Delete all data on uninstall** is checked. When it is checked, uninstalling removes tables, options, product mapping meta, signing keys and release ZIPs. A custom `TWH_RELEASES_DIR` folder itself is kept.

## 10. Development

```bash
cd talkwyn-hub
composer install
composer test   # PHPUnit: keys, domains/dev detection, activation limits, expiry/renewal math, signatures, download tokens
composer lint   # php -l on every file
```

### Layout

```
talkwyn-hub/
  talkwyn-hub.php          bootstrap, constants, HPOS declaration
  uninstall.php
  includes/
    Domain/                pure logic (unit tested): KeyGenerator, Domain, ActivationPolicy,
                           ExpiryCalculator, Signer, DownloadToken, Crypto, Markdown, ZipInspector
    Install/               Schema (dbDelta + versioning), Installer
    Repository/            Products, Releases, Licenses, Activations, Events
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
