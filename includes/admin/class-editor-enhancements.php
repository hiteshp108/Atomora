<?php
namespace Atomora\Admin;

use Atomora\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conditionally loads editor-only UX enhancement assets, gated per-feature
 * by the Atomora settings screen. Only enqueues CSS/JS for features the
 * site owner has enabled, and only inside the Elementor editor context.
 */
class Editor_Enhancements {

	public function __construct() {
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_assets' ] );
	}

	public function enqueue_editor_assets() {

		// Full class names (CSS only).
		if ( 'yes' === get_option( Loader::OPTION_FULL_CLASS_NAMES, 'yes' ) ) {
			wp_enqueue_style(
				'atomora-full-class-names',
				ATOMORA_URL . 'assets/css/full-class-names.css',
				[],
				ATOMORA_VERSION
			);
		}

		// Class highlighter (CSS + JS).
		if ( 'yes' === get_option( Loader::OPTION_CLASS_HIGHLIGHTER, 'yes' ) ) {
			wp_enqueue_style(
				'atomora-class-highlighter',
				ATOMORA_URL . 'assets/css/class-highlighter.css',
				[],
				ATOMORA_VERSION
			);
			wp_enqueue_script(
				'atomora-class-highlighter',
				ATOMORA_URL . 'assets/js/class-highlighter.js',
				[],
				ATOMORA_VERSION,
				true
			);
		}
		// Structure Navigator: Move up / down / in / out (context menu + row buttons).
		if ( 'yes' === get_option( 'atomora_navigator_move', 'yes' ) ) {
			wp_enqueue_style(
				'atomora-navigator-move',
				ATOMORA_URL . 'assets/css/navigator-move.css',
				[],
				ATOMORA_VERSION
			);
			wp_enqueue_script(
				'atomora-navigator-move',
				ATOMORA_URL . 'assets/js/navigator-move.js',
				[],
				ATOMORA_VERSION,
				true
			);
		}

		// Top bar button: toggles a Figma-style layout grid overlay on the canvas.
		if ( 'yes' === get_option( 'atomora_grid_overlay', 'yes' ) ) {
			wp_enqueue_script(
				'atomora-grid-overlay',
				ATOMORA_URL . 'assets/js/grid-overlay.js',
				wp_script_is( 'elementor-v2-editor-app-bar', 'registered' ) ? [ 'elementor-v2-editor-app-bar' ] : [],
				ATOMORA_VERSION,
				true
			);
		}

		// Tab Navigator: search + expand/collapse bar for Style sections
		// (CSS + JS).
		if ( 'yes' === get_option( Loader::OPTION_TAB_NAVIGATOR, 'yes' ) ) {
			wp_enqueue_style(
				'atomora-tab-navigator',
				ATOMORA_URL . 'assets/css/tab-navigator.css',
				[],
				ATOMORA_VERSION
			);
			wp_enqueue_script(
				'atomora-tab-navigator',
				ATOMORA_URL . 'assets/js/tab-navigator.js',
				[],
				ATOMORA_VERSION,
				true
			);
		}

		// EXPERIMENTAL — Design System quick-reorder (Move to top/up/down/
		// bottom on Global Classes rows). Off by default: unlike the other
		// editor enhancements above, this dispatches into Elementor's own
		// undocumented internal Redux store rather than using CSS or a
		// supported hook. See assets/js/design-system-tools.js for the
		// full explanation and its built-in self-check/warning behavior.
		if ( 'yes' === get_option( Loader::OPTION_DESIGN_SYSTEM_TOOLS, 'no' ) ) {
			wp_enqueue_script(
				'atomora-design-system-tools',
				ATOMORA_URL . 'assets/js/design-system-tools.js',
				[],
				ATOMORA_VERSION,
				true
			);
		}

		// EXPERIMENTAL — Sr. No. row numbers on Classes/Variables rows.
		// Independent toggle: purely cosmetic (never reads/writes
		// Elementor state), so it works whether or not the reorder
		// tools above are enabled. See design-system-row-numbers.js.
		if ( 'yes' === get_option( Loader::OPTION_DESIGN_SYSTEM_ROW_NUMBERS, 'no' ) ) {
			wp_enqueue_script(
				'atomora-design-system-row-numbers',
				ATOMORA_URL . 'assets/js/design-system-row-numbers.js',
				[],
				ATOMORA_VERSION,
				true
			);
		}

		// EXPERIMENTAL — Bulk select + bulk move (top/bottom) on Classes
		// and Variables rows. Delete is deliberately not part of this
		// build yet — see design-system-bulk-actions.js header.
		if ( 'yes' === get_option( Loader::OPTION_DESIGN_SYSTEM_BULK_ACTIONS, 'no' ) ) {
			wp_enqueue_script(
				'atomora-design-system-bulk-actions',
				ATOMORA_URL . 'assets/js/design-system-bulk-actions.js',
				[],
				ATOMORA_VERSION,
				true
			);
		}
	}
}