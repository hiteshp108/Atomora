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
 * Native "In Tags" only covers the blog 'post_tag' taxonomy (confirmed
 * by reading its source). This adds the same experience for
 * WooCommerce's 'product_tag' taxonomy.
 */
class Product_Tag_Condition extends Condition_Base {

	public function get_name() {
		return 'atomora_product_tag';
	}

	public function get_label() {
		return esc_html__( 'Product Tag', 'atomora' ) . ' ⚛';
	}

	public function get_group() {
		return 'post';
	}

	public function check( $args ): bool {
		if ( ! class_exists( '\WooCommerce' ) || empty( $args['tags'] ) ) {
			return true;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		$product_tags = wp_get_post_terms( $post_id, 'product_tag', [ 'fields' => 'ids'] );
		if ( ! is_array( $product_tags ) ) {
			$product_tags = [];
		}

		$tag_ids = array_column( $args['tags'], 'id' );

		return Comparators_Checker::check_array_contains( $args['comparator'], $tag_ids, $product_tags );
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
			'tags',
			[
				'type'         => QueryControlModule::QUERY_CONTROL_ID,
				'autocomplete' => [
					'object' => QueryControlModule::QUERY_OBJECT_TAX,
					'query'  => [
						'taxonomy' => 'product_tag',
					],
				],
				'multiple'    => true,
				'placeholder' => esc_html__( 'Type to search', 'atomora' ),
				'required'    => true,
			]
		);
	}
}