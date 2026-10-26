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
		$this->assertSame( 'trash', get_post_status( $rewrite_id ) );
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
				'ID'            => $rewrite_id,
				'post_content'  => 'Scheduled rewrite',
				'post_status'   => 'future',
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);

		$this->assertSame( 'Current', get_post( $original_id )->post_content );
		$this->assertSame( 'future', get_post_status( $rewrite_id ) );
		$this->assertSame( (string) $rewrite_id, get_post_meta( $original_id, '_oli_dp_rewrite_copy', true ) );

		wp_publish_post( $rewrite_id );

		$this->assertSame( 'Scheduled rewrite', get_post( $original_id )->post_content );
	}

	public function test_featured_image_setting_is_honored() {
		$original_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$image_id    = self::factory()->attachment->create();
		set_post_thumbnail( $original_id, $image_id );

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
}
