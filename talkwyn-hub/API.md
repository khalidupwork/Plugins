# Talkwyn Hub API (`talkwyn-hub/v1`)

Base URL: `https://talkwyn.com/wp-json/talkwyn-hub/v1/`

The client plugin depends on this contract. Fields may be **added** in future versions; existing fields are never renamed or removed.

- [Conventions](#conventions)
- [Signatures](#signatures)
- [Common request fields](#common-request-fields)
- [Errors](#errors)
- [POST license/activate](#post-licenseactivate)
- [POST license/deactivate](#post-licensedeactivate)
- [POST license/check](#post-licensecheck)
- [POST update/check](#post-updatecheck)
- [GET download](#get-downloadtoken)
- [Rate limiting](#rate-limiting)
- [Dev and staging sites](#dev-and-staging-sites)

---

## Conventions

- All endpoints except `download` are `POST` with a JSON body (`Content-Type: application/json`).
- Responses are JSON with `Cache-Control: no-store`.
- Dates are ISO 8601 in UTC (`2027-10-07T00:00:00Z`); `null` means lifetime/never.
- `server_time` is a unix timestamp (seconds, UTC).

Every response has this envelope:

```json
{
  "success": true,
  "data": { "...": "...", "server_time": 1791331200, "nonce": "<echo of request nonce>" },
  "signature": "<base64 Ed25519 signature of canonical JSON of data>",
  "key_id": "a1b2c3d4"
}
```

## Signatures

Every response, including errors, is signed with Ed25519 (`sodium_crypto_sign_detached`). The signature covers the canonical JSON of the `data` object only.

### Canonical JSON

1. Decode the response JSON into associative arrays. Empty objects `{}` and empty arrays `[]` both become an empty array.
2. Recursively sort the keys of every object (associative array) in byte order (`ksort($a, SORT_STRING)`). Lists (keys `0..n-1`) keep their order.
3. Encode with `json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`, with no whitespace.

No floats are used in signed payloads.

### Verification steps (client)

1. Generate a random `nonce` (16+ bytes, hex) for each request and send it.
2. Parse the JSON body. Reject the response if `data` or `signature` is missing.
3. Build the canonical JSON of `data` and verify it:
   `sodium_crypto_sign_verify_detached(base64_decode(signature), canonical, base64_decode(public_key))`.
   Try every trusted public key; `key_id` tells you which one signed.
4. Check that `data.nonce` equals the nonce you sent (prevents replay).
5. Check that `abs(data.server_time - time()) <= 600` (10 minutes).
6. Treat an unverifiable response as "hub unreachable", never as a license decision.

### Keys and rotation

The public keys are listed under **Talkwyn Hub → Settings → Signing keys**. Rotation is done in two steps so existing installs never break:

1. **Generate next key.** Ship a client release that trusts both the `active` and the `next` public keys.
2. **Promote next key.** Once most sites run that release, the next key becomes active and the old key is retired.

## Common request fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `license_key` | string | yes | `TALK-XXXX-XXXX-XXXX-XXXX`. Case and whitespace are ignored. |
| `instance_id` | string | yes | UUID generated once per site by the client (`[A-Za-z0-9-]{8,64}`). |
| `site_url` | string | yes | `home_url()`. Normalized server-side: lowercase, no scheme/port/`www.`, path kept. |
| `product` | string | yes | Software product slug, e.g. `talkwyn-pro`. |
| `plugin_version` | string | no | Installed plugin version. |
| `wp_version` | string | no | WordPress version. |
| `php_version` | string | no | PHP version. |
| `nonce` | string | yes | Random string `[A-Za-z0-9_-]{8,128}`, echoed in `data.nonce`. |
| `channel` | string | update/check only | `stable` (default) or `beta`. |

## Errors

HTTP 4xx/5xx with:

```json
{
  "success": false,
  "error": { "code": "limit_reached", "message": "Human readable message" },
  "data": { "error_code": "limit_reached", "server_time": 1791331200, "nonce": "...", "...": "..." },
  "signature": "base64...",
  "key_id": "a1b2c3d4"
}
```

`data.error_code` is part of the signed payload, so a client can trust a signed `revoked` or `expired` answer and disable Pro. An unsigned or unverifiable error must be treated as a network problem, and the grace period applies.

| Code | HTTP | Meaning | Extra signed `data` |
|---|---|---|---|
| `bad_request` | 400 | Missing or invalid field (nonce, instance_id, site_url, product, key) | – |
| `invalid_key` | 404 | Key unknown or malformed | – |
| `wrong_product` | 400 | Key belongs to another software product | – |
| `expired` | 403 | License expired | `renew_url` |
| `revoked` | 403 | Refunded, cancelled, charged back or revoked by admin | – |
| `suspended` | 403 | Suspended (e.g. payment dispute open) | – |
| `limit_reached` | 403 | All production slots used | `activations_used`, `activation_limit`, `manage_url` |
| `rate_limited` | 429 | Too many requests | – |
| `not_found` | 404 | `download` only: release or file missing | – |
| `server_error` | 500 | Unexpected error (temporary) | – |

## POST `license/activate`

Validates the key, product, status (`active`), expiry and activation limit. Dev sites and deactivated rows don't count toward the limit. If the same `instance_id` (or, failing that, the same normalized domain) is already active, that row is reused and no new slot is consumed.

Request:

```json
{
  "license_key": "TALK-ABCD-EFGH-JKLM-NPQR",
  "instance_id": "3f1c8a4e-6c1b-4a8f-9a77-0c2b7d3e9f10",
  "site_url": "https://www.example.com",
  "product": "talkwyn-pro",
  "plugin_version": "1.4.0",
  "wp_version": "6.6.2",
  "php_version": "8.2.12",
  "nonce": "9b1d3f6a2c4e8b0a7d5f1e3c9a6b2d4f"
}
```

Success (200):

```json
{
  "success": true,
  "data": {
    "status": "active",
    "plan": "business",
    "product": "talkwyn-pro",
    "expires_at": "2027-10-07T00:00:00Z",
    "activations_used": 2,
    "activation_limit": 5,
    "is_dev_site": false,
    "site_active": true,
    "features": ["pro"],
    "server_time": 1791331200,
    "nonce": "9b1d3f6a2c4e8b0a7d5f1e3c9a6b2d4f"
  },
  "signature": "base64...",
  "key_id": "a1b2c3d4"
}
```

| Field | Notes |
|---|---|
| `status` | `active` on success |
| `plan` | Plan slug from the product mapping (`personal`, `business`, `agency`, `lifetime`, …) |
| `expires_at` | ISO date or `null` (lifetime) |
| `activations_used` | Active production sites (dev sites excluded) |
| `activation_limit` | `0` = unlimited |
| `is_dev_site` | This site matched a dev/staging pattern and doesn't use a slot |
| `site_active` | This instance holds an active activation |
| `features` | Feature flags from the product mapping |

Errors: `bad_request`, `invalid_key`, `wrong_product`, `expired`, `revoked`, `suspended`, `limit_reached`, `rate_limited`.

## POST `license/deactivate`

Frees the slot held by `instance_id`. It succeeds for any existing key, even if expired, revoked or suspended, so customers can always clean up. Calling it again is harmless.

Success `data`: the same shape as activate, plus `"deactivated": true|false` (false when no active row matched). `site_active` is `false`.

Errors: `bad_request`, `invalid_key`, `wrong_product`, `rate_limited`.

## POST `license/check`

Daily heartbeat. Returns the same `data` shape as activate, and updates `last_check_at` and the version fields of the activation. It **never creates an activation**. If the site was deactivated remotely (from My Account or by an admin), the response is still `success: true` with `"site_active": false`, and the client should stop Pro features.

Errors: `bad_request`, `invalid_key`, `wrong_product`, `expired` (with `renew_url`), `revoked`, `suspended`, `rate_limited`.

## POST `update/check`

The request also includes `channel` (`stable` | `beta`). The beta channel gets the highest version across stable and beta.

Success `data`:

```json
{
  "slug": "talkwyn-pro",
  "name": "Talkwyn Pro",
  "homepage": "https://talkwyn.com",
  "new_version": "1.5.0",
  "package": "https://talkwyn.com/wp-json/talkwyn-hub/v1/download?token=...",
  "requires": "6.4",
  "requires_php": "8.0",
  "tested": "6.7",
  "last_updated": "2026-10-01T12:00:00Z",
  "changelog_html": "<h4>1.5.0 <small>(2026-10-01)</small></h4><ul><li>…</li></ul>",
  "description_html": "<p>…</p>",
  "icons": { "1x": "https://…", "2x": "https://…" },
  "banners": { "low": "https://…", "high": "https://…" },
  "license_status": "active",
  "site_active": true,
  "renew_url": null,
  "server_time": 1791331200,
  "nonce": "..."
}
```

- `new_version` is `null` when no active release exists.
- `package` is only set when the license is `active` **and** this `instance_id` has an active activation. Otherwise it is `null`.
- Expired licenses still see `new_version` (so the update is visible) with `package: null` and a `renew_url`.
- `package` URLs expire after 10 minutes. The reference SDK fetches a fresh one right before downloading (`upgrader_pre_download`).

Errors: `bad_request`, `invalid_key`, `wrong_product`, `revoked`, `suspended`, `rate_limited`.

## GET `download?token=…`

Streams the release ZIP (`application/zip`). The token:

- Format: `base64url(payload) "." base64url(HMAC-SHA256(payload))`
- Payload: `{ "p": "dl", "l": <license_id>, "r": <release_id>, "e": <expiry>, "n": <random> }`
- Single-purpose (`p = dl`), bound to one license and one release, valid for 10 minutes, signed with a key derived from `TWH_SECRET_KEY`.

At download time the license must still be `active` and the release active. Each download is logged (`update_download`). ZIPs are stored in `TWH_RELEASES_DIR`, or in a protected uploads folder with `.htaccess` deny rules and random file names.

Errors return JSON in the error format: `bad_request` (invalid/expired token), `not_found`, `expired`, `revoked`, `suspended`, `rate_limited`.

## Rate limiting

Fixed windows, configurable under Settings (default **30 requests per 10 minutes**):

- per client IP (all endpoints), and
- per license key (`activate`, `deactivate`, `check`, `update/check`).

Exceeded → HTTP 429, `rate_limited`. IPs are stored only as HMAC-SHA256 hashes with a server-side key (GDPR). Behind a proxy or CDN, use the `twh_client_ip` filter to read a trusted header. Override per scope with the `twh_rate_limit_max` filter (`$max, $scope` where scope is `ip`, `key` or `download`).

## Dev and staging sites

These never count toward the activation limit (`is_dev_site: true`):

`localhost`, any IP address, `*.local`, `*.localhost`, `*.test`, `*.dev`, `*.example`, `*.invalid`, `staging.*`, `dev.*`, `*.wpengine.com`, `*.kinsta.cloud`, `*.flywheelsites.com`, `*.instawp.xyz`

The list is editable in Settings and filterable via `twh_dev_domain_patterns` (`$patterns, $host`). Moving an activation from a dev domain to a production domain needs a free slot.

## Hooks reference (hub)

| Hook | Type | Purpose |
|---|---|---|
| `twh_license_issued` | action | `($license_id, $args)` after a license is created |
| `twh_license_renewed` | action | `($license_id, $new_expiry_ts)` |
| `twh_license_upgraded` | action | `($license_id, $mapping)` |
| `twh_license_revoked` | action | `($license_id, $reason)` |
| `twh_dev_domain_patterns` | filter | Dev host patterns |
| `twh_client_ip` | filter | Client IP detection |
| `twh_rate_limit_max` | filter | Requests per window per scope |
| `twh_license_key_prefix` | filter | Key prefix (default `TALK`) |
| `twh_accept_key_format` | filter | Accept keys with other formats (e.g. imported legacy keys) |
| `twh_suspend_on_hold` | filter | Suspend licenses when a paid order goes on hold (dispute). Default `true` |
| `twh_email` | filter | Modify an outgoing email (`subject`, `html`, `to`) |
| `twh_admin_capability` | filter | Capability for the admin area (default `manage_woocommerce`) |
| `twh_has_invoice_plugin` | filter | Hide the built-in invoice view |
| `twh_plan_label` | filter | Display label for a plan slug |
