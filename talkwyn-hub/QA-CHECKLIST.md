# Talkwyn Hub: manual QA checklist

Run this on a staging copy of talkwyn.com with Stripe in **test mode** (card `4242 4242 4242 4242`), plus a separate test WordPress site that runs the Talkwyn plugin with the client SDK. Tick each box.

**Test-site naming tip:** use a production-looking domain for the client (e.g. `client1.example.com` mapped in `/etc/hosts` doesn't work, because `*.example` is a dev pattern; use something like `qa-client1.com`). Use `*.local` for dev-site tests.

## 0. Setup

- [ ] `TWH_SECRET_KEY` is defined. Settings → System status shows ✅. No warning notice.
- [ ] Settings → Signing keys shows one `active` key. Its public key is copied into the client config.
- [ ] Product "Talkwyn Pro" (variable) has the Personal (1 site, 365 d), Business (5, 365), Agency (0, 365) and Lifetime (5, 0) variations, all mapped in the **Talkwyn Hub** tab.
- [ ] Releases: version `1.0.0` uploaded (active). The client site runs `1.0.0`.
- [ ] Uploading a ZIP whose header says `1.0.1` with version field `1.0.2` is **rejected** ("does not match").
- [ ] Uploading a ZIP without a plugin header is rejected.
- [ ] Opening the ZIP's direct URL under `/wp-content/uploads/talkwyn-hub-releases/` returns 403/404 (or `TWH_RELEASES_DIR` is outside the web root).

## 1. Buy → receive key

- [ ] As a new customer, buy **Personal** with Stripe. The order goes to `processing`/`completed`.
- [ ] The thank-you page shows the key `TALK-XXXX-XXXX-XXXX-XXXX` and a "Manage your licenses" link.
- [ ] The WooCommerce order email shows the key. The admin "New order" email shows only a masked key.
- [ ] The branded "Your Talkwyn Pro license key" email arrives, with the key highlighted.
- [ ] The order has a note "Talkwyn Hub issued license keys: …ABCD".
- [ ] Admin → Licenses shows the license: plan *personal*, limit 1, expiry in 365 days.
- [ ] **Idempotency:** change the order status to `completed`, then `processing`, then `completed`. Still exactly one license; no second email.
- [ ] Buy **quantity 2** of Business → two different keys in one email.
- [ ] My Account → Licenses: masked key, Reveal shows the full key, Copy copies it, status badge, expiry, sites `0 / 1`, Renew button.
- [ ] View page source of My Account → Licenses: the full key is **not** in the HTML.
- [ ] Buy as a guest, then register with the same email → the license is **not** shown automatically. **Add license** with the emailed key → it appears.

## 2. Activate

- [ ] On the client site, paste the key → **Activate**. The badge turns Active; plan, expiry and sites `1 / 1` are shown.
- [ ] `is_pro()` is true and Pro features work.
- [ ] Admin license detail shows the activation (domain, versions, last check). The log has an `activate` event.
- [ ] Activate again on the same site → still `1 / 1` (row reused, not duplicated).
- [ ] Enter a wrong key → "This license key is not valid." The hub logs `invalid_key` with only the last 4 characters.
- [ ] Use a key from another product (create one manually for a second product) → `wrong_product`.

## 3. Hit the limit

- [ ] Activate the same Personal key on a second production site → error "already active on 1 site(s)…" (`limit_reached`).
- [ ] Activate it on `something.local` and `staging.qa-client1.com` → works and shows "dev/staging site does not count". Sites stay at `1 / 1`.
- [ ] Clone the production client site to a new domain → the daily check (or **Check now**) gets a new instance id and tries to activate. On a dev domain it succeeds; on a production domain it reports the limit.

## 4. Deactivate

- [ ] Client → **Deactivate** → the license is removed locally; the hub shows the activation as deactivated; sites `0 / 1`.
- [ ] Activate the second production site now → succeeds.
- [ ] My Account → license detail → **Deactivate** a site → the slot is freed. On that site, the next **Check now** shows "This site was deactivated from your account" and Pro turns off.
- [ ] Admin → license detail → **Deactivate** works the same way.

## 5. Update

- [ ] Upload release `1.1.0` with a Markdown changelog.
- [ ] Client: Dashboard → Updates → **Check again** → Talkwyn shows 1.1.0.
- [ ] **View details** opens the modal with description, changelog, banners and icons.
- [ ] **Update now** succeeds even if the update transient is hours old (fresh package URL). An `update_download` event is logged.
- [ ] Copy the `package` URL from an `update/check` response and open it after 11 minutes → `This download link is invalid or has expired.`
- [ ] Set channel `beta` in the client config and upload `1.2.0-beta1` as beta → only beta sites see it.
- [ ] Untick **Active** on 1.2.0-beta1 → beta sites see 1.1.0 again.
- [ ] My Account → Software downloads lists the latest stable ZIP and the changelog. Download works.

## 6. Refund → revoke

- [ ] Fully refund the Personal order in WooCommerce (Stripe refund) → the license becomes **Revoked**. The admin gets an email "License …ABCD revoked (refund)".
- [ ] Client **Check now** → "Your license has been revoked." Pro turns off right away (signed `revoked`).
- [ ] Partial refund on another order → the log has a `refund` event with `partial: true`; the license stays Active.
- [ ] Cancel an order that had licenses → revoked.
- [ ] **Dispute:** move a paid order to `on-hold` → licenses become *Suspended*. Move it back to `completed` → *Active* again. Move it to `failed` → *Revoked*.

## 7. Expire → renew

- [ ] Admin → license detail → set the expiry to **tomorrow** → run `wp cron event run twh_daily` → nothing yet; a 7-day reminder is sent (because tomorrow is within 7 days).
- [ ] Set the expiry to 20 days ahead, clear "reminders" by saving, and run cron → the **30-day** reminder email arrives with a Renew link. Run cron again → no duplicate.
- [ ] Set the expiry to yesterday and run cron → status **Expired**; the "expired" email with a one-click renewal link arrives; an `expire` event is logged.
- [ ] Client **Check now** → "Your license has expired" with a Renew button. `update/check` still shows the new version, but without a package and with an "upgrade notice".
- [ ] Open the renewal link in a private window (logged out) → the cart contains the Personal variation at **price − 20%**, labeled "Renewal of license TALK-****-****-****-ABCD", quantity locked to 1.
- [ ] Pay → the license is Active, expiry = now + 365 days, and the "renewed" email arrives.
- [ ] Renew early (license still active with 30 days left) → expiry = old expiry + 365 days.
- [ ] Refund the renewal order → the expiry rolls back to the previous date (Expired again if that date has passed). The admin is notified.
- [ ] Tamper with the renewal URL (`twh_renew=<other id>`) → "This renewal link is not valid."
- [ ] Lifetime license: no Renew button, no reminders, `expires_at: null`.

## 8. Upgrade

- [ ] My Account → Personal license (bought today) → **Upgrade** lists Business and Agency (not Lifetime), priced about (99 − 49) × remaining/365.
- [ ] Choose Business → checkout at the prorated price → pay.
- [ ] The **same key** now shows plan *business*, limit 5, and the same expiry. The order note says "upgraded license #… to plan business".
- [ ] The client **Check now** shows Business, sites `x / 5`.
- [ ] Refund the upgrade order → the plan, limit and features roll back to Personal.

## 9. Offline / tampering (client)

- [ ] Block talkwyn.com on the client (e.g. `define( 'WP_HTTP_BLOCK_EXTERNAL', true );`) and run **Check now** → Pro stays active; after 1+ day a warning notice shows "…stay active for N more day(s)".
- [ ] Simulate 8 days offline (edit the `talkwyn_license_state` option: `last_check` = now − 8 days) → Pro turns off.
- [ ] Change one public key character in the client config → activation fails with "response could not be verified".
- [ ] Set the client server clock 15 minutes off → "server clock differs…".

## 10. Admin & ops

- [ ] Dashboard numbers match the Licenses list; the charts render; version distribution shows 1.0.0/1.1.0.
- [ ] Licenses search works for the last 4 characters, the full key, a customer email and a domain. The status, plan and product filters work.
- [ ] Bulk suspend → bulk reactivate → bulk export CSV (keys masked; no cell starts with `=`).
- [ ] Create a license manually for a partner email that has an account → it appears in their account. For an email without an account → the email is sent; after the partner registers, **Add license** with the key links it. The same key can't be added by a second account.
- [ ] Edit the expiry or limit and add a private note → an `admin_edit` event lists the changes.
- [ ] Resend the license email works.
- [ ] Logs: filter by type, license id and date. Set retention to 1 day, run cron → old events are pruned.
- [ ] Rate limit: 31 `license/check` calls in a row from one IP → the 31st returns HTTP 429 `rate_limited`, logged once.
- [ ] Signing keys: **Generate next key** → two keys shown (`active`, `next`). The client trusting both still works. **Promote** → the client with only the new key works; a client with only the old key fails verification (expected).
- [ ] My Account → Orders → **Invoice** opens a printable invoice (only when no invoice plugin is active). Another customer's invoice URL returns 404.
- [ ] Settings → "Delete all data on uninstall" off → delete the plugin → reinstall → all data is still there.
- [ ] HPOS on (WooCommerce → Settings → Advanced → Features → High-performance order storage) → repeat sections 1, 6 and 7.

## 11. Free trial (1.1.0, confirm-first since 1.4.0)

- [ ] Put `[twh_trial_form]` on a page. Submit name, email and `https://shop-one.com` → the form is replaced by "Check your inbox" without a page reload, and one email "Confirm your email to start your Talkwyn trial" arrives. No license exists yet.
- [ ] Submit the same email again within 2 minutes → "We already sent you a confirmation link", no second email.
- [ ] Open the confirm link with a new email → an account is created, you are signed in and land on My Account → Licenses with the trial. Email "Your 15-day Talkwyn Pro trial has started" has the key and a "Set your password" link that opens the WooCommerce reset form.
- [ ] Open the same confirm link again → "This confirmation link has expired or was already used".
- [ ] Confirm with the email of an existing account → the trial is added to that account, nobody is signed in, the email says "log in any time".
- [ ] Header popup: the trial form shows placeholders only (no labels), and errors (for example `localhost`) show inside the popup.
- [ ] The same email again (also `name+x@gmail.com` for a Gmail address) → "already used". The same site with another email → refused. A `mailinator.com` email and `http://localhost` → refused.
- [ ] Submit the form in under 3 seconds, or fill the hidden field → refused quietly. 6 starts from one IP in an hour → the 6th is refused.
- [ ] Activate the trial key in Talkwyn on shop-one.com → plugin shows "Trial: 15 days left". A second site → `limit_reached`.
- [ ] Set the trial end to 2 days from now (license detail) and run cron → "2 days left" email, sent once.
- [ ] Set the end to yesterday and run cron → status expired, "Your Talkwyn trial has ended" email with an upgrade link; plugin shows "Trial ended" and **Upgrade to keep Pro**.
- [ ] Click the upgrade link while logged out → checkout with "Upgrade trial to Business". Pay → the same key is active for 1 year with 5 sites; "Your Talkwyn Pro is yours" email; Dashboard trial-to-paid rate goes up.
- [ ] Refund that order → the license goes back to an ended trial.
- [ ] Card mode: pick **Card on file** without WooCommerce Subscriptions → an admin notice explains why and the no-card form stays.

## 12. Partners (1.1.0)

- [ ] As a customer, My Account → Partners → apply. The admin gets "New partner application". Status shows "Pending review".
- [ ] Talkwyn Hub → Partners → **Approve** → the partner gets "You're a Talkwyn Partner" with the link.
- [ ] In a private window open `/r/partner-code?to=/pricing/` → lands on /pricing/; a `twh_ref` cookie (HttpOnly, 60 days) is set; the click shows on the partner dashboard.
- [ ] Buy a plan in that window → a pending commission (20% of the line total) on the Referrals tab and the partner dashboard; the partner gets "New referral".
- [ ] Start a trial in a window with the cookie, then upgrade it → the commission is created at upgrade time with source `trial`.
- [ ] Buy while logged in as the partner → the commission is rejected as a self-referral.
- [ ] Refund half → the commission halves. Refund the rest → rejected with reason `refund`.
- [ ] Set approval days to 0 and run cron → pending becomes approved; the partner gets "commission approved".
- [ ] When approved ≥ threshold, the partner appears on the Payouts tab; **Export CSV** has their decrypted payout details; **Mark paid** with a reference → "Payout sent" email; history shows it.
- [ ] Reject a referral without a reason → refused with a notice. With a reason → the partner sees it.
- [ ] `GET /wp-json/talkwyn-hub/v1/partners/terms` returns the current settings.
- [ ] Turn Partners off → `?ref=` does nothing, My Account → Partners says the program is closed.

## 16. Trial spam check and popup text (1.6.2)

- [ ] With the Talkwyn theme 2.10.0 and Turnstile keys set: the trial popup and the pricing page form show the Cloudflare check. Without it solved, the form says "Please complete the spam check and try again." and the check resets.
- [ ] Without keys: no check, the trial works as before.
- [ ] "Check your inbox" box: title 18px, text 15px, the same size as the text above it in the popup.

## 15. Email sender and release warning (1.6.1)

- [ ] Settings → Emails: From name and From email empty → a trial email arrives from the site title (not "WordPress").
- [ ] From name `Talkwyn`, From email `hello@talkwyn.com` → trial and license emails arrive from "Talkwyn <hello@talkwyn.com>".
- [ ] Delete every stable release of Talkwyn Pro → every Hub screen shows "No release uploaded: Talkwyn Pro" with an Upload link. Other admin screens do not.
- [ ] Upload the Pro ZIP as stable → the warning is gone, and a customer with a trial sees "Talkwyn Pro .zip" on the dashboard and Talkwyn Pro under Downloads.

## 14. Keys only in the account (1.6.0)

- [ ] Keys in emails off: the trial welcome and license emails show `TALK-****-****-****-XXXX` and an "Open your dashboard" button; the full key is on My Account → Licenses.
- [ ] Logged out, add a license product to the cart: checkout asks to create an account and refuses guest checkout.
- [ ] As a customer with a trial, buy a plan from the pricing page (not the upgrade link): still one license, same key, now paid with the plan's sites and a new term.
- [ ] Turn Keys in emails on: emails show the full key again, guest checkout works as before.

## 13. Customer dashboard (1.5.0)

- [ ] Logged out, /my-account/ shows the normal login form.
- [ ] Logged in with a trial: sidebar, top bar with Log out, "Welcome back", 3 stat cards, features list, current plan with site slots, Your sites (Live or Dev).
- [ ] Create product `talkwyn`, upload the free ZIP as a release: **Download .zip** downloads `talkwyn-<version>.zip`. With an active license, **Talkwyn Pro .zip** downloads the Pro release.
- [ ] Downloads page lists "Talkwyn (free plugin)" first, then Pro for active licenses.
- [ ] A customer with no license sees 0 stats, "Unlock with Pro", **Start free 15-day trial**, Free plan.
- [ ] Billing shows Orders and invoices plus Billing address tabs; Account shows the details form; Support opens /contact/.
- [ ] Phone width: the sidebar turns into a top menu that scrolls sideways; stats sit in three small columns.

## 12. Admin screens (1.4.0)

- [ ] Every Talkwyn Hub screen shows the pink header with the version, the two header buttons and the page tabs. The active page is dark.
- [ ] Settings: tabs General, Trial, Partners, Invoices, Emails, Signing keys, System status. Save on the Trial tab returns to the Trial tab with "Saved.".
- [ ] Emails tab: each template opens on click; placeholders copy on click.
- [ ] Desktop: the "Save settings" tab sits on the right edge and hides on Signing keys and System status.
