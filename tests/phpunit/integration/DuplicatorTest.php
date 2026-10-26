<?php
/**
 * Integration tests for clone, new draft, and Rewrite & Republish.
 *
 * @package Oli_Duplicate_Post
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	/**
	 * Skip the suite when the WordPress test library is not installed.
	 */
	class Oli_DP_Duplicator_Test extends PHPUnit\Framework\TestCase {

		public function test_wordpress_test_suite_unavailable() {
			$this->markTestSkipped( 'WordPress test suite is not available. Run bin/install-wp-tests.sh or wp-env.' );
		}
	}

	return;
}

/**
 * @covers Oli_DP_Duplicator
 * @covers Oli_DP_Rewrite_Republish
 */
class Oli_DP_Duplicator_Test extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private $user_id;

	public function set_up() {
		parent::set_up();

		Oli_DP_Rewrite_Republish::invalidate_pending_delete_ids_cache();

		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );

		update_option(
			'oli_dp_settings',
			wp_parse_args(
				array(
					'enabled_post_types'  => array( 'post', 'page' ),
					'allowed_roles'       => array( 'administrator', 'editor' ),
					'copy_featured_image' => true,
					'title_suffix'        => ' (Copy)',
				),
				Oli_DP_Settings::get_default_settings()
			)
		);
	}

	public function test_clone_copies_content_meta_and_taxonomy() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Source Post',
				'post_content' => 'Body text',
				'post_excerpt' => 'Excerpt',
				'post_status'  => 'publish',
			)
		);

		update_post_meta( $original_id, 'olidp_custom', 'keep-me' );
		update_post_meta( $original_id, '_edit_lock', 'skip-me' );
		wp_set_object_terms( $original_id, array( 'news' ), 'category' );

		$new_id = Oli_DP_Duplicator::duplicate( $original_id, 'clone' );
		$this->assertIsInt( $new_id );

		$copy = get_post( $new_id );
		$this->assertSame( 'Source Post (Copy)', $copy->post_title );
		$this->assertSame( 'Body text', $copy->post_content );
		$this->assertSame( 'Excerpt', $copy->post_excerpt );
		$this->assertSame( 'keep-me', get_post_meta( $new_id, 'olidp_custom', true ) );
		$this->assertSame( '', get_post_meta( $new_id, '_edit_lock', true ) );
		$this->assertSame( (string) $original_id, get_post_meta( $new_id, '_dp_original', true ) );
		$this->assertContains( 'news', wp_get_object_terms( $new_id, 'category', array( 'fields' => 'slugs' ) ) );
	}

	public function test_new_draft_is_always_draft() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'  => 'Live Post',
				'post_status' => 'publish',
			)
		);

		$new_id = Oli_DP_Duplicator::duplicate( $original_id, 'new_draft' );
		$this->assertSame( 'draft', get_post_status( $new_id ) );
	}

	public function test_rewrite_creates_linked_copy_without_title_suffix() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
				'post_name'    => 'live-article',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		$this->assertIsInt( $rewrite_id );
		$this->assertSame( 'draft', get_post_status( $rewrite_id ) );
		$this->assertSame( 'Live Article', get_post( $rewrite_id )->post_title );
		$this->assertSame( (string) $original_id, get_post_meta( $rewrite_id, '_oli_dp_original_post', true ) );
		$this->assertSame( (string) $original_id, get_post_meta( $rewrite_id, '_dp_original', true ) );
		$this->assertSame( (string) $rewrite_id, get_post_meta( $original_id, '_oli_dp_rewrite_copy', true ) );
	}

	public function test_rewrite_merge_on_publish_preserves_slug_and_updates_content() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
				'post_name'    => 'live-article',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );

		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_title'   => 'Updated Article',
				'post_content' => 'New body',
			)
		);

		wp_publish_post( $rewrite_id );

		$original = get_post( $original_id );
		$this->assertSame( 'Updated Article', $original->post_title );
		$this->assertSame( 'New body', $original->post_content );
		$this->assertSame( 'live-article', $original->post_name );
		$this->assertSame( 'publish', $original->post_status );
		$this->assertEmpty( get_post_meta( $original_id, '_oli_dp_rewrite_copy', true ) );
		$this->assertNotNull( get_post( $rewrite_id ) );

		Oli_DP_Rewrite_Republish::delete_merged_copies();

		$this->assertNull( get_post( $rewrite_id ) );
		$this->assertFalse( get_post_status( $rewrite_id ) );
	}

	public function test_scheduled_republish_does_not_merge_until_publish() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Stay Live',
				'post_content' => 'Current',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );

		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_content' => 'Scheduled rewrite',
				'post_status'  => 'draft',
			)
		);

		$rewrite = get_post( $rewrite_id );
		Oli_DP_Rewrite_Republish::handle_publish( 'future', 'draft', $rewrite );

		$this->assertSame( 'Current', get_post( $original_id )->post_content );
		$this->assertSame( (string) $rewrite_id, get_post_meta( $original_id, '_oli_dp_rewrite_copy', true ) );
		$this->assertSame( 'draft', get_post_status( $rewrite_id ) );

		wp_publish_post( $rewrite_id );

		$this->assertSame( 'Scheduled rewrite', get_post( $original_id )->post_content );
		Oli_DP_Rewrite_Republish::delete_merged_copies();
	}

	public function test_future_gmt_rewrite_copy_does_not_merge_immediately() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Stay Live',
				'post_content' => 'Current',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		$gmt        = gmdate( 'Y-m-d H:i:s', time() + 60 );
		$local      = get_date_from_gmt( $gmt );

		wp_update_post(
			array(
				'ID'            => $rewrite_id,
				'post_content'  => 'Too soon',
				'post_status'   => 'future',
				'post_date'     => $local,
				'post_date_gmt' => $gmt,
				'edit_date'     => true,
			)
		);

		$rewrite = get_post( $rewrite_id );
		Oli_DP_Rewrite_Republish::handle_publish( 'publish', 'future', $rewrite );
		Oli_DP_Rewrite_Republish::merge_after_save( $rewrite_id, $rewrite, true );
		Oli_DP_Rewrite_Republish::delete_merged_copies();

		$this->assertSame( 'Current', get_post( $original_id )->post_content );
		$this->assertNotNull( get_post( $rewrite_id ) );
	}

	public function test_cancel_schedule_then_publish_now_merges() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Stay Live',
				'post_content' => 'Current',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		$gmt        = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$local      = get_date_from_gmt( $gmt );

		wp_update_post(
			array(
				'ID'            => $rewrite_id,
				'post_content'  => 'Cancelled schedule',
				'post_status'   => 'future',
				'post_date'     => $local,
				'post_date_gmt' => $gmt,
				'edit_date'     => true,
			)
		);

		$this->assertNotEmpty( get_post_meta( $rewrite_id, '_oli_dp_scheduled_gmt', true ) );

		wp_update_post(
			array(
				'ID'            => $rewrite_id,
				'post_status'   => 'draft',
				'post_date'     => current_time( 'mysql' ),
				'post_date_gmt' => current_time( 'mysql', true ),
				'edit_date'     => true,
			)
		);

		$this->assertSame( '', get_post_meta( $rewrite_id, '_oli_dp_scheduled_gmt', true ) );

		wp_publish_post( $rewrite_id );

		$this->assertSame( 'Cancelled schedule', get_post( $original_id )->post_content );
		Oli_DP_Rewrite_Republish::delete_merged_copies();
		$this->assertNull( get_post( $rewrite_id ) );
	}

	public function test_metabox_loader_deletes_merged_rewrite_copy() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_content' => 'New body',
			)
		);
		wp_publish_post( $rewrite_id );

		$this->assertSame( 'New body', get_post( $original_id )->post_content );
		$this->assertNotNull( get_post( $rewrite_id ) );

		$_REQUEST['meta-box-loader'] = '1';
		$_REQUEST['post']            = (string) $rewrite_id;
		Oli_DP_Rewrite_Republish::queue_delete_after_metabox();
		Oli_DP_Rewrite_Republish::delete_merged_copies();

		$this->assertNull( get_post( $rewrite_id ) );
		unset( $_REQUEST['meta-box-loader'], $_REQUEST['post'] );
	}

	public function test_rest_republish_defers_delete_and_schedules_cron() {
		$previous_uri           = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts/1';

		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_content' => 'New body',
			)
		);
		wp_publish_post( $rewrite_id );

		$this->assertSame( 'New body', get_post( $original_id )->post_content );
		$this->assertNotNull( get_post( $rewrite_id ) );
		$this->assertSame( 'draft', get_post_status( $rewrite_id ) );
		$pending = get_post_meta( $rewrite_id, '_oli_dp_pending_delete', true );
		$this->assertNotEmpty( $pending );
		$this->assertTrue( is_numeric( $pending ) );
		$this->assertNotFalse( wp_next_scheduled( 'oli_dp_delete_rewrite_copy', array( $rewrite_id ) ) );

		Oli_DP_Rewrite_Republish::delete_rewrite_copy( $rewrite_id );
		$this->assertNull( get_post( $rewrite_id ) );

		$_SERVER['REQUEST_URI'] = $previous_uri;
	}

	public function test_featured_image_setting_is_honored() {
		$original_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$image_id    = self::factory()->attachment->create();
		update_post_meta( $original_id, '_thumbnail_id', $image_id );

		$settings                        = Oli_DP_Settings::get_settings();
		$settings['copy_featured_image'] = false;

		$new_id = Oli_DP_Duplicator::duplicate( $original_id, 'clone', $settings );
		$this->assertFalse( (bool) get_post_thumbnail_id( $new_id ) );

		$settings['copy_featured_image'] = true;
		$copied                          = Oli_DP_Duplicator::duplicate( $original_id, 'clone', $settings );
		$this->assertSame( $image_id, (int) get_post_thumbnail_id( $copied ) );
	}

	public function test_template_tag_delegates_to_duplicator() {
		$original_id = self::factory()->post->create( array( 'post_title' => 'API Post' ) );
		$new_id      = oli_dp_duplicate_post( $original_id, 'new_draft' );

		$this->assertIsInt( $new_id );
		$this->assertSame( 'draft', get_post_status( $new_id ) );
	}

	public function test_variable_product_variations_are_published_and_synced() {
		$this->register_product_post_types();

		$settings                       = Oli_DP_Settings::get_settings();
		$settings['enabled_post_types'] = array( 'post', 'page', 'product' );
		$settings['copy_status']        = false;
		update_option( 'oli_dp_settings', $settings );

		$lookup_ids = array();
		add_action(
			'oli_dp_woocommerce_variable_synced',
			static function ( $product_id, $synced, $ids ) use ( &$lookup_ids ) {
				unset( $product_id, $synced );
				$lookup_ids = $ids;
			},
			10,
			3
		);

		$product_id = self::factory()->post->create(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_title'  => 'Variable Shirt',
			)
		);

		self::factory()->post->create(
			array(
				'post_type'   => 'product_variation',
				'post_status' => 'publish',
				'post_parent' => $product_id,
				'post_title'  => 'Variable Shirt - Blue',
			)
		);

		$new_id = Oli_DP_Duplicator::duplicate( $product_id, 'clone' );
		$this->assertIsInt( $new_id );
		$this->assertSame( 'draft', get_post_status( $new_id ) );

		$copies = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_parent'    => $new_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 1, $copies );
		$this->assertSame( 'publish', $copies[0]->post_status );
		$this->assertContains( $new_id, $lookup_ids );
		$this->assertContains( (int) $copies[0]->ID, $lookup_ids );
	}

	public function test_private_variations_keep_private_status() {
		$this->register_product_post_types();

		$settings                       = Oli_DP_Settings::get_settings();
		$settings['enabled_post_types'] = array( 'post', 'page', 'product' );
		$settings['copy_status']        = false;
		update_option( 'oli_dp_settings', $settings );

		$product_id = self::factory()->post->create(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_title'  => 'Variable Shirt',
			)
		);

		self::factory()->post->create(
			array(
				'post_type'   => 'product_variation',
				'post_status' => 'private',
				'post_parent' => $product_id,
				'post_title'  => 'Variable Shirt - Hidden',
			)
		);

		$new_id = Oli_DP_Duplicator::duplicate( $product_id, 'clone' );
		$copies = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_parent'    => $new_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 1, $copies );
		$this->assertSame( 'private', $copies[0]->post_status );
	}

	public function test_title_prefix_and_suffix_spaces_are_preserved() {
		$sanitized = Oli_DP_Settings::sanitize_settings(
			array(
				'title_prefix' => 'Copy of ',
				'title_suffix' => ' (Copy)',
			)
		);

		$this->assertSame( 'Copy of ', $sanitized['title_prefix'] );
		$this->assertSame( ' (Copy)', $sanitized['title_suffix'] );

		$original_id = self::factory()->post->create(
			array(
				'post_title'  => 'Hello',
				'post_status' => 'publish',
			)
		);

		$settings                 = Oli_DP_Settings::get_settings();
		$settings['title_prefix'] = $sanitized['title_prefix'];
		$settings['title_suffix'] = $sanitized['title_suffix'];
		$settings['copy_title']   = true;

		$new_id = Oli_DP_Duplicator::duplicate( $original_id, 'clone', $settings );
		$this->assertSame( 'Copy of Hello (Copy)', get_post( $new_id )->post_title );
	}

	public function test_copy_attachments_creates_real_attachments() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'  => 'Has Media',
				'post_status' => 'publish',
			)
		);

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_parent'    => $original_id,
				'post_title'     => 'Hero Image',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'guid'           => 'https://example.com/wp-content/uploads/2026/10/hero.jpg',
			)
		);

		update_post_meta( $attachment_id, '_wp_attached_file', '2026/10/hero.jpg' );
		update_post_meta(
			$attachment_id,
			'_wp_attachment_metadata',
			array(
				'width'  => 800,
				'height' => 600,
			)
		);

		$settings                     = Oli_DP_Settings::get_settings();
		$settings['copy_attachments'] = true;

		$new_id = Oli_DP_Duplicator::duplicate( $original_id, 'clone', $settings );
		$this->assertIsInt( $new_id );

		$copied = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_parent'    => $new_id,
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 1, $copied );
		$copy = $copied[0];
		$this->assertSame( 'attachment', $copy->post_type );
		$this->assertSame( 'inherit', $copy->post_status );
		$this->assertSame( 'image/jpeg', $copy->post_mime_type );
		$this->assertSame( '2026/10/hero.jpg', get_post_meta( $copy->ID, '_wp_attached_file', true ) );
		$this->assertSame(
			array(
				'width'  => 800,
				'height' => 600,
			),
			get_post_meta( $copy->ID, '_wp_attachment_metadata', true )
		);
		$this->assertSame( (string) $attachment_id, get_post_meta( $copy->ID, '_dp_original', true ) );
	}

	public function test_admin_bar_only_resolves_single_edit_screens() {
		global $pagenow;

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$method  = new ReflectionMethod( 'Oli_DP_Admin_Bar', 'get_current_post' );
		$method->setAccessible( true );

		set_current_screen( 'edit-post' );
		$pagenow      = 'edit.php';
		$_GET['post'] = $post_id;
		$this->assertNull( $method->invoke( null ) );

		set_current_screen( 'post' );
		$pagenow = 'post.php';
		$post    = $method->invoke( null );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $post_id, (int) $post->ID );

		unset( $_GET['post'] );
		set_current_screen( 'front' );
	}

	public function test_merged_copy_is_hidden_and_cleaned_after_five_minutes() {
		$previous_uri           = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts/1';

		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_content' => 'New body',
			)
		);
		wp_publish_post( $rewrite_id );

		$this->assertSame( 'New body', get_post( $original_id )->post_content );
		$this->assertNotNull( get_post( $rewrite_id ) );
		$this->assertSame( 'draft', get_post_status( $rewrite_id ) );
		$this->assertContains( $rewrite_id, Oli_DP_Rewrite_Republish::get_pending_delete_ids() );

		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$this->assertNotContains( $rewrite_id, $query->posts );

		$sitemap_args = apply_filters( 'wp_sitemaps_posts_query_args', array( 'post_type' => 'post' ), 'post' );
		$this->assertContains( $rewrite_id, $sitemap_args['post__not_in'] );

		$this->assertSame( array(), Oli_DP_Rewrite_Republish::delete_stale_merged_copies() );
		$this->assertNotNull( get_post( $rewrite_id ) );

		update_post_meta( $rewrite_id, '_oli_dp_pending_delete', (string) ( time() - ( 6 * MINUTE_IN_SECONDS ) ) );
		$deleted = Oli_DP_Rewrite_Republish::delete_stale_merged_copies();
		$this->assertContains( $rewrite_id, $deleted );
		$this->assertNull( get_post( $rewrite_id ) );

		$_SERVER['REQUEST_URI'] = $previous_uri;
	}

	public function test_rewrite_copy_has_list_post_state() {
		$original_id = self::factory()->post->create(
			array(
				'post_title'  => 'Demo Article A',
				'post_status' => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		$copy_states = Oli_DP_Rewrite_Republish::add_post_state( array(), get_post( $rewrite_id ) );
		$original_states = Oli_DP_Rewrite_Republish::add_post_state( array(), get_post( $original_id ) );

		$this->assertArrayHasKey( 'oli_dp_rewrite', $copy_states );
		$this->assertStringContainsString( 'Rewrite of:', wp_strip_all_tags( $copy_states['oli_dp_rewrite'] ) );
		$this->assertStringContainsString( 'Demo Article A', wp_strip_all_tags( $copy_states['oli_dp_rewrite'] ) );
		$this->assertArrayHasKey( 'oli_dp_original', $original_states );
		$this->assertSame(
			sprintf( 'Rewrite in progress (draft #%d)', $rewrite_id ),
			$original_states['oli_dp_original']
		);
	}

	public function test_merged_copy_cannot_be_published_again() {
		$previous_uri           = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts/1';

		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_title'   => 'Updated Article',
				'post_content' => 'New body',
			)
		);
		wp_publish_post( $rewrite_id );

		$this->assertSame( 'Updated Article', get_post( $original_id )->post_title );
		$this->assertSame( 'draft', get_post_status( $rewrite_id ) );
		$this->assertNotEmpty( get_post_meta( $rewrite_id, '_oli_dp_pending_delete', true ) );
		$this->assertSame( '', get_post_meta( $rewrite_id, '_oli_dp_original_post', true ) );

		foreach ( array( 'private', 'pending', 'publish', 'future' ) as $status ) {
			$prepared              = new stdClass();
			$prepared->ID          = $rewrite_id;
			$prepared->post_status = $status;
			$request               = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $rewrite_id );
			$request->set_param( 'id', $rewrite_id );
			$request->set_param( 'status', $status );

			$guarded = Oli_DP_Rewrite_Republish::rest_prevent_pending_delete_publish( $prepared, $request );
			$this->assertSame( 'draft', $guarded->post_status, $status );

			$filtered = Oli_DP_Rewrite_Republish::prevent_pending_delete_publish(
				array(
					'post_status' => $status,
				),
				array(
					'ID' => $rewrite_id,
				)
			);
			$this->assertSame( 'draft', $filtered['post_status'], $status );

			wp_update_post(
				array(
					'ID'          => $rewrite_id,
					'post_status' => $status,
				)
			);
			$this->assertSame( 'draft', get_post_status( $rewrite_id ), $status );

			Oli_DP_Rewrite_Republish::handle_publish( $status, 'draft', get_post( $rewrite_id ) );
			$this->assertSame( 'draft', get_post_status( $rewrite_id ), $status );
		}

		$allowed = Oli_DP_Rewrite_Republish::prevent_pending_delete_publish(
			array(
				'post_status' => 'trash',
			),
			array(
				'ID' => $rewrite_id,
			)
		);
		$this->assertSame( 'trash', $allowed['post_status'] );

		$this->assertSame( 'Updated Article', get_post( $original_id )->post_title );
		$this->assertSame( 'New body', get_post( $original_id )->post_content );

		$_SERVER['REQUEST_URI'] = $previous_uri;
	}

	public function test_pending_delete_ids_are_cached_in_a_transient() {
		$previous_uri           = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts/1';

		$this->assertSame( array(), Oli_DP_Rewrite_Republish::get_pending_delete_ids() );
		$this->assertSame( array(), get_transient( Oli_DP_Rewrite_Republish::PENDING_DELETE_IDS_TRANSIENT ) );

		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_content' => 'New body',
			)
		);
		wp_publish_post( $rewrite_id );

		$ids = Oli_DP_Rewrite_Republish::get_pending_delete_ids();
		$this->assertContains( $rewrite_id, $ids );
		$this->assertSame( $ids, get_transient( Oli_DP_Rewrite_Republish::PENDING_DELETE_IDS_TRANSIENT ) );

		$property = new ReflectionProperty( 'Oli_DP_Rewrite_Republish', 'pending_delete_ids' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		$this->assertSame( $ids, Oli_DP_Rewrite_Republish::get_pending_delete_ids() );

		Oli_DP_Rewrite_Republish::delete_rewrite_copy( $rewrite_id );
		$this->assertNull( get_post( $rewrite_id ) );

		$after_delete = Oli_DP_Rewrite_Republish::get_pending_delete_ids();
		$this->assertNotContains( $rewrite_id, $after_delete );
		$this->assertSame( $after_delete, get_transient( Oli_DP_Rewrite_Republish::PENDING_DELETE_IDS_TRANSIENT ) );

		$_SERVER['REQUEST_URI'] = $previous_uri;
	}

	public function test_admin_list_query_does_not_exclude_merged_copies() {
		$previous_uri           = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts/1';

		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_content' => 'New body',
			)
		);
		wp_publish_post( $rewrite_id );
		$_SERVER['REQUEST_URI'] = $previous_uri;

		set_current_screen( 'edit-post' );
		$admin_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$this->assertContains( $rewrite_id, $admin_query->posts );

		set_current_screen( 'front' );
		$front_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$this->assertNotContains( $rewrite_id, $front_query->posts );

		$_SERVER['REQUEST_URI'] = $previous_uri;
	}

	public function test_admin_cleanup_skips_postmeta_when_pending_cache_is_empty() {
		Oli_DP_Rewrite_Republish::get_pending_delete_ids();
		$this->assertSame( array(), get_transient( Oli_DP_Rewrite_Republish::PENDING_DELETE_IDS_TRANSIENT ) );

		$pending_meta_selects = 0;
		$query_filter         = static function ( $sql ) use ( &$pending_meta_selects ) {
			if ( false !== stripos( $sql, 'SELECT' ) && false !== strpos( $sql, '_oli_dp_pending_delete' ) ) {
				++$pending_meta_selects;
			}
			return $sql;
		};
		add_filter( 'query', $query_filter );

		set_current_screen( 'dashboard' );
		Oli_DP_Rewrite_Republish::cleanup_stale_merged_copies();
		remove_filter( 'query', $query_filter );

		$this->assertSame( 0, $pending_meta_selects );
		$this->assertSame( array(), Oli_DP_Rewrite_Republish::get_stale_merged_copy_ids() );
		set_current_screen( 'front' );
	}

	public function test_admin_cleanup_still_deletes_stale_merged_copies() {
		$previous_uri           = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts/1';

		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_content' => 'New body',
			)
		);
		wp_publish_post( $rewrite_id );
		$_SERVER['REQUEST_URI'] = $previous_uri;

		update_post_meta( $rewrite_id, '_oli_dp_pending_delete', (string) ( time() - ( 6 * MINUTE_IN_SECONDS ) ) );

		set_current_screen( 'dashboard' );
		Oli_DP_Rewrite_Republish::cleanup_stale_merged_copies();
		set_current_screen( 'front' );

		$this->assertNull( get_post( $rewrite_id ) );
	}

	public function test_metabox_loader_redirects_to_original_after_merge() {
		$previous_uri           = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts/1';

		$original_id = self::factory()->post->create(
			array(
				'post_title'   => 'Live Article',
				'post_content' => 'Old body',
				'post_status'  => 'publish',
			)
		);

		$rewrite_id = Oli_DP_Duplicator::duplicate( $original_id, 'rewrite_republish' );
		wp_update_post(
			array(
				'ID'           => $rewrite_id,
				'post_content' => 'New body',
			)
		);
		wp_publish_post( $rewrite_id );
		$_SERVER['REQUEST_URI'] = $previous_uri;

		$_REQUEST['meta-box-loader'] = '1';
		$_REQUEST['post']            = (string) $rewrite_id;

		$from = admin_url( 'post.php?post=' . $rewrite_id . '&action=edit&message=4' );
		$to   = Oli_DP_Rewrite_Republish::redirect_after_merge( $from );

		$to_query = array();
		wp_parse_str( (string) wp_parse_url( $to, PHP_URL_QUERY ), $to_query );

		$this->assertSame( (string) $original_id, (string) $to_query['post'] );
		$this->assertSame( '1', (string) $to_query['oli_dp_republished'] );

		unset( $_REQUEST['meta-box-loader'], $_REQUEST['post'] );
	}

	public function test_row_actions_use_clarified_labels() {
		$post    = get_post( self::factory()->post->create( array( 'post_status' => 'publish' ) ) );
		$actions = Oli_DP_Post_List::add_row_actions( array(), $post );

		$this->assertStringContainsString( 'Duplicate now', $actions['oli_dp_clone'] );
		$this->assertStringContainsString( 'Duplicate and edit', $actions['oli_dp_new_draft'] );
		$this->assertStringContainsString( 'Rewrite and republish', $actions['oli_dp_rewrite'] );
	}

	public function test_bulk_actions_include_clone_and_rewrite() {
		$actions = Oli_DP_Bulk_Actions::add_bulk_actions( array() );

		$this->assertArrayHasKey( 'oli_dp_clone', $actions );
		$this->assertArrayHasKey( 'oli_dp_rewrite_republish', $actions );
	}

	public function test_default_post_types_are_post_and_page() {
		$this->register_product_post_types();

		$defaults = Oli_DP_Settings::get_default_settings();
		$this->assertSame( array( 'post', 'page' ), $defaults['enabled_post_types'] );
		$this->assertNotContains( 'product', $defaults['enabled_post_types'] );
	}

	/**
	 * Register product post types used by WooCommerce-aware tests.
	 */
	private function register_product_post_types() {
		register_post_type(
			'product',
			array(
				'public'          => true,
				'capability_type' => 'post',
			)
		);

		register_post_type(
			'product_variation',
			array(
				'public'          => false,
				'capability_type' => 'post',
			)
		);
	}
}
