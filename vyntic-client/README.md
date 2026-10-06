# Vyntic Client

Shared library bundled in every Vyntic plugin (as `vendor/vyntic-client/`). It gives all Vyntic plugins one **Vyntic** admin menu with an overview screen, and delivers updates from the Vyntic Hub (`https://vyntic.studio`, override with `define( 'VYNTIC_HUB_URL', '...' )`).

Several plugins can bundle different copies: the newest copy is loaded once.

When you change this library, copy it into each plugin again (`vendor/vyntic-client/`) and raise the version in `loader.php` and `class-vyntic-client.php`.

Only the site address, WordPress/PHP versions and the versions of Vyntic plugins are sent to the hub. Packages are only installed from the hub's own host over HTTPS.
