<?php

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Google_Gemini_API extends API {
	private $api_key;
	private $model;
	private $temperature = 0.2;
	private $max_tokens  = 4096;

	public function set_api_key( $api_key ) {
		$this->api_key = sanitize_text_field( $api_key );
	}

	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
		// You can set specific parameters based on the model if needed.
	}

	public function send_prompt( $prompt, $system_message = '', $override_body = array() ) {
		$prompt = $this->trim_prompt( $prompt );

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->api_key;

		// Build the request body
		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array('text' => $prompt)
					)
				)
			),
		);

		// Merge override_body with allowed keys
		$allowed_keys = array('contents', 'temperature', 'max_tokens');
		$override_body = array_intersect_key( $override_body, array_flip( $allowed_keys ) );
		$body = array_merge( $body, $override_body );

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
