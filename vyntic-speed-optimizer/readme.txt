=== Vyntic Speed Optimizer ===
Contributors: vyntic
Author: Vyntic Studio
Author URI: https://vyntic.studio/
Plugin URI: https://vyntic.studio/plugins/vyntic-speed-optimizer/
Tags: speed, cache, pagespeed, core web vitals, optimize
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

All-in-one speed optimizer that runs 100% on your own server. No account, no login, no "connect" step.

== Description ==

Vyntic Speed Optimizer pushes Google PageSpeed / Core Web Vitals scores into the green with one click:

* **Page cache**: static HTML served before WordPress loads (advanced-cache.php drop-in), GZIP copies, auto-purge on edits, background preloading, mobile cache option.
* **Delay JavaScript until user interaction**: every script (analytics, chat, sliders, jQuery and more) runs on the first scroll / tap / mouse move / key press. Removes almost all Total Blocking Time. Scripts keep their original order; DOMContentLoaded, window load and jQuery ready/load handlers still fire, and the first click is replayed.
* **Built-in compatibility exclusions**: cookie/GDPR banners (CookieYes, Complianz, Cookiebot, Borlabs, iubenda, OneTrust and more), sliders (Slider Revolution, Smart Slider, LayerSlider, MetaSlider and more) and JavaScript lazy loaders keep working before the first interaction. Every excluded script automatically takes its dependencies (e.g. jQuery and its localized config) with it, so nothing runs in the wrong order. Pure data scripts (`var config = {...}`) and the Google consent stub always run immediately.
* **Tracking & pixels**: Meta/Facebook Pixel, GA4, Tag Manager, Google Ads, TikTok, Clarity, Hotjar, PixelYourSite and more: choose "Delay until interaction" (best score) or "Load normally" (count every visit). Cart, checkout, account and order-received pages never delay scripts, so purchase/conversion events are never lost.
* **Safety CSS**: page preloaders, AOS, Divi and WOW.js animations stay visible while scripts are delayed.
* **Preview mode**: admins can check the optimized page while logged in with `?vso_preview=1` (admin bar → Preview optimized page).
* **Remove unused CSS (local)**: for every page only the CSS rules it uses are inlined; full stylesheets load on interaction. No render-blocking CSS and no external service.
* **Defer JavaScript** (dependency-aware: a script is never deferred when something that runs immediately needs it), **minify CSS / HTML**, optional JS minify (Extreme level).
* **Images**: native lazy loading (first N images skipped), `fetchpriority="high"` for the LCP image, missing width/height added (CLS), WebP conversion on your server (GD/Imagick) with bulk tool, lazy iframes, YouTube click-to-load.
* **Fonts**: `font-display: swap`, Google Fonts hosted locally, preconnect and preload.
* **Tweaks**: disable emojis, embeds, Dashicons for visitors, jQuery Migrate, query strings, XML-RPC, heartbeat control.
* **Database cleanup**: revisions, drafts, trash, spam, expired transients, orphaned meta, table optimize, weekly auto-clean.
* **Built-in PageSpeed test** (mobile + desktop) on the dashboard.
* Admin bar: clear all cache / clear this page / open PageSpeed.
* Per-page switches in the editor: "Disable optimizations" and "Never cache".

= Optimization levels =

* **Safe**: cache, minify, defer, lazy load, WebP. Works everywhere.
* **Balanced (recommended)**: Safe + delay all JavaScript, YouTube facade, local Google Fonts, instant navigation.
* **Extreme**: Balanced + remove unused CSS.
* **Off**: everything disabled.

== Installation ==

1. Zip the `vyntic-speed-optimizer` folder (or use the provided zip) → Plugins → Add New → Upload Plugin → Activate.
2. Deactivate any other cache / optimization plugin (WP Rocket, LiteSpeed Cache, 10Web Booster, Autoptimize and others).
3. Open **Vyntic Speed** in the admin menu. The Balanced level is already active. Click **Test now** to see your score, or pick **Extreme** for the highest score.
4. Check your important pages while logged out (or in a private window). Logged-in admins see the original, unoptimized site by default.

