<?php
/**
 * OpenAI Responses API class.
 *
 * Handles communication with the OpenAI Responses API.
 *
 * @package WP-Autoplugin
 * @since 1.7.0
 * @version 1.7.0
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI Responses API class.
 */
class OpenAI_Responses_API extends OpenAI_API {

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.openai.com/v1/responses';

	/**
	 * Send a prompt to the API.
	 *
	 * @param string $prompt The prompt.
	 * @param string $system_message The system message.
	 * @param array  $override_body The override body.
	 *
	 * @return string|\WP_Error
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {
		$body = [
			'model' => $this->model,
			'input' => $prompt,
		];

		if ( ! empty( $system_message ) ) {
			$body['instructions'] = $system_message;
		}

		// Responses API uses max_output_tokens for output limits.
		if ( ! empty( $this->max_tokens ) ) {
			$body['max_output_tokens'] = $this->max_tokens;
		}

		if ( ! empty( $this->reasoning_effort ) ) {
			$body['reasoning'] = [
				'effort' => $this->reasoning_effort,
			];
		}

		// Ignore response_format for the Responses API (incompatible with text.format expectations).
		if ( isset( $override_body['response_format'] ) ) {
			unset( $override_body['response_format'] );
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

		if ( isset( $data['error'] ) ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Error communicating with the API.', 'wp-autoplugin' );
			return new \WP_Error( 'api_error', $message );
		}

		// Extract token usage for reporting.
		$this->last_token_usage = $this->extract_token_usage( $data, 'openai' );

		if ( isset( $data['output_text'] ) && ! empty( $data['output_text'] ) ) {
			return $data['output_text'];
		}

		if ( isset( $data['output'] ) && is_array( $data['output'] ) ) {
			$output_text = '';

			foreach ( $data['output'] as $item ) {
				if ( ! isset( $item['content'] ) || ! is_array( $item['content'] ) ) {
					continue;
				}

				foreach ( $item['content'] as $content_item ) {
					if ( isset( $content_item['type'], $content_item['text'] ) && 'output_text' === $content_item['type'] ) {
						$output_text .= $content_item['text'];
					}
				}
			}

			if ( ! empty( $output_text ) ) {
				return $output_text;
			}
		}

		return new \WP_Error( 'api_error', 'Error communicating with the API.' . "\n" . print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}

	/**
	 * Get the allowed parameters.
	 *
	 * @return array The allowed parameters.
	 */
	protected function get_allowed_parameters() {
		return [
			'model',
			'instructions',
			'input',
			'top_p',
			'max_output_tokens',
			'metadata',
			'reasoning',
			'modalities',
			'previous_response_id',
			'store',
			'text',
			'audio',
			'attachments',
			'tools',
			'tool_choice',
			'parallel_tool_calls',
		];
	}
}
