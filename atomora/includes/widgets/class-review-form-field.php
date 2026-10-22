<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Elements\Base\Render_Context;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-review-child.php';
/**
 * Atomora — Review Form Field: rating input, review text, name, email or
 * submit button of the Reviews Form.
 */
class Review_Form_Field extends Atomic_Widget_Base {
	use Review_Child;

	public static $widget_description = 'A field of the review form: star rating, review text, name, email or submit button. Use inside a Reviews Form.';

	public static function get_element_type(): string {
		return 'e-atomora-review-form-field';
	}

	public function get_title() {
		return esc_html__( 'Review Form Field', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'form', 'field', 'rating', 'submit', 'input' ];
	}

	protected function get_templates(): array {
		return [
			'atomora/review-form-field' => __DIR__ . '/templates/review-form-field.html.twig',
		];
	}

	protected static function define_props_schema(): array {
		return self::child_props() + [
			'field' => String_Prop_Type::make()->enum( [ 'rating', 'comment', 'author', 'email' ] )->default( 'comment' ),
			'label' => String_Prop_Type::make()->default( '' ),
		];
	}

	protected function define_atomic_controls(): array {
		return [
			Section::make()
				->set_id( 'content' )
				->set_label( esc_html__( 'Content', 'atomora' ) )
				->set_items( [
					Select_Control::bind_to( 'field' )
						->set_label( esc_html__( 'Field', 'atomora' ) )
						->set_options( [
							[ 'value' => 'rating', 'label' => esc_html__( 'Star rating', 'atomora' ) ],
							[ 'value' => 'comment', 'label' => esc_html__( 'Review text', 'atomora' ) ],
							[ 'value' => 'author', 'label' => esc_html__( 'Name', 'atomora' ) ],
							[ 'value' => 'email', 'label' => esc_html__( 'Email', 'atomora' ) ],
						] ),
					Text_Control::bind_to( 'label' )
						->set_label( esc_html__( 'Label (empty = default)', 'atomora' ) ),
				] ),
			$this->settings_section(),
		];
	}

	protected function template_context( array $settings ): array {
		$field  = $settings['field'] ?? 'comment';
		$editor = Review_Context::is_editor();
		$labels = [
			'rating'  => __( 'Your rating', 'atomora' ),
			'comment' => __( 'Your review', 'atomora' ),
			'author'  => __( 'Name', 'atomora' ),
			'email'   => __( 'Email', 'atomora' ),
		];

		$show = true;
		if ( ! $editor ) {
			if ( 'rating' === $field && 'no' === get_option( 'woocommerce_enable_review_rating' ) ) {
				$show = false;
			}
		}

		return [
			'show'            => $show,
			'default_label'   => $labels[ $field ] ?? '',
			'rating_required' => function_exists( 'wc_review_ratings_required' ) && wc_review_ratings_required(),
		];
	}
}
