=== Vyntic Speed Optimizer ===
Contributors: vyntic
Tags: speed, cache, pagespeed, core web vitals, optimize
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

All-in-one speed optimizer that runs 100% on your own server. No account, no login, no "connect" step.

== Description ==

Vyntic Speed Optimizer pushes Google PageSpeed / Core Web Vitals scores into the green with one click:

* **Page cache** – static HTML served before WordPress loads (advanced-cache.php drop-in), GZIP copies, auto-purge on edits, background preloading, mobile cache option.
* **Delay JavaScript until user interaction** – every script (analytics, chat, sliders, jQuery…) runs on the first scroll / tap / mouse move / key press. Removes almost all Total Blocking Time. Scripts keep their original order; DOMContentLoaded, window load and jQuery ready/load handlers still fire, and the first click is replayed.
* **Remove unused CSS (local)** – for every page only the CSS rules it uses are inlined; full stylesheets load on interaction. No render-blocking CSS and no external service.
* **Defer JavaScript**, **minify CSS / JS / HTML**.
* **Images** – native lazy loading (first N images skipped), `fetchpriority="high"` for the LCP image, missing width/height added (CLS), WebP conversion on your server (GD/Imagick) with bulk tool, lazy iframes, YouTube click-to-load.
* **Fonts** – `font-display: swap`, Google Fonts hosted locally, preconnect and preload.
* **Tweaks** – disable emojis, embeds, Dashicons for visitors, jQuery Migrate, query strings, XML-RPC, heartbeat control.
* **Database cleanup** – revisions, drafts, trash, spam, expired transients, orphaned meta, table optimize, weekly auto-clean.
* **Built-in PageSpeed test** (mobile + desktop) on the dashboard.
* Admin bar: clear all cache / clear this page / open PageSpeed.
* Per-page switches in the editor: "Disable optimizations" and "Never cache".

= Optimization levels =

* **Safe** – cache, minify, defer, lazy load, WebP. Works everywhere.
* **Balanced (recommended)** – Safe + delay all JavaScript, YouTube facade, local Google Fonts, instant navigation.
* **Extreme** – Balanced + remove unused CSS.
* **Off** – everything disabled.

== Installation ==

1. Zip the `vyntic-speed-optimizer` folder (or use the provided zip) → Plugins → Add New → Upload Plugin → Activate.
2. Deactivate any other cache / optimization plugin (WP Rocket, LiteSpeed Cache, 10Web Booster, Autoptimize, …).
3. Open **Vyntic Speed** in the admin menu. The Balanced level is already active. Click **Test now** to see your score, or pick **Extreme** for the highest score.
4. Check your important pages while logged out (or in a private window). Logged-in admins see the original, unoptimized site by default.

The plugin writes `wp-content/advanced-cache.php`, adds `define( 'WP_CACHE', true );` to wp-config.php and adds browser-cache rules to .htaccess. All three are removed again on deactivation.

== Frequently Asked Questions ==

= Do I need an account or API key? =
No. Everything is computed on your server. A Google PageSpeed API key is optional and only removes Google's rate limit on the built-in score test.

= A slider / menu / popup only works after I scroll =
That is the "delay JavaScript" feature. Add part of its script URL (for example `slick` or `swiper`) to *CSS & JavaScript → Do not delay scripts containing*, or set *Also run scripts after (seconds)*.

= Something looks unstyled before the first interaction (Extreme level) =
Add the class names (wildcards allowed, e.g. `swiper-*`) to *Always keep these selectors*, or switch to Balanced.

= How do I see a page without optimization? =
Add `?vso_off=1` to the URL.

= How do I know a page is served from cache? =
Look for the response header `X-Vyntic-Cache: HIT` and the HTML comment `Cached by Vyntic Speed Optimizer` at the end of the source.

= Nginx? =
Page cache, optimizations and WebP work on any server. The `.htaccess` browser-cache rules only apply to Apache / LiteSpeed; on Nginx add equivalent `expires` rules to your server config.

== Developer hooks ==

* `vso_skip_request` (bool) – skip optimization for the current request.
* `vso_cache_page` (bool) – prevent storing the current page.
* `vso_delay_js_exclusions`, `vso_defer_js_exclusions` (array) – extra exclusion keywords.
* `vso_optimized_html` (string) – final HTML.
* `vso_preload_limit` (int) – max posts preloaded (default 500).
* Constants `DONOTOPTIMIZE` and `DONOTCACHEPAGE` are respected; scripts with `data-no-delay` or `data-no-optimize` are never delayed.

== Changelog ==

= 1.0.0 =
* First release.

== Credits ==

Bundles matthiasmullie/minify and matthiasmullie/path-converter (MIT license).
