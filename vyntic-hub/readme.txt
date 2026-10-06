=== Vyntic Hub ===
Contributors: vyntic
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Your own WordPress plugin hub on vyntic.studio: publish a version once and every site using that plugin gets the update.

== Description ==

Install this on vyntic.studio (the hub). Plugins that include the Vyntic Client library (vendor/vyntic-client) ask the hub for updates twice a day and update through the normal WordPress Updates screen, with auto-update support.

* **One clean menu:** Vyntic Hub > Dashboard, Plugins, Sites, Settings.
* **Releases:** upload the plugin zip. Name, version, requirements and changelog are read from the zip. The newest version goes live automatically; "Make live" picks another version for new downloads.
* **SEO pages:** each plugin gets its own page at /plugins/{slug}/ with description, download button, requirements, installation steps, changelog and SoftwareApplication structured data. A "WordPress Plugins" page with the [vyntic_plugins] grid is created at /plugins/ on activation. Pages appear in the WordPress / Yoast / Rank Math sitemaps automatically.
* **Sites:** see which sites use each plugin and which version they run (only the site address and versions are stored). Outdated sites are marked.

== Publishing a plugin ==

1. Vyntic Hub > Add plugin.
2. In "Releases", click "Upload new version (.zip)" and pick the plugin zip (the zip must contain the plugin folder, e.g. vyntic-speed-optimizer/...).
3. Write the description (this is the SEO page content), fill the Excerpt (short description) and set the Featured image (icon, 256x256).
4. Publish.

== Publishing an update ==

1. Raise the "Version:" number in the plugin's main file (and add a changelog entry in readme.txt).
2. Zip the plugin folder.
3. Vyntic Hub > Plugins > Edit > Upload new version (.zip).

Sites see the update within 12 hours (or right away with Vyntic > "Check for updates now").

Note: WordPress never installs an older version over a newer one. "Make live" on an older release only affects new downloads. To undo a bad release on existing sites, publish a fixed, higher version.

== Connecting a plugin ==

Copy the vyntic-client folder into the plugin as vendor/vyntic-client and add to the main plugin file:

    require_once __DIR__ . '/vendor/vyntic-client/loader.php';
    vyntic_client_register( array(
        'file' => __FILE__,
        'slug' => 'your-plugin-folder',
        'name' => 'Your Plugin',
        'page' => 'your-admin-page-slug',
    ) );

The plugin then appears under the shared "Vyntic" admin menu. Register its settings page with add_submenu_page( 'vyntic', ... ).

== API ==

* GET  /?rest_route=/vyntic-hub/v1/plugins
* POST /?rest_route=/vyntic-hub/v1/check
* GET  /?rest_route=/vyntic-hub/v1/info/{slug}
* GET  /?vyntic_download={slug}&version={version}
