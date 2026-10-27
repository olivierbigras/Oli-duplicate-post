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
delete_option( 'oli_dp_db_version' );

// Clear every scheduled Rewrite & Republish cleanup event, regardless of args.
wp_unschedule_hook( 'oli_dp_delete_rewrite_copy' );

global $wpdb;

// `_oli_dp_source_post` replaced `_dp_original` in 1.0.10. The legacy `_dp_original` rows are not
// deleted: Yoast Duplicate Post writes the same key, so removing them could break its data.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		'DELETE FROM %i WHERE meta_key IN (%s, %s, %s, %s, %s)',
		$wpdb->postmeta,
		'_oli_dp_source_post',
		'_oli_dp_original_post',
		'_oli_dp_rewrite_copy',
		'_oli_dp_scheduled_gmt',
		'_oli_dp_pending_delete'
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
		$wpdb->options,
		$wpdb->esc_like( '_transient_oli_dp_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_oli_dp_' ) . '%'
	)
);
