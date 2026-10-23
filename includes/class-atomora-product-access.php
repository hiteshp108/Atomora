<?php
namespace Atomora;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/**
 * Product visibility guard.
 *
 * Widgets can be pointed at a specific product by ID. Only products the
 * current visitor may see are returned, so an editor cannot expose a draft,
 * private, pending or password-protected product on a public page.
 */
final class Product_Access {

	/**
	 * @param mixed $product A WC_Product, false or null.
	 * @return \WC_Product|false The product when it is public or the user may read it.
	 */
	public static function public_product( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}

		$id = $product->get_id();
		$id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $id;

		if ( 'product' !== get_post_type( $id ) ) {
			return false;
		}

		if ( current_user_can( 'read_product', $id ) ) {
			return $product;
		}

		if ( 'publish' !== get_post_status( $id ) || post_password_required( $id ) ) {
			return false;
		}

		return $product;
	}
}