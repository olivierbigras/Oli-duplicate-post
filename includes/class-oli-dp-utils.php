<?php
/**
 * Shared helpers for Oli Duplicate Post.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure helpers used by duplication, settings, and tests.
 *
 * @since 1.0.0
 */
class Oli_DP_Utils {

	/**
	 * Parse a comma-separated list or array into a trimmed list of values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	public static function parse_list( $value ) {
		if ( is_array( $value ) ) {
			$items = $value;
		} else {
			$items = explode( ',', (string) $value );
		}

		$items = array_map( 'trim', $items );
		$items = array_filter(
			$items,
			static function ( $item ) {
				return '' !== $item;
			}
		);

		return array_values( $items );
	}

	/**
	 * Whether a value matches a wildcard pattern (* and ?).
	 *
	 * @since 1.0.0
	 *
	 * @param string $value   Value to test.
	 * @param string $pattern Wildcard pattern.
	 * @return bool
	 */
	public static function matches_pattern( $value, $pattern ) {
		$regex = '/^' . str_replace( array( '\*', '\?' ), array( '.*', '.' ), preg_quote( $pattern, '/' ) ) . '$/';

		return (bool) preg_match( $regex, $value );
	}

	/**
	 * Whether a meta key matches any excluded pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $meta_key      Meta key.
	 * @param string[] $excluded_keys Patterns (wildcards allowed).
	 * @return bool
	 */
	public static function should_exclude_meta( $meta_key, $excluded_keys ) {
		foreach ( (array) $excluded_keys as $pattern ) {
			if ( '' === $pattern ) {
				continue;
			}

			if ( self::matches_pattern( $meta_key, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the duplicated title.
	 *
	 * Rewrite copies keep the original title so the merge can preserve branding.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title  Original title.
	 * @param string $prefix Prefix.
	 * @param string $suffix Suffix.
	 * @param string $mode   Duplication mode.
	 * @return string
	 */
	public static function build_duplicate_title( $title, $prefix, $suffix, $mode ) {
		if ( 'rewrite_republish' === $mode ) {
			return $title;
		}

		return $prefix . $title . $suffix;
	}

	/**
	 * Internal meta keys that must never be copied.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function default_internal_meta_keys() {
		return array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_old_date',
			'_thumbnail_id',
			'_oli_dp_original_post',
			'_oli_dp_rewrite_copy',
		);
	}

	/**
	 * WooCommerce analytics / review meta that should not be cloned.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function woocommerce_excluded_meta_keys() {
		return array(
			'total_sales',
			'_wc_average_rating',
			'_wc_rating_count',
			'_wc_review_count',
		);
	}

	/**
	 * Whether the current admin screen may show this plugin's notices.
	 *
	 * Guideline 11: notices stay on the plugin settings page, comparison
	 * screen, or post list/edit screens that the current request is using.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context settings|compare|post_edit|post_list|any_plugin.
	 * @return bool
	 */
	public static function allows_admin_notice( $context = 'any_plugin' ) {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		switch ( $context ) {
			case 'settings':
				return 'settings_page_oli-duplicate-post-settings' === $screen->id;
			case 'compare':
				return 'admin_page_oli-dp-compare' === $screen->id;
			case 'post_edit':
				return 'post' === $screen->base;
			case 'post_list':
				return 'edit' === $screen->base;
			default:
				return (
					in_array( $screen->id, array( 'settings_page_oli-duplicate-post-settings', 'admin_page_oli-dp-compare' ), true )
					|| in_array( $screen->base, array( 'post', 'edit' ), true )
				);
		}
	}

	/**
	 * Whether an admin asset hook belongs to this plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	public static function should_enqueue_admin_assets( $hook ) {
		$allowed_hooks = array(
			'settings_page_oli-duplicate-post-settings',
			'admin_page_oli-dp-compare',
			'post.php',
			'post-new.php',
			'edit.php',
		);

		return in_array( $hook, $allowed_hooks, true );
	}
}
