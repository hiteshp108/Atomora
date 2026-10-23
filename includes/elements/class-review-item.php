<?php
namespace Atomora\Elements;

use Elementor\Modules\AtomicWidgets\Elements\Div_Block\Div_Block;
use Elementor\Modules\AtomicWidgets\Elements\Base\Widget_Builder;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Dimensions_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Color_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_Definition;
use Elementor\Modules\AtomicWidgets\Styles\Style_Variant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Atomora — Review Item.
 *
 * Template of ONE review. The Reviews List repeats it for every review; the
 * Review Part widgets placed inside read the review being rendered.
 */
class Review_Item extends Div_Block {

	public static $widget_description = 'Template for a single review, repeated once per review by the Reviews List. Place Review Part widgets inside.';

	public static function get_type() {
		return 'e-atomora-review-item';
	}

	public static function get_element_type(): string {
		return 'e-atomora-review-item';
	}

	public function get_title() {
		return esc_html__( 'Review Item', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-testimonial';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'item', 'container' ];
	}

	protected function define_panel_categories(): array {
		return [ 'atomora-review' ];
	}

	/** Default Review Part widgets of a new Review Item. */
	public static function default_parts(): array {
		$parts = [];
		foreach ( [ 'avatar', 'author', 'verified', 'date', 'rating', 'content' ] as $part ) {
			$parts[] = Widget_Builder::make( 'e-atomora-review-item-part' )
				->settings( [ 'part' => String_Prop_Type::generate( $part ) ] )
				->build();
		}
		return $parts;
	}

	protected function define_default_children() {
		return self::default_parts();
	}

	protected function define_base_styles(): array {
		return [
			static::BASE_STYLE_KEY => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'display', String_Prop_Type::generate( 'flex' ) )
						->add_prop( 'flex-direction', String_Prop_Type::generate( 'column' ) )
						->add_prop( 'align-items', String_Prop_Type::generate( 'flex-start' ) )
						->add_prop( 'gap', Size_Prop_Type::generate( [ 'size' => 8, 'unit' => 'px' ] ) )
						->add_prop( 'padding', Dimensions_Prop_Type::generate( [
							'block-start'  => Size_Prop_Type::generate( [ 'size' => 20, 'unit' => 'px' ] ),
							'inline-end'   => Size_Prop_Type::generate( [ 'size' => 20, 'unit' => 'px' ] ),
							'block-end'    => Size_Prop_Type::generate( [ 'size' => 20, 'unit' => 'px' ] ),
							'inline-start' => Size_Prop_Type::generate( [ 'size' => 20, 'unit' => 'px' ] ),
						] ) )
						->add_prop( 'border-style', String_Prop_Type::generate( 'solid' ) )
						->add_prop( 'border-width', Size_Prop_Type::generate( [ 'size' => 1, 'unit' => 'px' ] ) )
						->add_prop( 'border-color', Color_Prop_Type::generate( '#e2e4e9' ) )
						->add_prop( 'border-radius', Size_Prop_Type::generate( [ 'size' => 8, 'unit' => 'px' ] ) )
				),
		];
	}
}