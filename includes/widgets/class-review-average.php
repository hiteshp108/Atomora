<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_Definition;
use Elementor\Modules\AtomicWidgets\Styles\Style_Variant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-review-part.php';

/**
 * Atomora — Review Average (the big "4.6" number).
 */
class Review_Average extends Atomic_Widget_Base {
	use Review_Part;

	public static $widget_description = 'Average rating number for a WooCommerce product.';

	public static function get_element_type(): string {
		return 'e-atomora-review-average';
	}

	public function get_title() {
		return esc_html__( 'Review Average', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-number-field';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'rating', 'average', 'score' ];
	}

	private static function part_slug(): string {
		return 'average';
	}

	protected function define_base_styles(): array {
		return [
			'base' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'font-size', Size_Prop_Type::generate( [ 'size' => 40, 'unit' => 'px' ] ) )
						->add_prop( 'font-weight', String_Prop_Type::generate( '700' ) )
				),
		];
	}

	protected static function define_props_schema(): array {
		return self::common_props() + [
			'decimals' => Number_Prop_Type::make()->default( 1 ),
		];
	}

	protected function define_atomic_controls(): array {
		return [
			$this->source_section(),
			Section::make()
				->set_id( 'content' )
				->set_label( esc_html__( 'Content', 'atomora' ) )
				->set_items( [
					Number_Control::bind_to( 'decimals' )
						->set_label( esc_html__( 'Decimals', 'atomora' ) )
						->set_min( 0 )
						->set_max( 2 ),
				] ),
			$this->settings_section(),
		];
	}

	private function render_part( \WC_Product $product, array $settings ): void {
		$decimals = min( 2, max( 0, (int) ( $settings['decimals'] ?? 1 ) ) );
		echo esc_html( number_format_i18n( (float) $product->get_average_rating(), $decimals ) );
	}
}
