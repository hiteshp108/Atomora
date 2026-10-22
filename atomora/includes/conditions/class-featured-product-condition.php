<?php
namespace Atomora\Conditions;

use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks WooCommerce's own "Featured" product flag (Catalog visibility →
 * Featured, set per-product) against the current page's product.
 */
class Featured_Product_Condition extends Condition_Base {

	public function get_name() {
		return 'atomora_featured_product';
	}

	public function get_label() {
		return esc_html__( 'Featured Product', 'atomora' ) . ' ⚛';
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

		$expected = ! empty( $args['is_featured'] );

		return $expected === $product->is_featured();
	}

	public function get_options() {
		$this->add_control(
			'is_featured',
			[
				'type'         => Controls_Manager::SWITCHER,
				'label'        => esc_html__( 'Is featured', 'atomora' ),
				'label_on'     => esc_html__( 'Yes', 'atomora' ),
				'label_off'    => esc_html__( 'No', 'atomora' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);
	}
}