=== Migrate Store: Export and Import WooCommerce Settings ===
Contributors: nagdy
Tags: woocommerce, woocommerce export, export shipping zones
Requires PHP: 7.4
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.1.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://ko-fi.com/nagdy

Migrate Store is a WooCommerce plugin for hassle-free migration of settings between sites, simplifying and accelerating the setup process.

== Description ==

Imagine you're setting up a new WooCommerce store. It's exciting, but there's a lot to do. As you're going through the setup process, you hit a wall - the settings. General settings, shipping zones, tax options, email setup, and the list seems endless. The sheer volume and complexity of settings can be overwhelming and time-consuming.

MigrateStore offers a seamless solution to transfer your settings from an existing store to your new one. It’s like packing up your shop’s essentials into a virtual moving box and unpacking them at your new location. No more manual setup, no more time wasted, no more headaches. With MigrateStore, setting up new WooCommerce stores is as easy as a breeze. Spend less time on settings and more time on growing your business.

### What options or settings does MigrateStore allow me to export/import?

1. WooCommerce → Settings → General.
2. WooCommerce → Settings → Tax options.
3. WooCommerce → Settings → **Shipping zones** (Spicy! 🌶️🌶️)
4. WooCommerce → Settings → Shipping options.
5. WooCommerce → Settings → **Accounts & Privacy**.
6. WooCommerce → Settings → Emails. (WooHoo 🙌🙌) This includes:
    - Email sender options
    - Email template options
    - Color customization
    - Default email notifications including:
        - New order
        - Cancelled order
        - Failed order
        - Order on-hold
        - Processing order
        - Refunded order
        - Customer invoice / Order details
        - Customer note
        - Reset password
        - New account
7. WooCommerce → Settings → Advanced → Page setup → Checkout endpoints & Account endpoints
8. WooCommerce → Settings → Shipping → Classes

Move your WooCommerce settings smoothly and effortlessly with MigrateStore!

Consider leaving a [5 star review](https://wordpress.org/support/plugin/migratestore/reviews/) if you like MigrateStore.


**Developers?**
Feel free to [fork the project on GitHub](https://github.com/amElnagdy/migratestore) and submit your contributions via pull request.

== Installation ==
1. Upload `migratestore` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Navigate to `MigrateStore` menu to start using the plugin.

== Frequently Asked Questions ==
= The plugin isn't working or have a bug? =
Post detailed information about the issue in the [support forum](http://wordpress.org/support/plugin/migratestore) and we will work to fix it.

== Screenshots ==
1. Plugin Settings.

== Changelog ==

= 1.1.8 =
* Fixed: Internationalization for missed string.

= 1.1.7 =
* Added: Check for ZipArchive dependency to prevent fatal errors.

= 1.1.6 =
* WooCommerce 9.7 compatibility.

= 1.1.5 =
* Added: Export and Import Shipping Classes.
* Updated: Design.

= 1.1.4 =
* WordPress 6.7 compatibility.
* Multisite compatibility.

= 1.1.3 =
* WordPress 6.5 compatibility.

= 1.1.2 =
* While importing shipping zones, we're not looking into the options table anymore.

= 1.1.1 =
* Minor changes. Mainly, changing MigrateStore to Migrate Store :)

= 1.1 =
* WordPress 6.4 compatibility.
* WooCommerce HPOS compatibility.

= 1.0 =
* Initial Release
