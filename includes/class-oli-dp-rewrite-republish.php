<?php
/**
 * Rewrite & Republish functionality.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates a working copy and merges it back into the original on publish.
 *
 * Scheduled copies stay as `future` until the scheduled time; only a
 * transition to `publish` merges the copy back.
 *
 * @since 1.0.0
 */
class Oli_DP_Rewrite_Republish {

	/**
	 * Whether a merge is already running.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $merging = false;

	/**
	 * Original post to redirect to after a classic-editor publish.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private static $redirect_original_id = 0;

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'handle_publish' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'show_rewrite_notice' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'add_post_state' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_schedule_meta_box' ) );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'add_publish_box_info' ) );
		add_filter( 'redirect_post_location', array( __CLASS__, 'redirect_after_merge' ) );
	}

	/**
	 * Handle post publish for rewrite & republish.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function handle_publish( $new_status, $old_status, $post ) {
		unset( $old_status );

		if ( self::$merging || ! $post instanceof WP_Post ) {
			return;
		}

		// Scheduled republish waits until the copy actually publishes.
		if ( 'publish' !== $new_status ) {
			return;
		}

		$original_post_id = absint( get_post_meta( $post->ID, '_oli_dp_original_post', true ) );
		if ( ! $original_post_id ) {
			return;
		}

		if ( is_user_logged_in() && ( ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( 'edit_post', $original_post_id ) ) ) {
			return;
		}

		if ( isset( $_POST['oli_dp_rewrite_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nonce = sanitize_text_field( wp_unslash( $_POST['oli_dp_rewrite_nonce'] ) );
			if ( ! wp_verify_nonce( $nonce, 'oli_dp_rewrite_publish' ) ) {
				return;
			}
		}

		self::$merging = true;
		$merged        = self::merge_to_original( $post->ID, $original_post_id );
		self::$merging = false;

		if ( ! $merged ) {
			return;
		}

		if ( is_user_logged_in() ) {
			set_transient( 'oli_dp_republish_success_' . get_current_user_id(), $original_post_id, 60 );
		}

		if ( is_admin() && ! wp_doing_cron() && ! wp_doing_ajax() && ! self::is_rest_request() ) {
			self::$redirect_original_id = $original_post_id;
		}
	}

	/**
	 * Merge rewrite copy to original post.
	 *
	 * The original permalink and publish date are preserved so the live URL
	 * does not change. The working copy is trashed after a successful merge.
	 *
	 * @since 1.0.0
	 *
	 * @param int $rewrite_post_id  The rewrite copy post ID.
	 * @param int $original_post_id The original post ID.
	 * @return bool True on success, false on failure.
	 */
	public static function merge_to_original( $rewrite_post_id, $original_post_id ) {
		$rewrite_post  = get_post( $rewrite_post_id );
		$original_post = get_post( $original_post_id );

		if ( ! $rewrite_post || ! $original_post ) {
			return false;
		}

		/**
		 * Fires before a rewrite copy is merged into the original.
		 *
		 * @since 1.0.0
		 *
		 * @param int $original_post_id Original post ID.
		 * @param int $rewrite_post_id  Rewrite copy ID.
		 */
		do_action( 'oli_dp_before_rewrite_merge', $original_post_id, $rewrite_post_id );

		$update_data = array(
			'ID'            => $original_post_id,
			'post_title'    => $rewrite_post->post_title,
			'post_content'  => $rewrite_post->post_content,
			'post_excerpt'  => $rewrite_post->post_excerpt,
			'post_name'     => $original_post->post_name,
			'post_date'     => $original_post->post_date,
			'post_date_gmt' => $original_post->post_date_gmt,
			'post_author'   => $rewrite_post->post_author,
			'post_password' => $rewrite_post->post_password,
			'menu_order'    => $rewrite_post->menu_order,
		);

		/**
		 * Filter post data written to the original during a rewrite merge.
		 *
		 * @since 1.0.0
		 *
		 * @param array   $update_data    Data passed to wp_update_post().
		 * @param WP_Post $rewrite_post   Rewrite copy.
		 * @param WP_Post $original_post  Original post.
		 */
		$update_data = apply_filters( 'oli_dp_rewrite_merge_post_data', $update_data, $rewrite_post, $original_post );

		$result = wp_update_post( $update_data, true );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		$settings = Oli_DP_Settings::get_settings();

		$excluded_keys = array_merge(
			Oli_DP_Utils::parse_list( isset( $settings['excluded_meta'] ) ? $settings['excluded_meta'] : '' ),
			Oli_DP_Utils::default_internal_meta_keys()
		);

		/**
		 * Filter meta keys skipped when merging a rewrite copy.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $excluded_keys     Keys and wildcard patterns.
		 * @param int      $original_post_id  Original post ID.
		 * @param int      $rewrite_post_id   Rewrite copy ID.
		 */
		$excluded_keys = apply_filters( 'oli_dp_rewrite_excluded_meta_keys', $excluded_keys, $original_post_id, $rewrite_post_id );

		$rewrite_meta = get_post_meta( $rewrite_post_id );
		foreach ( $rewrite_meta as $meta_key => $meta_values ) {
			if ( Oli_DP_Utils::should_exclude_meta( $meta_key, $excluded_keys ) ) {
				continue;
			}

			delete_post_meta( $original_post_id, $meta_key );

			foreach ( $meta_values as $meta_value ) {
				add_post_meta( $original_post_id, $meta_key, maybe_unserialize( $meta_value ) );
			}
		}

		$taxonomies = get_object_taxonomies( $original_post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $rewrite_post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $original_post_id, $terms, $taxonomy );
			}
		}

		$thumbnail_id = get_post_thumbnail_id( $rewrite_post_id );
		if ( $thumbnail_id ) {
			update_post_meta( $original_post_id, '_thumbnail_id', $thumbnail_id );
		} else {
			delete_post_meta( $original_post_id, '_thumbnail_id' );
		}

		$template = get_page_template_slug( $rewrite_post_id );
		if ( $template ) {
			update_post_meta( $original_post_id, '_wp_page_template', $template );
		} else {
			delete_post_meta( $original_post_id, '_wp_page_template' );
		}

		$format = get_post_format( $rewrite_post_id );
		if ( $format ) {
			set_post_format( $original_post_id, $format );
		} else {
			set_post_format( $original_post_id, false );
		}

		delete_post_meta( $original_post_id, '_oli_dp_rewrite_copy' );
		delete_post_meta( $rewrite_post_id, '_oli_dp_original_post' );

		wp_trash_post( $rewrite_post_id );

		/**
		 * Fires after a rewrite copy is merged back to the original.
		 *
		 * @since 1.0.0
		 *
		 * @param int $original_post_id The original post ID.
		 * @param int $rewrite_post_id  The rewrite copy post ID.
		 */
		do_action( 'oli_dp_rewrite_republish_merged', $original_post_id, $rewrite_post_id );

		return true;
	}

	/**
	 * Show admin notice for rewrite copies and successful republish.
	 *
	 * @since 1.0.0
	 */
	public static function show_rewrite_notice() {
		if ( Oli_DP_Utils::allows_admin_notice( 'post_edit' ) || Oli_DP_Utils::allows_admin_notice( 'post_list' ) ) {
			self::render_success_notice();
		}

		if ( ! Oli_DP_Utils::allows_admin_notice( 'post_edit' ) ) {
			return;
		}

		$post_id = self::get_requested_post_id();
		if ( ! $post_id ) {
			return;
		}

		$original_post_id = absint( get_post_meta( $post_id, '_oli_dp_original_post', true ) );
		if ( ! $original_post_id ) {
			return;
		}

		$original_post = get_post( $original_post_id );
		if ( ! $original_post ) {
			return;
		}

		$edit_link    = get_edit_post_link( $original_post_id );
		$view_link    = get_permalink( $original_post_id );
		$compare_link = wp_nonce_url(
			add_query_arg(
				array(
					'page'     => 'oli-dp-compare',
					'original' => $original_post_id,
					'rewrite'  => $post_id,
				),
				admin_url( 'admin.php' )
			),
			'oli_dp_compare_' . $original_post_id . '_' . $post_id
		);

		?>
		<div class="notice notice-info">
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: 1: Original post title, 2: Edit link, 3: View link, 4: Compare link */
						__( 'This is a <strong>Rewrite &amp; Republish</strong> copy of "%1$s". When you publish this post, it will replace the original. <a href="%2$s">Edit original</a> | <a href="%3$s" target="_blank" rel="noopener noreferrer">View original</a> | <a href="%4$s">Compare changes</a>', 'oli-duplicate-post' ),
						array(
							'strong' => array(),
							'a'      => array(
								'href'   => array(),
								'target' => array(),
								'rel'    => array(),
							),
						)
					),
					esc_html( $original_post->post_title ),
					esc_url( $edit_link ),
					esc_url( $view_link ),
					esc_url( $compare_link )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the republish success notice when a transient is present.
	 *
	 * @since 1.0.0
	 */
	private static function render_success_notice() {
		$success_post_id = get_transient( 'oli_dp_republish_success_' . get_current_user_id() );
		if ( ! $success_post_id ) {
			return;
		}

		delete_transient( 'oli_dp_republish_success_' . get_current_user_id() );
		$post = get_post( $success_post_id );
		if ( ! $post ) {
			return;
		}

		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: post title */
					esc_html__( 'Successfully republished "%s" with your changes.', 'oli-duplicate-post' ),
					esc_html( $post->post_title )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add post state for rewrite copies.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $post_states Array of post states.
	 * @param WP_Post $post        Post object.
	 * @return array Modified post states.
	 */
	public static function add_post_state( $post_states, $post ) {
		$original_post_id = get_post_meta( $post->ID, '_oli_dp_original_post', true );

		if ( $original_post_id ) {
			$post_states['oli_dp_rewrite'] = __( 'Rewrite & Republish', 'oli-duplicate-post' );
		}

		$rewrite_copy_id = get_post_meta( $post->ID, '_oli_dp_rewrite_copy', true );

		if ( $rewrite_copy_id ) {
			$post_states['oli_dp_original'] = __( 'Original (being rewritten)', 'oli-duplicate-post' );
		}

		return $post_states;
	}

	/**
	 * Add schedule meta box for rewrite copies.
	 *
	 * @since 1.0.0
	 */
	public static function add_schedule_meta_box() {
		$post_id = self::get_requested_post_id();
		if ( ! $post_id ) {
			return;
		}

		$original_post_id = get_post_meta( $post_id, '_oli_dp_original_post', true );
		if ( ! $original_post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		add_meta_box(
			'oli_dp_republish_schedule',
			__( 'Republish Schedule', 'oli-duplicate-post' ),
			array( __CLASS__, 'render_schedule_meta_box' ),
			$post->post_type,
			'side',
			'high'
		);
	}

	/**
	 * Render schedule meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_schedule_meta_box( $post ) {
		$original_post_id = get_post_meta( $post->ID, '_oli_dp_original_post', true );
		if ( ! $original_post_id ) {
			return;
		}

		$original_post = get_post( $original_post_id );
		if ( ! $original_post ) {
			return;
		}
		?>
		<div class="oli-dp-schedule-info">
			<p>
				<strong><?php esc_html_e( 'Original Post:', 'oli-duplicate-post' ); ?></strong><br>
				<?php echo esc_html( $original_post->post_title ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'When you publish this post it replaces the original immediately. Scheduling keeps the original live until the scheduled time.', 'oli-duplicate-post' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Actions:', 'oli-duplicate-post' ); ?></strong>
			</p>
			<ul class="oli-dp-schedule-list">
				<li><?php esc_html_e( 'Publish now: replace the original immediately.', 'oli-duplicate-post' ); ?></li>
				<li><?php esc_html_e( 'Schedule: replace the original at the scheduled time.', 'oli-duplicate-post' ); ?></li>
				<li><?php esc_html_e( 'Save draft: keep editing without publishing.', 'oli-duplicate-post' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Add info to publish box for rewrite copies.
	 *
	 * @since 1.0.0
	 */
	public static function add_publish_box_info() {
		global $post;

		if ( ! $post ) {
			return;
		}

		$original_post_id = get_post_meta( $post->ID, '_oli_dp_original_post', true );
		if ( ! $original_post_id ) {
			return;
		}

		?>
		<div class="misc-pub-section oli-dp-republish-info">
			<span class="dashicons dashicons-update oli-dp-republish-icon"></span>
			<strong><?php esc_html_e( 'Rewrite & Republish Mode', 'oli-duplicate-post' ); ?></strong>
			<p class="description oli-dp-republish-note">
				<?php esc_html_e( 'Publishing will replace the original post.', 'oli-duplicate-post' ); ?>
			</p>
			<?php wp_nonce_field( 'oli_dp_rewrite_publish', 'oli_dp_rewrite_nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Redirect the classic editor to the original post after a merge.
	 *
	 * @since 1.0.0
	 *
	 * @param string $location Redirect URL.
	 * @return string
	 */
	public static function redirect_after_merge( $location ) {
		if ( ! self::$redirect_original_id ) {
			return $location;
		}

		$edit_link = get_edit_post_link( self::$redirect_original_id, 'raw' );
		if ( ! $edit_link ) {
			return $location;
		}

		return add_query_arg( 'oli_dp_republished', 1, $edit_link );
	}

	/**
	 * Get comparison data for two posts.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id_1 First post ID.
	 * @param int $post_id_2 Second post ID.
	 * @return array Comparison data.
	 */
	public static function get_comparison_data( $post_id_1, $post_id_2 ) {
		$post_1 = get_post( $post_id_1 );
		$post_2 = get_post( $post_id_2 );

		if ( ! $post_1 || ! $post_2 ) {
			return array();
		}

		return array(
			'title'   => array(
				'original' => $post_1->post_title,
				'rewrite'  => $post_2->post_title,
			),
			'content' => array(
				'original' => $post_1->post_content,
				'rewrite'  => $post_2->post_content,
			),
			'excerpt' => array(
				'original' => $post_1->post_excerpt,
				'rewrite'  => $post_2->post_excerpt,
			),
		);
	}

	/**
	 * Current post ID from the editor request.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	private static function get_requested_post_id() {
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		global $post;
		if ( $post instanceof WP_Post ) {
			return (int) $post->ID;
		}

		return 0;
	}

	/**
	 * Whether the current request is a REST request.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private static function is_rest_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( isset( $GLOBALS['wp_rest_server'] ) && $GLOBALS['wp_rest_server'] instanceof WP_REST_Server ) {
			return true;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			if ( false !== strpos( $request_uri, '/wp-json/' ) ) {
				return true;
			}
		}

		return false;
	}
}
