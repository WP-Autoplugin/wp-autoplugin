<?php
/**
 * Google Gemini API class.
 *
 * @package WP-Autoplugin
 * @since 1.0.0
 * @version 1.0.5
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Gemini API class.
 */
class Google_Gemini_API extends API {

	/**
	 * Selected model.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Temperature parameter.
	 *
	 * @var float
	 */
	private $temperature = 0.2;

	/**
	 * Max tokens parameter.
	 *
	 * @var int
	 */
	private $max_tokens = 8192;

	/**
	 * Set the model.
	 *
	 * @param string $model The model.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	/**
	 * Send a prompt to the API.
	 *
	 * @param string $prompt         The prompt.
	 * @param string $system_message The system message.
	 * @param array  $override_body  The override body.
	 * @return mixed
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {
		$prompt = $this->trim_prompt( $prompt );

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->api_key;

		// Default safetySettings.
		$safety_settings = [
			[
				'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
				'threshold' => 'BLOCK_ONLY_HIGH',
			],
		];

		// Default generationConfig.
		$generation_config = [
			'temperature'     => $this->temperature,
			'maxOutputTokens' => $this->max_tokens,
		];

		// Merge override_body['generationConfig'] into $generation_config.
		if ( isset( $override_body['generationConfig'] ) && is_array( $override_body['generationConfig'] ) ) {
			$generation_config = array_merge( $generation_config, $override_body['generationConfig'] );
			unset( $override_body['generationConfig'] );
		}

		// Override safetySettings if provided.
		if ( isset( $override_body['safetySettings'] ) && is_array( $override_body['safetySettings'] ) ) {
			$safety_settings = $override_body['safetySettings'];
			unset( $override_body['safetySettings'] );
		}

		// Build the request body.
		$body = [
			'contents'         => [
				[
					'parts' => [
						[ 'text' => $prompt ],
					],
				],
			],
			'safetySettings'   => $safety_settings,
			'generationConfig' => $generation_config,
		];

		$headers = [
			'Content-Type' => 'application/json',
		];

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 60,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['candidates'][0]['content']['parts'] ) ) {
			return new \WP_Error( 'api_error', 'Error communicating with the Google Gemini API.' . "\n" . print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		$parts     = $data['candidates'][0]['content']['parts'];
		$last_part = end( $parts );
		return $last_part['text'];
	}
}
