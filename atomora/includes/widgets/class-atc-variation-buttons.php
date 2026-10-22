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
 * Atomora — Variation: Buttons (standalone widget)
 *
 * Same underlying markup/data contract as Variation: Dropdown (a real,
 * WC-correct <select> per attribute, so WooCommerce's own matching JS
 * works identically) — flagged with data-atc-variation-style="buttons"
 * so atc-widgets.js visually hides the native select (kept for
 * accessibility/functionality) and generates custom button/pill options
 * from its own <option> list, dispatching a real 'change' event on
 * click so WooCommerce's own wc_variation_form() plugin picks it up
 * exactly as if a dropdown were used — verified directly against
 * WooCommerce's add-to-cart-variation.js, which listens for 'change' on
 * '.variations select' regardless of visibility.
 *
 * Styling: colors/border/radius are set directly via plain hex/length
 * fields in the "Variation Button" section (General tab), applied via
 * an inline <style> block scoped to this exact widget instance with
 * !important — this is deliberately NOT the classes/Style-tab workflow
 * (tried first, but proved unreliable due to cascade-order conflicts
 * with the plugin's own default CSS), so it can't be blocked by
 * stylesheet load order or caching.
 */
class Atc_Variation_Buttons extends Atomic_Widget_Base {

	public static $widget_description = 'Variation selector rendered as buttons/pills instead of a dropdown — same WooCommerce-compatible matching underneath.';

	public static function get_element_type(): string {
		return 'e-atomora-atc-variation-buttons';
	}

	public function get_title() {
		return esc_html__( 'Variation: Buttons (no dropdown)', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-select';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'variations', 'options', 'buttons', 'swatches', 'cart' ];
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

			// Direct hex-code / length fields — applied via an inline
			// <style> block scoped to this exact widget instance with
			// !important. Empty string (default) means "use the
			// plugin's own default look".
			'button_text_color'         => String_Prop_Type::make()->default( '' ),
			'button_bg_color'           => String_Prop_Type::make()->default( '' ),
			'button_border_color'       => String_Prop_Type::make()->default( '' ),
			'button_border_radius'      => String_Prop_Type::make()->default( '' ),
			'button_hover_text_color'   => String_Prop_Type::make()->default( '' ),
			'button_hover_bg_color'     => String_Prop_Type::make()->default( '' ),
			'button_hover_border_color' => String_Prop_Type::make()->default( '' ),

			// Previously missing entirely: the "normal" colors above apply
			// to every button regardless of selection state, so a selected
			// button looked identical to any other until literally
			// hovered. These give the selected button its own persistent
			// look at rest, not just on hover.
			'button_selected_text_color'   => String_Prop_Type::make()->default( '' ),
			'button_selected_bg_color'     => String_Prop_Type::make()->default( '' ),
			'button_selected_border_color' => String_Prop_Type::make()->default( '' ),

			'attributes' => Attributes_Prop_Type::make(),

			...( class_exists( '\ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type' )
				? [ 'display-conditions' => \ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type::make() ]
				: [] ),

			'html_tag' => String_Prop_Type::make()
				->enum( [ 'div', 'section' ] )
				->default( 'div' ),
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
				->set_id( 'button_colors' )
				->set_label( esc_html__( 'Variation Button', 'atomora' ) )
				->set_items( [
					Text_Control::bind_to( 'button_text_color' )
						->set_label( esc_html__( 'Text color (normal)', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. #000000', 'atomora' ) ),
					Text_Control::bind_to( 'button_bg_color' )
						->set_label( esc_html__( 'Background color (normal)', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. #ffffff', 'atomora' ) ),
					Text_Control::bind_to( 'button_border_color' )
						->set_label( esc_html__( 'Border color (normal)', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. #d1d5db', 'atomora' ) ),
					Text_Control::bind_to( 'button_border_radius' )
						->set_label( esc_html__( 'Border radius', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. 4px or 50%', 'atomora' ) ),
					Text_Control::bind_to( 'button_selected_text_color' )
						->set_label( esc_html__( 'Text color (selected)', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. #ffffff', 'atomora' ) ),
					Text_Control::bind_to( 'button_selected_bg_color' )
						->set_label( esc_html__( 'Background color (selected)', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. #000000', 'atomora' ) ),
					Text_Control::bind_to( 'button_selected_border_color' )
						->set_label( esc_html__( 'Border color (selected)', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. #000000', 'atomora' ) ),
					Text_Control::bind_to( 'button_hover_text_color' )
						->set_label( esc_html__( 'Text color (hover)', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. #ffffff', 'atomora' ) ),
					Text_Control::bind_to( 'button_hover_bg_color' )
						->set_label( esc_html__( 'Background color (hover)', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. #000000', 'atomora' ) ),
					Text_Control::bind_to( 'button_hover_border_color' )
						->set_label( esc_html__( 'Border color (hover)', 'atomora' ) )
						->set_placeholder( esc_html__( 'e.g. #000000', 'atomora' ) ),
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

	private function maybe_classes_control( string $prop_name, string $label = '' ) {
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Classes_Control' ) ) {
			return null;
		}
		try {
			return Classes_Control::bind_to( $prop_name )->set_label( '' !== $label ? $label : esc_html__( 'CSS Classes', 'atomora' ) );
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
			\Atomora\Admin\Crash_Reporter::instance()->report( 'Variation Buttons render', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — Variation Buttons render error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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

	/**
	 * Sanitizes a CSS length value (e.g. "4px", "50%", "0.5em") for safe
	 * direct inclusion inside an inline <style> block. Rejects anything
	 * that doesn't match a plain number + optional unit, so a value
	 * can't break out of the intended CSS rule.
	 */
	private function sanitize_css_length( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		return preg_match( '/^[0-9]+(\.[0-9]+)?(px|%|em|rem)?$/', $value ) ? $value : '';
	}

	/**
	 * Direct hex-color / length fields, applied via an inline <style>
	 * block scoped to this exact widget instance's own generated ID,
	 * with !important — bypasses the classes/Style-tab workflow
	 * entirely, so cascade order and any caching between plugin CSS and
	 * Elementor's own generated class CSS can't block it. Only outputs
	 * rules for fields the user actually filled in; sanitize_hex_color()
	 * and sanitize_css_length() reject anything that isn't a real value,
	 * so nothing unsafe can reach the page even if a field somehow
	 * contained something else.
	 */
	private function render_style_overrides( string $instance_id, array $settings ): void {
		$text             = sanitize_hex_color( $settings['button_text_color'] ?? '' );
		$bg               = sanitize_hex_color( $settings['button_bg_color'] ?? '' );
		$border           = sanitize_hex_color( $settings['button_border_color'] ?? '' );
		$radius           = $this->sanitize_css_length( $settings['button_border_radius'] ?? '' );
		$selected_text    = sanitize_hex_color( $settings['button_selected_text_color'] ?? '' );
		$selected_bg      = sanitize_hex_color( $settings['button_selected_bg_color'] ?? '' );
		$selected_border  = sanitize_hex_color( $settings['button_selected_border_color'] ?? '' );
		$hover_text       = sanitize_hex_color( $settings['button_hover_text_color'] ?? '' );
		$hover_bg         = sanitize_hex_color( $settings['button_hover_bg_color'] ?? '' );
		$hover_border     = sanitize_hex_color( $settings['button_hover_border_color'] ?? '' );

		if (
			! $text && ! $bg && ! $border && ! $radius &&
			! $selected_text && ! $selected_bg && ! $selected_border &&
			! $hover_text && ! $hover_bg && ! $hover_border
		) {
			return;
		}

		$selector          = '[data-atc-instance="' . esc_attr( $instance_id ) . '"] .atomora-atc-variation-btn';
		$selected_selector = $selector . '.is-selected';
		$hover_selector    = $selector . ':hover';

		$normal_rules = array_filter( [
			$text ? 'color: ' . $text . ' !important;' : '',
			$bg ? 'background: ' . $bg . ' !important;' : '',
			$border ? 'border-color: ' . $border . ' !important;' : '',
			$radius ? 'border-radius: ' . $radius . ' !important;' : '',
		] );
		$selected_rules = array_filter( [
			$selected_text ? 'color: ' . $selected_text . ' !important;' : '',
			$selected_bg ? 'background: ' . $selected_bg . ' !important;' : '',
			$selected_border ? 'border-color: ' . $selected_border . ' !important;' : '',
		] );
		$hover_rules = array_filter( [
			$hover_text ? 'color: ' . $hover_text . ' !important;' : '',
			$hover_bg ? 'background: ' . $hover_bg . ' !important;' : '',
			$hover_border ? 'border-color: ' . $hover_border . ' !important;' : '',
		] );

		echo '<style>';
		if ( $normal_rules ) {
			echo $selector . '{' . implode( ' ', $normal_rules ) . '}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS, not HTML; every dynamic value here is already sanitize_hex_color()/sanitize_css_length()-validated or Elementor's own generated ID, so HTML-escaping would corrupt the attribute-selector quotes instead of protecting anything.
		}
		if ( $selected_rules ) {
			// Deliberately output AFTER normal, BEFORE hover: with equal
			// specificity + !important on both the normal and selected
			// rules, source order decides ties, so selected needs to come
			// after normal to actually show. Coming before hover means
			// hovering a selected button still shows the hover color
			// (unchanged from before) rather than fighting with it.
			echo $selected_selector . '{' . implode( ' ', $selected_rules ) . '}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $hover_rules ) {
			echo $hover_selector . '{' . implode( ' ', $hover_rules ) . '}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</style>';
	}

	private function render_widget(): void {
		$settings = $this->get_atomic_settings();
		$product  = $this->resolve_product( $settings );

		if ( ! $product instanceof \WC_Product ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<p class="atomora-atc-empty">' . esc_html__( 'Variation: Buttons: no product found.', 'atomora' ) . '</p>';
			}
			return;
		}

		if ( ! $product->is_type( 'variable' ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<p class="atomora-atc-empty">' . esc_html__( 'Variation: Buttons: this product has no variations (nothing to show on the live site).', 'atomora' ) . '</p>';
			}
			return;
		}

		/** @var \WC_Product_Variable $product */
		$root_tag = in_array( $settings['html_tag'] ?? 'div', [ 'div', 'section' ], true ) ? $settings['html_tag'] : 'div';

		$classes = [ 'atomora-atc-variations-wrap' ];
		if ( ! empty( $settings['classes'] ) && is_array( $settings['classes'] ) ) {
			foreach ( $settings['classes'] as $class ) {
				$classes[] = sanitize_html_class( $class );
			}
		}

		$this->add_render_attribute( '_wrapper', 'class', $classes );
		$custom_attrs = $settings['attributes'] ?? [];
		if ( is_array( $custom_attrs ) ) {
			foreach ( $custom_attrs as $attr ) {
				if ( ! empty( $attr['key'] ) && is_string( $attr['key'] ) ) {
					$this->add_render_attribute( '_wrapper', sanitize_key( $attr['key'] ), $attr['value'] ?? '' );
				}
			}
		}

		$attributes  = $product->get_variation_attributes();
		$variations  = $product->get_available_variations();
		$instance_id = 'atc-vb-' . $this->get_id();

		$this->render_style_overrides( $instance_id, $settings );
		?>
		<<?php echo esc_html( $root_tag ); ?> <?php $this->print_render_attribute_string( '_wrapper' ); ?> data-atc-instance="<?php echo esc_attr( $instance_id ); ?>">
			<form
				class="variations_form cart atomora-atc-variations"
				data-atc-variation-style="buttons"
				method="post"
				enctype="multipart/form-data"
				data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"
				data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
				data-product_variations="<?php echo esc_attr( wp_json_encode( $variations ) ); ?>"
			>
				<div class="variations">
					<?php foreach ( $attributes as $attribute_name => $options ) : ?>
						<div class="atomora-atc-option">
							<label class="atomora-atc-option-label" for="<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>">
								<?php echo esc_html( wc_attribute_label( $attribute_name ) ); ?>
							</label>
							<div class="atomora-atc-option-control">
								<?php
								echo wc_dropdown_variation_attribute_options( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									[
										'options'   => $options,
										'attribute' => $attribute_name,
										'product'   => $product,
										'return'    => true,
									]
								);
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="single_variation_wrap">
					<div class="woocommerce-variation single_variation"></div>
					<input type="hidden" name="variation_id" class="variation_id" value="0" />
				</div>
			</form>
		</<?php echo esc_html( $root_tag ); ?>>
		<?php
	}
}