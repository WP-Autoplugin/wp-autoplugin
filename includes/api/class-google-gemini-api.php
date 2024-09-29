<?php

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Google_Gemini_API extends API {
	private $api_key;
	private $model;
	private $temperature = 0.2;
	private $max_tokens  = 8192;

	public function set_api_key( $api_key ) {
		$this->api_key = sanitize_text_field( $api_key );
	}

	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	public function send_prompt( $prompt, $system_message = '', $override_body = array() ) {
		$prompt = $this->trim_prompt( $prompt );

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->api_key;

		// Default safetySettings
		$safety_settings = array(
			array(
				'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
				'threshold' => 'BLOCK_ONLY_HIGH',
			),
		);

		// Default generationConfig
		$generation_config = array(
			'temperature'     => $this->temperature,
			'maxOutputTokens' => $this->max_tokens,
		);

		// Merge override_body['generationConfig'] into $generation_config
		if ( isset( $override_body['generationConfig'] ) && is_array( $override_body['generationConfig'] ) ) {
			$generation_config = array_merge( $generation_config, $override_body['generationConfig'] );
			unset( $override_body['generationConfig'] );
		}

		// Override safetySettings if provided
		if ( isset( $override_body['safetySettings'] ) && is_array( $override_body['safetySettings'] ) ) {
			$safety_settings = $override_body['safetySettings'];
			unset( $override_body['safetySettings'] );
		}

		// Build the request body
		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $prompt ),
					),
				),
			),
			'safetySettings'   => $safety_settings,
			'generationConfig' => $generation_config,
		);

		$headers = array(
			'Content-Type' => 'application/json',
		);

		$response = wp_remote_post( $url, array(
			'timeout' => 60,
			'headers' => $headers,
			'body' => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( $body, true );

		// Parse the response
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return $data['candidates'][0]['content']['parts'][0]['text'];
		} else {
			return new \WP_Error( 'api_error', 'Error communicating with the Google Gemini API.' . "\n" . print_r( $data, true ) );
		}
	}
}
