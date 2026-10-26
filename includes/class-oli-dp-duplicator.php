<?php
/**
 * Core duplication logic.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Duplicates posts, pages, and custom post types.
 *
 * @since 1.0.0
 */
class Oli_DP_Duplicator {

	/**
	 * Duplicate a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id The post ID to duplicate.
	 * @param string $mode    The duplication mode: 'clone', 'new_draft', or 'rewrite_republish'.
	 * @param array  $args    Optional arguments to override default behavior.
	 * @return int|WP_Error The new post ID on success, WP_Error on failure.
	 */
	public static function duplicate( $post_id, $mode = 'clone', $args = array() ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'oli-duplicate-post' ) );
		}

		$internal = ! empty( $args['oli_dp_internal'] );
		if ( ! $internal && ! Oli_DP_Permissions::can_duplicate( $post_id ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have permission to duplicate this post.', 'oli-duplicate-post' ) );
		}

		if ( 'rewrite_republish' === $mode && ! $internal && ! Oli_DP_Permissions::can_rewrite( $post_id ) ) {
			return new WP_Error( 'rewrite_denied', __( 'This post cannot be rewritten and republished.', 'oli-duplicate-post' ) );
		}

		$settings = wp_parse_args( $args, Oli_DP_Settings::get_settings() );

		/**
		 * Fires before a post is duplicated.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Post $post     Original post.
		 * @param string  $mode     Duplication mode.
		 * @param array   $settings Settings used for this duplicate.
		 */
		do_action( 'oli_dp_before_duplicate', $post, $mode, $settings );

		$new_post_data = self::prepare_post_data( $post, $mode, $settings );

		/**
		 * Filter the post data before duplication.
		 *
		 * @since 1.0.0
		 *
		 * @param array   $new_post_data The new post data.
		 * @param WP_Post $post          The original post object.
		 * @param string  $mode          The duplication mode.
		 * @param array   $settings      The settings array.
		 */
		$new_post_data = apply_filters( 'oli_dp_pre_duplicate_post_data', $new_post_data, $post, $mode, $settings );

		$new_post_id = wp_insert_post( $new_post_data, true );

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		self::copy_post_meta( $post_id, $new_post_id, $settings );

		self::copy_taxonomies( $post_id, $new_post_id, $settings );

		if ( ! empty( $settings['copy_featured_image'] ) ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( $thumbnail_id ) {
				update_post_meta( $new_post_id, '_thumbnail_id', $thumbnail_id );
			}
		}

		if ( ! empty( $settings['copy_attachments'] ) ) {
			self::copy_attachments( $post_id, $new_post_id );
		}

		if ( ! empty( $settings['copy_children'] ) ) {
			self::copy_children( $post_id, $new_post_id );
		}

		if ( ! empty( $settings['copy_comments'] ) ) {
			self::copy_comments( $post_id, $new_post_id );
		}

		update_post_meta( $new_post_id, '_dp_original', $post_id );

		if ( 'rewrite_republish' === $mode ) {
			update_post_meta( $new_post_id, '_oli_dp_original_post', $post_id );
			update_post_meta( $post_id, '_oli_dp_rewrite_copy', $new_post_id );
		}

		Oli_DP_WooCommerce::after_duplicate( $post_id, $new_post_id, $mode, $settings );

		/**
		 * Fires after a post is duplicated.
		 *
		 * @since 1.0.0
		 *
		 * @param int     $new_post_id The new post ID.
		 * @param WP_Post $post        The original post object.
		 * @param string  $mode        The duplication mode.
		 * @param array   $settings    The settings array.
		 */
		do_action( 'oli_dp_post_duplicated', $new_post_id, $post, $mode, $settings );

		return $new_post_id;
	}

	/**
	 * Prepare post data for duplication.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post     The original post.
	 * @param string  $mode     The duplication mode.
	 * @param array   $settings The settings array.
	 * @return array The prepared post data.
	 */
	private static function prepare_post_data( $post, $mode, $settings ) {
		$current_user = wp_get_current_user();

		$new_post_data = array(
			'post_type'      => $post->post_type,
			'post_status'    => 'draft',
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_parent'    => $post->post_parent,
		);

		if ( ! empty( $settings['copy_title'] ) ) {
			$prefix = isset( $settings['title_prefix'] ) ? $settings['title_prefix'] : '';
			$suffix = isset( $settings['title_suffix'] ) ? $settings['title_suffix'] : '';

			/**
			 * Filter the duplicated post title.
			 *
			 * @since 1.0.0
			 *
			 * @param string  $title    Built title.
			 * @param WP_Post $post     Original post.
			 * @param string  $mode     Duplication mode.
			 * @param array   $settings Settings array.
			 */
			$new_post_data['post_title'] = apply_filters(
				'oli_dp_duplicate_title',
				Oli_DP_Utils::build_duplicate_title( $post->post_title, $prefix, $suffix, $mode ),
				$post,
				$mode,
				$settings
			);
		} else {
			$new_post_data['post_title'] = '';
		}

		if ( ! empty( $settings['copy_content'] ) ) {
			$new_post_data['post_content'] = $post->post_content;
		}

		if ( ! empty( $settings['copy_excerpt'] ) ) {
			$new_post_data['post_excerpt'] = $post->post_excerpt;
		}

		if ( ! empty( $settings['copy_date'] ) && 'rewrite_republish' !== $mode ) {
			$new_post_data['post_date']     = $post->post_date;
			$new_post_data['post_date_gmt'] = $post->post_date_gmt;
		}

		if ( ! empty( $settings['copy_status'] ) && 'clone' === $mode ) {
			$new_post_data['post_status'] = $post->post_status;
		}

		if ( 'product_variation' === $post->post_type ) {
			$new_post_data['post_status'] = 'publish';
		}

		if ( ! empty( $settings['copy_slug'] ) && 'rewrite_republish' !== $mode ) {
			$new_post_data['post_name'] = $post->post_name;
		} else {
			$new_post_data['post_name'] = '';
		}

		if ( ! empty( $settings['copy_author'] ) ) {
			$new_post_data['post_author'] = $post->post_author;
		} else {
			$new_post_data['post_author'] = $current_user->ID;
		}

		if ( ! empty( $settings['copy_password'] ) ) {
			$new_post_data['post_password'] = $post->post_password;
		}

		if ( ! empty( $settings['copy_menu_order'] ) ) {
			$new_post_data['menu_order'] = $post->menu_order;
		}

		return $new_post_data;
	}

	/**
	 * Copy post meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $original_post_id The original post ID.
	 * @param int   $new_post_id      The new post ID.
	 * @param array $settings         The settings array.
	 */
	private static function copy_post_meta( $original_post_id, $new_post_id, $settings ) {
		$post_meta = get_post_meta( $original_post_id );

		$excluded_keys = array_merge(
			Oli_DP_Utils::parse_list( isset( $settings['excluded_meta'] ) ? $settings['excluded_meta'] : '' ),
			Oli_DP_Utils::default_internal_meta_keys()
		);

		if ( Oli_DP_WooCommerce::is_product( get_post( $original_post_id ) ) ) {
			$excluded_keys = array_merge( $excluded_keys, Oli_DP_WooCommerce::excluded_meta_keys() );
		}

		/**
		 * Filter meta keys excluded from a duplicate.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $excluded_keys     Keys and wildcard patterns.
		 * @param int      $original_post_id  Original post ID.
		 * @param int      $new_post_id       New post ID.
		 * @param array    $settings          Settings array.
		 */
		$excluded_keys = apply_filters( 'oli_dp_excluded_meta_keys', $excluded_keys, $original_post_id, $new_post_id, $settings );

		foreach ( $post_meta as $meta_key => $meta_values ) {
			if ( Oli_DP_Utils::should_exclude_meta( $meta_key, $excluded_keys ) ) {
				continue;
			}

			foreach ( $meta_values as $meta_value ) {
				$meta_value = maybe_unserialize( $meta_value );

				/**
				 * Filter a copied meta value.
				 *
				 * @since 1.0.0
				 *
				 * @param mixed  $meta_value        Meta value.
				 * @param string $meta_key          Meta key.
				 * @param int    $original_post_id  Original post ID.
				 * @param int    $new_post_id       New post ID.
				 */
				$meta_value = apply_filters( 'oli_dp_copied_meta_value', $meta_value, $meta_key, $original_post_id, $new_post_id );
				add_post_meta( $new_post_id, $meta_key, $meta_value );
			}
		}

		if ( ! empty( $settings['copy_template'] ) ) {
			$template = get_page_template_slug( $original_post_id );
			if ( $template ) {
				update_post_meta( $new_post_id, '_wp_page_template', $template );
			}
		}

		if ( ! empty( $settings['copy_format'] ) ) {
			$format = get_post_format( $original_post_id );
			if ( $format ) {
				set_post_format( $new_post_id, $format );
			}
		}

		/**
		 * Fires after meta is copied to a duplicate.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $original_post_id Original post ID.
		 * @param int   $new_post_id      New post ID.
		 * @param array $settings         Settings array.
		 */
		do_action( 'oli_dp_after_copy_meta', $original_post_id, $new_post_id, $settings );
	}

	/**
	 * Copy taxonomies.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $original_post_id The original post ID.
	 * @param int   $new_post_id      The new post ID.
	 * @param array $settings         The settings array.
	 */
	private static function copy_taxonomies( $original_post_id, $new_post_id, $settings ) {
		$post       = get_post( $original_post_id );
		$taxonomies = get_object_taxonomies( $post->post_type );

		$excluded_taxonomies = Oli_DP_Utils::parse_list( isset( $settings['excluded_taxonomies'] ) ? $settings['excluded_taxonomies'] : '' );

		/**
		 * Filter taxonomies excluded from a duplicate.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $excluded_taxonomies Taxonomy names.
		 * @param int      $original_post_id    Original post ID.
		 * @param int      $new_post_id         New post ID.
		 * @param array    $settings            Settings array.
		 */
		$excluded_taxonomies = apply_filters( 'oli_dp_excluded_taxonomies', $excluded_taxonomies, $original_post_id, $new_post_id, $settings );

		foreach ( $taxonomies as $taxonomy ) {
			if ( in_array( $taxonomy, $excluded_taxonomies, true ) ) {
				continue;
			}

			$terms = wp_get_object_terms( $original_post_id, $taxonomy, array( 'fields' => 'ids' ) );

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_post_id, $terms, $taxonomy );
			}
		}

		/**
		 * Fires after taxonomies are copied to a duplicate.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $original_post_id Original post ID.
		 * @param int   $new_post_id      New post ID.
		 * @param array $settings         Settings array.
		 */
		do_action( 'oli_dp_after_copy_taxonomies', $original_post_id, $new_post_id, $settings );
	}

	/**
	 * Copy attachments as real attachment posts pointing at the same files.
	 *
	 * The physical file is shared with the original (Yoast-style). The new
	 * row is `post_type=attachment` with `_wp_attached_file` and metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param int $original_post_id The original post ID.
	 * @param int $new_post_id      The new post ID.
	 */
	private static function copy_attachments( $original_post_id, $new_post_id ) {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'post_parent'    => $original_post_id,
				'post_status'    => 'any',
			)
		);

		foreach ( $attachments as $attachment ) {
			$file     = get_post_meta( $attachment->ID, '_wp_attached_file', true );
			$absolute = function_exists( 'get_attached_file' ) ? get_attached_file( $attachment->ID ) : '';
			if ( ! $absolute ) {
				$absolute = $file;
			}

			$new_attachment = array(
				'post_title'     => $attachment->post_title,
				'post_content'   => $attachment->post_content,
				'post_excerpt'   => $attachment->post_excerpt,
				'post_status'    => 'inherit',
				'post_mime_type' => $attachment->post_mime_type,
				'guid'           => $attachment->guid,
				'post_author'    => $attachment->post_author,
				'menu_order'     => $attachment->menu_order,
			);

			if ( function_exists( 'wp_insert_attachment' ) ) {
				$new_attachment_id = wp_insert_attachment( $new_attachment, $absolute, $new_post_id, true );
			} else {
				$new_attachment['post_type']   = 'attachment';
				$new_attachment['post_parent'] = $new_post_id;
				$new_attachment_id             = wp_insert_post( $new_attachment, true );
			}

			if ( is_wp_error( $new_attachment_id ) || ! $new_attachment_id ) {
				continue;
			}

			$excluded_keys = array_merge(
				Oli_DP_Utils::default_internal_meta_keys(),
				array( '_wp_attached_file' )
			);

			$attachment_meta = get_post_meta( $attachment->ID );
			foreach ( $attachment_meta as $meta_key => $meta_values ) {
				if ( Oli_DP_Utils::should_exclude_meta( $meta_key, $excluded_keys ) ) {
					continue;
				}

				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $new_attachment_id, $meta_key, maybe_unserialize( $meta_value ) );
				}
			}

			if ( $file && ! get_post_meta( $new_attachment_id, '_wp_attached_file', true ) ) {
				update_post_meta( $new_attachment_id, '_wp_attached_file', $file );
			}

			update_post_meta( $new_attachment_id, '_dp_original', $attachment->ID );
		}
	}

	/**
	 * Copy child posts, excluding attachments and product variations.
	 *
	 * @since 1.0.0
	 *
	 * @param int $original_post_id The original post ID.
	 * @param int $new_post_id      The new post ID.
	 */
	private static function copy_children( $original_post_id, $new_post_id ) {
		$children = get_posts(
			array(
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'post_parent'    => $original_post_id,
				'post_status'    => 'any',
			)
		);

		foreach ( $children as $child ) {
			if ( in_array( $child->post_type, array( 'attachment', 'product_variation', 'revision' ), true ) ) {
				continue;
			}

			$settings                    = Oli_DP_Settings::get_settings();
			$settings['copy_children']   = false;
			$settings['oli_dp_internal'] = true;

			$new_child_id = self::duplicate( $child->ID, 'clone', $settings );

			if ( ! is_wp_error( $new_child_id ) ) {
				wp_update_post(
					array(
						'ID'          => $new_child_id,
						'post_parent' => $new_post_id,
					)
				);
			}
		}
	}

	/**
	 * Copy comments and preserve parent relationships.
	 *
	 * @since 1.0.0
	 *
	 * @param int $original_post_id The original post ID.
	 * @param int $new_post_id      The new post ID.
	 */
	private static function copy_comments( $original_post_id, $new_post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $original_post_id,
				'status'  => 'all',
			)
		);

		$comment_map = array();

		foreach ( $comments as $comment ) {
			$new_comment_data = array(
				'comment_post_ID'      => $new_post_id,
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_content'      => $comment->comment_content,
				'comment_type'         => $comment->comment_type,
				'comment_parent'       => 0,
				'user_id'              => $comment->user_id,
				'comment_date'         => $comment->comment_date,
				'comment_approved'     => $comment->comment_approved,
				'comment_agent'        => $comment->comment_agent,
				'comment_author_IP'    => $comment->comment_author_IP,
			);

			$new_comment_id = wp_insert_comment( $new_comment_data );

			if ( $new_comment_id ) {
				$comment_map[ $comment->comment_ID ] = $new_comment_id;

				$comment_meta = get_comment_meta( $comment->comment_ID );
				foreach ( $comment_meta as $meta_key => $meta_values ) {
					foreach ( $meta_values as $meta_value ) {
						add_comment_meta( $new_comment_id, $meta_key, maybe_unserialize( $meta_value ) );
					}
				}
			}
		}

		foreach ( $comment_map as $old_id => $new_id ) {
			$comment = get_comment( $old_id );
			if ( $comment && $comment->comment_parent && isset( $comment_map[ $comment->comment_parent ] ) ) {
				wp_update_comment(
					array(
						'comment_ID'     => $new_id,
						'comment_parent' => $comment_map[ $comment->comment_parent ],
					)
				);
			}
		}
	}
}
