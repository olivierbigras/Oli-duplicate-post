<?php
/**
 * WordPress hooks and filters integration.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-post handlers and duplicate URLs.
 *
 * @since 1.0.0
 */
class Oli_DP_Hooks {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_post_oli_dp_duplicate', array( __CLASS__, 'handle_duplicate_request' ) );
		add_action( 'admin_post_oli_dp_duplicate_post', array( __CLASS__, 'handle_duplicate_action' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_success_notice' ) );
	}

	/**
	 * Handle GET duplicate requests from row actions, admin bar, and editors.
	 *
	 * @since 1.0.0
	 */
	public static function handle_duplicate_request() {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action  = isset( $_GET['oli_dp_action'] ) ? sanitize_key( wp_unslash( $_GET['oli_dp_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $post_id || ! wp_verify_nonce( $nonce, 'oli_dp_duplicate_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oli-duplicate-post' ) );
		}

		self::process_duplicate( $post_id, $action );
	}

	/**
	 * Handle POST duplicate action from admin_post hook.
	 *
	 * @since 1.0.0
	 */
	public static function handle_duplicate_action() {
		if ( ! isset( $_POST['post_id'] ) || ! isset( $_POST['oli_dp_nonce'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'oli-duplicate-post' ) );
		}

		$post_id = absint( wp_unslash( $_POST['post_id'] ) );
		$nonce   = sanitize_text_field( wp_unslash( $_POST['oli_dp_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'oli_dp_duplicate_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oli-duplicate-post' ) );
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'clone';
		self::process_duplicate( $post_id, $mode );
	}

	/**
	 * Duplicate a post and redirect.
	 *
	 * Clone stays on the list table. New Draft and Rewrite & Republish open
	 * the editor on the new copy.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID.
	 * @param string $action  Mode slug.
	 */
	private static function process_duplicate( $post_id, $action ) {
		if ( ! Oli_DP_Permissions::can_duplicate( $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this post.', 'oli-duplicate-post' ) );
		}

		$mode_map = array(
			'clone'             => 'clone',
			'new_draft'         => 'new_draft',
			'rewrite_republish' => 'rewrite_republish',
		);

		if ( ! isset( $mode_map[ $action ] ) ) {
			wp_die( esc_html__( 'Invalid action.', 'oli-duplicate-post' ) );
		}

		$mode        = $mode_map[ $action ];
		$new_post_id = Oli_DP_Duplicator::duplicate( $post_id, $mode );

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html( $new_post_id->get_error_message() ) );
		}

		wp_safe_redirect( self::get_after_duplicate_redirect( $post_id, $new_post_id, $mode ) );
		exit;
	}

	/**
	 * Redirect target after a successful duplicate.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $original_id Original post ID.
	 * @param int    $new_post_id New post ID.
	 * @param string $mode        Duplication mode.
	 * @return string
	 */
	private static function get_after_duplicate_redirect( $original_id, $new_post_id, $mode ) {
		if ( 'clone' === $mode ) {
			$post = get_post( $original_id );
			$list = admin_url( 'edit.php' );
			if ( $post && 'post' !== $post->post_type ) {
				$list = add_query_arg( 'post_type', $post->post_type, $list );
			}

			return add_query_arg(
				array(
					'oli_dp_duplicated' => 1,
					'oli_dp_mode'       => $mode,
					'oli_dp_new'        => $new_post_id,
				),
				$list
			);
		}

		return add_query_arg(
			array(
				'oli_dp_duplicated' => 1,
				'oli_dp_mode'       => $mode,
			),
			get_edit_post_link( $new_post_id, 'raw' )
		);
	}

	/**
	 * Show success notice after duplication.
	 *
	 * @since 1.0.0
	 */
	public static function show_success_notice() {
		if ( ! isset( $_GET['oli_dp_duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! Oli_DP_Utils::allows_admin_notice( 'post_edit' ) && ! Oli_DP_Utils::allows_admin_notice( 'post_list' ) ) {
			return;
		}

		$mode = isset( $_GET['oli_dp_mode'] ) ? sanitize_key( wp_unslash( $_GET['oli_dp_mode'] ) ) : 'clone'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'clone'             => __( 'Post cloned successfully.', 'oli-duplicate-post' ),
			'new_draft'         => __( 'New draft created successfully.', 'oli-duplicate-post' ),
			'rewrite_republish' => __( 'Rewrite copy created successfully. Edit and publish to replace the original.', 'oli-duplicate-post' ),
		);

		$message = isset( $messages[ $mode ] ) ? $messages[ $mode ] : $messages['clone'];

		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Get duplicate URL for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id The post ID.
	 * @param string $action  The action: 'clone', 'new_draft', or 'rewrite_republish'.
	 * @return string The duplicate URL.
	 */
	public static function get_duplicate_url( $post_id, $action = 'clone' ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'        => 'oli_dp_duplicate',
					'oli_dp_action' => $action,
					'post'          => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			'oli_dp_duplicate_' . $post_id
		);
	}
}
