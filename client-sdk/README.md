# Talkwyn License Client (SDK)

A drop-in PHP class (`class-talkwyn-license-client.php`) for the Talkwyn plugin. It talks to [Talkwyn Hub](../talkwyn-hub/API.md).

## What it does

- Stores the license key (in an option that is never printed in full), status, plan, expiry, features and the time of the last successful check.
- Generates an `instance_id` (UUID) once per site. If the site URL changes (migration or clone), it gets a new instance id and activates again. Clones on dev/staging domains don't use a slot.
- **Activate / Deactivate / daily check** (WP-Cron event `{prefix}_daily_check`).
- **Verifies every response:** Ed25519 signature over the canonical JSON of `data` with the embedded public key(s), the echoed `nonce`, and `server_time` within ±10 minutes. An unverifiable response is treated as "hub unreachable", never as a license decision.
- **Grace period:** if the hub can't be reached, Pro stays active for 7 days after the last successful check, with an admin notice.
- **Updates:** hooks `pre_set_site_transient_update_plugins` and `plugins_api` (the "View details" modal). Right before downloading, it fetches a fresh 10-minute package URL (`upgrader_pre_download`). "Check again" on Dashboard → Updates bypasses its cache.
- **Helpers:** `is_pro()`, `get_plan()`, `has_feature( $feature )`, `in_grace_period()`, `state()`.
- **Settings partial:** `render_settings()` outputs the license box: key input, Activate/Deactivate/Check now buttons, status badge, plan, expiry, sites, and a "Manage license" link to `talkwyn.com/my-account/licenses/`.

## Usage

```php
require_once __DIR__ . '/includes/class-talkwyn-license-client.php';

function talkwyn_license(): Talkwyn_License_Client {
	static $client = null;
	if ( null === $client ) {
		$client = new Talkwyn_License_Client( array(
			'api_url'     => 'https://talkwyn.com/wp-json/talkwyn-hub/v1/',
			'product'     => 'talkwyn-pro',
			'plugin_file' => __FILE__,          // Main plugin file.
			'version'     => TALKWYN_VERSION,
			'public_keys' => array( 'BASE64_PUBLIC_KEY_FROM_HUB_SETTINGS=' ),
		) );
	}
	return $client;
}
add_action( 'plugins_loaded', static function () { talkwyn_license()->init(); } );

if ( talkwyn_license()->is_pro() ) { /* load Pro */ }
```

On your settings page: `talkwyn_license()->render_settings();`

During a key rotation, list both the `active` and `next` public keys in `public_keys`.

## Options used (prefix `talkwyn_license` by default)

| Option | Content | Autoload |
|---|---|---|
| `{prefix}_key` | License key | no |
| `{prefix}_state` | status, plan, expires_at, features, sites, last_check, offline_since, last_error, renew_url | no |
| `{prefix}_instance` | `id` (UUID) and the URL it was created for | no |
| `_transient_{prefix}_update_info` | Cached `update/check` response (6 h) | n/a |

Delete these in your plugin's `uninstall.php`, and clear the cron hook `{prefix}_daily_check`.
