<?php
/**
 * Unit tests for Oli_DP_Utils.
 *
 * @package Oli_Duplicate_Post
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers Oli_DP_Utils
 */
class Oli_DP_Utils_Test extends TestCase {

	public function test_parse_list_splits_and_trims() {
		$this->assertSame(
			array( '_edit_lock', '_sku', 'total_sales' ),
			Oli_DP_Utils::parse_list( ' _edit_lock, _sku, total_sales , ' )
		);
	}

	public function test_parse_list_accepts_arrays() {
		$this->assertSame(
			array( 'post', 'page' ),
			Oli_DP_Utils::parse_list( array( 'post', '', 'page' ) )
		);
	}

	public function test_wildcard_meta_exclusion() {
		$patterns = array( '_private_*', 'exact_key' );

		$this->assertTrue( Oli_DP_Utils::should_exclude_meta( '_private_token', $patterns ) );
		$this->assertTrue( Oli_DP_Utils::should_exclude_meta( 'exact_key', $patterns ) );
		$this->assertFalse( Oli_DP_Utils::should_exclude_meta( '_public_token', $patterns ) );
	}

	public function test_rewrite_title_omits_prefix_and_suffix() {
		$this->assertSame(
			'Hello',
			Oli_DP_Utils::build_duplicate_title( 'Hello', 'Copy of ', ' (Copy)', 'rewrite_republish' )
		);
	}

	public function test_clone_title_uses_prefix_and_suffix() {
		$this->assertSame(
			'Copy of Hello (Copy)',
			Oli_DP_Utils::build_duplicate_title( 'Hello', 'Copy of ', ' (Copy)', 'clone' )
		);
	}

	public function test_internal_meta_keys_include_rewrite_markers() {
		$keys = Oli_DP_Utils::default_internal_meta_keys();

		$this->assertContains( '_oli_dp_original_post', $keys );
		$this->assertContains( '_oli_dp_rewrite_copy', $keys );
		$this->assertContains( '_oli_dp_scheduled_gmt', $keys );
		$this->assertContains( '_dp_original', $keys );
		$this->assertContains( '_thumbnail_id', $keys );
	}

	public function test_gmt_mysql_is_future_uses_utc() {
		$now = 1700000000;

		$this->assertTrue( Oli_DP_Utils::gmt_mysql_is_future( gmdate( 'Y-m-d H:i:s', $now + 60 ), $now ) );
		$this->assertFalse( Oli_DP_Utils::gmt_mysql_is_future( gmdate( 'Y-m-d H:i:s', $now ), $now ) );
		$this->assertFalse( Oli_DP_Utils::gmt_mysql_is_future( gmdate( 'Y-m-d H:i:s', $now - 1 ), $now ) );
		$this->assertFalse( Oli_DP_Utils::gmt_mysql_is_future( '0000-00-00 00:00:00', $now ) );
	}

	public function test_sanitize_title_affix_keeps_leading_and_trailing_spaces() {
		$this->assertSame( 'Copy of ', Oli_DP_Utils::sanitize_title_affix( 'Copy of ' ) );
		$this->assertSame( ' (Copy)', Oli_DP_Utils::sanitize_title_affix( ' (Copy)' ) );
		$this->assertSame( 'Copy of ', Oli_DP_Utils::sanitize_title_affix( '<em>Copy of </em>' ) );
	}

	public function test_woocommerce_excludes_sales_and_ratings() {
		$keys = Oli_DP_Utils::woocommerce_excluded_meta_keys();

		$this->assertContains( 'total_sales', $keys );
		$this->assertContains( '_wc_average_rating', $keys );
		$this->assertContains( '_wc_review_count', $keys );
	}
}
