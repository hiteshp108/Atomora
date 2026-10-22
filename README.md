# Atomora — Elementor Atomic Editor and WooCommerce Plugin

Atomora is a WordPress plugin that extends Elementor's Atomic Editor (Editor V4) with native Elementor widgets, WooCommerce product and cart elements, product reviews, display conditions, and editor tools. Atomora widgets use Elementor's native Style, Settings, and Interactions panels.

## Features

- **WooCommerce Elementor widgets:** Product Gallery Slider with thumbnails, lightbox, and autoplay; Product Card with quick view; Add to Cart and Buy Now buttons; quantity controls; variation selectors; and product price.
- **WooCommerce product reviews:** Nestable Reviews container with Reviews List and Reviews Form; customizable review item parts; review pagination or load more; and review average, count, stars, summary, and rating breakdown widgets.
- **Elementor display conditions:** Show or hide elements based on login state, WooCommerce stock, product price, category, tag, featured status, date range, custom field, or URL value.
- **Elementor editor tools:** CSS class highlighter, full class names in the Style tab, searchable tab navigator, Design System tools, Structure panel move controls, and a responsive layout grid overlay.
- **Admin utilities:** Optionally hide Elementor promotional notices and configure opt-in crash report emails.

## Requirements

- WordPress 6.3 or later
- PHP 8.1 or later
- Elementor 4.0 or later with the Atomic Editor enabled
- WooCommerce for product, cart, and review widgets
- Elementor Pro is optional; the Reviews Form also works without it

## Installation

1. Download this repository and extract it, or clone it with Git.
2. Upload the `atomora` directory to `/wp-content/plugins/`.
3. Activate **Atomora** from the Plugins screen.
4. Enable the Atomic Editor in Elementor's settings.
5. Edit a page with Elementor and find Atomora widgets in the **Atomora** category.

## Privacy

Atomora does not connect to external services or collect or send user data. Crash report emails are sent only if an email address is entered on the Crash Reports screen.

## Data removal

Plugin data is retained when Atomora is deleted unless the deletion option is enabled under **Settings > Atomora**.

## Plugin details

The plugin source is in [`atomora/`](atomora/). See the [plugin readme](atomora/readme.txt) for the full feature list, FAQ, requirements, and changelog. Current version: **1.0.8**.
