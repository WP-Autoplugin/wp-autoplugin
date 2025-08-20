<?php
/**
 * Custom API class.
 *
 * @package WP-Autoplugin
 * @since 1.2
 * @version 1.2
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom API class that connects to user-defined OpenAI-compatible endpoints.
 */
class Custom_API extends OpenAI_API {

	/**
	 * Additional headers specified by the user.
	 *
	 * @var array
	 */
	protected $extra_headers = [];

	/**
	 * Configure the custom API with the user-defined settings.
	 *
	 * @param string $endpoint   The custom API endpoint (url).
	 * @param string $api_key    The API key for authentication.
	 * @param string $model      The model parameter sent to the API.
	 * @param array  $headers    Additional headers (key/value pairs).
	 */
	public function set_custom_config( $endpoint, $api_key, $model, $headers = [] ) {
		$this->api_url       = $endpoint;
		$this->api_key       = $api_key;
		$this->model         = $model;
		$this->extra_headers = $this->parse_extra_headers( $headers );
	}

	/**
	 * Override the send_prompt to include user-defined headers.
	 *
	 * @param string $prompt         The user prompt.
	 * @param string $system_message Optional system message.
	 * @param array  $override_body  Optional parameters to override in the request body.
	 *
	 * @return string|\WP_Error The response or a WP_Error object on failure.
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = [] ) {
		$messages = [];
		if ( $system_message ) {
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
			'model'       => $this->model,
			'temperature' => $this->temperature,
			'max_tokens'  => $this->max_tokens,
			'messages'    => $messages,
		];

		// Only keep valid keys from $override_body.
		$allowed_keys  = $this->get_allowed_parameters();
		$override_body = array_intersect_key( $override_body, array_flip( $allowed_keys ) );
		$body          = array_merge( $body, $override_body );

		// Merge default auth header with any extra headers.
		$headers = array_merge(
			[
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			$this->extra_headers
		);

		$response = wp_remote_post(
			$this->api_url,
			[
				'timeout' => 300,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// Extract token usage for reporting.
		$this->last_token_usage = $this->extract_token_usage( $data, 'custom' );

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new \WP_Error(
				'api_error',
				__( 'Error communicating with the API.', 'wp-autoplugin' ) . "\n" . print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			);
		}

		return $data['choices'][0]['message']['content'];
	}

	/**
	 * Convert the user’s header lines into an associative array.
	 *
	 * @param array $headers Array of lines like ["X-Test=Value", "Accept=application/json"].
	 * @return array Key-value pairs for use in wp_remote_post header.
	 */
	protected function parse_extra_headers( $headers ) {
		$parsed = [];
		foreach ( $headers as $header_line ) {
			if ( strpos( $header_line, '=' ) !== false ) {
				list( $key, $value ) = explode( '=', $header_line, 2 );
				$key                 = trim( $key );
				$value               = trim( $value );
				if ( $key && $value ) {
					$parsed[ $key ] = $value;
				}
			}
		}
		return $parsed;
	}
}
