<?php
namespace Atomora\Conditions;

use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;
use ElementorPro\Modules\DisplayConditions\Classes\Comparator_Provider;
use ElementorPro\Modules\DisplayConditions\Classes\Comparators_Checker;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No native condition compares a numeric product price at all. Compares
 * the current page's product price (as a whole number in the store's
 * base currency unit) against a configured value.
 */
class Product_Price_Condition extends Condition_Base {

	public function get_name() {
		return 'atomora_product_price';
	}

	public function get_label() {
		return esc_html__( 'Product Price', 'atomora' ) . ' ⚛';
	}

	public function get_group() {
		return 'post';
	}

	public function check( $args ): bool {
		if ( ! class_exists( '\WooCommerce' ) ) {
			return false;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}

		$price    = (int) round( (float) $product->get_price() );
		$compare  = (int) ( $args['price'] ?? 0 );
		$comparator = $args['comparator'] ?? Comparator_Provider::COMPARATOR_IS;

		return Comparators_Checker::check_numeric_constraints( $comparator, $price, $compare );
	}

	public function get_options() {
		$comparators = Comparator_Provider::get_comparators(
			[
				Comparator_Provider::COMPARATOR_IS,
				Comparator_Provider::COMPARATOR_IS_NOT,
				Comparator_Provider::COMPARATOR_IS_LESS_THAN_INCLUSIVE,
				Comparator_Provider::COMPARATOR_IS_GREATER_THAN_INCLUSIVE,
			]
		);

		$this->add_control(
			'comparator',
			[
				'type'    => Controls_Manager::SELECT,
				'options' => $comparators,
				'default' => Comparator_Provider::COMPARATOR_IS_LESS_THAN_INCLUSIVE,
			]
		);

		$this->add_control(
			'price',
			[
				'type'    => Controls_Manager::NUMBER,
				'label'   => esc_html__( 'Price', 'atomora' ),
				'default' => 0,
				'min'     => 0,
			]
		);
	}
}