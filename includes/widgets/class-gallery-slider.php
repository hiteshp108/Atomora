<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
// EXPERIMENTAL / UNVERIFIED control class — see class-product-card.php for
// the same note. Used defensively via class_exists() at the call site.
use Elementor\Modules\AtomicWidgets\Controls\Types\Classes_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Background_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Color_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_Definition;
use Elementor\Modules\AtomicWidgets\Styles\Style_Variant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Atomora — Gallery Slider
 *
 * A native Elementor Editor V4 (Atomic) widget: css-class based styling,
 * built on Elementor's own Atomic_Widget_Base / Prop_Type / Control system
 * (no legacy Controls_Manager code at all). Renders a thumbnail + main-image
 * "grid-to-slider" gallery that can pull images from a single WooCommerce
 * product's own gallery, or from a live query of multiple products.
 */
class Gallery_Slider extends Atomic_Widget_Base {

	public static $widget_description = 'A responsive, WooCommerce-aware image gallery slider with a thumbnail + main-image layout.';

	public static function get_element_type(): string {
		return 'e-atomora-gallery-slider';
	}

	public function get_title() {
		return esc_html__( 'Gallery Slider', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-slider-image';
	}

	public function get_keywords() {
		return [ 'atomic', 'gallery', 'slider', 'woocommerce', 'product', 'images' ];
	}

	public function get_categories(): array {
		return [ 'atomora' ];
	}

	/**
	 * ------------------------------------------------------------------
	 * Style tab defaults. Matched to this widget's own CSS defaults
	 * (border-radius) so the Style tab reflects what's actually rendering
	 * rather than showing blank/unset values. Deliberately limited to a
	 * purely cosmetic property — never display/position/flex/aspect-ratio,
	 * all of which this widget's own CSS already controls with !important
	 * where it matters, so nothing here can fight that.
	 * ------------------------------------------------------------------
	 */
	protected function define_base_styles(): array {
		return [
			'base' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'border-radius', Size_Prop_Type::generate( [ 'size' => 8, 'unit' => 'px' ] ) )
				),

			// Sub-selector defaults (Mechanism A) — baked-in default styling
			// for specific sub-parts, matching this widget's own CSS.
			// Deliberately not true separate child elements: those would
			// break per-product/per-slide data when this widget is used
			// inside a loop, since default children are seeded once at
			// editor-time rather than recomputed on every render.
			'base .atomora-gallery-slider__arrow' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( '#ffffff' ),
						] ) )
				),

			'base .atomora-gallery-slider__thumb' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'border-radius', Size_Prop_Type::generate( [ 'size' => 6, 'unit' => 'px' ] ) )
				),

			// --- Per-addon sub-selector defaults (Mechanism A): Thumbnails
			// row and Lightbox now have their own entries here, matching
			// their own Settings sections and their own markup class —
			// same convention as Arrows above.
			'base .atomora-gallery-slider__thumbs' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( 'transparent' ),
						] ) )
				),

			'base .atomora-gallery-slider__lightbox' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( 'rgba(0, 0, 0, 0.9)' ),
						] ) )
				),

			'base .atomora-gallery-slider__lightbox-close' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( 'rgba(255, 255, 255, 0.9)' ),
						] ) )
				),
		];
	}
    
	/**
	 * ------------------------------------------------------------------
	 * Props schema — the single source of truth. Elementor's own V4 panel
	 * reads this to build the Settings UI (via define_atomic_controls())
	 * and the automatic Style tab (via the widget's base styles + the
	 * global style system), so there's nothing to hand-roll on the JS side.
	 * ------------------------------------------------------------------
	 */
	protected static function define_props_schema(): array {
		return [
			'classes' => Classes_Prop_Type::make()->default( [] ),

			// --- Data source ---
			'source' => String_Prop_Type::make()
				->enum( [ 'product_gallery', 'product_query' ] )
				->default( 'product_gallery' ),

			'product_source' => String_Prop_Type::make()
				->enum( [ 'current', 'manual' ] )
				->default( 'current' ),

			'manual_product_id' => Number_Prop_Type::make()->default( 0 ),

			'query_type' => String_Prop_Type::make()
				->enum( [ 'recent', 'featured', 'best_selling', 'on_sale', 'category' ] )
				->default( 'featured' ),

			'category_slug' => String_Prop_Type::make()->default( '' ),

			'products_limit' => Number_Prop_Type::make()->default( 8 ),

			'link_to_product' => Boolean_Prop_Type::make()->default( true ),

			// --- Layout ---
			'thumbnails_position' => String_Prop_Type::make()
				->enum( [ 'bottom', 'left', 'right', 'none' ] )
				->default( 'bottom' ),

			// Tablet/mobile default to "inherit" — meaning "use the value
			// from the next breakpoint up" (mobile inherits tablet, tablet
			// inherits desktop). Nothing auto-changes on smaller screens
			// unless you explicitly pick a different value for that device.
			'thumbnails_position_tablet' => String_Prop_Type::make()
				->enum( [ 'inherit', 'bottom', 'left', 'right', 'none' ] )
				->default( 'inherit' ),
			'thumbnails_position_mobile' => String_Prop_Type::make()
				->enum( [ 'inherit', 'bottom', 'left', 'right', 'none' ] )
				->default( 'inherit' ),

			// --- Slider box shape & image fit ---
			// "Ratio" governs the outer slider box (.main); "Fit" governs
			// how each image is displayed within that fixed box — they're
			// two separate concerns, hence two separate controls.
			'slider_ratio' => String_Prop_Type::make()
				->enum( [ 'viewport', '1x1', '4x3', '3x4', '16x9', '9x16', '3x2', '2x3', 'auto' ] )
				->default( 'viewport' ),
			'image_fit' => String_Prop_Type::make()
				->enum( [ 'cover', 'contain', 'fill' ] )
				->default( 'cover' ),

			// --- Behaviour ---
			'autoplay'       => Boolean_Prop_Type::make()->default( false ),
			'autoplay_speed' => Number_Prop_Type::make()->default( 4000 ),
			'loop'           => Boolean_Prop_Type::make()->default( true ),
			'show_arrows'    => Boolean_Prop_Type::make()->default( true ),
			'lightbox'       => Boolean_Prop_Type::make()->default( false ),
			'hover_zoom'       => Boolean_Prop_Type::make()->default( true ),
			'hover_zoom_scale' => Number_Prop_Type::make()->default( 1.8 ),
			'lazy_load'      => Boolean_Prop_Type::make()->default( true ),

			// --- Per-addon class props (mirrors Product Card pattern) ---
			'thumbnails_classes' => Classes_Prop_Type::make()->default( [] ),
			'arrows_classes'     => Classes_Prop_Type::make()->default( [] ),
			'lightbox_classes'   => Classes_Prop_Type::make()->default( [] ),

			'attributes' => Attributes_Prop_Type::make(),

			// --- Native Display Conditions (Elementor Pro) ---
			// Replaces the old hand-built system — see Product Card for
			// full rationale. Guarded: only added if Elementor Pro's
			// Display Conditions module is actually present/licensed.
			...( class_exists( '\ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type' )
				? [ 'display-conditions' => \ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type::make() ]
				: [] ),

			// --- Widget-level General group (locked rule) ---
			// 'html_tag' is a reserved prop name — Elementor renders its
			// own native control for it in a "Structure" group (confirmed
			// live), so the enum must match its 6 native options exactly.
			'html_tag' => String_Prop_Type::make()
				->enum( [ 'div', 'header', 'section', 'article', 'aside', 'footer' ] )
				->default( 'div' ),
		];
	}

	/**
	 * ------------------------------------------------------------------
	 * The native V4 panel UI. Grouped exactly like Elementor's own atomic
	 * widgets (Content / Settings sections); the Style tab itself is
	 * generated automatically by the Atomic Editor from the base styling
	 * system, so it never needs to be defined here.
	 * ------------------------------------------------------------------
	 */
	protected function define_atomic_controls(): array {
		return [
			Section::make()
				->set_id( 'source' )
				->set_label( esc_html__( 'Source', 'atomora' ) )
				->set_items( [
					Select_Control::bind_to( 'source' )
						->set_label( esc_html__( 'Show', 'atomora' ) )
						->set_options( [
							[ 'value' => 'product_gallery', 'label' => esc_html__( 'Single product gallery', 'atomora' ) ],
							[ 'value' => 'product_query', 'label' => esc_html__( 'Multiple products (query)', 'atomora' ) ],
						] ),

					Select_Control::bind_to( 'product_source' )
						->set_label( esc_html__( 'Which product', 'atomora' ) )
						->set_options( [
							[ 'value' => 'current', 'label' => esc_html__( 'Current product (dynamic)', 'atomora' ) ],
							[ 'value' => 'manual', 'label' => esc_html__( 'Specific product ID', 'atomora' ) ],
						] ),

					Number_Control::bind_to( 'manual_product_id' )
						->set_label( esc_html__( 'Product ID', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. 128', 'atomora' ) )
						->set_min( 0 ),

					Select_Control::bind_to( 'query_type' )
						->set_label( esc_html__( 'Product query', 'atomora' ) )
						->set_options( [
							[ 'value' => 'recent', 'label' => esc_html__( 'Recent products', 'atomora' ) ],
							[ 'value' => 'featured', 'label' => esc_html__( 'Featured products', 'atomora' ) ],
							[ 'value' => 'best_selling', 'label' => esc_html__( 'Best selling', 'atomora' ) ],
							[ 'value' => 'on_sale', 'label' => esc_html__( 'On sale', 'atomora' ) ],
							[ 'value' => 'category', 'label' => esc_html__( 'Specific category', 'atomora' ) ],
						] ),

					Text_Control::bind_to( 'category_slug' )
						->set_label( esc_html__( 'Category slug', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. phone-cases', 'atomora' ) ),

					Number_Control::bind_to( 'products_limit' )
						->set_label( esc_html__( 'Number of products', 'atomora' ) )
						->set_min( 1 )
						->set_max( 50 ),

					Switch_Control::bind_to( 'link_to_product' )
						->set_label( esc_html__( 'Link slides to product page', 'atomora' ) ),
				] ),

			Section::make()
				->set_id( 'layout' )
				->set_label( esc_html__( 'Layout', 'atomora' ) )
				->set_items( [
					Select_Control::bind_to( 'slider_ratio' )
						->set_label( esc_html__( 'Slider ratio', 'atomora' ) )
						->set_options( [
							[ 'value' => 'viewport', 'label' => esc_html__( 'Full height (fills the screen height)', 'atomora' ) ],
							[ 'value' => '1x1', 'label' => esc_html__( 'Square (1:1)', 'atomora' ) ],
							[ 'value' => '4x3', 'label' => esc_html__( 'Landscape (4:3)', 'atomora' ) ],
							[ 'value' => '3x4', 'label' => esc_html__( 'Portrait (3:4)', 'atomora' ) ],
							[ 'value' => '16x9', 'label' => esc_html__( 'Widescreen (16:9)', 'atomora' ) ],
							[ 'value' => '9x16', 'label' => esc_html__( 'Tall (9:16)', 'atomora' ) ],
							[ 'value' => '3x2', 'label' => esc_html__( 'Landscape (3:2)', 'atomora' ) ],
							[ 'value' => '2x3', 'label' => esc_html__( 'Portrait (2:3)', 'atomora' ) ],
							[ 'value' => 'auto', 'label' => esc_html__( 'Auto (box follows each image\'s own shape)', 'atomora' ) ],
						] ),

					Select_Control::bind_to( 'image_fit' )
						->set_label( esc_html__( 'Image fit', 'atomora' ) )
						->set_options( [
							[ 'value' => 'cover', 'label' => esc_html__( 'Cover (fills box, may crop)', 'atomora' ) ],
							[ 'value' => 'contain', 'label' => esc_html__( 'Contain (shows full image, may letterbox)', 'atomora' ) ],
							[ 'value' => 'fill', 'label' => esc_html__( 'Fill (stretches to fit)', 'atomora' ) ],
						] ),
				] ),

			// --- Each addon below is its own Section: own settings, own
			// markup class, own style sub-selector (see define_base_styles()) —
			// tied to the parent slider's slide data, never a standalone
			// widget, same as native Loop → Pagination.
			Section::make()
				->set_id( 'thumbnails' )
				->set_label( esc_html__( 'Thumbnails', 'atomora' ) )
				->set_items( array_filter( [
					Select_Control::bind_to( 'thumbnails_position' )
						->set_label( esc_html__( 'Thumbnails (Desktop)', 'atomora' ) )
						->set_options( [
							[ 'value' => 'bottom', 'label' => esc_html__( 'Bottom', 'atomora' ) ],
							[ 'value' => 'left', 'label' => esc_html__( 'Left', 'atomora' ) ],
							[ 'value' => 'right', 'label' => esc_html__( 'Right', 'atomora' ) ],
							[ 'value' => 'none', 'label' => esc_html__( 'Hidden', 'atomora' ) ],
						] ),

					Select_Control::bind_to( 'thumbnails_position_tablet' )
						->set_label( esc_html__( 'Thumbnails (Tablet)', 'atomora' ) )
						->set_options( [
							[ 'value' => 'inherit', 'label' => esc_html__( 'Inherit from Desktop', 'atomora' ) ],
							[ 'value' => 'bottom', 'label' => esc_html__( 'Bottom', 'atomora' ) ],
							[ 'value' => 'left', 'label' => esc_html__( 'Left', 'atomora' ) ],
							[ 'value' => 'right', 'label' => esc_html__( 'Right', 'atomora' ) ],
							[ 'value' => 'none', 'label' => esc_html__( 'Hidden', 'atomora' ) ],
						] ),

					Select_Control::bind_to( 'thumbnails_position_mobile' )
						->set_label( esc_html__( 'Thumbnails (Mobile)', 'atomora' ) )
						->set_options( [
							[ 'value' => 'inherit', 'label' => esc_html__( 'Inherit from Tablet', 'atomora' ) ],
							[ 'value' => 'bottom', 'label' => esc_html__( 'Bottom', 'atomora' ) ],
							[ 'value' => 'left', 'label' => esc_html__( 'Left', 'atomora' ) ],
							[ 'value' => 'right', 'label' => esc_html__( 'Right', 'atomora' ) ],
							[ 'value' => 'none', 'label' => esc_html__( 'Hidden', 'atomora' ) ],
						] ),
					$this->maybe_classes_control( 'thumbnails_classes' ),
				] ) ),

			Section::make()
				->set_id( 'arrows' )
				->set_label( esc_html__( 'Arrows', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'show_arrows' )
						->set_label( esc_html__( 'Show arrows', 'atomora' ) ),
					$this->maybe_classes_control( 'arrows_classes' ),
				] ) ),

			Section::make()
				->set_id( 'autoplay' )
				->set_label( esc_html__( 'Autoplay', 'atomora' ) )
				->set_items( [
					Switch_Control::bind_to( 'autoplay' )
						->set_label( esc_html__( 'Autoplay', 'atomora' ) ),
					Number_Control::bind_to( 'autoplay_speed' )
						->set_label( esc_html__( 'Autoplay speed (ms)', 'atomora' ) )
						->set_min( 1000 )
						->set_step( 500 ),
				] ),

			Section::make()
				->set_id( 'lightbox' )
				->set_label( esc_html__( 'Lightbox', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'lightbox' )
						->set_label( esc_html__( 'Enable lightbox / zoom on click', 'atomora' ) ),
					Switch_Control::bind_to( 'hover_zoom' )
						->set_label( esc_html__( 'Zoom image on hover (like WooCommerce\'s default gallery)', 'atomora' ) ),
					Number_Control::bind_to( 'hover_zoom_scale' )
						->set_label( esc_html__( 'Hover zoom level', 'atomora' ) )
						->set_min( 1.2 )
						->set_max( 4 )
						->set_step( 0.1 ),
					$this->maybe_classes_control( 'lightbox_classes' ),
				] ) ),

			Section::make()
				->set_id( 'behavior' )
				->set_label( esc_html__( 'Behavior', 'atomora' ) )
				->set_items( [
					Switch_Control::bind_to( 'loop' )
						->set_label( esc_html__( 'Loop slides', 'atomora' ) ),
					Switch_Control::bind_to( 'lazy_load' )
						->set_label( esc_html__( 'Lazy-load images', 'atomora' ) ),
				] ),

			// Widget-level General/Settings group — ID, Attributes —
			// matching native Atomic Elements' own Settings group (locked
			// rule, applied once per widget, not per addon). HTML Tag is
			// NOT added here: it's a reserved prop name Elementor renders
			// natively in its own "Structure" group instead. Link is
			// deliberately omitted: the slider contains its own thumbnail
			// and arrow buttons, so wrapping the root in <a> would nest
			// interactive elements, which is invalid HTML.
			Section::make()
				->set_id( 'settings' )
				->set_label( esc_html__( 'Settings', 'atomora' ) )
				->set_items( array_filter( [
					Text_Control::bind_to( '_cssid' )
						->set_label( esc_html__( 'ID', 'atomora' ) )
						->set_meta( $this->get_css_id_control_meta() ),

					$this->maybe_attributes_control( 'attributes' ),
				] ) ),
		];
	}

	/**
	 * Guarded Classes_Control builder — see class-product-card.php for the
	 * full rationale. Returns null (filtered out via array_filter) if the
	 * control class is missing/incompatible, instead of fataling.
	 */
	private function maybe_classes_control( string $prop_name ) {
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Classes_Control' ) ) {
			return null;
		}

		try {
			return Classes_Control::bind_to( $prop_name )
				->set_label( esc_html__( 'CSS Classes', 'atomora' ) );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Classes_Control unavailable for ' . $prop_name . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		}
	}

	/**
	 * Guarded Attributes_Control builder — same pattern as
	 * maybe_classes_control(). Widget-level only.
	 */
	private function maybe_attributes_control( string $prop_name ) {
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Attributes_Control' ) ) {
			return null;
		}

		try {
			return \Elementor\Modules\AtomicWidgets\Controls\Types\Attributes_Control::bind_to( $prop_name )
				->set_label( esc_html__( 'Attributes', 'atomora' ) );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Attributes_Control unavailable for ' . $prop_name . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		}
	}

	/**
	 * Merges an addon's own class prop into a base class, sanitized —
	 * same pattern as the root '_wrapper' class handling.
	 */
	private function addon_class_string( string $base_class, array $settings, string $prop_name ): string {
		$classes = [ $base_class ];

		if ( ! empty( $settings[ $prop_name ] ) && is_array( $settings[ $prop_name ] ) ) {
			foreach ( $settings[ $prop_name ] as $class ) {
				$classes[] = sanitize_html_class( $class );
			}
		}

		return implode( ' ', array_filter( $classes ) );
	}

	/**
	 * ------------------------------------------------------------------
	 * Assets: only enqueued on pages/requests where the widget is actually
	 * present, per Elementor's own dependency system — no dead weight, no
	 * global-footer script.
	 * ------------------------------------------------------------------
	 */
	public function get_style_depends() {
		wp_register_style(
			'atomora-gallery-slider',
			ATOMORA_URL . 'assets/css/gallery-slider.css',
			[],
			ATOMORA_VERSION
		);
		return [ 'atomora-gallery-slider' ];
	}

	public function get_script_depends() {
		wp_register_script(
			'atomora-gallery-slider',
			ATOMORA_URL . 'assets/js/gallery-slider.js',
			[],
			ATOMORA_VERSION,
			true
		);
		return [ 'atomora-gallery-slider' ];
	}

	/**
	 * ------------------------------------------------------------------
	 * Frontend render. Deliberately plain PHP (no Twig) so the widget has
	 * no dependency on Elementor's internal template-rendering pipeline —
	 * only on the stable Atomic_Widget_Base contract (get_atomic_settings()).
	 * ------------------------------------------------------------------
	 */
	protected function render() {
		try {
			$this->render_widget();
		} catch ( \Throwable $e ) {
			\Atomora\Admin\Crash_Reporter::instance()->report( 'Gallery Slider render', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Gallery Slider render error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			if ( current_user_can( 'edit_posts' ) ) {
				printf(
					'<div class="atomora-gallery-slider__empty">%s</div>',
					esc_html__( 'Gallery Slider hit an unexpected error while rendering (likely an Elementor Atomic Widgets update). Check the site\'s debug log for details.', 'atomora' )
				);
			}
		}
	}

	private function render_widget() {
		$settings = $this->get_atomic_settings();

		$slides = $this->get_slides( $settings );

		$thumb_positions = $this->resolve_thumbnail_positions( $settings );

		$this->add_render_attribute( '_wrapper', 'class', 'atomora-gallery-slider' );

		if ( ! empty( $settings['classes'] ) && is_array( $settings['classes'] ) ) {
			foreach ( $settings['classes'] as $class ) {
				$this->add_render_attribute( '_wrapper', 'class', sanitize_html_class( $class ) );
			}
		}

		$this->add_render_attribute( '_wrapper', 'data-thumbs-desktop', $thumb_positions['desktop'] );
		$this->add_render_attribute( '_wrapper', 'data-thumbs-tablet', $thumb_positions['tablet'] );
		$this->add_render_attribute( '_wrapper', 'data-thumbs-mobile', $thumb_positions['mobile'] );
		$this->add_render_attribute( '_wrapper', 'data-ratio', sanitize_html_class( $settings['slider_ratio'] ?? '1x1' ) );
		$this->add_render_attribute( '_wrapper', 'data-fit', sanitize_html_class( $settings['image_fit'] ?? 'contain' ) );
		$this->add_render_attribute( '_wrapper', 'data-autoplay', ! empty( $settings['autoplay'] ) ? '1' : '0' );
		$this->add_render_attribute( '_wrapper', 'data-autoplay-speed', absint( $settings['autoplay_speed'] ?? 4000 ) );
		$this->add_render_attribute( '_wrapper', 'data-loop', ! empty( $settings['loop'] ) ? '1' : '0' );
		$this->add_render_attribute( '_wrapper', 'data-arrows', ! empty( $settings['show_arrows'] ) ? '1' : '0' );
		$this->add_render_attribute( '_wrapper', 'data-lightbox', ! empty( $settings['lightbox'] ) ? '1' : '0' );
		$this->add_render_attribute( '_wrapper', 'data-hover-zoom', ! empty( $settings['hover_zoom'] ) ? '1' : '0' );
		$this->add_render_attribute( '_wrapper', 'data-hover-zoom-scale', esc_attr( (float) ( $settings['hover_zoom_scale'] ?? 1.8 ) ) );

		$custom_attrs = $settings['attributes'] ?? [];
		if ( is_array( $custom_attrs ) ) {
			foreach ( $custom_attrs as $attr ) {
				if ( ! empty( $attr['key'] ) && is_string( $attr['key'] ) ) {
					$this->add_render_attribute( '_wrapper', sanitize_key( $attr['key'] ), $attr['value'] ?? '' );
				}
			}
		}

		$root_tag = $settings['html_tag'] ?? 'div';
		if ( ! in_array( $root_tag, [ 'div', 'header', 'section', 'article', 'aside', 'footer' ], true ) ) {
			$root_tag = 'div';
		}

		if ( empty( $slides ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return;
			}
			printf(
				'<%1$s %2$s><p class="atomora-gallery-slider__empty">%3$s</p></%1$s>',
				esc_html( $root_tag ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$this->get_render_attribute_string( '_wrapper' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html__( 'Gallery Slider: no images found yet. Check the product source in the Source settings, or that WooCommerce is active.', 'atomora' )
			);
			return;
		}

		$loading_attr = empty( $settings['lazy_load'] ) ? 'eager' : 'lazy';
		?>
		<<?php echo esc_html( $root_tag ); ?> <?php $this->print_render_attribute_string( '_wrapper' ); ?>>
			<div class="atomora-gallery-slider__main">
				<?php foreach ( $slides as $index => $slide ) : ?>
					<figure class="atomora-gallery-slider__slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-index="<?php echo esc_attr( $index ); ?>">
						<?php if ( ! empty( $slide['link'] ) ) : ?>
							<a href="<?php echo esc_url( $slide['link'] ); ?>" class="atomora-gallery-slider__link">
						<?php endif; ?>

						<img
							src="<?php echo esc_url( $slide['full'] ); ?>"
							alt="<?php echo esc_attr( $slide['alt'] ); ?>"
							loading="<?php echo esc_attr( $loading_attr ); ?>"
							decoding="async"
							class="atomora-gallery-slider__image"
							<?php if ( ! empty( $settings['lightbox'] ) ) : ?>
								data-lightbox-src="<?php echo esc_url( $slide['full'] ); ?>"
							<?php endif; ?>
						/>

						<?php if ( ! empty( $slide['link'] ) ) : ?>
							</a>
						<?php endif; ?>
					</figure>
				<?php endforeach; ?>

				<?php if ( ! empty( $settings['show_arrows'] ) && count( $slides ) > 1 ) : ?>
					<button type="button" class="<?php echo esc_attr( $this->addon_class_string( 'atomora-gallery-slider__arrow atomora-gallery-slider__arrow--prev', $settings, 'arrows_classes' ) ); ?>" aria-label="<?php esc_attr_e( 'Previous slide', 'atomora' ); ?>">&#10094;</button>
					<button type="button" class="<?php echo esc_attr( $this->addon_class_string( 'atomora-gallery-slider__arrow atomora-gallery-slider__arrow--next', $settings, 'arrows_classes' ) ); ?>" aria-label="<?php esc_attr_e( 'Next slide', 'atomora' ); ?>">&#10095;</button>
				<?php endif; ?>
			</div>

			<?php if ( count( $slides ) > 1 ) : ?>
				<div class="<?php echo esc_attr( $this->addon_class_string( 'atomora-gallery-slider__thumbs', $settings, 'thumbnails_classes' ) ); ?>">
					<?php foreach ( $slides as $index => $slide ) : ?>
						<button
							type="button"
							class="atomora-gallery-slider__thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
							data-index="<?php echo esc_attr( $index ); ?>"
							aria-label="<?php echo esc_attr( $slide['alt'] ); ?>"
						>
							<img
								src="<?php echo esc_url( $slide['thumb'] ); ?>"
								alt=""
								loading="<?php echo esc_attr( $loading_attr ); ?>"
								decoding="async"
							/>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $settings['lightbox'] ) ) : ?>
				<div class="<?php echo esc_attr( $this->addon_class_string( 'atomora-gallery-slider__lightbox', $settings, 'lightbox_classes' ) ); ?>" hidden>
					<button type="button" class="atomora-gallery-slider__lightbox-close" aria-label="<?php esc_attr_e( 'Close', 'atomora' ); ?>">&times;</button>
					<?php if ( count( $slides ) > 1 ) : ?>
						<button type="button" class="atomora-gallery-slider__lightbox-arrow atomora-gallery-slider__lightbox-arrow--prev" aria-label="<?php esc_attr_e( 'Previous image', 'atomora' ); ?>">&#10094;</button>
						<button type="button" class="atomora-gallery-slider__lightbox-arrow atomora-gallery-slider__lightbox-arrow--next" aria-label="<?php esc_attr_e( 'Next image', 'atomora' ); ?>">&#10095;</button>
					<?php endif; ?>
					<img src="" alt="" class="atomora-gallery-slider__lightbox-image" />
				</div>
			<?php endif; ?>
		</<?php echo esc_html( $root_tag ); ?>>
		<?php
	}

	private function resolve_thumbnail_positions( array $settings ): array {
		$desktop = $settings['thumbnails_position'] ?? 'bottom';

		$tablet = $settings['thumbnails_position_tablet'] ?? 'inherit';
		if ( 'inherit' === $tablet ) {
			$tablet = $desktop;
		}

		$mobile = $settings['thumbnails_position_mobile'] ?? 'inherit';
		if ( 'inherit' === $mobile ) {
			$mobile = $tablet;
		}

		return [
			'desktop' => sanitize_html_class( $desktop ),
			'tablet'  => sanitize_html_class( $tablet ),
			'mobile'  => sanitize_html_class( $mobile ),
		];
	}

	private function get_slides( array $settings ): array {
		if ( ! class_exists( '\WooCommerce' ) ) {
			return [];
		}

		if ( 'product_gallery' === ( $settings['source'] ?? 'product_gallery' ) ) {
			return $this->get_single_product_slides( $settings );
		}

		return $this->get_query_slides( $settings );
	}

	private function resolve_product( array $settings ) {
		if ( 'manual' === ( $settings['product_source'] ?? 'current' ) ) {
			$product_id = absint( $settings['manual_product_id'] ?? 0 );
			return $product_id ? wc_get_product( $product_id ) : false;
		}

		global $product;
		if ( $product instanceof \WC_Product ) {
			return $product;
		}

		$post_id = get_the_ID();
		if ( $post_id && 'product' === get_post_type( $post_id ) ) {
			return wc_get_product( $post_id );
		}

		return false;
	}

	private function get_single_product_slides( array $settings ): array {
		$product = $this->resolve_product( $settings );

		if ( ! $product instanceof \WC_Product ) {
			return [];
		}

		$image_ids = array_filter( array_merge(
			[ $product->get_image_id() ],
			$product->get_gallery_image_ids()
		) );

		$use_original_size = 'auto' === ( $settings['slider_ratio'] ?? 'viewport' );

		$slides = [];
		foreach ( $image_ids as $attachment_id ) {
			$slides[] = $this->build_slide_from_attachment( $attachment_id, $product->get_name(), $use_original_size );
		}

		return $slides;
	}

	private function get_query_slides( array $settings ): array {
		$limit = max( 1, absint( $settings['products_limit'] ?? 8 ) );
		$type  = $settings['query_type'] ?? 'featured';

		$args = [
			'status' => 'publish',
			'limit'  => $limit,
			'return' => 'objects',
		];

		switch ( $type ) {
			case 'featured':
				$args['featured'] = true;
				break;
			case 'best_selling':
				$args['orderby'] = 'popularity';
				break;
			case 'on_sale':
				$ids = wc_get_product_ids_on_sale();
				if ( empty( $ids ) ) {
					return [];
				}
				$args['include'] = array_slice( $ids, 0, $limit );
				break;
			case 'category':
				$slug = sanitize_title( $settings['category_slug'] ?? '' );
				if ( '' === $slug ) {
					return [];
				}
				$args['category'] = [ $slug ];
				break;
			case 'recent':
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
		}

		$products           = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : [];
		$use_original_size  = 'auto' === ( $settings['slider_ratio'] ?? 'viewport' );

		$slides = [];
		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$slide = $this->build_slide_from_attachment( $product->get_image_id(), $product->get_name(), $use_original_size );

			if ( empty( $slide['full'] ) ) {
				continue;
			}

			if ( ! empty( $settings['link_to_product'] ) ) {
				$slide['link'] = get_permalink( $product->get_id() );
			}

			$slides[] = $slide;
		}

		return $slides;
	}

	private function build_slide_from_attachment( $attachment_id, string $fallback_alt, bool $use_original_size = false ): array {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return [ 'full' => '', 'thumb' => '', 'alt' => $fallback_alt, 'link' => '' ];
		}

		// "Auto" ratio needs each image's true, uncropped proportions to do
		// its job. WooCommerce's 'woocommerce_single' size is commonly
		// configured to hard-crop every image to one fixed ratio — which
		// would make every slide come out the same shape regardless of the
		// original photo, and Auto mode would have nothing left to adapt to.
		$full = $use_original_size
			? wp_get_attachment_image_url( $attachment_id, 'full' )
			: wp_get_attachment_image_url( $attachment_id, 'woocommerce_single' );
		$thumb = wp_get_attachment_image_url( $attachment_id, 'woocommerce_gallery_thumbnail' );
		$alt   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		return [
			'full'  => $full ? $full : wp_get_attachment_url( $attachment_id ),
			'thumb' => $thumb ? $thumb : $full,
			'alt'   => $alt ? $alt : $fallback_alt,
			'link'  => '',
		];
	}
}