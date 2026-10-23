<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
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
 * Atomora — Add to Cart Button (standalone widget)
 *
 * Fully independent — resolves its own product, no parent container. At
 * click time, JS (atc-widgets.js) looks elsewhere on the page for a
 * Quantity widget and/or Variations widget sharing the same
 * data-product-id and uses their live values; defaults to quantity 1
 * and no variation if none are present. Posts directly to WooCommerce's
 * core AJAX endpoint using the exact field contract verified against
 * WooCommerce's own source (the variation's own ID sent as product_id,
 * not a separate variation_id field).
 */
class Atc_Add_To_Cart_Button extends Atomic_Widget_Base {

	public static $widget_description = 'Standalone AJAX Add to Cart button. Pairs with the Product Quantity / Product Variations widgets via matching product, but works alone too.';

	public static function get_element_type(): string {
		return 'e-atomora-atc-add-to-cart-btn';
	}

	public function get_title() {
		return esc_html__( 'Add to Cart Button', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-cart-medium';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'add to cart', 'button' ];
	}

	public function get_categories(): array {
		return [ 'atomora' ];
	}

	protected function define_base_styles(): array {
		return [
			'base' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'background', Background_Prop_Type::generate( [
							'color' => Color_Prop_Type::generate( '#000000' ),
						] ) )
						->add_prop( 'border-radius', Size_Prop_Type::generate( [ 'size' => 6, 'unit' => 'px' ] ) )
				),
		];
	}

	protected static function define_props_schema(): array {
		return [
			'classes' => Classes_Prop_Type::make()->default( [] ),

			'product_source' => String_Prop_Type::make()
				->enum( [ 'current', 'manual' ] )
				->default( 'current' ),
			'manual_product_id' => Number_Prop_Type::make()->default( 0 ),

			'label' => String_Prop_Type::make()->default( '' ),
			'show_view_cart_link' => Boolean_Prop_Type::make()->default( true ),

			'attributes' => Attributes_Prop_Type::make(),

			...( class_exists( '\ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type' )
				? [ 'display-conditions' => \ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type::make() ]
				: [] ),
		];
	}

	protected function define_atomic_controls(): array {
		return [
			Section::make()
				->set_id( 'source' )
				->set_label( esc_html__( 'Product', 'atomora' ) )
				->set_items( [
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
				] ),
			Section::make()
				->set_id( 'content' )
				->set_label( esc_html__( 'Content', 'atomora' ) )
				->set_items( [
					Text_Control::bind_to( 'label' )
						->set_label( esc_html__( 'Button text', 'atomora' ) )
						->set_placeholder( esc_html__( 'Leave blank to use WooCommerce\'s default text', 'atomora' ) ),
					Switch_Control::bind_to( 'show_view_cart_link' )
						->set_label( esc_html__( 'Show "View cart" link in popup', 'atomora' ) ),
				] ),
			Section::make()
				->set_id( 'settings' )
				->set_label( esc_html__( 'Settings', 'atomora' ) )
				->set_items( array_filter( [
					Text_Control::bind_to( '_cssid' )
						->set_label( esc_html__( 'ID', 'atomora' ) )
						->set_meta( $this->get_css_id_control_meta() ),
					$this->maybe_classes_control( 'classes' ),
					$this->maybe_attributes_control( 'attributes' ),
				] ) ),
		];
	}

	private function maybe_classes_control( string $prop_name ) {
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Classes_Control' ) ) {
			return null;
		}
		try {
			return Classes_Control::bind_to( $prop_name )->set_label( esc_html__( 'CSS Classes', 'atomora' ) );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Classes_Control unavailable: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		}
	}

	private function maybe_attributes_control( string $prop_name ) {
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Attributes_Control' ) ) {
			return null;
		}
		try {
			return \Elementor\Modules\AtomicWidgets\Controls\Types\Attributes_Control::bind_to( $prop_name )
				->set_label( esc_html__( 'Attributes', 'atomora' ) );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Attributes_Control unavailable: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		}
	}

	public function get_style_depends() {
		wp_register_style( 'atomora-atc-widgets', ATOMORA_URL . 'assets/css/atc-widgets.css', [], ATOMORA_VERSION );
		return [ 'atomora-atc-widgets' ];
	}

	public function get_script_depends() {
		wp_register_script( 'atomora-atc-widgets', ATOMORA_URL . 'assets/js/atc-widgets.js', [ 'jquery' ], ATOMORA_VERSION, true );

		wp_localize_script( 'atomora-atc-widgets', 'atomoraAtc', [
			'ajaxUrl'           => class_exists( '\WC_AJAX' ) ? \WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
			'checkoutUrl'       => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			'cartUrl'           => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'addedText'         => esc_html__( 'Added to cart.', 'atomora' ),
			'viewCartText'      => esc_html__( 'View cart', 'atomora' ),
			'errorText'         => esc_html__( 'Something went wrong. Please try again.', 'atomora' ),
			'selectOptionsText' => esc_html__( 'Please select all options before continuing.', 'atomora' ),
		] );

		$deps = [ 'atomora-atc-widgets' ];
		if ( wp_script_is( 'wc-add-to-cart-variation', 'registered' ) ) {
			$deps[] = 'wc-add-to-cart-variation';
		}
		return $deps;
	}

	protected function render() {
		try {
			$this->render_widget();
		} catch ( \Throwable $e ) {
			\Atomora\Admin\Crash_Reporter::instance()->report( 'Add to Cart Button render', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Add to Cart Button render error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	private function resolve_product( array $settings ) {
		if ( ! class_exists( '\WooCommerce' ) ) {
			return false;
		}
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

	private function render_widget(): void {
		$settings = $this->get_atomic_settings();
		$product  = $this->resolve_product( $settings );

		if ( ! $product instanceof \WC_Product ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<p class="atomora-atc-empty">' . esc_html__( 'Add to Cart Button: no product found.', 'atomora' ) . '</p>';
			}
			return;
		}

		$classes = [ 'atomora-atc-add-btn' ];
		if ( ! empty( $settings['classes'] ) && is_array( $settings['classes'] ) ) {
			foreach ( $settings['classes'] as $class ) {
				$classes[] = sanitize_html_class( $class );
			}
		}

		$this->add_render_attribute( '_wrapper', 'class', $classes );
		$this->add_render_attribute( '_wrapper', 'data-product-id', $product->get_id() );
		$this->add_render_attribute( '_wrapper', 'data-is-variable', $product->is_type( 'variable' ) ? '1' : '0' );
		$this->add_render_attribute( '_wrapper', 'data-atc-action', 'add' );
		$this->add_render_attribute( '_wrapper', 'data-show-view-cart', ( ! isset( $settings['show_view_cart_link'] ) || $settings['show_view_cart_link'] ) ? '1' : '0' );
		$this->add_render_attribute( '_wrapper', 'type', 'button' );

		$custom_attrs = $settings['attributes'] ?? [];
		if ( is_array( $custom_attrs ) ) {
			foreach ( $custom_attrs as $attr ) {
				if ( ! empty( $attr['key'] ) && is_string( $attr['key'] ) ) {
					$this->add_render_attribute( '_wrapper', sanitize_key( $attr['key'] ), $attr['value'] ?? '' );
				}
			}
		}

		if ( ! $product->is_purchasable() ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<p class="atomora-atc-empty">' . esc_html__( 'Add to Cart Button: product is not purchasable.', 'atomora' ) . '</p>';
			}
			return;
		}

		$label = ! empty( $settings['label'] ) ? $settings['label'] : $product->single_add_to_cart_text();
		?>
		<button <?php $this->print_render_attribute_string( '_wrapper' ); ?>>
			<?php echo esc_html( $label ); ?>
		</button>
		<?php
	}
}