<?php
/**
 * Post list row actions.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clone, New Draft, and Rewrite links on list tables.
 *
 * @since 1.0.0
 */
class Oli_DP_Post_List {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_filter( 'post_row_actions', array( __CLASS__, 'add_row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'add_row_actions' ), 10, 2 );
	}

	/**
	 * Add duplicate row actions.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Post object.
	 * @return array Modified row actions.
	 */
	public static function add_row_actions( $actions, $post ) {
		$settings = Oli_DP_Settings::get_settings();

		if ( empty( $settings['show_row_actions'] ) ) {
			return $actions;
		}

		if ( ! Oli_DP_Permissions::can_duplicate( $post->ID ) ) {
			return $actions;
		}

		$duplicate_actions = array();

		$clone_url                         = Oli_DP_Hooks::get_duplicate_url( $post->ID, 'clone' );
		$duplicate_actions['oli_dp_clone'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $clone_url ),
			/* translators: %s: post title */
			esc_attr( sprintf( __( 'Clone &#8220;%s&#8221;', 'oli-duplicate-post' ), $post->post_title ) ),
			esc_html__( 'Clone', 'oli-duplicate-post' )
		);

		$new_draft_url                         = Oli_DP_Hooks::get_duplicate_url( $post->ID, 'new_draft' );
		$duplicate_actions['oli_dp_new_draft'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $new_draft_url ),
			/* translators: %s: post title */
			esc_attr( sprintf( __( 'New Draft of &#8220;%s&#8221;', 'oli-duplicate-post' ), $post->post_title ) ),
			esc_html__( 'New Draft', 'oli-duplicate-post' )
		);

		if ( 'publish' === $post->post_status ) {
			$existing_rewrite = absint( get_post_meta( $post->ID, '_oli_dp_rewrite_copy', true ) );

			if ( ! $existing_rewrite || ! get_post( $existing_rewrite ) ) {
				if ( Oli_DP_Permissions::can_rewrite( $post->ID ) ) {
					$rewrite_url                         = Oli_DP_Hooks::get_duplicate_url( $post->ID, 'rewrite_republish' );
					$duplicate_actions['oli_dp_rewrite'] = sprintf(
						'<a href="%s" aria-label="%s">%s</a>',
						esc_url( $rewrite_url ),
						/* translators: %s: post title */
						esc_attr( sprintf( __( 'Rewrite & Republish &#8220;%s&#8221;', 'oli-duplicate-post' ), $post->post_title ) ),
						esc_html__( 'Rewrite & Republish', 'oli-duplicate-post' )
					);
				}
			} else {
				$edit_url                            = get_edit_post_link( $existing_rewrite );
				$duplicate_actions['oli_dp_rewrite'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					esc_url( $edit_url ),
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Edit Rewrite Copy of &#8220;%s&#8221;', 'oli-duplicate-post' ), $post->post_title ) ),
					esc_html__( 'Edit Rewrite Copy', 'oli-duplicate-post' )
				);
			}
		}

		if ( isset( $actions['edit'] ) ) {
			$offset  = array_search( 'edit', array_keys( $actions ), true );
			$actions = array_slice( $actions, 0, $offset + 1, true ) +
						$duplicate_actions +
						array_slice( $actions, $offset + 1, null, true );
		} else {
			$actions = $duplicate_actions + $actions;
		}

		return $actions;
	}
}
