<?php
namespace Atomora\Elements;

use Atomora\Widgets\Review_Context;
use Elementor\Plugin;
use Elementor\Modules\AtomicWidgets\Elements\Div_Block\Div_Block;
use Elementor\Modules\AtomicWidgets\Elements\Base\Element_Builder;
use Elementor\Modules\AtomicWidgets\Elements\Base\Widget_Builder;
use Elementor\Modules\AtomicWidgets\Elements\Base\Render_Context;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Escaped_Html_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Key_Value_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_Definition;
use Elementor\Modules\AtomicWidgets\Styles\Style_Variant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ATOMORA_PATH . 'includes/widgets/class-review-context.php';
require_once __DIR__ . '/class-review-item.php';

/**
 * Atomora — Reviews List.
 *
 * Native nestable element. Its Review Item child is a template that is
 * repeated for every review; the other children (e.g. Load More) render once.
 */
class Reviews_List extends Div_Block {

	const ITEM_TYPE = 'e-atomora-review-item';

	public static $widget_description = 'WooCommerce reviews list. Contains a Review Item template (repeated per review) and a Load More button.';

	/** @var array|null */
	private $state = null;

	public static function get_type() {
		return 'e-atomora-reviews-list';
	}

	public static function get_element_type(): string {
		return 'e-atomora-reviews-list';
	}

	public function get_title() {
		return esc_html__( 'Reviews List', 'atomora' ) . ' ⚛';
	}

	public function get_icon() {
		return 'eicon-comments';
	}

	public function get_keywords() {
		return [ 'atomic', 'woocommerce', 'review', 'rating', 'product', 'list', 'container' ];
	}

	protected function define_panel_categories(): array {
		return [ 'atomora-review' ];
	}

	protected static function define_props_schema(): array {
		return parent::define_props_schema() + Review_Context::product_props() + [
			'reviews_per_page' => Number_Prop_Type::make()->default( 5 ),
			'columns'          => Number_Prop_Type::make()->default( 3 ),
			'items_gap'        => Number_Prop_Type::make()->default( 16 ),
			'pagination_type'  => String_Prop_Type::make()->enum( [ 'load_more', 'numbered' ] )->default( 'load_more' ),
		];
	}

