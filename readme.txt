=== Atomora ===
Contributors: hishmitesh
Tags: elementor, atomic editor, woocommerce, widgets, reviews
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: elementor
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Native widgets and editor tools for Elementor's Atomic Editor (Editor V4), with WooCommerce product, cart and review elements.

== Description ==

Atomora adds widgets built on Elementor's own Atomic Widgets system. They use the same Style, Settings and Interactions panels as Elementor's native V4 elements, so they feel like part of the editor.

= Main features =

**WooCommerce widgets**

* Gallery Slider: product gallery with thumbnails, lightbox, autoplay and per-device layout.
* Product Card: product card with quick view.
* Add to Cart family: Add to Cart button, Buy Now button, quantity input with plus and minus buttons, variation selects and variation buttons, and product price.

**Reviews**

* Reviews container with a Reviews List and a Reviews Form as nestable elements.
* Review Item template repeated per review, with parts for avatar, author, date, rating, verified badge and text.
* Load more or numbered pagination, and a multi-column review grid.
* Rating summary, average, count, breakdown and star widgets.
* The Reviews Form uses Elementor Pro's form elements when Elementor Pro is active, and works without it.

**Display conditions**

* Show or hide elements by login state, stock status, product price, category, tag, featured status, date range, or a custom field or URL value.

**Editor tools**

* Class highlighter and full class names in the Style tab.
* Tab navigator with search and expand or collapse for style sections.
* Design System bulk actions and tools.
* Structure panel move up, down, in and out, with keyboard shortcuts.
* Layout grid overlay in the editor top bar, with columns, gutter, margin and size per device.
* Optional hiding of Elementor promotional notices.
* Crash log for the plugin's own errors, viewable in wp-admin. Errors are only emailed if you enter an address on the Crash Reports screen.

== External services ==

This plugin does not connect to any external service and does not collect or send user data. The optional crash-report email is only sent to an address you enter yourself on the Crash Reports screen.

== Requirements ==

* WordPress 6.3 or later
* PHP 8.1 or later
* Elementor 4.0 or later with the Atomic Editor enabled
* WooCommerce for the product, cart and review widgets
* Elementor Pro is optional

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory, or install it from the Plugins screen.
2. Activate it through the Plugins screen.
3. Make sure the Atomic Editor is enabled in Elementor's settings.
4. Edit a page with Elementor and find the widgets in the "Atomora" category.

== Frequently Asked Questions ==

= Do I need Elementor Pro? =

No. Elementor Pro is optional. When active, the Reviews Form uses its native form elements.

= Do I need WooCommerce? =

Only for the product, cart and review widgets.

= What happens to my data if I delete the plugin? =

Nothing is removed unless you turn on the delete option under Settings > Atomora.

== Changelog ==

= 1.0.8 =
* Reviews List and Reviews Form are now containers with default child elements.
* Added review grid layout, native Elementor buttons and form fields for reviews.
* Added Structure panel move controls and a layout grid overlay to the editor.
* Crash report emails are now opt-in, with no default recipient.