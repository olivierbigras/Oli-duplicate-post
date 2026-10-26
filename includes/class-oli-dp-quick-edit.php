<?php
/**
 * Quick edit integration.
 *
 * @package Oli_Duplicate_Post
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a Copy to New Draft control inside Quick Edit.
 *
 * @since 1.0.0
 */
class Oli_DP_Quick_Edit {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue Quick Edit script on list tables.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$settings = Oli_DP_Settings::get_settings();
		if ( empty( $settings['show_row_actions'] ) ) {
			return;
		}

		wp_enqueue_style(
			'oli-dp-admin',
			OLI_DP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			OLI_DP_VERSION
		);

		wp_enqueue_script(
			'oli-dp-quick-edit',
			OLI_DP_PLUGIN_URL . 'assets/js/quick-edit.js',
			array( 'jquery' ),
			OLI_DP_VERSION,
			true
		);

		wp_localize_script(
			'oli-dp-quick-edit',
			'oliDpQuickEdit',
			array(
				'copyToNewDraft' => __( 'Copy to New Draft', 'oli-duplicate-post' ),
			)
		);
	}
}
