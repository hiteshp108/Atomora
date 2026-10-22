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
 * Atomora — Review Part: one piece (avatar, author, verified badge, date,
 * rating, content) of the review currently being rendered by a Review Item.
 */
class Review_Item_Part extends Atomic_Widget_Base {
	use Review_Child;

	public static $widget_description = 'One part of a single review (avatar, author, verified badge, date, rating or content). Use inside a Review Item.';

	public static function get_element_type(): string {
		return 'e-atomora-review-item-part';
	}

	public function get_title() {
		return esc_html__( 'Review Part', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-testimonial';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'author', 'avatar', 'date', 'rating', 'content' ];
	}

	protected function get_templates(): array {
		return [
			'atomora/review-part' => __DIR__ . '/templates/review-part.html.twig',
		];
	}

	protected static function define_props_schema(): array {
		return self::child_props() + [
			'part'           => String_Prop_Type::make()->enum( [ 'avatar', 'author', 'verified', 'date', 'rating', 'content' ] )->default( 'content' ),
			'avatar_size'    => Number_Prop_Type::make()->default( 48 ),
			'verified_label' => String_Prop_Type::make()->default( 'Verified owner' ),
		];
	}

	protected function define_atomic_controls(): array {
		return [
			Section::make()
				->set_id( 'content' )
				->set_label( esc_html__( 'Content', 'atomora' ) )
				->set_items( [
					Select_Control::bind_to( 'part' )
						->set_label( esc_html__( 'Show', 'atomora' ) )
						->set_options( [
							[ 'value' => 'avatar', 'label' => esc_html__( 'Avatar', 'atomora' ) ],
							[ 'value' => 'author', 'label' => esc_html__( 'Author', 'atomora' ) ],
							[ 'value' => 'verified', 'label' => esc_html__( 'Verified badge', 'atomora' ) ],
							[ 'value' => 'date', 'label' => esc_html__( 'Date', 'atomora' ) ],
							[ 'value' => 'rating', 'label' => esc_html__( 'Rating (stars)', 'atomora' ) ],
							[ 'value' => 'content', 'label' => esc_html__( 'Review text', 'atomora' ) ],
						] ),
					Number_Control::bind_to( 'avatar_size' )
						->set_label( esc_html__( 'Avatar size (px)', 'atomora' ) )
						->set_min( 16 )
						->set_max( 256 ),
					Text_Control::bind_to( 'verified_label' )->set_label( esc_html__( 'Verified label', 'atomora' ) ),
				] ),
			$this->settings_section(),
		];
	}

	protected function template_context( array $settings ): array {
		$c = Review_Context::current_comment();
		if ( ! $c ) {
			return [ 'show' => false ];
		}

		$ctx = [ 'show' => true ];
		switch ( $settings['part'] ?? 'content' ) {
			case 'avatar':
				$ctx['avatar_html'] = (string) get_avatar( $c['comment'] ? $c['comment'] : '', min( 256, max( 16, absint( $settings['avatar_size'] ?? 48 ) ) ) );
				break;
			case 'author':
				$ctx['author'] = $c['author'];
				break;
			case 'verified':
				$ctx['show'] = (bool) $c['verified'];
				break;
			case 'date':
				$ctx['date_text'] = $c['date'];
				$ctx['date_iso']  = $c['date_iso'];
				break;
			case 'rating':
				$ctx['show']       = $c['rating'] > 0;
				$ctx['stars_html'] = Review_Context::stars_html( $c['rating'] );
				break;
			default:
				$ctx['content_html'] = wp_kses_post( wpautop( $c['content'] ) );
		}
		return $ctx;
	}
}