	protected function define_atomic_controls(): array {
		return array_merge(
			[
				Review_Context::product_section(),
				Section::make()
					->set_id( 'layout' )
					->set_label( esc_html__( 'Layout', 'atomora' ) )
					->set_items( [
						Number_Control::bind_to( 'columns' )
							->set_label( esc_html__( 'Reviews per row', 'atomora' ) )
							->set_min( 1 )
							->set_max( 6 ),
						Number_Control::bind_to( 'items_gap' )
							->set_label( esc_html__( 'Gap between reviews (px)', 'atomora' ) )
							->set_min( 0 ),
					] ),
				Section::make()
					->set_id( 'pagination' )
					->set_label( esc_html__( 'Pagination', 'atomora' ) )
					->set_items( [
						Number_Control::bind_to( 'reviews_per_page' )
							->set_label( esc_html__( 'Reviews per page', 'atomora' ) )
							->set_min( 1 )
							->set_max( 50 ),
						Select_Control::bind_to( 'pagination_type' )
							->set_label( esc_html__( 'Type', 'atomora' ) )
							->set_options( [
								[ 'value' => 'load_more', 'label' => esc_html__( 'Load more button', 'atomora' ) ],
								[ 'value' => 'numbered', 'label' => esc_html__( 'Numbered', 'atomora' ) ],
							] ),
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


	/** Native Elementor button (e-button) carrying one HTML attribute. */
	public static function native_button( string $text, string $attr_key, string $attr_value ): array {
		return Widget_Builder::make( 'e-button' )
			->settings( [
				'text'       => Escaped_Html_Prop_Type::generate( $text ),
				'attributes' => Attributes_Prop_Type::generate( [
					Key_Value_Prop_Type::generate( [
						'key'   => String_Prop_Type::generate( $attr_key ),
						'value' => String_Prop_Type::generate( $attr_value ),
					] ),
				] ),
			] )
			->build();
	}

	/** Default children of a new Reviews List: the Review Item template + Load More. */
	public static function default_children(): array {
		return [
			Element_Builder::make( self::ITEM_TYPE )->children( Review_Item::default_parts() )->build(),
			self::native_button( esc_html__( 'Load more reviews', 'atomora' ), 'data-atomora-load-more', '1' ),
		];
	}

	protected function define_default_children() {
		return self::default_children();
	}

	protected function get_templates(): array {
		return [
			'atomora/reviews-list' => __DIR__ . '/templates/reviews-list.html.twig',
		];
	}

	private function get_state(): array {
		if ( null === $this->state ) {
			$s       = $this->get_atomic_settings();
			$product = Review_Context::product( $s );
			$per     = min( 50, max( 1, absint( $s['reviews_per_page'] ?? 5 ) ) );
			$total   = $product ? (int) get_comments( Review_Context::comment_args( $product->get_id() ) + [ 'count' => true ] ) : 0;

			$this->state = [
				'product'    => $product,
				'per_page'   => $per,
				'pagination' => 'numbered' === ( $s['pagination_type'] ?? 'load_more' ) ? 'numbered' : 'load_more',
			'columns'    => min( 6, max( 1, absint( $s['columns'] ?? 3 ) ) ),
			'gap'        => absint( $s['items_gap'] ?? 16 ),
				'total'      => $total,
				'pages'      => (int) ceil( $total / $per ),
			];
		}
		return $this->state;
	}

	protected function define_render_context(): array {
		$context = Review_Context::product_context( $this->get_atomic_settings() );
		$state   = $this->get_state();

		$context[] = [
			'context_key' => Review_Context::LIST_KEY,
			'context'     => [
				'pagination' => $state['pagination'],
				'pages'      => $state['pages'],
			],
		];
		return $context;
	}

	protected function build_template_context(): array {
		$state = $this->get_state();
		$doc   = Plugin::$instance->documents->get_current();

		return $this->build_base_template_context() + [
			'product_id'  => $state['product'] ? $state['product']->get_id() : 0,
			'total_pages' => $state['pages'],
			'pagination'  => $state['pagination'],
			'doc_id'      => $doc ? $doc->get_main_id() : (int) get_the_ID(),
		];
	}

	private function get_item_child() {
		foreach ( $this->get_children() as $child ) {
			if ( self::ITEM_TYPE === ( $child->get_data()['elType'] ?? '' ) ) {
				return $child;
			}
		}
		return null;
	}

	/** Renders the Review Item template once per review of the requested page. */
	private function items_html( int $product_id, int $page, int $per_page ): string {
		$item = $this->get_item_child();
		if ( ! $item ) {
			return '';
		}

		$ids = get_comments( Review_Context::comment_args( $product_id ) + [
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'fields'  => 'ids',
		] );

		$data = $item->get_data();
		$html = '';

		foreach ( $ids as $comment_id ) {
			Render_Context::push( Review_Context::COMMENT_KEY, [ 'comment_id' => (int) $comment_id ] );
			$instance = Plugin::$instance->elements_manager->create_element_instance( $data );
			if ( $instance ) {
				ob_start();
				$instance->print_element();
				$html .= ob_get_clean();
			}
			Render_Context::pop( Review_Context::COMMENT_KEY );
		}

		return $html;
	}

	protected function render_children_to_html(): string {
		wp_enqueue_script( Review_Context::register_script() );

		$state = $this->get_state();
		if ( ! $state['product'] ) {
			return current_user_can( 'edit_posts' ) ? '<p class="atomora-reviews__notice">' . esc_html__( 'No product found.', 'atomora' ) . '</p>' : '';
		}

		$html       = '';
		$items_done = false;

		foreach ( $this->get_children() as $child ) {
			if ( self::ITEM_TYPE === ( $child->get_data()['elType'] ?? '' ) ) {
				if ( ! $items_done ) {
					$items_done = true;
					$html      .= '<div class="atomora-reviews-items" data-cols="' . (int) $state['columns'] . '" style="--atomora-review-cols:' . (int) $state['columns'] . ';--atomora-review-gap:' . (int) $state['gap'] . 'px">'
						. ( $state['total'] > 0
							? $this->items_html( $state['product']->get_id(), 1, $state['per_page'] )
							: '<p class="atomora-reviews__empty">' . esc_html__( 'There are no reviews yet.', 'atomora' ) . '</p>' )
						. '</div>';
				}
				continue;
			}
			ob_start();
			$child->print_element();
			$html .= ob_get_clean();
		}

		if ( 'numbered' === $state['pagination'] && $state['pages'] > 1 ) {
			$html .= '<nav class="atomora-reviews__pages">';
			for ( $i = 1; $i <= $state['pages']; $i++ ) {
				$html .= sprintf(
					'<button type="button" class="atomora-reviews__page%1$s" data-page="%2$d">%2$d</button>',
					1 === $i ? ' is-active' : '',
					$i
				);
			}
			$html .= '</nav>';
		}

		return $html;
	}

	public function page_payload( int $product_id, int $page ): array {
		$s     = $this->get_atomic_settings();
		$per   = min( 50, max( 1, absint( $s['reviews_per_page'] ?? 5 ) ) );
		$total = (int) get_comments( Review_Context::comment_args( $product_id ) + [ 'count' => true ] );

		return [
			'html'  => $this->items_html( $product_id, $page, $per ),
			'page'  => $page,
			'total' => (int) ceil( $total / $per ),
		];
	}

	private static function find_element( array $elements, string $id ) {
		foreach ( $elements as $element ) {
			if ( ( $element['id'] ?? '' ) === $id ) {
				return $element;
			}
			if ( ! empty( $element['elements'] ) ) {
				$found = self::find_element( $element['elements'], $id );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/** admin-ajax handler for "load more" / numbered pagination. */
	public static function ajax_load_reviews(): void {
		check_ajax_referer( 'atomora_reviews', 'nonce' );

		$doc_id     = absint( $_POST['doc_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$element_id = sanitize_text_field( wp_unslash( $_POST['element_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$product_id = absint( $_POST['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$page       = max( 1, absint( $_POST['page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $doc_id || '' === $element_id || ! $product_id
			|| 'product' !== get_post_type( $product_id ) || 'publish' !== get_post_status( $product_id )
			|| 'publish' !== get_post_status( $doc_id ) ) {
			wp_send_json_error( null, 404 );
		}

		$document = Plugin::$instance->documents->get( $doc_id );
		$data     = $document ? self::find_element( (array) $document->get_elements_data(), $element_id ) : null;
		if ( ! $data || static::get_element_type() !== ( $data['elType'] ?? '' ) ) {
			wp_send_json_error( null, 404 );
		}

		$list = Plugin::$instance->elements_manager->create_element_instance( $data );
		if ( ! $list instanceof self ) {
			wp_send_json_error( null, 404 );
		}

		wp_send_json_success( $list->page_payload( $product_id, $page ) );
	}
}