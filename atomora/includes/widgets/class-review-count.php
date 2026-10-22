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
 * Atomora — Review Count ("Based on 12 reviews").
 */
class Review_Count extends Atomic_Widget_Base {
	use Review_Part;

	public static $widget_description = 'Number of reviews for a WooCommerce product, with editable singular/plural text.';

	public static function get_element_type(): string {
		return 'e-atomora-review-count';
	}

	public function get_title() {
		return esc_html__( 'Review Count', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-counter';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'rating', 'count', 'total' ];
	}

	private static function part_slug(): string {
		return 'count';
	}

	protected function define_base_styles(): array {
		return [
			'base' => Style_Definition::make()
				->add_variant(
					Style_Variant::make()
						->add_prop( 'font-size', Size_Prop_Type::generate( [ 'size' => 14, 'unit' => 'px' ] ) )
				),
		];
	}

	protected static function define_props_schema(): array {
		return self::common_props() + [
			'text_singular' => String_Prop_Type::make()->default( 'Based on %s review' ),
			'text_plural'   => String_Prop_Type::make()->default( 'Based on %s reviews' ),
		];
	}

	protected function define_atomic_controls(): array {
		return [
			$this->source_section(),
			Section::make()
				->set_id( 'content' )
				->set_label( esc_html__( 'Content', 'atomora' ) )
				->set_items( [
					Text_Control::bind_to( 'text_singular' )
						->set_label( esc_html__( 'Text (1 review)', 'atomora' ) )
						/* translators: %s: the review count placeholder. */
						->set_placeholder( esc_html__( 'Use %s for the number', 'atomora' ) ),
					Text_Control::bind_to( 'text_plural' )
						->set_label( esc_html__( 'Text (many reviews)', 'atomora' ) )
						/* translators: %s: the review count placeholder. */
						->set_placeholder( esc_html__( 'Use %s for the number', 'atomora' ) ),
				] ),
			$this->settings_section(),
		];
	}

	private function render_part( \WC_Product $product, array $settings ): void {
		$count    = (int) $product->get_review_count();
		$template = 1 === $count
			? (string) ( $settings['text_singular'] ?? 'Based on %s review' )
			: (string) ( $settings['text_plural'] ?? 'Based on %s reviews' );

		echo esc_html( str_replace( '%s', number_format_i18n( $count ), $template ) );
	}
}
