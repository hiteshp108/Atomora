<?php
namespace Atomora\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hides Elementor's own promotional/upsell UI (the "AI" nags, "Go Pro"
 * banners, image-optimizer ads, etc.) inside wp-admin and the Elementor
 * editor — opt-in via Atomora's Settings page.
 *
 * IMPORTANT — set expectations honestly: Elementor has an open, unresolved
 * feature request for an official "disable promotions" toggle (as of this
 * writing, still not implemented). There is no supported API for this.
 * This class uses the same targeted CSS-hiding + script-dequeue approach
 * the wider Elementor community uses, scoped to specific known class
 * names/IDs (never a blanket ".notice" hide, so other plugins' genuine
 * notices are never touched). Because it targets Elementor's own current
 * class names rather than a stable API, a future Elementor update could
 * rename something and require this list to be updated.
 */
class Hide_Elementor_Ads {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'print_admin_css' ] );
		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'print_editor_css' ] );
		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'dequeue_ai_scripts' ] );
	}

	/**
	 * Scoped to specific Elementor-owned selectors only — deliberately
	 * NOT a blanket ".notice" hide, so genuine notices from other
	 * plugins/WordPress core are never affected.
	 */
	public function print_admin_css() {
		wp_register_style( 'atomora-hide-ads-admin', false, [], ATOMORA_VERSION );
		wp_enqueue_style( 'atomora-hide-ads-admin' );
		wp_add_inline_style(
			'atomora-hide-ads-admin',
			'.e-notice--cta,.elementor-control-media__promotions,.e-image-ai-media-library,#e-image-ai-attachment-details,#e-image-ai-insert-media{display:none !important}'
		);
	}

	/**
	 * Editor-side counterpart (Elementor editor page).
	 */
	public function print_editor_css() {
		wp_register_style( 'atomora-hide-ads-editor', false, [], ATOMORA_VERSION );
		wp_enqueue_style( 'atomora-hide-ads-editor' );
		wp_add_inline_style(
			'atomora-hide-ads-editor',
			'.e-ai-button,.elementor-control-media__promotions,.elementor-nerd-box,.e-notice--cta{display:none !important}'
		);
	}

	public function dequeue_ai_scripts() {
		if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		wp_dequeue_script( 'elementor-ai' );
		wp_dequeue_script( 'elementor-ai-media-library' );
	}
}