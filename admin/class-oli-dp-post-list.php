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
		add_action( 'admin_init', array( __CLASS__, 'register_original_columns' ) );
	}

	/**
	 * Register the Original column on enabled list tables.
	 *
	 * @since 1.0.1
	 */
	public static function register_original_columns() {
		$settings           = Oli_DP_Settings::get_settings();
		$enabled_post_types = isset( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array();

		foreach ( (array) $enabled_post_types as $post_type ) {
			add_filter( 'manage_' . $post_type . '_posts_columns', array( __CLASS__, 'add_original_column' ) );
			add_action( 'manage_' . $post_type . '_posts_custom_column', array( __CLASS__, 'render_original_column' ), 10, 2 );
		}
	}

	/**
	 * Add the Original column.
	 *
	 * @since 1.0.1
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_original_column( $columns ) {
		$columns['oli_dp_original'] = __( 'Original', 'oli-duplicate-post' );
		return $columns;
	}

	/**
	 * Render a link to the source post stored in `_dp_original`.
	 *
	 * @since 1.0.1
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_original_column( $column, $post_id ) {
		if ( 'oli_dp_original' !== $column ) {
			return;
		}

		$original_id = Oli_DP_Utils::get_original_post_id( $post_id );
		if ( ! $original_id ) {
			echo '&mdash;';
			return;
		}

		$original = get_post( $original_id );
		if ( ! $original ) {
			echo '&mdash;';
			return;
		}

		$edit_link = get_edit_post_link( $original_id );
		if ( $edit_link ) {
			printf(
				'<a href="%s">%s</a>',
				esc_url( $edit_link ),
				esc_html( get_the_title( $original ) )
			);
			return;
		}

		echo esc_html( get_the_title( $original ) );
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
