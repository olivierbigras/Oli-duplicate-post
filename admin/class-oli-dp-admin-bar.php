<?php
/**
 * Admin bar duplicate links.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Duplicate This menu in the admin bar (admin and front-end singular views).
 *
 * @since 1.0.0
 */
class Oli_DP_Admin_Bar {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_items' ), 100 );
	}

	/**
	 * Add admin bar items.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
	 */
	public static function add_admin_bar_items( $wp_admin_bar ) {
		$settings = Oli_DP_Settings::get_settings();

		if ( empty( $settings['show_admin_bar'] ) ) {
			return;
		}

		$post = self::get_current_post();
		if ( ! $post || ! Oli_DP_Permissions::can_duplicate( $post->ID ) ) {
			return;
		}

		$wp_admin_bar->add_menu(
			array(
				'id'    => 'oli_dp_duplicate',
				'title' => __( 'Duplicate This', 'oli-duplicate-post' ),
				'href'  => Oli_DP_Hooks::get_duplicate_url( $post->ID, 'new_draft' ),
			)
		);

		$wp_admin_bar->add_menu(
			array(
				'parent' => 'oli_dp_duplicate',
				'id'     => 'oli_dp_clone',
				'title'  => __( 'Clone', 'oli-duplicate-post' ),
				'href'   => Oli_DP_Hooks::get_duplicate_url( $post->ID, 'clone' ),
			)
		);

		$wp_admin_bar->add_menu(
			array(
				'parent' => 'oli_dp_duplicate',
				'id'     => 'oli_dp_new_draft',
				'title'  => __( 'New Draft', 'oli-duplicate-post' ),
				'href'   => Oli_DP_Hooks::get_duplicate_url( $post->ID, 'new_draft' ),
			)
		);

		if ( 'publish' === $post->post_status ) {
			$existing_rewrite = absint( get_post_meta( $post->ID, '_oli_dp_rewrite_copy', true ) );

			if ( $existing_rewrite && get_post( $existing_rewrite ) ) {
				$wp_admin_bar->add_menu(
					array(
						'parent' => 'oli_dp_duplicate',
						'id'     => 'oli_dp_rewrite',
						'title'  => __( 'Edit Rewrite Copy', 'oli-duplicate-post' ),
						'href'   => get_edit_post_link( $existing_rewrite ),
					)
				);
			} elseif ( Oli_DP_Permissions::can_rewrite( $post->ID ) ) {
				$wp_admin_bar->add_menu(
					array(
						'parent' => 'oli_dp_duplicate',
						'id'     => 'oli_dp_rewrite',
						'title'  => __( 'Rewrite & Republish', 'oli-duplicate-post' ),
						'href'   => Oli_DP_Hooks::get_duplicate_url( $post->ID, 'rewrite_republish' ),
					)
				);
			}
		}
	}

	/**
	 * Resolve the post shown in the admin bar.
	 *
	 * List tables (`edit.php`) have no current post. `get_post()` there
	 * returns an arbitrary row, so Duplicate This is limited to the single
	 * edit screen (`post.php`) and singular front-end views.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Post|null
	 */
	private static function get_current_post() {
		if ( is_admin() ) {
			if ( ! self::is_single_edit_screen() ) {
				return null;
			}

			if ( ! isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return null;
			}

			$post = get_post( absint( wp_unslash( $_GET['post'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $post instanceof WP_Post ? $post : null;
		}

		if ( is_singular() ) {
			$queried = get_queried_object();
			return $queried instanceof WP_Post ? $queried : null;
		}

		return null;
	}

	/**
	 * Whether the request is a single post edit screen.
	 *
	 * @since 1.0.1
	 *
	 * @return bool
	 */
	private static function is_single_edit_screen() {
		global $pagenow;

		return isset( $pagenow ) && 'post.php' === $pagenow;
	}
}
