<?php
/**
 * WP-Autoplugin Admin Settings class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that registers plugin settings in the admin.
 */
class Settings {

	/**
	 * Constructor hooks into 'admin_init'.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register the plugin settings fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_openai_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_anthropic_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_google_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_xai_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_model' );
	}
}
