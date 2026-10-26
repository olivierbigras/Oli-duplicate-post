<?php
/**
 * Edit screen meta box and block editor panel.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic editor meta box and block editor sidebar/Options menu.
 *
 * @since 1.0.0
 */
class Oli_DP_Meta_Box {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Add meta box to edit screen.
	 *
	 * @since 1.0.0
	 */
	public static function add_meta_box() {
		$settings = Oli_DP_Settings::get_settings();

		if ( empty( $settings['show_edit_screen'] ) ) {
			return;
		}

		$enabled_post_types = isset( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array();

		foreach ( (array) $enabled_post_types as $post_type ) {
			add_meta_box(
				'oli_dp_duplicate_box',
				__( 'Duplicate Post', 'oli-duplicate-post' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render meta box content.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_meta_box( $post ) {
		if ( ! Oli_DP_Permissions::can_duplicate( $post->ID ) ) {
			esc_html_e( 'You do not have permission to duplicate this post.', 'oli-duplicate-post' );
			return;
		}

		$clone_url     = Oli_DP_Hooks::get_duplicate_url( $post->ID, 'clone' );
		$new_draft_url = Oli_DP_Hooks::get_duplicate_url( $post->ID, 'new_draft' );
		$original_id   = Oli_DP_Utils::get_original_post_id( $post->ID );
		$original      = $original_id ? get_post( $original_id ) : null;

		?>
		<div class="oli-dp-meta-box">
			<?php if ( $original ) : ?>
				<?php $original_edit = get_edit_post_link( $original_id ); ?>
				<p class="description">
					<?php
					if ( $original_edit ) {
						printf(
							wp_kses(
								/* translators: %s: original post title as a link */
								__( 'Original: %s', 'oli-duplicate-post' ),
								array(
									'a' => array(
										'href' => array(),
									),
								)
							),
							sprintf(
								'<a href="%s">%s</a>',
								esc_url( $original_edit ),
								esc_html( $original->post_title )
							)
						);
					} else {
						printf(
							/* translators: %s: original post title */
							esc_html__( 'Original: %s', 'oli-duplicate-post' ),
							esc_html( $original->post_title )
						);
					}
					?>
				</p>
			<?php endif; ?>
			<p>
				<a href="<?php echo esc_url( $clone_url ); ?>" class="button button-secondary button-large oli-dp-button-full">
					<?php esc_html_e( 'Clone', 'oli-duplicate-post' ); ?>
				</a>
			</p>
			<p>
				<a href="<?php echo esc_url( $new_draft_url ); ?>" class="button button-secondary button-large oli-dp-button-full">
					<?php esc_html_e( 'New Draft', 'oli-duplicate-post' ); ?>
				</a>
			</p>
			<?php if ( 'publish' === $post->post_status ) : ?>
				<?php
				$existing_rewrite = absint( get_post_meta( $post->ID, '_oli_dp_rewrite_copy', true ) );

				if ( $existing_rewrite && get_post( $existing_rewrite ) ) {
					$edit_url = get_edit_post_link( $existing_rewrite );
					?>
					<p>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-primary button-large oli-dp-button-full">
							<?php esc_html_e( 'Edit Rewrite Copy', 'oli-duplicate-post' ); ?>
						</a>
					</p>
				<?php } elseif ( Oli_DP_Permissions::can_rewrite( $post->ID ) ) { ?>
					<?php $rewrite_url = Oli_DP_Hooks::get_duplicate_url( $post->ID, 'rewrite_republish' ); ?>
					<p>
						<a href="<?php echo esc_url( $rewrite_url ); ?>" class="button button-secondary button-large oli-dp-button-full">
							<?php esc_html_e( 'Rewrite & Republish', 'oli-duplicate-post' ); ?>
						</a>
					</p>
				<?php } ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @since 1.0.0
	 */
	public static function enqueue_block_editor_assets() {
		$settings = Oli_DP_Settings::get_settings();

		if ( empty( $settings['show_edit_screen'] ) ) {
			return;
		}

		$post = get_post();

		if ( ! $post || ! Oli_DP_Permissions::can_duplicate( $post->ID ) ) {
			return;
		}

		$clone_url       = Oli_DP_Hooks::get_duplicate_url( $post->ID, 'clone' );
		$new_draft_url   = Oli_DP_Hooks::get_duplicate_url( $post->ID, 'new_draft' );
		$rewrite_url     = '';
		$original_edit   = '';
		$original_title  = '';
		$compare_url     = '';
		$original_id     = absint( get_post_meta( $post->ID, '_oli_dp_original_post', true ) );
		$is_rewrite_copy = (bool) $original_id;

		if ( $is_rewrite_copy ) {
			$original = get_post( $original_id );
			if ( $original ) {
				$original_title = $original->post_title;
				$original_edit  = get_edit_post_link( $original_id, 'raw' );
				$compare_url    = wp_nonce_url(
					add_query_arg(
						array(
							'page'     => 'oli-dp-compare',
							'original' => $original_id,
							'rewrite'  => $post->ID,
						),
						admin_url( 'admin.php' )
					),
					'oli_dp_compare_' . $original_id . '_' . $post->ID
				);
			}
		} elseif ( 'publish' === $post->post_status && Oli_DP_Permissions::can_rewrite( $post->ID ) ) {
			$rewrite_url = Oli_DP_Hooks::get_duplicate_url( $post->ID, 'rewrite_republish' );
		}

		wp_enqueue_style(
			'oli-dp-admin',
			OLI_DP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			OLI_DP_VERSION
		);

		wp_enqueue_script(
			'oli-dp-block-editor',
			OLI_DP_PLUGIN_URL . 'assets/js/block-editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-notices', 'wp-hooks' ),
			OLI_DP_VERSION,
			true
		);

		wp_localize_script(
			'oli-dp-block-editor',
			'oliDpBlockEditor',
			array(
				'cloneUrl'        => $clone_url,
				'newDraftUrl'     => $new_draft_url,
				'rewriteUrl'      => $rewrite_url,
				'isRewrite'       => $is_rewrite_copy,
				'originalEditUrl' => $original_edit ? $original_edit : '',
				'compareUrl'      => $compare_url,
				'i18n'            => array(
					'duplicatePost'         => __( 'Duplicate Post', 'oli-duplicate-post' ),
					'clone'                 => __( 'Clone', 'oli-duplicate-post' ),
					'newDraft'              => __( 'New Draft', 'oli-duplicate-post' ),
					'copyToNewDraft'        => __( 'Copy to New Draft', 'oli-duplicate-post' ),
					'rewriteRepublish'      => __( 'Rewrite & Republish', 'oli-duplicate-post' ),
					'rewriteNotice'         => sprintf(
						/* translators: %s: original post title */
						__( 'You are editing a rewrite of “%s”. Publishing will replace the original.', 'oli-duplicate-post' ),
						$original_title
					),
					'viewOriginal'          => __( 'View original', 'oli-duplicate-post' ),
					'compare'               => __( 'Compare', 'oli-duplicate-post' ),
					'republish'             => __( 'Republish', 'oli-duplicate-post' ),
					'republishWarningTitle' => __( 'Original will be overwritten', 'oli-duplicate-post' ),
					'prePublishWarning'     => sprintf(
						/* translators: %s: original post title */
						__( 'When you click Republish, this copy will replace “%s” (same URL, same ID). The copy will then be deleted.', 'oli-duplicate-post' ),
						$original_title
					),
					'republishSchedule'     => __( 'Republish Schedule', 'oli-duplicate-post' ),
					'scheduleHelp'          => __( 'When you publish this post it replaces the original immediately. Scheduling keeps the original live until the scheduled time.', 'oli-duplicate-post' ),
					'rewriteCopyLabel'      => __( '[Rewrite copy]', 'oli-duplicate-post' ),
				),
			)
		);
	}
}
