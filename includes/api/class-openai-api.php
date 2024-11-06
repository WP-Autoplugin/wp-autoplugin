<?php
/**
 * OpenAI API class.
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

class OpenAI_API extends API {

	/**
	 * Selected model.
	 *
	 * @var string
	 */
	protected $model;

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
	protected $max_tokens  = 4096;

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
		$this->model = sanitize_text_field( $model );

		// Set the temperature and max tokens based on the model.
		$model_params = array(
			'gpt-4o' => array(
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			),
			'chatgpt-4o-latest' => array(
				'temperature' => 0.2,
				'max_tokens'  => 16384,
			),
			'gpt-4o-mini' => array(
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			),
			'gpt-4-turbo' => array(
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			),
			'gpt-3.5-turbo' => array(
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			),
		);

		if ( isset( $model_params[ $model ] ) ) {
			$this->temperature = $model_params[ $model ]['temperature'];
			$this->max_tokens  = $model_params[ $model ]['max_tokens'];
		}
	}

	/**
	 * Send a prompt to the API.
	 *
	 * @param string $prompt The prompt.
	 * @param string $system_message The system message.
	 * @param array  $override_body The override body.
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = array() ) {
		$prompt = $this->trim_prompt( $prompt );
		$messages = array();
		if ( ! empty( $system_message ) ) {
			$messages[] = array(
				'role' => 'system',
				'content' => $system_message,
			);
		}

		$messages[] = array(
			'role' => 'user',
			'content' => $prompt,
		);

		$body = array(
			'model'       => $this->model,
			'temperature' => $this->temperature,
			'max_tokens'  => $this->max_tokens,
			'messages'    => $messages,
		);

		// Keep only allowed keys in the override body.
		$allowed_keys = $this->get_allowed_parameters();
		$override_body = array_intersect_key( $override_body, array_flip( $allowed_keys ) );
		$body = array_merge( $body, $override_body );

		$response = wp_remote_post( $this->api_url, array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body, true );

		// "Continue" functionality:
		// If finish_reason is "length", the response is too long.
		// We need to send a new request with the whole conversation so far, so the AI can continue from where it left off.
		if ( isset( $data['choices'][0]['finish_reason'] ) && 'length' === $data['choices'][0]['finish_reason'] ) {
			$messages[] = array(
				'role' => 'assistant',
				'content' => $data['choices'][0]['message']['content'],
			);

			$body = array(
				'model'       => $this->model,
				'temperature' => $this->temperature,
				'max_tokens'  => $this->max_tokens,
				'messages'    => $messages,
			);

			$response = wp_remote_post( $this->api_url, array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( $body ),
			) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body = wp_remote_retrieve_body( $response );

			$new_data = json_decode( $body, true );

			if ( ! isset( $new_data['choices'][0]['message']['content'] ) ) {
				return new \WP_Error( 'api_error', 'Error communicating with the API.' . "\n" . print_r( $new_data, true ) );
			}

			// Merge the new response with the old one.
			$data['choices'][0]['message']['content'] .= $new_data['choices'][0]['message']['content'];
		}

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return $data['choices'][0]['message']['content'];
		} else {
			return new \WP_Error( 'api_error', 'Error communicating with the API.' . "\n" . print_r( $data, true ) );
		}
	}

	/**
	 * Get the allowed parameters.
	 *
	 * @return array The allowed parameters.
	 */
	protected function get_allowed_parameters() {
		return array(
			'model',
			'temperature',
			'max_tokens',
			'messages',
			'response_format',
		);
	}
}
