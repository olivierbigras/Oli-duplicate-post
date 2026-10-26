<?php
/**
 * Bulk actions for duplicating posts.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk Clone and New Draft on list tables.
 *
 * @since 1.0.0
 */
class Oli_DP_Bulk_Actions {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_bulk_actions' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_bulk_action_notice' ) );
	}

	/**
	 * Register bulk actions for every enabled post type.
	 *
	 * @since 1.0.0
	 */
	public static function register_bulk_actions() {
		$settings           = Oli_DP_Settings::get_settings();
		$enabled_post_types = isset( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array();

		foreach ( (array) $enabled_post_types as $post_type ) {
			add_filter( 'bulk_actions-edit-' . $post_type, array( __CLASS__, 'add_bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
		}
	}

	/**
	 * Add bulk actions to post list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public static function add_bulk_actions( $actions ) {
		$settings = Oli_DP_Settings::get_settings();

		if ( empty( $settings['show_bulk_actions'] ) ) {
			return $actions;
		}

		$actions['oli_dp_clone']     = __( 'Clone', 'oli-duplicate-post' );
		$actions['oli_dp_new_draft'] = __( 'New Draft', 'oli-duplicate-post' );

		return $actions;
	}

	/**
	 * Handle bulk action.
	 *
	 * @since 1.0.0
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action name.
	 * @param array  $post_ids    Array of post IDs.
	 * @return string Modified redirect URL.
	 */
	public static function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( ! in_array( $action, array( 'oli_dp_clone', 'oli_dp_new_draft' ), true ) ) {
			return $redirect_to;
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
			return $redirect_to;
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bulk-posts' ) ) {
			return $redirect_to;
		}

		$mode             = ( 'oli_dp_clone' === $action ) ? 'clone' : 'new_draft';
		$duplicated_count = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id || ! Oli_DP_Permissions::can_duplicate( $post_id ) ) {
				continue;
			}

			$new_post_id = Oli_DP_Duplicator::duplicate( $post_id, $mode );

			if ( ! is_wp_error( $new_post_id ) ) {
				++$duplicated_count;
			}
		}

		return add_query_arg( 'oli_dp_bulk_duplicated', $duplicated_count, $redirect_to );
	}

	/**
	 * Show bulk action notice on the list table only.
	 *
	 * @since 1.0.0
	 */
	public static function show_bulk_action_notice() {
		if ( ! isset( $_REQUEST['oli_dp_bulk_duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! Oli_DP_Utils::allows_admin_notice( 'post_list' ) ) {
			return;
		}

		$count = absint( wp_unslash( $_REQUEST['oli_dp_bulk_duplicated'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $count > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of posts duplicated */
						_n( '%d post duplicated.', '%d posts duplicated.', $count, 'oli-duplicate-post' ),
						$count
					)
				)
			);
		}
	}
}
