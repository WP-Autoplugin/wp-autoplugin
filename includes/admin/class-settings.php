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
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_planner_model' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_coder_model' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_reviewer_model' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_plugin_mode' );

		// Advanced AI Parameters.
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_max_tokens', [
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
		] );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_temperature', [
			'type'              => 'number',
			'default'           => 0,
			'sanitize_callback' => [ $this, 'sanitize_temperature' ],
		] );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_top_p', [
			'type'              => 'number',
			'default'           => 0,
			'sanitize_callback' => [ $this, 'sanitize_top_p' ],
		] );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_seed', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		] );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_stop_sequences' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_response_format', [
			'type'    => 'string',
			'default' => '',
		] );
	}

	/**
	 * Sanitize temperature value.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return float
	 */
	public function sanitize_temperature( $value ) {
		$value = floatval( $value );
		if ( $value < 0 || $value > 2 ) {
			return 0;
		}
		return $value;
	}

	/**
	 * Sanitize top_p value.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return float
	 */
	public function sanitize_top_p( $value ) {
		$value = floatval( $value );
		if ( $value < 0 || $value > 1 ) {
			return 0;
		}
		return $value;
	}
}
