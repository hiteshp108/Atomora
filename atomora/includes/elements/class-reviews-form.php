<?php
namespace Atomora\Elements;

use Atomora\Widgets\Review_Context;
use Elementor\Modules\AtomicWidgets\Elements\Div_Block\Div_Block;
use Elementor\Modules\AtomicWidgets\Elements\Base\Widget_Builder;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Key_Value_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Escaped_Html_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_Definition;
use Elementor\Modules\AtomicWidgets\Styles\Style_Variant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ATOMORA_PATH . 'includes/widgets/class-review-context.php';

/**
 * Atomora — Reviews Form.
 *
 * Native nestable element rendered as a <form> that posts to WordPress'
 * comment handler. Its children are the form fields.
 */
class Reviews_Form extends Div_Block {

	public static $widget_description = 'WooCommerce review form (a <form> element). Contains a heading, rating, review, name, email fields and a submit button.';

	public static function get_type() {
		return 'e-atomora-reviews-form';
	}

	public static function get_element_type(): string {
		return 'e-atomora-reviews-form';
	}

	public function get_title() {
		return esc_html__( 'Review Form', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'rating', 'product', 'form', 'container' ];
	}

	protected function define_panel_categories(): array {
		return [ 'atomora-review' ];
	}

	protected static function define_props_schema(): array {
		return parent::define_props_schema() + Review_Context::product_props();
	}

	protected function define_atomic_controls(): array {
		return [
			Review_Context::product_section(),
			Section::make()
				->set_id( 'settings' )
				->set_label( esc_html__( 'Settings', 'atomora' ) )
				->set_items( [
					Text_Control::bind_to( '_cssid' )
						->set_label( esc_html__( 'ID', 'atomora' ) )
						->set_meta( $this->get_css_id_control_meta() ),
				] ),
		];
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

	/** Default children of a new Reviews Form (native Elementor Pro form elements when available). */
	public static function default_children( $native = null ): array {
		if ( null === $native ) {
			$native = Review_Context::native_form_available();
		}

		$field = function ( string $name ): array {
			return Widget_Builder::make( 'e-atomora-review-form-field' )
				->settings( [ 'field' => String_Prop_Type::generate( $name ) ] )
				->build();
		};

		$children = [
			Widget_Builder::make( 'e-heading' )
				->settings( [
					'title' => Escaped_Html_Prop_Type::generate( esc_html__( 'Add a review', 'atomora' ) ),
					'tag'   => String_Prop_Type::generate( 'h3' ),
				] )
				->build(),
			$field( 'rating' ),
		];

		if ( $native ) {
			// Name and ID are the same value: the native textarea uses its ID as its `name`.
			$children[] = Widget_Builder::make( 'e-form-label' )
				->settings( [
					'text'     => Review_Context::native_text( 'e-form-label', esc_html__( 'Your review', 'atomora' ) ),
					'input-id' => String_Prop_Type::generate( 'comment' ),
				] )
				->build();
			$children[] = Widget_Builder::make( 'e-form-textarea' )
				->settings( [
					'_cssid'   => String_Prop_Type::generate( 'comment' ),
					'required' => Boolean_Prop_Type::generate( true ),
				] )
				->build();
		} else {
			$children[] = $field( 'comment' );
		}

		if ( $native ) {
			foreach ( [ 'author' => [ esc_html__( 'Name', 'atomora' ), 'text' ], 'email' => [ esc_html__( 'Email', 'atomora' ), 'email' ] ] as $name => $def ) {
				$children[] = Widget_Builder::make( 'e-form-label' )
					->settings( [
						'text'     => Review_Context::native_text( 'e-form-label', $def[0] ),
						'input-id' => String_Prop_Type::generate( $name ),
					] )
					->build();
				$children[] = Widget_Builder::make( 'e-form-input' )
					->settings( [
						'_cssid'   => String_Prop_Type::generate( $name ),
						'type'     => String_Prop_Type::generate( $def[1] ),
						'required' => Boolean_Prop_Type::generate( true ),
					] )
					->build();
			}
		} else {
			$children[] = $field( 'author' );
			$children[] = $field( 'email' );
		}

		if ( $native ) {
			$children[] = Widget_Builder::make( 'e-form-submit-button' )
				->settings( [ 'text' => Review_Context::native_text( 'e-form-submit-button', esc_html__( 'Submit', 'atomora' ) ) ] )
				->build();
		} else {
			$children[] = Widget_Builder::make( 'e-button' )
				->settings( [
					'text'       => Escaped_Html_Prop_Type::generate( esc_html__( 'Submit', 'atomora' ) ),
					'attributes' => Attributes_Prop_Type::generate( [
						Key_Value_Prop_Type::generate( [
							'key'   => String_Prop_Type::generate( 'type' ),
							'value' => String_Prop_Type::generate( 'submit' ),
						] ),
					] ),
				] )
				->build();
		}

		return $children;
	}
	protected function define_default_children() {
		return self::default_children();
	}

	protected function define_render_context(): array {
		return Review_Context::product_context( $this->get_atomic_settings() );
	}

	protected function get_templates(): array {
		return [
			'atomora/reviews-form' => __DIR__ . '/templates/reviews-form.html.twig',
		];
	}

	protected function build_template_context(): array {
		return $this->build_base_template_context() + [
			'form_action' => site_url( '/wp-comments-post.php' ),
		];
	}

	protected function render_children_to_html(): string {
		$product = Review_Context::product( $this->get_atomic_settings() );
		if ( ! $product ) {
			return current_user_can( 'edit_posts' ) ? '<p class="atomora-reviews__notice">' . esc_html__( 'No product found.', 'atomora' ) . '</p>' : '';
		}

		$product_id = $product->get_id();
		if ( ! comments_open( $product_id ) ) {
			return '';
		}

		if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
			return '<p class="atomora-reviews__notice">' . wp_kses_post( sprintf(
				/* translators: %s: login link */
				__( 'You must be %s to post a review.', 'atomora' ),
				'<a href="' . esc_url( wp_login_url( get_permalink( $product_id ) ) ) . '">' . esc_html__( 'logged in', 'atomora' ) . '</a>'
			) ) . '</p>';
		}

		if ( 'yes' === get_option( 'woocommerce_review_rating_verification_required' )
			&& ( ! is_user_logged_in() || ! wc_customer_bought_product( '', get_current_user_id(), $product_id ) ) ) {
			return '<p class="atomora-reviews__notice">' . esc_html__( 'Only logged in customers who have purchased this product may leave a review.', 'atomora' ) . '</p>';
		}

		return parent::render_children_to_html()
			. '<input type="hidden" name="comment_post_ID" value="' . esc_attr( (string) $product_id ) . '">'
			. '<input type="hidden" name="comment_parent" value="0">';
	}
}