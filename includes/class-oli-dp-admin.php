<?php
/**
 * Admin interface initialization.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues admin assets on plugin-related screens only.
 *
 * @since 1.0.0
 */
class Oli_DP_Admin {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_filter( 'plugin_action_links_' . OLI_DP_PLUGIN_BASENAME, array( __CLASS__, 'add_plugin_action_links' ) );
	}

	/**
	 * Enqueue admin styles on this plugin's screens.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( ! Oli_DP_Utils::should_enqueue_admin_assets( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'oli-dp-admin',
			OLI_DP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			OLI_DP_VERSION
		);
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public static function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=oli-duplicate-post-settings' ) ),
			esc_html__( 'Settings', 'oli-duplicate-post' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
