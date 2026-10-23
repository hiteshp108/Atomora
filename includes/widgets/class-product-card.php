<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
// EXPERIMENTAL / UNVERIFIED: this control class is not part of the plugin's
// confirmed-safe API surface yet (see Compatibility::required_atomic_widgets_classes()).
// Wrapped in class_exists() everywhere it's used below so a missing/renamed
// class degrades to "no per-addon class field" instead of a fatal.
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
 * Atomora — Product Card
 *
 * A native Elementor Editor V4 (Atomic) widget: hover-reveal WooCommerce
 * product card (image swap, title/subtitle/swatches/price/Add to Cart),
 * plus wishlist, star rating, stock/new/free-shipping badges, a sale
 * countdown, and an AJAX quick-view popup that reuses WooCommerce's own
 * single-product add-to-cart template rather than reimplementing cart
 * logic — so variable products, AJAX add-to-cart, and any theme filters
 * already hooked into that template keep working exactly as on the real
 * product page. No legacy Elementor V3 widget code anywhere in this file.
 */
class Product_Card extends Atomic_Widget_Base {

	public static $widget_description = 'A WooCommerce product card with hover-reveal details, quick view, wishlist, and stock/sale badges.';

	public function __construct( $data = [], $args = null ) {
		parent::__construct( $data, $args );

		// Registered here (not in the Loader) so it lives with the rest of
		// the widget's logic. Safe to register unconditionally — Elementor
		// only ever constructs one instance of this widget class per
		// request, so this can't double-register across multiple card
		// placements on the same page.
		}

	public static function get_element_type(): string {
		return 'e-atomora-product-card';
	}

