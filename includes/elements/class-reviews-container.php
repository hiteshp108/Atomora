<?php
namespace Atomora\Elements;

use Elementor\Modules\AtomicWidgets\Elements\Div_Block\Div_Block;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_Definition;
use Elementor\Modules\AtomicWidgets\Styles\Style_Variant;
use Elementor\Modules\AtomicWidgets\Elements\Base\Widget_Builder;
use Elementor\Modules\AtomicWidgets\Elements\Base\Element_Builder;
use Elementor\Modules\AtomicWidgets\PropTypes\Escaped_Html_Prop_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-reviews-list.php';
require_once __DIR__ . '/class-reviews-form.php';

/**
 * Atomora — Reviews container.
 *
 * A native nestable atomic element (same engine as Div Block). It ships with
 * NO default children: the user drops the Review parts / Reviews List /
 * Review Form inside it. Its only extra job is publishing the chosen product
 * to its children through Elementor's Render_Context.
 */
class Reviews_Container extends Div_Block {

	const CONTEXT_KEY = 'atomora-reviews';

	public static $widget_description = 'Container for WooCommerce review parts. Sets the product for the Review elements placed inside it.';

	public static function get_type() {
		return 'e-atomora-reviews';
	}

	public static function get_element_type(): string {
		return 'e-atomora-reviews';
	}

	public function get_title() {
		return esc_html__( 'Reviews', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-product-rating';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'rating', 'product', 'container' ];
	}

	protected function define_panel_categories(): array {
		return [ 'atomora-review' ];
	}

	protected static function define_props_schema(): array {
		return parent::define_props_schema() + [
			'product_source'    => String_Prop_Type::make()->enum( [ 'current', 'manual' ] )->default( 'current' ),
			'manual_product_id' => Number_Prop_Type::make()->default( 0 ),
		];
	}

	protected function define_atomic_controls(): array {
		return array_merge(
			[
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
			],
			parent::define_atomic_controls()
		);
	}

	protected function define_base_styles(): array {
		return [
			static::BASE_STYLE_KEY => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'display', String_Prop_Type::generate( 'flex' ) )
						->add_prop( 'flex-direction', String_Prop_Type::generate( 'column' ) )
						->add_prop( 'gap', Size_Prop_Type::generate( [ 'size' => 16, 'unit' => 'px' ] ) )
				),
		];
	}

	/**
	 * Starter layout for a newly dropped container. Editor-only config: it is
	 * applied once when the element is inserted and never runs at render time.
	 */
	protected function define_default_children() {
		return [
			Widget_Builder::make( 'e-heading' )
				->settings( [
					'title' => Escaped_Html_Prop_Type::generate( esc_html__( 'Customer reviews', 'atomora' ) ),
				] )
				->build(),
			Widget_Builder::make( 'e-atomora-review-average' )->build(),
			Widget_Builder::make( 'e-atomora-review-stars' )->build(),
			Widget_Builder::make( 'e-atomora-review-count' )->build(),
			Widget_Builder::make( 'e-atomora-review-breakdown' )->build(),
			Element_Builder::make( 'e-atomora-reviews-list' )->children( Reviews_List::default_children() )->build(),
			Element_Builder::make( 'e-atomora-reviews-form' )->children( Reviews_Form::default_children() )->build(),
		];
	}

	protected function define_render_context(): array {
		$settings = $this->get_atomic_settings();

		return [
			[
				'context_key' => self::CONTEXT_KEY,
				'context'     => [
					'source'            => ( $settings['product_source'] ?? 'current' ) === 'manual' ? 'manual' : 'current',
					'manual_product_id' => absint( $settings['manual_product_id'] ?? 0 ),
				],
			],
		];
	}
}
