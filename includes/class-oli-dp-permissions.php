<?php
/**
 * Permissions and capability checks.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability and role checks for duplication.
 *
 * @since 1.0.0
 */
class Oli_DP_Permissions {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Reserved for future capability registration.
	}

	/**
	 * Check if current user can duplicate a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if user can duplicate, false otherwise.
	 */
	public static function can_duplicate( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return false;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object ) {
			return false;
		}

		if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
			return false;
		}

		$settings           = Oli_DP_Settings::get_settings();
		$enabled_post_types = isset( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array();

		/**
		 * Filter post types that can be duplicated.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $enabled_post_types Enabled post type names.
		 * @param array    $settings           Plugin settings.
		 */
		$enabled_post_types = apply_filters( 'oli_dp_enabled_post_types', $enabled_post_types, $settings );

		if ( ! in_array( $post->post_type, (array) $enabled_post_types, true ) ) {
			return apply_filters( 'oli_dp_can_duplicate', false, $post_id, $post );
		}

		$allowed_roles = isset( $settings['allowed_roles'] ) ? $settings['allowed_roles'] : array();

		/**
		 * Filter roles allowed to duplicate posts.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $allowed_roles Allowed role slugs.
		 * @param array    $settings      Plugin settings.
		 */
		$allowed_roles = apply_filters( 'oli_dp_allowed_roles', $allowed_roles, $settings );

		if ( ! empty( $allowed_roles ) ) {
			$user             = wp_get_current_user();
			$user_roles       = (array) $user->roles;
			$has_allowed_role = false;

			foreach ( $user_roles as $role ) {
				if ( in_array( $role, (array) $allowed_roles, true ) ) {
					$has_allowed_role = true;
					break;
				}
			}

			if ( ! $has_allowed_role ) {
				return apply_filters( 'oli_dp_can_duplicate', false, $post_id, $post );
			}
		}

		$can = true;

		/**
		 * Filter whether the current user can duplicate a post.
		 *
		 * @since 1.0.0
		 *
		 * @param bool    $can     Whether duplication is allowed.
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 */
		return (bool) apply_filters( 'oli_dp_can_duplicate', $can, $post_id, $post );
	}

	/**
	 * Whether a post can start a Rewrite & Republish copy.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function can_rewrite( $post_id ) {
		if ( ! self::can_duplicate( $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return false;
		}

		if ( get_post_meta( $post_id, '_oli_dp_original_post', true ) ) {
			return false;
		}

		$existing = absint( get_post_meta( $post_id, '_oli_dp_rewrite_copy', true ) );
		if ( $existing && get_post( $existing ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Check if current user can access settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user can access settings, false otherwise.
	 */
	public static function can_manage_settings() {
		return current_user_can( 'manage_options' );
	}
}
