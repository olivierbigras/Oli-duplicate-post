<?php
/**
 * WooCommerce-aware duplication helpers.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sensible product cloning: unique SKUs, no sales stats, copy variations.
 *
 * @since 1.0.0
 */
class Oli_DP_WooCommerce {

	/**
	 * Whether a post is a WooCommerce product or variation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post|null $post Post object.
	 * @return bool
	 */
	public static function is_product( $post ) {
		return $post && in_array( $post->post_type, array( 'product', 'product_variation' ), true );
	}

	/**
	 * Meta keys that should not be cloned for products.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function excluded_meta_keys() {
		return Oli_DP_Utils::woocommerce_excluded_meta_keys();
	}

	/**
	 * Finish a product duplicate: unique SKU and variations.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $original_id Original post ID.
	 * @param int    $new_id      New post ID.
	 * @param string $mode        Duplication mode.
	 * @param array  $settings    Settings used for the duplicate.
	 */
	public static function after_duplicate( $original_id, $new_id, $mode, $settings ) {
		$post = get_post( $original_id );
		if ( ! self::is_product( $post ) ) {
			return;
		}

		self::uniquify_sku( $new_id );

		if ( 'product' === $post->post_type ) {
			self::copy_variations( $original_id, $new_id, $mode, $settings );
		}
	}

	/**
	 * Append the new product ID to a copied SKU so it stays unique.
	 *
	 * @since 1.0.0
	 *
	 * @param int $new_id New product ID.
	 */
	private static function uniquify_sku( $new_id ) {
		$sku = get_post_meta( $new_id, '_sku', true );
		if ( '' === $sku || false === $sku ) {
			return;
		}

		/**
		 * Filter the unique SKU assigned to a duplicated product.
		 *
		 * @since 1.0.0
		 *
		 * @param string $sku    Proposed unique SKU.
		 * @param int    $new_id New product ID.
		 */
		$unique_sku = apply_filters( 'oli_dp_woocommerce_unique_sku', $sku . '-' . $new_id, $new_id );
		update_post_meta( $new_id, '_sku', $unique_sku );
	}

	/**
	 * Duplicate product variations as children of the new product.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $original_id Original product ID.
	 * @param int    $new_id      New product ID.
	 * @param string $mode        Duplication mode.
	 * @param array  $settings    Settings used for the parent duplicate.
	 */
	private static function copy_variations( $original_id, $new_id, $mode, $settings ) {
		unset( $mode );

		$variations = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_parent'    => $original_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		if ( empty( $variations ) ) {
			return;
		}

		foreach ( $variations as $variation ) {
			$child_settings                    = $settings;
			$child_settings['copy_children']   = false;
			$child_settings['oli_dp_internal'] = true;

			$new_variation_id = Oli_DP_Duplicator::duplicate( $variation->ID, 'clone', $child_settings );
			if ( is_wp_error( $new_variation_id ) ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $new_variation_id,
					'post_parent' => $new_id,
				)
			);
		}
	}
}
