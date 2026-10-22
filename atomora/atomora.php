<?php
/**
 * Plugin Name:       Atomora
 * Plugin URI:        https://github.com/hiteshp108/Atomora
 * Description:       CSS-class-first widgets built natively for Elementor's Editor V4 (Atomic Editor). Adds V4-only Atomic widgets with the same Style/Settings/Interactions UI as native V4 elements. Ships with a WooCommerce-aware Gallery Slider and Product Card.
 * Version:           1.0.8
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * Author:            Hitesh Prajapati
 * Author URI:        https://github.com/hiteshp108
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       atomora
 * Requires Plugins:  elementor
 * Elementor tested up to: 4.3
 * Elementor Pro tested up to: 4.2
 *
 * @package Atomora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'ATOMORA_VERSION', '1.0.8' );
define( 'ATOMORA_FILE', __FILE__ );
define( 'ATOMORA_PATH', plugin_dir_path( __FILE__ ) );
define( 'ATOMORA_URL', plugin_dir_url( __FILE__ ) );
define( 'ATOMORA_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements this plugin needs before it touches anything else.
define( 'ATOMORA_MIN_PHP', '8.1' );
define( 'ATOMORA_MIN_WP', '6.3' );
define( 'ATOMORA_MIN_ELEMENTOR', '4.0.0' );

/**
 * Everything below this line only ever runs after we've confirmed the
 * environment is safe (right PHP/WP, Elementor active & new enough, and the
 * v4 Atomic Editor actually available). If any check fails we bail out
 * quietly with an admin notice instead of risking a fatal error on a site
 * we can't SSH into to fix.
 */
require_once ATOMORA_PATH . 'includes/class-atomora-compatibility.php';

if ( \Atomora\Compatibility::check() ) {
	require_once ATOMORA_PATH . 'includes/class-atomora-loader.php';
	\Atomora\Loader::instance();
}

// Uninstall data cleanup is handled in uninstall.php (only runs on plugin
// *deletion* from the Plugins screen, per the WordPress uninstall API).