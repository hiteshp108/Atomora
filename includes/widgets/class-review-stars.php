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
 * Atomora — Review Stars (average rating as stars).
 */
class Review_Stars extends Atomic_Widget_Base {
	use Review_Part;

	public static $widget_description = 'Star display of the average rating for a WooCommerce product.';

	public static function get_element_type(): string {
		return 'e-atomora-review-stars';
	}

	public function get_title() {
		return esc_html__( 'Review Stars', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-rating';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'rating', 'stars' ];
	}

	private static function part_slug(): string {
		return 'stars';
	}

	protected function define_base_styles(): array {
		return [
			'base' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'font-size', Size_Prop_Type::generate( [ 'size' => 20, 'unit' => 'px' ] ) )
				),
		];
	}

	protected static function define_props_schema(): array {
		return self::common_props();
	}

	protected function define_atomic_controls(): array {
		return [
			$this->source_section(),
			$this->settings_section(),
		];
	}

	private function render_part( \WC_Product $product, array $settings ): void {
		echo self::stars_html( (float) $product->get_average_rating() ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
