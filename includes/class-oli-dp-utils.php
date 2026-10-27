<?php
/**
 * Shared helpers for Oli Duplicate Post.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure helpers used by duplication, settings, and tests.
 *
 * @since 1.0.0
 */
class Oli_DP_Utils {

	/**
	 * Meta key storing the source post ID on every copy.
	 *
	 * Replaces the unprefixed `_dp_original` key written up to 1.0.9.
	 *
	 * @since 1.0.10
	 */
	const SOURCE_META_KEY = '_oli_dp_source_post';

	/**
	 * Option storing the data schema version.
	 *
	 * @since 1.0.10
	 */
	const DB_VERSION_OPTION = 'oli_dp_db_version';

	/**
	 * Run pending data migrations once per version (idempotent).
	 *
	 * @since 1.0.10
	 */
	public static function maybe_upgrade() {
		if ( version_compare( (string) get_option( self::DB_VERSION_OPTION, '0' ), '1.0.10', '>=' ) ) {
			return;
		}

		self::migrate_source_meta();
		update_option( self::DB_VERSION_OPTION, '1.0.10', false );
	}

	/**
	 * Copy legacy `_dp_original` values to `_oli_dp_source_post`.
	 *
	 * Only copies rows that do not have the new key yet, so running it twice
	 * changes nothing. The legacy rows are left in place on purpose: Yoast
	 * Duplicate Post writes the same key, and deleting it could break that
	 * plugin's data. This plugin no longer writes or reads `_dp_original`.
	 *
	 * @since 1.0.10
	 *
	 * @return int Number of copied rows.
	 */
	public static function migrate_source_meta() {
		global $wpdb;

		$copied     = 0;
		$batch_size = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration batch.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT legacy.post_id, legacy.meta_value FROM %i legacy
					LEFT JOIN %i newkey ON newkey.post_id = legacy.post_id AND newkey.meta_key = %s
					WHERE legacy.meta_key = %s AND newkey.meta_id IS NULL
					LIMIT %d',
					$wpdb->postmeta,
					$wpdb->postmeta,
					self::SOURCE_META_KEY,
					'_dp_original',
					200
				)
			);

			if ( empty( $rows ) ) {
				break;
			}

			$batch_size  = count( $rows );
			$batch_added = 0;
			foreach ( $rows as $row ) {
				$source_id = absint( $row->meta_value );
				$post_id   = absint( $row->post_id );
				if ( ! $post_id ) {
					continue;
				}
				// A zero or invalid legacy value is stored as 0 so the row is not selected again.
				if ( add_post_meta( $post_id, self::SOURCE_META_KEY, $source_id, true ) ) {
					++$batch_added;
				}
			}
			$copied += $batch_added;
		} while ( 200 === $batch_size && $batch_added > 0 );

		return $copied;
	}


	/**
	 * Parse a comma-separated list or array into a trimmed list of values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	public static function parse_list( $value ) {
		if ( is_array( $value ) ) {
			$items = $value;
		} else {
			$items = explode( ',', (string) $value );
		}

		$items = array_map( 'trim', $items );
		$items = array_filter(
			$items,
			static function ( $item ) {
				return '' !== $item;
			}
		);

		return array_values( $items );
	}

	/**
	 * Whether a value matches a wildcard pattern (* and ?).
	 *
	 * @since 1.0.0
	 *
	 * @param string $value   Value to test.
	 * @param string $pattern Wildcard pattern.
	 * @return bool
	 */
	public static function matches_pattern( $value, $pattern ) {
		$regex = '/^' . str_replace( array( '\*', '\?' ), array( '.*', '.' ), preg_quote( $pattern, '/' ) ) . '$/';

		return (bool) preg_match( $regex, $value );
	}

	/**
	 * Whether a meta key matches any excluded pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $meta_key      Meta key.
	 * @param string[] $excluded_keys Patterns (wildcards allowed).
	 * @return bool
	 */
	public static function should_exclude_meta( $meta_key, $excluded_keys ) {
		foreach ( (array) $excluded_keys as $pattern ) {
			if ( '' === $pattern ) {
				continue;
			}

			if ( self::matches_pattern( $meta_key, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the duplicated title.
	 *
	 * Rewrite copies keep the original title so the merge can preserve branding.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title  Original title.
	 * @param string $prefix Prefix.
	 * @param string $suffix Suffix.
	 * @param string $mode   Duplication mode.
	 * @return string
	 */
	public static function build_duplicate_title( $title, $prefix, $suffix, $mode ) {
		if ( 'rewrite_republish' === $mode ) {
			return $title;
		}

		return $prefix . $title . $suffix;
	}

	/**
	 * Whether a MySQL GMT datetime is still in the future (UTC).
	 *
	 * Uses `time()` and `mysql2date( 'U', $gmt, false )` so local timezone
	 * offsets cannot make a +60s schedule look like "now".
	 *
	 * @since 1.0.2
	 *
	 * @param string   $gmt MySQL GMT datetime.
	 * @param int|null $now UTC timestamp, or null for time().
	 * @return bool
	 */
	public static function gmt_mysql_is_future( $gmt, $now = null ) {
		if ( ! is_string( $gmt ) || '' === $gmt || '0000-00-00 00:00:00' === $gmt ) {
			return false;
		}

		if ( null === $now ) {
			$now = time();
		}

		if ( function_exists( 'mysql2date' ) ) {
			$timestamp = mysql2date( 'U', $gmt, false );
		} else {
			$timestamp = strtotime( $gmt . ' UTC' );
		}

		if ( ! $timestamp ) {
			return false;
		}

		return (int) $timestamp > (int) $now;
	}

	/**
	 * Sanitize a title prefix or suffix without trimming spaces.
	 *
	 * `sanitize_text_field()` strips leading and trailing whitespace, which
	 * would turn "Copy of " into "Copy of" and join it against the title.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed $value Raw prefix or suffix.
	 * @return string
	 */
	public static function sanitize_title_affix( $value ) {
		if ( ! is_string( $value ) ) {
			$value = (string) $value;
		}

		if ( function_exists( 'wp_unslash' ) ) {
			$value = wp_unslash( $value );
		}

		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$value = wp_check_invalid_utf8( $value );
		}

		// wp_strip_all_tags() and sanitize_text_field() both trim. wp_kses()
		// strips markup while keeping leading and trailing spaces.
		if ( function_exists( 'wp_kses' ) ) {
			$value = wp_kses( $value, array() );
		} else {
			$value = strip_tags( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		}

		return $value;
	}

	/**
	 * Original post ID stored on a duplicate.
	 *
	 * @since 1.0.1
	 *
	 * @param int $post_id Duplicate post ID.
	 * @return int
	 */
	public static function get_original_post_id( $post_id ) {
		$original_id = absint( get_post_meta( $post_id, self::SOURCE_META_KEY, true ) );
		if ( $original_id ) {
			return $original_id;
		}

		return absint( get_post_meta( $post_id, '_oli_dp_original_post', true ) );
	}

	/**
	 * Internal meta keys that must never be copied.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function default_internal_meta_keys() {
		return array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_old_date',
			'_thumbnail_id',
			'_dp_original',
			self::SOURCE_META_KEY,
			'_oli_dp_original_post',
			'_oli_dp_rewrite_copy',
			'_oli_dp_scheduled_gmt',
			'_oli_dp_pending_delete',
		);
	}

	/**
	 * WooCommerce analytics / review meta that should not be cloned.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function woocommerce_excluded_meta_keys() {
		return array(
			'total_sales',
			'_wc_average_rating',
			'_wc_rating_count',
			'_wc_review_count',
		);
	}

	/**
	 * Whether the current admin screen may show this plugin's notices.
	 *
	 * Guideline 11: notices stay on the plugin settings page, comparison
	 * screen, or post list/edit screens that the current request is using.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context settings|compare|post_edit|post_list|any_plugin.
	 * @return bool
	 */
	public static function allows_admin_notice( $context = 'any_plugin' ) {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		switch ( $context ) {
			case 'settings':
				return 'settings_page_oli-duplicate-post-settings' === $screen->id;
			case 'compare':
				return 'admin_page_oli-dp-compare' === $screen->id;
			case 'post_edit':
				return 'post' === $screen->base;
			case 'post_list':
				return 'edit' === $screen->base;
			default:
				return (
					in_array( $screen->id, array( 'settings_page_oli-duplicate-post-settings', 'admin_page_oli-dp-compare' ), true )
					|| in_array( $screen->base, array( 'post', 'edit' ), true )
				);
		}
	}

	/**
	 * Whether an admin asset hook belongs to this plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	public static function should_enqueue_admin_assets( $hook ) {
		$allowed_hooks = array(
			'settings_page_oli-duplicate-post-settings',
			'admin_page_oli-dp-compare',
			'post.php',
			'post-new.php',
			'edit.php',
		);

		return in_array( $hook, $allowed_hooks, true );
	}
}
