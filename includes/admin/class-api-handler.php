<?php
/**
 * WP-Autoplugin API Handler class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Admin;

use WP_Autoplugin\API;
use WP_Autoplugin\OpenAI_API;
use WP_Autoplugin\Anthropic_API;
use WP_Autoplugin\Google_Gemini_API;
use WP_Autoplugin\XAI_API;
use WP_Autoplugin\Custom_API;
use WP_Autoplugin\Admin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles the API selection.
 */
class Api_Handler {

	/**
	 * The API object.
	 *
	 * @var API
	 */
	public $ai_api;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$model        = get_option( 'wp_autoplugin_model' );
		$this->ai_api = $this->get_api( $model );
	}

	/**
	 * Get the API object based on the selected model or a custom model.
	 *
	 * @param string $model The model to use.
	 *
	 * @return API|null
	 */
	public function get_api( $model ) {
		$openai_api_key    = get_option( 'wp_autoplugin_openai_api_key' );
		$anthropic_api_key = get_option( 'wp_autoplugin_anthropic_api_key' );
		$google_api_key    = get_option( 'wp_autoplugin_google_api_key' );
		$xai_api_key       = get_option( 'wp_autoplugin_xai_api_key' );
		$custom_models     = get_option( 'wp_autoplugin_custom_models', [] );

		$api = null;

		if ( ! empty( $openai_api_key ) && array_key_exists( $model, Admin::get_models()['OpenAI'] ) ) {
			$api = new OpenAI_API();
			$api->set_api_key( $openai_api_key );
			$api->set_model( $model );
		} elseif ( ! empty( $anthropic_api_key ) && array_key_exists( $model, Admin::get_models()['Anthropic'] ) ) {
			$api = new Anthropic_API();
			$api->set_api_key( $anthropic_api_key );
			$api->set_model( $model );
		} elseif ( ! empty( $google_api_key ) && array_key_exists( $model, Admin::get_models()['Google'] ) ) {
			$api = new Google_Gemini_API();
			$api->set_api_key( $google_api_key );
			$api->set_model( $model );
		} elseif ( ! empty( $xai_api_key ) && array_key_exists( $model, Admin::get_models()['xAI'] ) ) {
			$api = new XAI_API();
			$api->set_api_key( $xai_api_key );
			$api->set_model( $model );
		}

		// Check custom models.
		if ( ! empty( $custom_models ) ) {
			foreach ( $custom_models as $custom_model ) {
				if ( $custom_model['name'] === $model ) {
					$api = new Custom_API();
					$api->set_custom_config(
						$custom_model['url'],
						$custom_model['apiKey'],
						$custom_model['modelParameter'],
						$custom_model['headers']
					);
					return $api;
				}
			}
		}

		return $api;
	}

	/**
	 * Get the planner model or fall back to default model.
	 *
	 * @return string
	 */
	public function get_planner_model() {
		$planner_model = get_option( 'wp_autoplugin_planner_model' );
		return ! empty( $planner_model ) ? $planner_model : get_option( 'wp_autoplugin_model' );
	}

	/**
	 * Get the coder model or fall back to default model.
	 *
	 * @return string
	 */
	public function get_coder_model() {
		$coder_model = get_option( 'wp_autoplugin_coder_model' );
		return ! empty( $coder_model ) ? $coder_model : get_option( 'wp_autoplugin_model' );
	}

	/**
	 * Get the reviewer model or fall back to default model.
	 *
	 * @return string
	 */
	public function get_reviewer_model() {
		$reviewer_model = get_option( 'wp_autoplugin_reviewer_model' );
		return ! empty( $reviewer_model ) ? $reviewer_model : get_option( 'wp_autoplugin_model' );
	}

	/**
	 * Get the API object for planner tasks.
	 *
	 * @return API|null
	 */
	public function get_planner_api() {
		return $this->get_api( $this->get_planner_model() );
	}

	/**
	 * Get the API object for coder tasks.
	 *
	 * @return API|null
	 */
	public function get_coder_api() {
		return $this->get_api( $this->get_coder_model() );
	}

	/**
	 * Get the API object for reviewer tasks.
	 *
	 * @return API|null
	 */
	public function get_reviewer_api() {
		return $this->get_api( $this->get_reviewer_model() );
	}

	/**
	 * Get the model that will be used for the next task based on current page.
	 *
	 * @return string
	 */
	public function get_next_task_model() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return get_option( 'wp_autoplugin_model' );
		}

		switch ( $screen->id ) {
			case 'wp-autoplugin_page_wp-autoplugin-generate':
				return $this->get_planner_model();
			case 'admin_page_wp-autoplugin-fix':
				return $this->get_planner_model();
			case 'admin_page_wp-autoplugin-extend':
				return $this->get_planner_model();
			case 'admin_page_wp-autoplugin-extend-hooks':
				return $this->get_planner_model();
			case 'admin_page_wp-autoplugin-extend-theme':
				return $this->get_planner_model();
			case 'admin_page_wp-autoplugin-explain':
				return $this->get_reviewer_model();
			default:
				return get_option( 'wp_autoplugin_model' );
		}
	}
}
