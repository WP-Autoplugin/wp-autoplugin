<?php

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anthropic_API {
	private $api_key;
	private $model;
	private $temperature = 0.2;
	private $max_tokens  = 4096;

	public function set_api_key( $api_key ) {
		$this->api_key = sanitize_text_field( $api_key );
	}

	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );

		// Set the temperature and max tokens based on the model.
		$model_params = array(
			'claude-3-5-sonnet-20240620' => array(
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			),
			'claude-3-opus-20240229' => array(
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			),
			'claude-3-sonnet-20240229' => array(
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			),
			'claude-3-haiku-20240307' => array(
				'temperature' => 0.2,
				'max_tokens'  => 4096,
			),
		);

		if ( isset( $model_params[ $model ] ) ) {
			$this->temperature = $model_params[ $model ]['temperature'];
			$this->max_tokens  = $model_params[ $model ]['max_tokens'];
		}
	}

	/*
	Example request:
	curl https://api.anthropic.com/v1/messages \
		--header "x-api-key: $ANTHROPIC_API_KEY" \
		--header "anthropic-version: 2023-06-01" \
		--header "content-type: application/json" \
		--data \
	'{
		"model": "claude-3-5-sonnet-20240620",
		"max_tokens": 1024,
		"messages": [
			{"role": "user", "content": "Hello, world"}
		]
	}'

	Successful response:
	{
		"content": [
			{
			"text": "Hi! My name is Claude.",
			"type": "text"
			}
		],
		"id": "msg_013Zva2CMHLNnXjNJJKqJ2EF",
		"model": "claude-3-5-sonnet-20240620",
		"role": "assistant",
		"stop_reason": "end_turn",
		"stop_sequence": null,
		"type": "message",
		"usage": {
			"input_tokens": 10,
			"output_tokens": 25
		}
	}
	 */
	public function send_prompt( $prompt, $system_message = '', $override_body = array() ) {
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
		$allowed_keys = array( 'model', 'temperature', 'max_tokens', 'messages' );
		$override_body = array_intersect_key( $override_body, array_flip( $allowed_keys ) );
		$body = array_merge( $body, $override_body );

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 60,
			'headers' => array(
				'x-api-key' => $this->api_key,
				'anthropic-version' => '2023-06-01',
				'content-type' => 'application/json',
			),
			'body' => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body, true );

		// If stop_reason is "max_tokens", the response is too long.
		// We need to send a new request with the whole conversation so far, so the AI can continue from where it left off.
		if ( isset( $data['stop_reason'] ) && 'max_tokens' === $data['stop_reason'] ) {
			$messages[] = array(
				'role'    => 'assistant',
				'content' => $data['content'][0]['text'],
			);

			$messages[] = array(
				'role'    => 'user',
				'content' => 'Continue exactly from where you left off.',
			);

			$body = array(
				'model'       => $this->model,
				'temperature' => $this->temperature,
				'max_tokens'  => $this->max_tokens,
				'messages'    => $messages,
			);

			$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
				'timeout' => 60,
				'headers' => array(
					'x-api-key' => $this->api_key,
					'anthropic-version' => '2023-06-01',
					'content-type' => 'application/json',
				),
				'body' => wp_json_encode( $body ),
			) );

error_log( 'Sent a new request because the response was too long.' );
error_log( print_r( $body, true ) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body = wp_remote_retrieve_body( $response );

			$new_data = json_decode( $body, true );

			if ( ! isset( $new_data['content'][0]['text'] ) ) {
				return new \WP_Error( 'api_error', 'Error communicating with the Anthropic API.' . "\n" . print_r( $new_data, true ) );
			}

			// Merge the new response with the old one.
			$data['content'][0]['text'] .= $new_data['content'][0]['text'];
		}

		if ( isset( $data['content'][0]['text'] ) ) {
			return $data['content'][0]['text'];
		} else {
			return new \WP_Error( 'api_error', 'Error communicating with the Anthropic API.' . "\n" . print_r( $data, true ) );
		}
	}
}