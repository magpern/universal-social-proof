=== Universal Social Proof ===
Contributors: magpern
Requires at least: 6.5
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Genuine, privacy-conscious WooCommerce purchase social-proof notifications.

== Description ==

Universal Social Proof shows genuine, privacy-conscious purchase notifications
for WooCommerce stores. No fabricated activity.

== Installation ==

1. Upload the plugin ZIP via Plugins → Add New → Upload, or extract to
   `wp-content/plugins/universal-social-proof/`.
2. Activate the plugin.

== Changelog ==

= 1.1.0 =
* Optional genuine add-to-cart social proof with short freshness window.
* Appearance controls and polished toaster design (CSS variables + custom CSS).
* Sources toggles; purchase retention days preserved; settings version 2.
* Runtime 1.1.0; Stable tag remains 1.0.2 until release.

= 1.0.2 =
* Plugins row: Settings and Diagnostics links to WooCommerce → Social Proof.

= 1.0.1 =
* Packaging fix: include uninstall.php in the release ZIP (ADR-0017).

= 1.0.0 =
* M7: hardening for production-recommended v1; runtime 1.0.0; Stable tag 1.0.0.

= 0.6.0 =
* M6 (internal): WooCommerce admin settings + diagnostics; runtime 0.6.0; Stable tag remains 0.5.0 until v1.0.0.

= 0.5.0 =
* M5: visitor-country weighting via soft Universal Geo Context dependency (tiered selection; shared PDP search cap; schema and public DTO unchanged).

= 0.4.1 =
* Automatic updates from a private update server (bundled Plugin Update Checker v5); base URL read from the PRIVATE_UPDATE_SERVER constant, inert when it is not defined.

= 0.4.0 =
* M4: server-rendered messages and targeting.
