<?php
/**
 * OpenAI API class.
 *
 * Handles communication with the OpenAI API.
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
 * OpenAI API class.
 */
class OpenAI_API extends API {

	/**
	 * Selected model.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * Original model name for special cases like o3-mini variants.
	 *
	 * @var string
	 */
	protected $original_model;

	/**
	 * Temperature parameter.
	 *
	 * @var float
	 */
	protected $temperature = 0.2;

	/**
	 * Max tokens parameter.
	 *
	 * @var int
	 */
	protected $max_tokens = 4096;

	/**
	 * Reasoning effort for o3-mini models.
	 *
	 * @var string
	 */
	protected $reasoning_effort = '';

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Set the model, temperature, and max tokens.
	 *
	 * @param string $model The model.
	 */
	public function set_model( $model ) {
		$this->original_model = sanitize_text_field( $model );

		// Handle o3-mini model variants.
		if ( in_array( $model, [ 'o3-mini-low', 'o3-mini-medium', 'o3-mini-high', 'o3-low', 'o3-medium', 'o3-high', 'o4-mini-low', 'o4-mini-medium', 'o4-mini-high' ], true ) ) {

			// Extract the base model name (e.g., o3-mini, o3, o4-mini).
			$base_model  = preg_replace( '/-low|-medium|-high$/', '', $model );
			$this->model = $base_model;

			// Extract reasoning effort from model name.
			$parts                  = explode( '-', $model );
			$this->reasoning_effort = end( $parts );
		} else {
			$this->model = $this->original_model;
		}

		// Set the temperature and max tokens based on the model.
		$model_params = [
			'o3-mini-low'       => [
				'max_tokens'       => 100000,
				'reasoning_effort' => 'low',
			],
			'o3-mini-medium'    => [
				'max_tokens'       => 100000,
				'reasoning_effort' => 'medium',
			],
			'o3-mini-high'      => [
				'max_tokens'       => 100000,
				'reasoning_effort' => 'high',
			],
			'o3-low'       => [
				'max_tokens'       => 100000,
				'reasoning_effort' => 'low',
			],
			'o3-medium'    => [
				'max_tokens'       => 100000,
				'reasoning_effort' => 'medium',
			],
			'o3-high'      => [
				'max_tokens'       => 100000,
				'reasoning_effort' => 'high',
			],
			'o4-mini-low'       => [
				'max_tokens'       => 100000,
				'reasoning_effort' => 'low',
			],
			'o4-mini-medium'    => [
				'max_tokens'       => 100000,
				'reasoning_effort' => 'medium',
			],
			'o4-mini-high'      => [
				'max_tokens'       => 100000,
				'reasoning_effort' => 'high',
			],
			'o1'                => [
				'max_tokens' => 32000,
			],
			'o1-preview'        => [
				'max_tokens' => 32000,
			],
			'gpt-4o'            => [
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			],
			'gpt-4.1'           => [
				'temperature' => 0.2,
				'max_tokens'  => 32768,
			],
			'gpt-4.1-mini'      => [
				'temperature' => 0.2,
				'max_tokens'  => 32768,
			],
			'gpt-4.1-nano'      => [
				'temperature' => 0.2,
				'max_tokens'  => 32768,
			],
			'chatgpt-4o-latest' => [
				'temperature' => 0.2,
				'max_tokens'  => 16384,
			],
			'gpt-4o-mini'       => [
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			],
			'gpt-4-turbo'       => [
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			],
			'gpt-3.5-turbo'     => [
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			],
		];

		if ( isset( $model_params[ $this->original_model ] ) ) {
			if ( isset( $model_params[ $this->original_model ]['temperature'] ) ) {
				$this->temperature = $model_params[ $this->original_model ]['temperature'];
			}
			$this->max_tokens = $model_params[ $this->original_model ]['max_tokens'];

			if ( isset( $model_params[ $this->original_model ]['reasoning_effort'] ) ) {
				$this->reasoning_effort = $model_params[ $this->original_model ]['reasoning_effort'];
			}
		}
	}

	/**
	 * Send a prompt to the API.
	 *
	 * @param string $prompt The prompt.
	 * @param string $system_message The system message.
	 * @param array  $override_body The override body.
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {
		$messages = [];
		if ( ! empty( $system_message ) ) {
			$messages[] = [
				'role'    => 'system',
				'content' => $system_message,
			];
		}

		$messages[] = [
			'role'    => 'user',
			'content' => $prompt,
		];

		$body = [
			'model'    => $this->model,
			'messages' => $messages,
		];

		// Handle special case for o3-mini-* models.
		if ( in_array( $this->model, [ 'o3-mini', 'o3', 'o4-mini' ], true ) ) {
			$body['max_completion_tokens'] = $this->max_tokens;
			$body['reasoning_effort']      = $this->reasoning_effort;
		} elseif ( 'o1' === $this->model || 'o1-preview' === $this->model ) {
			$body['max_completion_tokens'] = $this->max_tokens;
		} else {
			$body['temperature'] = $this->temperature;
			$body['max_tokens']  = $this->max_tokens;
		}

		// Keep only allowed keys in the override body.
		$allowed_keys  = $this->get_allowed_parameters();
		$override_body = array_intersect_key( $override_body, array_flip( $allowed_keys ) );
		$body          = array_merge( $body, $override_body );

		$response = wp_remote_post(
			$this->api_url,
			[
				'timeout' => 300,
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body, true );

		// "Continue" functionality:
		// If finish_reason is "length", the response is too long.
		// We need to send a new request with the whole conversation so far, so the AI can continue from where it left off.
		if ( isset( $data['choices'][0]['finish_reason'] ) && 'length' === $data['choices'][0]['finish_reason'] ) {
			$messages[] = [
				'role'    => 'assistant',
				'content' => $data['choices'][0]['message']['content'],
			];

			$body = [
				'model'       => $this->model,
				'temperature' => $this->temperature,
				'max_tokens'  => $this->max_tokens,
				'messages'    => $messages,
			];

			$response = wp_remote_post(
				$this->api_url,
				[
					'timeout' => 300,
					'headers' => [
						'Authorization' => 'Bearer ' . $this->api_key,
						'Content-Type'  => 'application/json',
					],
					'body'    => wp_json_encode( $body ),
				]
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body = wp_remote_retrieve_body( $response );

			$new_data = json_decode( $body, true );

			if ( ! isset( $new_data['choices'][0]['message']['content'] ) ) {
				return new \WP_Error( 'api_error', 'Error communicating with the API.' . "\n" . print_r( $new_data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			// Merge the new response with the old one.
			$data['choices'][0]['message']['content'] .= $new_data['choices'][0]['message']['content'];
		}

		// Extract token usage for reporting.
		$this->last_token_usage = $this->extract_token_usage( $data, 'openai' );

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return $data['choices'][0]['message']['content'];
		} else {
			return new \WP_Error( 'api_error', 'Error communicating with the API.' . "\n" . print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}

	/**
	 * Get the allowed parameters.
	 *
	 * @return array The allowed parameters.
	 */
	protected function get_allowed_parameters() {
		return [
			'model',
			'temperature',
			'max_tokens',
			'max_completion_tokens',
			'reasoning_effort',
			'messages',
			'response_format',
		];
	}
}