	public function get_title() {
		return esc_html__( 'Product Card', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-product-images';
	}

	public function get_keywords() {
		return [ 'atomic', 'product', 'card', 'woocommerce', 'shop', 'quick view' ];
	}

	public function get_categories(): array {
		return [ 'atomora' ];
	}

	/**
	 * ------------------------------------------------------------------
	 * Style tab defaults. Matched to this widget's own CSS defaults
	 * (border-radius, white background) so the Style tab reflects what's
	 * actually rendering rather than showing blank/unset values.
	 * Deliberately limited to purely cosmetic properties — never display/
	 * position/flex — so nothing here can ever fight the widget's own CSS,
	 * which already handles all structural layout.
	 * ------------------------------------------------------------------
	 */
	protected function define_base_styles(): array {
		return [
			'base' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'border-radius', Size_Prop_Type::generate( [ 'size' => 12, 'unit' => 'px' ] ) )
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( '#ffffff' ),
						] ) )
				),

			// Sub-selector defaults (Mechanism A): these give Elementor's
			// own generated stylesheet the same defaults our CSS file
			// already applies to specific sub-parts. This does NOT create
			// an independent Style-tab section for these parts — it's
			// baked-in default styling only, matching what's documented
			// as the deliberate, loop-safe choice over true separate child
			// elements (which would break per-product data in a loop).
			'base .atomora-product-card__wishlist' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( 'rgba(255, 255, 255, 0.92)' ),
						] ) )
				),

			'base .atomora-product-card__quick-view' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( 'rgba(255, 255, 255, 0.95)' ),
						] ) )
				),

			'base .atomora-product-card__cart .button' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'border-radius', Size_Prop_Type::generate( [ 'size' => 8, 'unit' => 'px' ] ) )
				),

			// --- Per-addon sub-selector defaults (Mechanism A). Each addon
			// below (badges, countdown, rating, swatches) now has its own
			// entry here, matching its own Settings section and its own
			// class in the markup — same convention as wishlist/quick-view
			// above and as native Elementor's Loop → Pagination pattern.
			'base .atomora-product-card__badge--sale' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( '#e53935' ),
						] ) )
				),

			'base .atomora-product-card__badge--new' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( '#6d28d9' ),
						] ) )
				),

			'base .atomora-product-card__badge--low-stock' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( '#f59e0b' ),
						] ) )
				),

			'base .atomora-product-card__badge--out-of-stock' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( '#6b7280' ),
						] ) )
				),

			'base .atomora-product-card__badge--shipping' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( '#16a34a' ),
						] ) )
				),

			'base .atomora-product-card__countdown' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( 'rgba(0, 0, 0, 0.75)' ),
						] ) )
						->add_prop( 'border-radius', Size_Prop_Type::generate( [ 'size' => 6, 'unit' => 'px' ] ) )
				),

			'base .atomora-product-card__rating' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'color', Color_Prop_Type::generate( '#f59e0b' ) )
				),

			'base .atomora-product-card__swatches' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( 'transparent' ),
						] ) )
				),
		];
	}

	/**
	 * ------------------------------------------------------------------
	 * Props schema.
	 * ------------------------------------------------------------------
	 */
	protected static function define_props_schema(): array {
		return [
			'classes' => Classes_Prop_Type::make()->default( [] ),

			// --- Source ---
			'product_source' => String_Prop_Type::make()
				->enum( [ 'current', 'manual' ] )
				->default( 'current' ),
			'manual_product_id' => Number_Prop_Type::make()->default( 0 ),

			// --- Content ---
			'show_discount_badge' => Boolean_Prop_Type::make()->default( true ),
			'note_text'           => String_Prop_Type::make()->default( '' ),
			'subtitle_mode'       => String_Prop_Type::make()
				->enum( [ 'none', 'category', 'custom' ] )
				->default( 'category' ),
			'subtitle_custom_text' => String_Prop_Type::make()->default( '' ),

			// --- Badges ---
			'show_wishlist'      => Boolean_Prop_Type::make()->default( true ),
			'show_rating'        => Boolean_Prop_Type::make()->default( true ),
			'show_stock_badge'   => Boolean_Prop_Type::make()->default( true ),
			'low_stock_threshold' => Number_Prop_Type::make()->default( 5 ),
			'show_new_badge'     => Boolean_Prop_Type::make()->default( true ),
			'new_badge_days'     => Number_Prop_Type::make()->default( 30 ),
			'show_swatches'      => Boolean_Prop_Type::make()->default( true ),
			'swatch_attribute'   => String_Prop_Type::make()->default( 'pa_color' ),
			'show_free_shipping_badge' => Boolean_Prop_Type::make()->default( false ),
			'show_countdown'     => Boolean_Prop_Type::make()->default( false ),

			// --- Quick view ---
			'enable_quick_view' => Boolean_Prop_Type::make()->default( true ),

			// --- Per-addon class props (test pass — see Classes_Control
			// import note above). Each mirrors the root 'classes' prop
			// convention but scoped to one addon's own element, so a
			// custom/global CSS class can be assigned to just that part.
			'wishlist_classes'            => Classes_Prop_Type::make()->default( [] ),
			'rating_classes'              => Classes_Prop_Type::make()->default( [] ),
			'stock_badge_classes'         => Classes_Prop_Type::make()->default( [] ),
			'discount_badge_classes'      => Classes_Prop_Type::make()->default( [] ),
			'new_badge_classes'           => Classes_Prop_Type::make()->default( [] ),
			'swatches_classes'            => Classes_Prop_Type::make()->default( [] ),
			'free_shipping_badge_classes' => Classes_Prop_Type::make()->default( [] ),
			'countdown_classes'           => Classes_Prop_Type::make()->default( [] ),
			'quick_view_classes'          => Classes_Prop_Type::make()->default( [] ),

			'attributes' => Attributes_Prop_Type::make(),

			// --- Native Display Conditions (Elementor Pro) ---
			// Replaces the old hand-built system. This exact prop key
			// ('display-conditions') is what Elementor Pro's own
			// 'elementor/atomic-widgets/controls' filter checks for before
			// auto-injecting its native Display Conditions control into
			// our 'settings' Section below — no control code needed here,
			// and no manual gating needed in render_card() either, since
			// Elementor Pro hides the whole widget via its own
			// before/after_render hooks before render_card() ever runs.
			// Guarded: only added if Elementor Pro's Display Conditions
			// module is actually present/licensed on this site.
			...( class_exists( '\ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type' )
				? [ 'display-conditions' => \ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type::make() ]
				: [] ),

			// --- Widget-level General group (locked rule) ---
			// 'html_tag' is a reserved prop name — Elementor auto-detects
			// it and renders its own native control in a "Structure" group,
			// bypassing our Select_Control entirely (confirmed live). Its
			// native dropdown offers 6 options, so the enum must match
			// exactly or picking one of the 3 we don't allow fails
			// validation. Default 'div' preserves current markup/behavior.
			'html_tag' => String_Prop_Type::make()
				->enum( [ 'div', 'header', 'section', 'article', 'aside', 'footer' ] )
				->default( 'div' ),
		];
	}

	/**
	 * ------------------------------------------------------------------
	 * The native V4 panel UI.
	 * ------------------------------------------------------------------
	 */
	protected function define_atomic_controls(): array {
		return [
			Section::make()
				->set_id( 'source' )
				->set_label( esc_html__( 'Source', 'atomora' ) )
				->set_items( [
					Select_Control::bind_to( 'product_source' )
						->set_label( esc_html__( 'Which product', 'atomora' ) )
						->set_options( [
							[ 'value' => 'current', 'label' => esc_html__( 'Current product (dynamic — use in a product loop)', 'atomora' ) ],
							[ 'value' => 'manual', 'label' => esc_html__( 'Specific product ID', 'atomora' ) ],
						] ),

					Number_Control::bind_to( 'manual_product_id' )
						->set_label( esc_html__( 'Product ID', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. 128', 'atomora' ) )
						->set_min( 0 ),
				] ),

			Section::make()
				->set_id( 'content' )
				->set_label( esc_html__( 'Content', 'atomora' ) )
				->set_items( [
					Switch_Control::bind_to( 'show_discount_badge' )
						->set_label( esc_html__( 'Show discount % badge', 'atomora' ) ),

					Text_Control::bind_to( 'note_text' )
						->set_label( esc_html__( 'Note text', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. Apple Watch Not Included — leave blank to hide', 'atomora' ) ),

					Select_Control::bind_to( 'subtitle_mode' )
						->set_label( esc_html__( 'Hover subtitle', 'atomora' ) )
						->set_options( [
							[ 'value' => 'none', 'label' => esc_html__( 'Hidden', 'atomora' ) ],
							[ 'value' => 'category', 'label' => esc_html__( 'Product category', 'atomora' ) ],
							[ 'value' => 'custom', 'label' => esc_html__( 'Custom text', 'atomora' ) ],
						] ),

					Text_Control::bind_to( 'subtitle_custom_text' )
						->set_label( esc_html__( 'Custom subtitle text', 'atomora' ) ),
				] ),

			// --- Each addon below is its own Section: own settings, own
			// markup class, own style sub-selector (see define_base_styles()) —
			// tied to the parent card's product data, never a standalone
			// widget, same as native Loop → Pagination.
			Section::make()
				->set_id( 'wishlist' )
				->set_label( esc_html__( 'Wishlist', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'show_wishlist' )
						->set_label( esc_html__( 'Show wishlist heart icon', 'atomora' ) ),
					$this->maybe_classes_control( 'wishlist_classes' ),
				] ) ),

			Section::make()
				->set_id( 'rating' )
				->set_label( esc_html__( 'Rating', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'show_rating' )
						->set_label( esc_html__( 'Show star rating', 'atomora' ) ),
					$this->maybe_classes_control( 'rating_classes' ),
				] ) ),

			Section::make()
				->set_id( 'stock_badge' )
				->set_label( esc_html__( 'Stock Badge', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'show_stock_badge' )
						->set_label( esc_html__( 'Show stock status badge', 'atomora' ) ),

					Number_Control::bind_to( 'low_stock_threshold' )
						->set_label( esc_html__( 'Low stock threshold', 'atomora' ) )
						->set_min( 1 ),
					$this->maybe_classes_control( 'stock_badge_classes' ),
				] ) ),

			Section::make()
				->set_id( 'discount_badge' )
				->set_label( esc_html__( 'Discount Badge', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'show_discount_badge' )
						->set_label( esc_html__( 'Show discount % badge', 'atomora' ) ),
					$this->maybe_classes_control( 'discount_badge_classes' ),
				] ) ),

			Section::make()
				->set_id( 'new_badge' )
				->set_label( esc_html__( 'New Badge', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'show_new_badge' )
						->set_label( esc_html__( '"New" badge', 'atomora' ) ),

					Number_Control::bind_to( 'new_badge_days' )
						->set_label( esc_html__( 'Show "New" for (days since published)', 'atomora' ) )
						->set_min( 1 ),
					$this->maybe_classes_control( 'new_badge_classes' ),
				] ) ),

			Section::make()
				->set_id( 'swatches' )
				->set_label( esc_html__( 'Swatches', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'show_swatches' )
						->set_label( esc_html__( 'Show color/variation swatches', 'atomora' ) ),

					Text_Control::bind_to( 'swatch_attribute' )
						->set_label( esc_html__( 'Swatch attribute taxonomy', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. pa_color', 'atomora' ) ),
					$this->maybe_classes_control( 'swatches_classes' ),
				] ) ),

			Section::make()
				->set_id( 'free_shipping_badge' )
				->set_label( esc_html__( 'Free Shipping Badge', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'show_free_shipping_badge' )
						->set_label( esc_html__( 'Show free shipping badge', 'atomora' ) ),
					$this->maybe_classes_control( 'free_shipping_badge_classes' ),
				] ) ),

			Section::make()
				->set_id( 'countdown' )
				->set_label( esc_html__( 'Sale Countdown', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'show_countdown' )
						->set_label( esc_html__( 'Show sale countdown timer', 'atomora' ) ),
					$this->maybe_classes_control( 'countdown_classes' ),
				] ) ),

			Section::make()
				->set_id( 'quick_view' )
				->set_label( esc_html__( 'Quick View', 'atomora' ) )
				->set_items( array_filter( [
					Switch_Control::bind_to( 'enable_quick_view' )
						->set_label( esc_html__( 'Enable quick view popup', 'atomora' ) ),
					$this->maybe_classes_control( 'quick_view_classes' ),
				] ) ),

			// Widget-level General/Settings group — ID, Attributes —
			// matching native Atomic Elements' own Settings group (locked
			// rule, applied once per widget, not per addon). HTML Tag is
			// NOT added here: 'html_tag' is a reserved prop name that
			// Elementor auto-detects and renders in its own native
			// "Structure" group instead, so a custom control for it here
			// would just be dead UI. Link is deliberately omitted too: the
			// card already contains its own product link, Add to Cart
			// form, and Wishlist/Quick View buttons, so wrapping the whole
			// card in another <a> would produce invalid nested-link HTML.
			// Display Conditions is NOT added here either — Elementor
			// Pro's own 'elementor/atomic-widgets/controls' filter
			// auto-injects its native control into this exact Section
			// (must be id 'settings') as long as the 'display-conditions'
			// prop exists in the schema above.
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
	 * EXPERIMENTAL / test pass (Product Card only — see conversation notes).
	 * Classes_Control is not on the plugin's confirmed-safe API list yet,
	 * so this builds it defensively: if the class doesn't exist or its
	 * expected methods aren't there, this returns null and array_filter()
	 * in define_atomic_controls() drops it from that section's items —
	 * degrading to "no per-addon class field" instead of a fatal.
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
	 * maybe_classes_control(). Widget-level only (per addon was tried and
	 * undone as too large a build — see conversation notes / locked memo).
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
	 * Merges an addon's own class prop (array of strings) into a base
	 * class list, sanitizing each — same pattern as the root '_wrapper'
	 * class handling in render_card().
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
	 * Assets.
	 * ------------------------------------------------------------------
	 */
	public function get_style_depends() {
		wp_register_style(
			'atomora-product-card',
			ATOMORA_URL . 'assets/css/product-card.css',
			[],
			ATOMORA_VERSION
		);
		return [ 'atomora-product-card' ];
	}

	public function get_script_depends() {
		wp_register_script(
			'atomora-product-card',
			ATOMORA_URL . 'assets/js/product-card.js',
			[],
			ATOMORA_VERSION,
			true
		);

		if ( class_exists( '\WooCommerce' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
			wp_enqueue_script( 'wc-add-to-cart-variation' );
			wp_enqueue_script( 'wc-cart-fragments' );
		}

		$is_single_template = false;
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->documents ) {
			$current_doc         = \Elementor\Plugin::$instance->documents->get_current();
			$is_single_template = $current_doc && 0 === strpos( (string) $current_doc->get_name(), 'single' );
		}

		wp_localize_script( 'atomora-product-card', 'atomoraProductCard', [
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'atomora_quick_view' ),
			'isSingleTemplate' => (bool) $is_single_template,
		] );

		return [ 'atomora-product-card' ];
	}

	/**
	 * ------------------------------------------------------------------
	 * Quick-view AJAX endpoint. Renders WooCommerce's own single-product
	 * add-to-cart template (which already branches correctly for simple,
	 * variable, grouped, and external products) rather than reimplementing
	 * cart logic, so quick-view Add to Cart behaves identically to the real
	 * product page — including AJAX add-to-cart, variation pricing, and any
	 * theme/plugin filters already hooked into that template.
	 * ------------------------------------------------------------------
	 */
	public function ajax_quick_view() {
		$nonce_ok = check_ajax_referer( 'atomora_quick_view', 'nonce', false );

		if ( ! $nonce_ok ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'atomora' ) ], 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$product    = $product_id ? \Atomora\Product_Access::public_product( wc_get_product( $product_id ) ) : false;

		if ( ! $product instanceof \WC_Product ) {
			wp_send_json_error( [ 'message' => __( 'Product not found.', 'atomora' ) ], 404 );
		}

		if ( 'publish' !== $product->get_status() && ! current_user_can( 'read_product', $product_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Product not found.', 'atomora' ) ], 404 );
		}

		try {
			$html = $this->render_quick_view_html( $product );
		} catch ( \Throwable $e ) {
			\Atomora\Admin\Crash_Reporter::instance()->report( 'Product Card quick view render', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Quick view render error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error( [ 'message' => __( 'Something went wrong loading this product.', 'atomora' ) ], 500 );
		}

		wp_send_json_success( [ 'html' => $html ] );
	}

	private function render_quick_view_html( \WC_Product $product ): string {
		global $post;
		$previous_post           = $post;
		$previous_global_product = $GLOBALS['product'] ?? null;

		$post               = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's loop template reads the global $product.
		setup_postdata( $post );

		ob_start();
		?>
		<div class="atomora-quick-view__layout">
			<div class="atomora-quick-view__image">
				<?php echo wp_get_attachment_image( $product->get_image_id(), 'woocommerce_single', false, [ 'loading' => 'eager' ] ); ?>
			</div>
			<div class="atomora-quick-view__details">
				<h2 class="atomora-quick-view__title"><?php echo esc_html( $product->get_name() ); ?></h2>
				<div class="atomora-quick-view__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
				<?php if ( $product->get_short_description() ) : ?>
					<div class="atomora-quick-view__excerpt"><?php echo wp_kses_post( wc_format_content( $product->get_short_description() ) ); ?></div>
				<?php endif; ?>
				<div class="atomora-quick-view__cart-form">
					<?php woocommerce_template_single_add_to_cart(); ?>
				</div>
				<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" class="atomora-quick-view__full-link">
					<?php esc_html_e( 'View full details', 'atomora' ); ?>
				</a>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		wp_reset_postdata();
		$post               = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$GLOBALS['product'] = $previous_global_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's loop template reads the global $product.

		return $html;
	}

	/**
	 * ------------------------------------------------------------------
	 * Frontend render.
	 * ------------------------------------------------------------------
	 */
	protected function render() {
		try {
			$this->render_card();
		} catch ( \Throwable $e ) {
			\Atomora\Admin\Crash_Reporter::instance()->report( 'Product Card render', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Product Card render error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			if ( current_user_can( 'edit_posts' ) ) {
				printf(
					'<div class="atomora-product-card__empty">%s</div>',
					esc_html__( 'Product Card hit an unexpected error while rendering (likely an Elementor Atomic Widgets update). Check the site\'s debug log for details.', 'atomora' )
				);
			}
		}
	}

	private function render_card() {
		$settings = $this->get_atomic_settings();

		if ( ! class_exists( '\WooCommerce' ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				printf(
					'<div class="atomora-product-card__empty">%s</div>',
					esc_html__( 'Product Card requires WooCommerce to be active.', 'atomora' )
				);
			}
			return;
		}

		$product = $this->resolve_product( $settings );

		if ( ! $product instanceof \WC_Product ) {
			if ( current_user_can( 'edit_posts' ) ) {
				printf(
					'<div class="atomora-product-card__empty">%s</div>',
					esc_html__( 'Product Card: no product found. Check the Source setting, or place this inside a WooCommerce product loop.', 'atomora' )
				);
			}
			return;
		}

		$product_id     = $product->get_id();
		$normal_image   = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_single' );
		$gallery_images = $product->get_gallery_image_ids();
		$hover_image    = ! empty( $gallery_images )
			? wp_get_attachment_image_url( reset( $gallery_images ), 'woocommerce_single' )
			: $normal_image;

		$this->add_render_attribute( '_wrapper', 'class', 'atomora-product-card' );

		if ( ! empty( $settings['classes'] ) && is_array( $settings['classes'] ) ) {
			foreach ( $settings['classes'] as $class ) {
				$this->add_render_attribute( '_wrapper', 'class', sanitize_html_class( $class ) );
			}
		}

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

		$permalink = get_permalink( $product_id );
		?>
		<<?php echo esc_html( $root_tag ); ?> <?php $this->print_render_attribute_string( '_wrapper' ); ?>>
			<div class="atomora-product-card__media">
				<a href="<?php echo esc_url( $permalink ); ?>" class="atomora-product-card__media-link">
					<?php if ( $normal_image ) : ?>
						<img
							src="<?php echo esc_url( $normal_image ); ?>"
							alt="<?php echo esc_attr( $product->get_name() ); ?>"
							loading="lazy"
							decoding="async"
							class="atomora-product-card__image atomora-product-card__image--normal"
						/>
					<?php endif; ?>
					<?php if ( $hover_image ) : ?>
						<img
							src="<?php echo esc_url( $hover_image ); ?>"
							alt=""
							loading="lazy"
							decoding="async"
							class="atomora-product-card__image atomora-product-card__image--hover"
						/>
					<?php endif; ?>
				</a>

				<div class="atomora-product-card__badges">
					<?php echo $this->render_badges( $product, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<?php if ( ! empty( $settings['show_wishlist'] ) ) : ?>
					<button
						type="button"
						class="<?php echo esc_attr( $this->addon_class_string( 'atomora-product-card__wishlist', $settings, 'wishlist_classes' ) ); ?>"
						data-product-id="<?php echo esc_attr( $product_id ); ?>"
						aria-pressed="false"
						aria-label="<?php esc_attr_e( 'Add to wishlist', 'atomora' ); ?>"
					>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path d="M12 21s-6.7-4.35-9.3-8.1C.8 10.2 1.6 6.6 4.6 5.1c2.3-1.2 4.9-.4 6.4 1.5C12.5 4.7 15.1 3.9 17.4 5.1c3 1.5 3.8 5.1 1.9 7.8C18.7 16.65 12 21 12 21z"/>
						</svg>
					</button>
				<?php endif; ?>

				<?php if ( ! empty( $settings['enable_quick_view'] ) ) : ?>
					<button
						type="button"
						class="<?php echo esc_attr( $this->addon_class_string( 'atomora-product-card__quick-view', $settings, 'quick_view_classes' ) ); ?>"
						data-product-id="<?php echo esc_attr( $product_id ); ?>"
						aria-label="<?php esc_attr_e( 'Quick view', 'atomora' ); ?>"
					>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
							<circle cx="12" cy="12" r="3"/>
						</svg>
					</button>
				<?php endif; ?>

				<?php if ( ! empty( $settings['show_countdown'] ) && $product->is_on_sale() && $product->get_date_on_sale_to() ) : ?>
					<div class="<?php echo esc_attr( $this->addon_class_string( 'atomora-product-card__countdown', $settings, 'countdown_classes' ) ); ?>" data-countdown-target="<?php echo esc_attr( $product->get_date_on_sale_to()->getTimestamp() * 1000 ); ?>"></div>
				<?php endif; ?>

				<div class="atomora-product-card__hover-panel">
					<?php $subtitle = $this->get_subtitle_text( $product, $settings ); ?>
					<?php if ( $subtitle ) : ?>
						<p class="atomora-product-card__subtitle"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>

					<h3 class="atomora-product-card__title"><?php echo esc_html( $product->get_name() ); ?></h3>

					<?php if ( ! empty( $settings['show_rating'] ) && $product->get_rating_count() > 0 ) : ?>
						<div class="<?php echo esc_attr( $this->addon_class_string( 'atomora-product-card__rating', $settings, 'rating_classes' ) ); ?>">
							<?php echo $this->render_stars( (float) $product->get_average_rating() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="atomora-product-card__rating-count">(<?php echo esc_html( $product->get_rating_count() ); ?>)</span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $settings['note_text'] ) ) : ?>
						<p class="atomora-product-card__note"><?php echo esc_html( $settings['note_text'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $settings['show_swatches'] ) ) : ?>
						<?php echo $this->render_swatches( $product, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>

					<div class="atomora-product-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>

					<div class="atomora-product-card__cart">
						<?php
						global $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- required by WooCommerce's own loop template below.
						$previous_global_product = $product;
						$product                 = $this->resolve_product( $settings ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's loop template reads the global $product.
						woocommerce_template_loop_add_to_cart();
						$product = $previous_global_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's loop template reads the global $product.
						?>
					</div>
				</div>
			</div>
		</<?php echo esc_html( $root_tag ); ?>>
		<?php
		$card_product = $this->resolve_product( $settings );
		if ( $card_product instanceof \WC_Product ) {
			$this->maybe_render_product_schema( $card_product );
		}
	}

	/**
	 * Emits Product/Offer/AggregateRating JSON-LD for this card — SEO/GEO/
	 * AEO locked rule #9 (schema-ready markup). Guarded against the one
	 * case where it would be a genuine duplicate: WooCommerce core already
	 * auto-injects the same schema on a product's own single-product page
	 * (via WC_Structured_Data on 'woocommerce_single_product_summary').
	 * Everywhere else this card is used — shop archives, homepage grids,
	 * curated landing pages — that native hook never fires, so this is the
	 * only structured data describing the product there.
	 */
	private function maybe_render_product_schema( \WC_Product $product ): void {
		$is_native_single_product_page = function_exists( 'is_product' )
			&& is_product()
			&& get_the_ID() === $product->get_id();

		if ( $is_native_single_product_page ) {
			return;
		}

		$image_url = wp_get_attachment_url( $product->get_image_id() );

		$data = [
			'@context' => 'https://schema.org/',
			'@type'    => 'Product',
			'name'     => $product->get_name(),
			'sku'      => $product->get_sku() ? $product->get_sku() : (string) $product->get_id(),
			'offers'   => [
				'@type'         => 'Offer',
				'url'           => get_permalink( $product->get_id() ),
				'priceCurrency' => get_woocommerce_currency(),
				'price'         => $product->get_price(),
				'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
			],
		];

		if ( $image_url ) {
			$data['image'] = $image_url;
		}

		$description = wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() );
		if ( '' !== $description ) {
			$data['description'] = $description;
		}

		if ( $product->get_rating_count() > 0 ) {
			$data['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => (string) $product->get_rating_count(),
			];
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function resolve_product( array $settings ) {
		if ( 'manual' === ( $settings['product_source'] ?? 'current' ) ) {
			$product_id = absint( $settings['manual_product_id'] ?? 0 );
			return $product_id ? \Atomora\Product_Access::public_product( wc_get_product( $product_id ) ) : false;
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

	private function get_subtitle_text( \WC_Product $product, array $settings ): string {
		$mode = $settings['subtitle_mode'] ?? 'category';

		if ( 'custom' === $mode ) {
			return (string) ( $settings['subtitle_custom_text'] ?? '' );
		}

		if ( 'category' === $mode ) {
			$terms = get_the_terms( $product->get_id(), 'product_cat' );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				return $terms[0]->name;
			}
		}

		return '';
	}

	private function render_badges( \WC_Product $product, array $settings ): string {
		$badges = [];

		if ( ! empty( $settings['show_discount_badge'] ) && $product->is_on_sale() ) {
			$regular = (float) $product->get_regular_price();
			$sale    = (float) $product->get_sale_price();
			if ( $regular > 0 ) {
				$percent  = round( ( ( $regular - $sale ) / $regular ) * 100 );
				$class    = $this->addon_class_string( 'atomora-product-card__badge atomora-product-card__badge--sale', $settings, 'discount_badge_classes' );
				$badges[] = '<span class="' . esc_attr( $class ) . '">-' . esc_html( $percent ) . '%</span>';
			}
		}

		if ( ! empty( $settings['show_new_badge'] ) ) {
			$days_old = ( time() - get_post_time( 'U', true, $product->get_id() ) ) / DAY_IN_SECONDS;
			if ( $days_old <= absint( $settings['new_badge_days'] ?? 30 ) ) {
				$class    = $this->addon_class_string( 'atomora-product-card__badge atomora-product-card__badge--new', $settings, 'new_badge_classes' );
				$badges[] = '<span class="' . esc_attr( $class ) . '">' . esc_html__( 'New', 'atomora' ) . '</span>';
			}
		}

		if ( ! empty( $settings['show_stock_badge'] ) ) {
			$status = $product->get_stock_status();
			$qty    = $product->get_stock_quantity();
			$low    = absint( $settings['low_stock_threshold'] ?? 5 );

			if ( 'outofstock' === $status ) {
				$class    = $this->addon_class_string( 'atomora-product-card__badge atomora-product-card__badge--out-of-stock', $settings, 'stock_badge_classes' );
				$badges[] = '<span class="' . esc_attr( $class ) . '">' . esc_html__( 'Sold Out', 'atomora' ) . '</span>';
			} elseif ( null !== $qty && $qty <= $low ) {
				$class    = $this->addon_class_string( 'atomora-product-card__badge atomora-product-card__badge--low-stock', $settings, 'stock_badge_classes' );
				$badges[] = '<span class="' . esc_attr( $class ) . '">' . esc_html__( 'Low Stock', 'atomora' ) . '</span>';
			}
		}

		if ( ! empty( $settings['show_free_shipping_badge'] ) && $this->product_has_free_shipping( $product ) ) {
			$class    = $this->addon_class_string( 'atomora-product-card__badge atomora-product-card__badge--shipping', $settings, 'free_shipping_badge_classes' );
			$badges[] = '<span class="' . esc_attr( $class ) . '">' . esc_html__( 'Free Shipping', 'atomora' ) . '</span>';
		}

		return implode( '', $badges );
	}

	/**
	 * WooCommerce has no single canonical "is this free shipping" check —
	 * this looks for the standard "Free shipping" shipping class slug,
	 * which is the common convention most free-shipping setups use.
	 */
	private function product_has_free_shipping( \WC_Product $product ): bool {
		$class = $product->get_shipping_class();
		return ! empty( $class ) && false !== strpos( $class, 'free' );
	}

	/**
	 * Best-effort color swatches: WooCommerce core has no built-in color
	 * swatch UI, so this looks for a `_swatch_color` term meta convention
	 * used by several popular swatch plugins/setups; falls back to a
	 * neutral dot with the term name as a tooltip when no color is found,
	 * so swatches still work as a *variation* picker even without one.
	 */
	private function render_swatches( \WC_Product $product, array $settings ): string {
		if ( ! $product->is_type( 'variable' ) ) {
			return '';
		}

		$attribute = sanitize_key( $settings['swatch_attribute'] ?? 'pa_color' );
		$terms     = wc_get_product_terms( $product->get_id(), $attribute, [ 'fields' => 'all' ] );

		if ( empty( $terms ) ) {
			return '';
		}

		$html = '<div class="' . esc_attr( $this->addon_class_string( 'atomora-product-card__swatches', $settings, 'swatches_classes' ) ) . '">';
		foreach ( $terms as $term ) {
			$color = get_term_meta( $term->term_id, '_swatch_color', true );
			$style = $color ? 'background-color:' . esc_attr( $color ) . ';' : 'background:#e5e7eb;';
			$html .= '<span class="atomora-product-card__swatch" style="' . $style . '" title="' . esc_attr( $term->name ) . '"></span>';
		}
		$html .= '</div>';

		return $html;
	}

         /**
	 * Real partial-fill stars (e.g. 3.5/5 shows 3 full + a half), not just
	 * 5 flat-colored icons. Standard two-layer technique: an "empty" row
	 * of 5 stars underneath, and a "filled" row of the same 5 stars on
	 * top, clipped via a percentage-width wrapper so it visually cuts off
	 * mid-star exactly at the rating value — same trick used for CSS
	 * font-icon star ratings, just with our own inline SVGs instead.
	 */
	private function render_stars( float $average ): string {
		$percent = max( 0, min( 100, ( $average / 5 ) * 100 ) );
		$star    = '<svg viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path d="M10 1.5l2.6 5.3 5.8.8-4.2 4.1 1 5.8L10 14.7l-5.2 2.8 1-5.8L1.6 7.6l5.8-.8z" fill="currentColor"/></svg>';

		$html  = '<span class="atomora-product-card__stars" role="img" aria-label="' . esc_attr( sprintf(
			/* translators: %s: average rating out of 5 */
			__( 'Rated %s out of 5', 'atomora' ),
			number_format_i18n( $average, 1 )
		) ) . '">';
		$html .= '<span class="atomora-product-card__stars-empty">' . str_repeat( $star, 5 ) . '</span>';
		$html .= '<span class="atomora-product-card__stars-filled" style="width:' . esc_attr( (string) round( $percent, 1 ) ) . '%;">' . str_repeat( $star, 5 ) . '</span>';
		$html .= '</span>';

		return $html;
	}
}