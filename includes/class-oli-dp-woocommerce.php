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
			$child_settings['copy_status']     = false;
			$child_settings['oli_dp_internal'] = true;

			$new_variation_id = Oli_DP_Duplicator::duplicate( $variation->ID, 'clone', $child_settings );
			if ( is_wp_error( $new_variation_id ) ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $new_variation_id,
					'post_parent' => $new_id,
					'post_status' => $variation->post_status,
				)
			);

			self::save_wc_product( $new_variation_id );
		}

		self::sync_variable_product( $new_id );
	}

	/**
	 * Publish-ready variable products: sync prices and fill the lookup table.
	 *
	 * WooCommerce only sells variable products whose variations are published
	 * and present in `wc_product_meta_lookup`.
	 *
	 * @since 1.0.1
	 *
	 * @param int $product_id New parent product ID.
	 */
	private static function sync_variable_product( $product_id ) {
		$synced = false;

		if ( class_exists( 'WC_Product_Variable' ) && is_callable( array( 'WC_Product_Variable', 'sync' ) ) ) {
			WC_Product_Variable::sync( $product_id );
			$synced = true;
		}

		$lookup_ids = array_merge(
			array( (int) $product_id ),
			self::get_variation_ids( $product_id )
		);

		foreach ( $lookup_ids as $lookup_id ) {
			self::update_product_lookup_table( $lookup_id );

			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $lookup_id );
			}
		}

		/**
		 * Fires after variations are copied and the parent is synced.
		 *
		 * @since 1.0.1
		 *
		 * @param int   $product_id New parent product ID.
		 * @param bool  $synced     Whether WC_Product_Variable::sync() ran.
		 * @param int[] $lookup_ids Product and variation IDs refreshed in lookup tables.
		 */
		do_action( 'oli_dp_woocommerce_variable_synced', $product_id, $synced, $lookup_ids );
	}

	/**
	 * Variation IDs attached to a product.
	 *
	 * @since 1.0.1
	 *
	 * @param int $product_id Parent product ID.
	 * @return int[]
	 */
	private static function get_variation_ids( $product_id ) {
		$variations = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_parent'    => $product_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		return array_map( 'intval', $variations );
	}

	/**
	 * Write one product or variation into wc_product_meta_lookup.
	 *
	 * @since 1.0.1
	 *
	 * @param int $product_id Product or variation ID.
	 */
	private static function update_product_lookup_table( $product_id ) {
		if ( self::save_wc_product( $product_id ) ) {
			return;
		}

		if ( function_exists( 'wc_update_product_lookup_tables_for_product' ) ) {
			wc_update_product_lookup_tables_for_product( $product_id );
			return;
		}

		if ( ! class_exists( 'WC_Data_Store' ) || ! is_callable( array( 'WC_Data_Store', 'load' ) ) ) {
			return;
		}

		$store_name = 'product_variation' === get_post_type( $product_id ) ? 'product-variation' : 'product';

		try {
			$data_store = WC_Data_Store::load( $store_name );
		} catch ( \Exception $exception ) {
			return;
		}

		if ( $data_store && is_callable( array( $data_store, 'update_lookup_table' ) ) ) {
			$data_store->update_lookup_table( $product_id, 'wc_product_meta_lookup' );
		}
	}

	/**
	 * Persist a product or variation through the WooCommerce CRUD API.
	 *
	 * `$product->save()` writes `wc_product_meta_lookup` the same way native
	 * WooCommerce duplication does.
	 *
	 * @since 1.0.2
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool True when WooCommerce saved the object.
	 */
	private static function save_wc_product( $product_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! is_callable( array( $product, 'save' ) ) ) {
			return false;
		}

		$product->save();
		return true;
	}
}
