<?php
namespace Atomora\Conditions;

use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds "WooCommerce Stock Status" as a selectable condition in Elementor
 * Pro's native Display Conditions builder (same system used by both
 * classic widgets' Advanced tab and Atomic elements' Settings group —
 * confirmed by reading Elementor Pro's actual source: one shared
 * Conditions_Manager, one shared 'elementor/display_conditions/register'
 * hook, injected into different UI locations depending on element type).
 *
 * KNOWN LIMITATION: like all of Elementor Pro's native conditions, this
 * checks the CURRENT PAGE's post (get_the_ID()) — it has no way to know
 * about a manually-selected product inside an Atomora widget (e.g.
 * Product Card set to show a specific product on an unrelated page). It
 * will only be accurate when the current page IS the product being
 * evaluated (typically: that product's own single product page).
 */
class WooCommerce_Stock_Condition extends Condition_Base {

	public function get_name() {
		return 'atomora_woocommerce_stock';
	}

	public function get_label() {
		return esc_html__( 'Stock Status', 'atomora' ) . ' ⚛';
	}

	public function get_group() {
		return 'post';
	}

	/**
	 * @param array $args The condition's own saved control values (from
	 *                     get_options() below) — NOT page/post context.
	 * @return bool
	 */
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

		$expected = $args['stock_status'] ?? '';

		if ( 'on_sale' === $expected ) {
			return $product->is_on_sale();
		}

		$actual = $product->get_stock_status(); // 'instock' | 'outofstock' | 'onbackorder'

		return $expected === $actual;
	}

	public function get_options() {
		$this->add_control(
			'stock_status',
			[
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Stock status', 'atomora' ),
				'default' => 'instock',
				'options' => [
					'instock'     => esc_html__( 'In stock', 'atomora' ),
					'outofstock'  => esc_html__( 'Out of stock', 'atomora' ),
					'onbackorder' => esc_html__( 'On backorder', 'atomora' ),
					'on_sale'     => esc_html__( 'On sale', 'atomora' ),
				],
			]
		);
	}
}