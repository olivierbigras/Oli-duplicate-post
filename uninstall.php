<?php
/**
 * Uninstall cleanup for Oli Duplicate Post.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly or if uninstall was not requested.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'oli_dp_settings' );

global $wpdb;

// Do not delete `_dp_original`: Yoast Duplicate Post uses the same key.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s)",
		'_oli_dp_original_post',
		'_oli_dp_rewrite_copy',
		'_oli_dp_scheduled_gmt',
		'_oli_dp_pending_delete'
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_oli_dp_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_oli_dp_' ) . '%'
	)
);
