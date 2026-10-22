<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Render_Context;
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
 * Atomora — shared logic for the single-purpose Review parts
 * (Average, Stars, Count, Breakdown). Each part is its own element with its
 * own Style tab; the Reviews container only supplies the product.
 *
 * Using classes must define:
 *   private static function part_slug(): string
 *   private function render_part( \WC_Product $product, array $settings ): void
 */
trait Review_Part {

	public function get_categories(): array {
		return [ 'atomora-review' ];
	}

	protected static function common_props(): array {
		return [
			'classes'           => Classes_Prop_Type::make()->default( [] ),
			'product_source'    => String_Prop_Type::make()->enum( [ 'inherit', 'current', 'manual' ] )->default( 'inherit' ),
			'manual_product_id' => Number_Prop_Type::make()->default( 0 ),
			'attributes'        => Attributes_Prop_Type::make(),

			...( class_exists( '\ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type' )
				? [ 'display-conditions' => \ElementorPro\Modules\AtomicWidgets\PropTypes\Display_Conditions\Display_Conditions_Prop_Type::make() ]
				: [] ),

			'html_tag' => String_Prop_Type::make()->enum( [ 'div', 'header', 'section', 'article', 'aside', 'footer' ] )->default( 'div' ),
		];
	}

	protected function source_section(): Section {
		return Section::make()
			->set_id( 'source' )
			->set_label( esc_html__( 'Product', 'atomora' ) )
			->set_items( [
				Select_Control::bind_to( 'product_source' )
					->set_label( esc_html__( 'Which product', 'atomora' ) )
					->set_options( [
						[ 'value' => 'inherit', 'label' => esc_html__( 'From Reviews container', 'atomora' ) ],
						[ 'value' => 'current', 'label' => esc_html__( 'Current product (dynamic)', 'atomora' ) ],
						[ 'value' => 'manual', 'label' => esc_html__( 'Specific product ID', 'atomora' ) ],
					] ),
				Number_Control::bind_to( 'manual_product_id' )
					->set_label( esc_html__( 'Product ID', 'atomora' ) )
					->set_placeholder( esc_html__( 'e.g. 128', 'atomora' ) )
					->set_min( 0 ),
			] );
	}

	protected function settings_section(): Section {
		return Section::make()
			->set_id( 'settings' )
			->set_label( esc_html__( 'Settings', 'atomora' ) )
			->set_items( array_filter( [
				Text_Control::bind_to( '_cssid' )
					->set_label( esc_html__( 'ID', 'atomora' ) )
					->set_meta( $this->get_css_id_control_meta() ),
				$this->maybe_classes_control( 'classes' ),
				$this->maybe_attributes_control( 'attributes' ),
			] ) );
	}

	private function maybe_classes_control( string $prop_name ) {
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Classes_Control' ) ) {
			return null;
		}
		try {
			return Classes_Control::bind_to( $prop_name )->set_label( esc_html__( 'CSS Classes', 'atomora' ) );
		} catch ( \Throwable $e ) {
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
			return null;
		}
	}

	public function get_style_depends() {
		wp_register_style( 'atomora-review-parts', ATOMORA_URL . 'assets/css/review-parts.css', [], ATOMORA_VERSION );
		return [ 'atomora-review-parts' ];
	}

	protected function render() {
		try {
			$this->render_widget();
		} catch ( \Throwable $e ) {
			\Atomora\Admin\Crash_Reporter::instance()->report( static::get_element_type() . ' render', $e );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Atomora — ' . static::get_element_type() . ' render error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	private function resolve_product( array $settings ) {
		if ( ! class_exists( '\WooCommerce' ) ) {
			return false;
		}

		$source     = $settings['product_source'] ?? 'inherit';
		$manual_id  = absint( $settings['manual_product_id'] ?? 0 );
		$use_manual = 'manual' === $source;

		if ( 'inherit' === $source ) {
			$context = Render_Context::get( 'atomora-reviews' );
			if ( ! empty( $context ) && 'manual' === ( $context['source'] ?? '' ) ) {
				$use_manual = true;
				$manual_id  = absint( $context['manual_product_id'] ?? 0 );
			}
		}

		if ( $use_manual ) {
			return $manual_id ? wc_get_product( $manual_id ) : false;
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
				echo '<p class="atomora-review__notice">' . esc_html__( 'No product found.', 'atomora' ) . '</p>';
			}
			return;
		}

		$allowed_tags = [ 'div', 'header', 'section', 'article', 'aside', 'footer' ];
		$root_tag     = in_array( $settings['html_tag'] ?? 'div', $allowed_tags, true ) ? $settings['html_tag'] : 'div';

		$classes = [ 'atomora-review', 'atomora-review--' . self::part_slug() ];
		if ( ! empty( $settings['classes'] ) && is_array( $settings['classes'] ) ) {
			foreach ( $settings['classes'] as $class ) {
				$classes[] = sanitize_html_class( $class );
			}
		}

		$this->add_render_attribute( '_wrapper', 'class', $classes );
		$this->add_render_attribute( '_wrapper', 'data-product-id', $product->get_id() );

		$custom_attrs = $settings['attributes'] ?? [];
		if ( is_array( $custom_attrs ) ) {
			foreach ( $custom_attrs as $attr ) {
				if ( ! empty( $attr['key'] ) && is_string( $attr['key'] ) ) {
					$this->add_render_attribute( '_wrapper', sanitize_key( $attr['key'] ), $attr['value'] ?? '' );
				}
			}
		}
		?>
		<<?php echo esc_html( $root_tag ); ?> <?php $this->print_render_attribute_string( '_wrapper' ); ?>>
			<?php $this->render_part( $product, $settings ); ?>
		</<?php echo esc_html( $root_tag ); ?>>
		<?php
	}

	/** Two-layer star display: dim row + clipped filled row, both use the text colour. */
	private static function stars_html( float $rating ): string {
		$rating  = max( 0.0, min( 5.0, $rating ) );
		$percent = round( ( $rating / 5 ) * 100, 2 );

		return sprintf(
			'<span class="atomora-review__stars" role="img" aria-label="%1$s"><span class="atomora-review__stars-empty" aria-hidden="true">★★★★★</span><span class="atomora-review__stars-fill" aria-hidden="true" style="width:%2$s%%">★★★★★</span></span>',
			/* translators: %s: rating */
			esc_attr( sprintf( __( 'Rated %s out of 5', 'atomora' ), round( $rating, 1 ) ) ),
			esc_attr( (string) $percent )
		);
	}
}
