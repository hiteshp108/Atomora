<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Classes_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Atomora — Quantity: Minus (standalone widget)
 *
 * A standalone "−" button. On click, finds the Quantity: Input widget
 * elsewhere on the page sharing the same product (via data-product-id)
 * and decrements it. Does nothing if no matching input is found.
 */
class Atc_Quantity_Minus extends Atomic_Widget_Base {

	public static $widget_description = 'Standalone "−" button — decrements the matching Quantity: Input widget (same product) elsewhere on the page.';

	public static function get_element_type(): string {
		return 'e-atomora-atc-qty-minus';
	}

	public function get_title() {
		return esc_html__( 'Quantity: Minus', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-minus';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'quantity', 'minus', 'decrement' ];
	}

	public function get_categories(): array {
		return [ 'atomora-woocommerce' ];
	}

	protected static function define_props_schema(): array {
		return [
			'classes' => Classes_Prop_Type::make()->default( [] ),

			'product_source' => String_Prop_Type::make()
				->enum( [ 'current', 'manual' ] )
				->default( 'current' ),
			'manual_product_id' => Number_Prop_Type::make()->default( 0 ),

			'label' => String_Prop_Type::make()->default( '−' ),

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
						->set_placeholder( '−' ),
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
		return [ 'atomora-atc-widgets' ];
	}

	protected function render() {
		try {
			$this->render_widget();
		} catch ( \Throwable $e ) {
			\Atomora\Admin\Crash_Reporter::instance()->report( 'Quantity Minus render', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Quantity Minus render error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	private function resolve_product( array $settings ) {
		if ( ! class_exists( '\WooCommerce' ) ) {
			return false;
		}
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

	private function render_widget(): void {
		$settings = $this->get_atomic_settings();
		$product  = $this->resolve_product( $settings );

		if ( ! $product instanceof \WC_Product ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<p class="atomora-atc-empty">' . esc_html__( 'Quantity: Minus: no product found.', 'atomora' ) . '</p>';
			}
			return;
		}

		$classes = [ 'atomora-atc-qty-minus' ];
		if ( ! empty( $settings['classes'] ) && is_array( $settings['classes'] ) ) {
			foreach ( $settings['classes'] as $class ) {
				$classes[] = sanitize_html_class( $class );
			}
		}

		$this->add_render_attribute( '_wrapper', 'class', $classes );
		$this->add_render_attribute( '_wrapper', 'type', 'button' );
		$this->add_render_attribute( '_wrapper', 'data-product-id', $product->get_id() );
		$this->add_render_attribute( '_wrapper', 'data-atc-qty-action', 'minus' );
		$this->add_render_attribute( '_wrapper', 'aria-label', esc_html__( 'Decrease quantity', 'atomora' ) );

		$custom_attrs = $settings['attributes'] ?? [];
		if ( is_array( $custom_attrs ) ) {
			foreach ( $custom_attrs as $attr ) {
				if ( ! empty( $attr['key'] ) && is_string( $attr['key'] ) ) {
					$this->add_render_attribute( '_wrapper', sanitize_key( $attr['key'] ), $attr['value'] ?? '' );
				}
			}
		}

		$label = ! empty( $settings['label'] ) ? $settings['label'] : '−';
		?>
		<button <?php $this->print_render_attribute_string( '_wrapper' ); ?>><?php echo esc_html( $label ); ?></button>
		<?php
	}
}