<?php
namespace Atomora\Conditions;

use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;
use ElementorPro\Modules\DisplayConditions\Classes\Comparator_Provider;
use ElementorPro\Modules\DisplayConditions\Classes\Comparators_Checker;
use ElementorPro\Modules\QueryControl\Module as QueryControlModule;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native "In Categories" only covers the blog 'category' taxonomy
 * (confirmed by reading its source — hardcoded 'category'). This adds
 * the same experience for WooCommerce's 'product_cat' taxonomy.
 *
 * Same page-context limitation as the Stock Status condition: checks
 * the current page's post, not a widget's manually-selected product.
 */
class Product_Category_Condition extends Condition_Base {

	public function get_name() {
		return 'atomora_product_category';
	}

	public function get_label() {
		return esc_html__( 'Product Category', 'atomora' ) . ' ⚛';
	}

	public function get_group() {
		return 'post';
	}

	public function check( $args ): bool {
		if ( ! class_exists( '\WooCommerce' ) || empty( $args['categories'] ) ) {
			return true;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		$product_categories = wp_get_post_terms( $post_id, 'product_cat', [ 'fields' => 'ids'] );
		if ( ! is_array( $product_categories ) ) {
			$product_categories = [];
		}

		if ( empty( $product_categories ) ) {
			return ! ( Comparator_Provider::COMPARATOR_IS === $args['comparator'] );
		}

		$category_ids = array_column( $args['categories'], 'id' );

		return Comparators_Checker::check_array_contains( $args['comparator'], $category_ids, $product_categories );
	}

	public function get_options() {
		$comparators = Comparator_Provider::get_comparators(
			[
				Comparator_Provider::COMPARATOR_IS,
				Comparator_Provider::COMPARATOR_IS_NOT,
			]
		);

		$this->add_control(
			'comparator',
			[
				'type'    => Controls_Manager::SELECT,
				'options' => $comparators,
				'default' => Comparator_Provider::COMPARATOR_IS,
			]
		);

		$this->add_control(
			'categories',
			[
				'type'         => QueryControlModule::QUERY_CONTROL_ID,
				'autocomplete' => [
					'object' => QueryControlModule::QUERY_OBJECT_TAX,
					'query'  => [
						'taxonomy' => 'product_cat',
					],
				],
				'multiple'    => true,
				'placeholder' => esc_html__( 'Type to search', 'atomora' ),
				'required'    => true,
			]
		);
	}
}