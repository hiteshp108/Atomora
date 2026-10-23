<?php
namespace Atomora\Widgets;

use Elementor\Modules\AtomicWidgets\Elements\Base\Render_Context;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Atomora — shared helpers for the Reviews List / Reviews Form elements
 * and their child widgets (product resolution, per-review context, assets).
 */
final class Review_Context {

	const PRODUCT_KEY = 'atomora-reviews';
	const LIST_KEY    = 'atomora-reviews-list';
	const COMMENT_KEY = 'atomora-review-comment';

	public static function is_editor(): bool {
		return Plugin::$instance->editor->is_edit_mode() || Plugin::$instance->preview->is_preview_mode();
	}

	public static function product_props(): array {
		return [
			'product_source'    => String_Prop_Type::make()->enum( [ 'inherit', 'current', 'manual' ] )->default( 'inherit' ),
			'manual_product_id' => Number_Prop_Type::make()->default( 0 ),
		];
	}

	public static function product_section(): Section {
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

	/** Render-context pairs publishing this element's own product choice to its children. */
	public static function product_context( array $settings ): array {
		$source = $settings['product_source'] ?? 'inherit';
		if ( 'inherit' === $source ) {
			return [];
		}
		return [
			[
				'context_key' => self::PRODUCT_KEY,
				'context'     => [
					'source'            => 'manual' === $source ? 'manual' : 'current',
					'manual_product_id' => absint( $settings['manual_product_id'] ?? 0 ),
				],
			],
		];
	}

	public static function product( array $settings ) {
		if ( ! class_exists( '\WooCommerce' ) ) {
			return false;
		}

		$source     = $settings['product_source'] ?? 'inherit';
		$manual_id  = absint( $settings['manual_product_id'] ?? 0 );
		$use_manual = 'manual' === $source;

		if ( 'inherit' === $source ) {
			$context = Render_Context::get( self::PRODUCT_KEY );
			if ( ! empty( $context ) && 'manual' === ( $context['source'] ?? '' ) ) {
				$use_manual = true;
				$manual_id  = absint( $context['manual_product_id'] ?? 0 );
			}
		}

		if ( $use_manual ) {
			return $manual_id ? \Atomora\Product_Access::public_product( wc_get_product( $manual_id ) ) : false;
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

	public static function comment_args( int $product_id ): array {
		return [
			'post_id'  => $product_id,
			'status'   => 'approve',
			'type__in' => [ 'review', 'comment' ],
			'parent'   => 0,
		];
	}

	/** Data of the review being rendered; sample data inside the editor; null on the front end without one. */
	public static function current_comment() {
		$ctx = Render_Context::get( self::COMMENT_KEY );
		if ( ! empty( $ctx['comment_id'] ) ) {
			$c = get_comment( absint( $ctx['comment_id'] ) );
			if ( $c ) {
				return [
					'comment'  => $c,
					'author'   => get_comment_author( $c ),
					'content'  => $c->comment_content,
					'date_iso' => get_comment_date( 'c', $c ),
					'date'     => get_comment_date( '', $c ),
					'rating'   => (float) get_comment_meta( $c->comment_ID, 'rating', true ),
					'verified' => function_exists( 'wc_review_is_from_verified_owner' ) && wc_review_is_from_verified_owner( $c->comment_ID ),
				];
			}
		}

		if ( self::is_editor() ) {
			return [
				'comment'  => null,
				'author'   => 'Jane Doe',
				'content'  => 'Great product, exactly as described. Fast delivery and nicely packed.',
				'date_iso' => gmdate( 'c' ),
				'date'     => wp_date( get_option( 'date_format' ) ),
				'rating'   => 4.5,
				'verified' => true,
			];
		}
		return null;
	}

	/** Two-layer star display: dim row + clipped filled row, both use the text colour. */
	public static function stars_html( float $rating ): string {
		$rating  = max( 0.0, min( 5.0, $rating ) );
		$percent = round( ( $rating / 5 ) * 100, 2 );

		return sprintf(
			'<span class="atomora-review__stars" role="img" aria-label="%1$s"><span class="atomora-review__stars-empty" aria-hidden="true">★★★★★</span><span class="atomora-review__stars-fill" aria-hidden="true" style="width:%2$s%%">★★★★★</span></span>',
			esc_attr( sprintf( /* translators: %s: rating */ __( 'Rated %s out of 5', 'atomora' ), round( $rating, 1 ) ) ),
			esc_attr( (string) $percent )
		);
	}


	/** True when Elementor Pro's native form elements (label, textarea, submit) are available. */
	public static function native_form_available(): bool {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return false;
		}
		$manager = Plugin::$instance->widgets_manager;
		foreach ( [ 'e-form-label', 'e-form-textarea', 'e-form-submit-button' ] as $type ) {
			if ( ! $manager->get_widget_types( $type ) ) {
				return false;
			}
		}
		return true;
	}

	/** Builds a native widget's `text` prop in whatever prop type this Elementor version uses. */
	public static function native_text( string $widget_type, string $text ): array {
		$value  = [ '$$type' => 'escaped-html', 'value' => $text ];
		$widget = Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
		if ( $widget ) {
			try {
				$schema = ( new \ReflectionMethod( $widget, 'define_props_schema' ) )->invoke( null );
				$default = isset( $schema['text'] ) ? $schema['text']->get_default() : null;
				if ( is_array( $default ) ) {
					if ( 'html-v3' === ( $default['$$type'] ?? '' ) ) {
						$default['value']['content']['value'] = $text;
						return $default;
					}
					$default['value'] = $text;
					return $default;
				}
			} catch ( \Throwable $e ) {
				return $value;
			}
		}
		return $value;
	}

	/** Registers (once) and returns the handle of the load-more / pagination script. */
	public static function register_script(): string {
		if ( ! wp_script_is( 'atomora-product-reviews', 'registered' ) ) {
			wp_register_script( 'atomora-product-reviews', ATOMORA_URL . 'assets/js/product-reviews.js', [], ATOMORA_VERSION, true );
			wp_localize_script( 'atomora-product-reviews', 'atomoraReviews', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'atomora_reviews' ),
			] );
		}
		return 'atomora-product-reviews';
	}
}