The plugin writes `wp-content/advanced-cache.php`, adds `define( 'WP_CACHE', true );` to wp-config.php and adds browser-cache rules to .htaccess. All three are removed again on deactivation.

== Frequently Asked Questions ==

= Do I need an account or API key? =
No. Everything is computed on your server. A Google PageSpeed API key is optional and only removes Google's rate limit on the built-in score test.

= Will Facebook / Meta Pixel and Google Analytics still track? =
Yes. With the default "Delay until interaction" they fire on the visitor's first scroll, tap or mouse move, so only visitors who leave without touching the page are missed. Choose *Tracking & pixels → Load normally* to count every visit (costs some score). Purchase events on the WooCommerce / EDD thank-you page are never delayed.

= A slider / menu / popup only works after I scroll =
That is the "delay JavaScript" feature. Add part of its script URL (for example `slick` or `swiper`) to *CSS & JavaScript → Do not delay scripts containing*, or set *Also run scripts after (seconds)*.

= Something looks unstyled before the first interaction (Extreme level) =
Add the class names (wildcards allowed, e.g. `swiper-*`) to *Always keep these selectors*, or switch to Balanced.

= How do I see why a script is not delayed? =
While logged in as admin, open the page with `?vso_preview=1&vso_debug=1` and view the page source. At the very end there is a list of every script: delayed, or running early and why.

= How do I see a page without optimization? =
Add `?vso_off=1` to the URL.

= How do I know a page is served from cache? =
Look for the response header `X-Vyntic-Cache: HIT` and the HTML comment `Cached by Vyntic Speed Optimizer` at the end of the source.

= Nginx? =
Page cache, optimizations and WebP work on any server. The `.htaccess` browser-cache rules only apply to Apache / LiteSpeed; on Nginx add equivalent `expires` rules to your server config.

== Developer hooks ==

* `vso_skip_request` (bool): skip optimization for the current request.
* `vso_cache_page` (bool): prevent storing the current page.
* `vso_delay_js_exclusions`, `vso_defer_js_exclusions` (array): extra exclusion keywords.
* `vso_optimized_html` (string): final HTML.
* `vso_disable_delay_here` (bool): turn off script delay for the current page.
* `vso_safety_css` (string): CSS printed while scripts are delayed.
* `vso_preload_limit` (int): max posts preloaded (default 500).
* Constants `DONOTOPTIMIZE` and `DONOTCACHEPAGE` are respected; scripts with `data-no-delay` or `data-no-optimize` are never delayed.

== Changelog ==

= 1.3.0 =
* Automatic updates from the Vyntic Hub: new versions appear in Dashboard > Updates like any other plugin, with auto-update support.
* All Vyntic plugins now share one "Vyntic" admin menu (Vyntic > Speed Optimizer) with an overview screen.

= 1.2.0 =
* Config blocks printed for a script (localized data, Elementor's config) no longer trigger exclusions on their own. Fixes Elementor and jQuery being loaded early because Elementor's config mentions "lazyload".
* Scripts that run early are now deferred whenever it is safe (order-based check), so jQuery is no longer render-blocking when a cookie banner needs it.
* Smarter LCP image: logos, icons and small images are skipped, and the real hero image gets fetchpriority="high".
* Remove unused CSS now also covers large inline style blocks (Elementor, ElementsKit).
* Admin script report: add ?vso_preview=1&vso_debug=1 to a URL and view the page source.
* Author: Vyntic Studio. Cleaner wording throughout.

= 1.1.0 =
* Built-in exclusions for cookie banners, sliders, lazy loaders and tracking pixels, with automatic dependency resolution.
* Tracking mode: delay or load normally. Script delay is always off on cart/checkout/thank-you pages.
* Data-only inline scripts and the Google consent stub always run immediately.
* Safety CSS for preloaders and entrance animations; admin preview mode (?vso_preview=1).
* Safer defer (keeps dependencies of non-deferred code in place); JS minify now only in Extreme.

= 1.0.0 =
* First release.

== Credits ==

Bundles matthiasmullie/minify and matthiasmullie/path-converter (MIT license).
