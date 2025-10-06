<?php
/**
 * Main API class.
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
 * API class.
 */
class API {

	/**
	 * API key.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Last API response token usage.
	 *
	 * @var array
	 */
	protected $last_token_usage = [];

	/**
	 * Set the API key.
	 *
	 * @param string $api_key The API key.
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = sanitize_text_field( $api_key );
	}

	/**
	 * Get the last API response token usage.
	 *
	 * @return array Token usage data with 'input_tokens' and 'output_tokens' keys.
	 */
	public function get_last_token_usage() {
		return $this->last_token_usage;
	}

	/**
	 * Extract and normalize token usage from API response.
	 *
	 * @param array  $response The API response data.
	 * @param string $provider The API provider name.
	 * @return array Normalized token usage with 'input_tokens' and 'output_tokens'.
	 */
	protected function extract_token_usage( $response, $provider ) {
		$usage = [
			'input_tokens'  => 0,
			'output_tokens' => 0,
		];

		if ( ! is_array( $response ) ) {
			return $usage;
		}

		switch ( $provider ) {
			case 'anthropic':
				if ( isset( $response['usage']['input_tokens'] ) ) {
					$usage['input_tokens'] = (int) $response['usage']['input_tokens'];
				}
				if ( isset( $response['usage']['output_tokens'] ) ) {
					$usage['output_tokens'] = (int) $response['usage']['output_tokens'];
				}
				break;

			case 'google':
				if ( isset( $response['usageMetadata']['promptTokenCount'] ) ) {
					$usage['input_tokens'] = (int) $response['usageMetadata']['promptTokenCount'];
				}
				if ( isset( $response['usageMetadata']['candidatesTokenCount'] ) ) {
					$usage['output_tokens'] = (int) $response['usageMetadata']['candidatesTokenCount'];
				}
				break;

			case 'openai':
			case 'xai':
			case 'custom':
				// Check for both naming conventions
				if ( isset( $response['usage']['prompt_tokens'] ) ) {
					$usage['input_tokens'] = (int) $response['usage']['prompt_tokens'];
				} elseif ( isset( $response['usage']['input_tokens'] ) ) {
					$usage['input_tokens'] = (int) $response['usage']['input_tokens'];
				}

				if ( isset( $response['usage']['completion_tokens'] ) ) {
					$usage['output_tokens'] = (int) $response['usage']['completion_tokens'];
				} elseif ( isset( $response['usage']['output_tokens'] ) ) {
					$usage['output_tokens'] = (int) $response['usage']['output_tokens'];
				}
				break;
		}

		return $usage;
	}
}
