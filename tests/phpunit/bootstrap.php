<?php
/**
 * PHPUnit bootstrap.
 *
 * Unit tests load plugin helpers without WordPress. Integration tests load
 * the official WordPress test suite when WP_TESTS_DIR is available.
 *
 * @package Oli_Duplicate_Post
 */

$vendor = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( file_exists( $vendor ) ) {
	require_once $vendor;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/oli-dp-abspath/' );
}

require_once dirname( __DIR__, 2 ) . '/includes/class-oli-dp-utils.php';

$tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $tests_dir ) {
	$tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( file_exists( $tests_dir . '/includes/functions.php' ) ) {
	define( 'OLIDP_HAS_WP_TESTS', true );

	require_once $tests_dir . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () {
			require dirname( __DIR__, 2 ) . '/oli-duplicate-post.php';
		}
	);

	require $tests_dir . '/includes/bootstrap.php';
} else {
	define( 'OLIDP_HAS_WP_TESTS', false );
}
