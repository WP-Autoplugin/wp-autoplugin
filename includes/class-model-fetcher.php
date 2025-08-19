<?php
/**
 * Model Fetcher class.
 *
 * Auto-fetches available models from AI providers.
 *
 * @package WP-Autoplugin
 * @since 1.5.0
 * @version 1.0.0
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Model Fetcher class.
 */
class Model_Fetcher {

	/**
	 * Provider configurations for fetching models.
	 *
	 * @var array
	 */
	private $providers = [
		'openai' => [
			'endpoint' => 'https://api.openai.com/v1/models',
			'headers_callback' => 'get_openai_headers',
			'parser' => 'parse_openai_models',
			'filter_pattern' => '/^(gpt|o[13]|chatgpt|dall-e|whisper|tts)/i',
		],
		'anthropic' => [
			'endpoint' => 'https://api.anthropic.com/v1/models',
			'headers_callback' => 'get_anthropic_headers',
			'parser' => 'parse_anthropic_models',
			'filter_pattern' => '/^claude/i',
			// Anthropic doesn't have a models endpoint yet, so we'll use static list with version check
			'static_models' => true,
			'version_endpoint' => 'https://api.anthropic.com/v1/messages',
		],
		'google' => [
			'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
			'headers_callback' => 'get_google_headers',
			'parser' => 'parse_google_models',
			'filter_pattern' => '/^(gemini|gemma)/i',
			'query_params' => '?key={api_key}',
		],
		'xai' => [
			'endpoint' => 'https://api.x.ai/v1/models',
			'headers_callback' => 'get_xai_headers',
			'parser' => 'parse_xai_models',
			'filter_pattern' => '/^grok/i',
		],
	];

	/**
	 * Model cache instance.
	 *
	 * @var Model_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cache = new Model_Cache();
	}

	/**
	 * Fetch models for a specific provider.
	 *
	 * @param string $provider The provider name.
	 * @param string $api_key The API key for authentication.
	 * @param bool   $force_refresh Force refresh bypassing cache.
	 * @return array|WP_Error Array of models or WP_Error on failure.
	 */
	public function fetch_provider_models( $provider, $api_key, $force_refresh = false ) {
		// Validate provider.
		if ( ! isset( $this->providers[ $provider ] ) ) {
			return new \WP_Error( 'invalid_provider', __( 'Invalid provider specified.', 'wp-autoplugin' ) );
		}

		// Check cache first unless force refresh.
		if ( ! $force_refresh ) {
			$cached_models = $this->cache->get_cached_models( $provider );
			if ( $cached_models !== false ) {
				return $cached_models;
			}
		}

		$config = $this->providers[ $provider ];

		// Handle static models (like Anthropic).
		if ( isset( $config['static_models'] ) && $config['static_models'] ) {
			$models = $this->get_static_models( $provider );
			$this->cache->cache_models( $provider, $models );
			return $models;
		}

		// Build request URL.
		$url = $config['endpoint'];
		if ( isset( $config['query_params'] ) ) {
			$url .= str_replace( '{api_key}', $api_key, $config['query_params'] );
		}

		// Get headers.
		$headers = $this->{$config['headers_callback']}( $api_key );

		// Make API request.
		$response = wp_remote_get( $url, [
			'headers' => $headers,
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			return new \WP_Error( 
				'api_error', 
				sprintf( __( 'API returned error code: %d', 'wp-autoplugin' ), $response_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data ) {
			return new \WP_Error( 'parse_error', __( 'Failed to parse API response.', 'wp-autoplugin' ) );
		}

		// Parse models using provider-specific parser.
		$models = $this->{$config['parser']}( $data, $config );

		// Cache the models.
		$this->cache->cache_models( $provider, $models );

		return $models;
	}

	/**
	 * Get OpenAI request headers.
	 *
	 * @param string $api_key The API key.
	 * @return array Headers array.
	 */
	private function get_openai_headers( $api_key ) {
		return [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Get Anthropic request headers.
	 *
	 * @param string $api_key The API key.
	 * @return array Headers array.
	 */
	private function get_anthropic_headers( $api_key ) {
		return [
			'x-api-key' => $api_key,
			'anthropic-version' => '2023-06-01',
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Get Google request headers.
	 *
	 * @param string $api_key The API key.
	 * @return array Headers array.
	 */
	private function get_google_headers( $api_key ) {
		return [
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Get xAI request headers.
	 *
	 * @param string $api_key The API key.
	 * @return array Headers array.
	 */
	private function get_xai_headers( $api_key ) {
		return [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Parse OpenAI models response.
	 *
	 * @param array $data Response data.
	 * @param array $config Provider config.
	 * @return array Parsed models.
	 */
	private function parse_openai_models( $data, $config ) {
		$models = [];
		
		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return $models;
		}

		foreach ( $data['data'] as $model ) {
			if ( ! isset( $model['id'] ) ) {
				continue;
			}

			// Filter models by pattern.
			if ( ! preg_match( $config['filter_pattern'], $model['id'] ) ) {
				continue;
			}

			// Skip embedding and other non-chat models.
			if ( strpos( $model['id'], 'embedding' ) !== false ) {
				continue;
			}

			$models[ $model['id'] ] = [
				'id' => $model['id'],
				'name' => $this->format_model_name( $model['id'] ),
				'created' => isset( $model['created'] ) ? $model['created'] : null,
				'owned_by' => isset( $model['owned_by'] ) ? $model['owned_by'] : 'openai',
			];
		}

		// Sort models by name.
		uasort( $models, function( $a, $b ) {
			return strcmp( $a['name'], $b['name'] );
		} );

		return $models;
	}

	/**
	 * Parse Anthropic models (static list).
	 *
	 * @param array $data Response data (unused for static).
	 * @param array $config Provider config.
	 * @return array Parsed models.
	 */
	private function parse_anthropic_models( $data, $config ) {
		// Since Anthropic doesn't have a models endpoint, return static list.
		return $this->get_static_models( 'anthropic' );
	}

	/**
	 * Parse Google models response.
	 *
	 * @param array $data Response data.
	 * @param array $config Provider config.
	 * @return array Parsed models.
	 */
	private function parse_google_models( $data, $config ) {
		$models = [];
		
		if ( ! isset( $data['models'] ) || ! is_array( $data['models'] ) ) {
			return $models;
		}

		foreach ( $data['models'] as $model ) {
			if ( ! isset( $model['name'] ) ) {
				continue;
			}

			// Extract model ID from name (format: models/gemini-pro).
			$model_id = str_replace( 'models/', '', $model['name'] );

			// Filter models by pattern.
			if ( ! preg_match( $config['filter_pattern'], $model_id ) ) {
				continue;
			}

			$models[ $model_id ] = [
				'id' => $model_id,
				'name' => $this->format_model_name( $model_id ),
				'display_name' => isset( $model['displayName'] ) ? $model['displayName'] : $model_id,
				'description' => isset( $model['description'] ) ? $model['description'] : '',
				'input_token_limit' => isset( $model['inputTokenLimit'] ) ? $model['inputTokenLimit'] : null,
				'output_token_limit' => isset( $model['outputTokenLimit'] ) ? $model['outputTokenLimit'] : null,
			];
		}

		return $models;
	}

	/**
	 * Parse xAI models response.
	 *
	 * @param array $data Response data.
	 * @param array $config Provider config.
	 * @return array Parsed models.
	 */
	private function parse_xai_models( $data, $config ) {
		$models = [];
		
		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return $models;
		}

		foreach ( $data['data'] as $model ) {
			if ( ! isset( $model['id'] ) ) {
				continue;
			}

			// Filter models by pattern.
			if ( ! preg_match( $config['filter_pattern'], $model['id'] ) ) {
				continue;
			}

			$models[ $model['id'] ] = [
				'id' => $model['id'],
				'name' => $this->format_model_name( $model['id'] ),
				'created' => isset( $model['created'] ) ? $model['created'] : null,
				'owned_by' => isset( $model['owned_by'] ) ? $model['owned_by'] : 'xai',
			];
		}

		return $models;
	}

	/**
	 * Get static models for providers without API endpoints.
	 *
	 * @param string $provider Provider name.
	 * @return array Static models list.
	 */
	private function get_static_models( $provider ) {
		$static_models = [
			'anthropic' => [
				'claude-3-7-sonnet-latest' => [
					'id' => 'claude-3-7-sonnet-latest',
					'name' => 'Claude 3.7 Sonnet (Latest)',
					'description' => 'Most intelligent model, latest version',
				],
				'claude-3-7-sonnet-20250219' => [
					'id' => 'claude-3-7-sonnet-20250219',
					'name' => 'Claude 3.7 Sonnet (2025-02-19)',
					'description' => 'Most intelligent model, February 2025 version',
				],
				'claude-3-7-sonnet-thinking' => [
					'id' => 'claude-3-7-sonnet-thinking',
					'name' => 'Claude 3.7 Sonnet Thinking',
					'description' => 'Chain-of-thought reasoning model',
				],
				'claude-3-5-sonnet-latest' => [
					'id' => 'claude-3-5-sonnet-latest',
					'name' => 'Claude 3.5 Sonnet (Latest)',
					'description' => 'Balanced intelligence and speed',
				],
				'claude-3-5-sonnet-20241022' => [
					'id' => 'claude-3-5-sonnet-20241022',
					'name' => 'Claude 3.5 Sonnet (2024-10-22)',
					'description' => 'October 2024 version',
				],
				'claude-3-5-haiku-latest' => [
					'id' => 'claude-3-5-haiku-latest',
					'name' => 'Claude 3.5 Haiku (Latest)',
					'description' => 'Fast and efficient',
				],
				'claude-3-opus-20240229' => [
					'id' => 'claude-3-opus-20240229',
					'name' => 'Claude 3 Opus',
					'description' => 'Previous generation, high capability',
				],
			],
		];

		return isset( $static_models[ $provider ] ) ? $static_models[ $provider ] : [];
	}

	/**
	 * Format model ID into human-readable name.
	 *
	 * @param string $model_id The model ID.
	 * @return string Formatted name.
	 */
	private function format_model_name( $model_id ) {
		// Special cases.
		$special_cases = [
			'gpt-4-turbo-preview' => 'GPT-4 Turbo Preview',
			'gpt-4-vision-preview' => 'GPT-4 Vision Preview',
			'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
			'gpt-4' => 'GPT-4',
			'gpt-4-32k' => 'GPT-4 32K',
			'claude-instant' => 'Claude Instant',
			'claude-2' => 'Claude 2',
			'gemini-pro' => 'Gemini Pro',
			'gemini-pro-vision' => 'Gemini Pro Vision',
		];

		if ( isset( $special_cases[ $model_id ] ) ) {
			return $special_cases[ $model_id ];
		}

		// General formatting: replace dashes/underscores with spaces and capitalize.
		$name = str_replace( [ '-', '_' ], ' ', $model_id );
		$name = ucwords( $name );

		// Handle version numbers.
		$name = preg_replace( '/(\d+)\.(\d+)/', '$1.$2', $name );

		return $name;
	}

	/**
	 * Test API key validity by attempting to fetch models.
	 *
	 * @param string $provider Provider name.
	 * @param string $api_key API key to test.
	 * @return bool|WP_Error True if valid, WP_Error if not.
	 */
	public function test_api_key( $provider, $api_key ) {
		$models = $this->fetch_provider_models( $provider, $api_key, true );
		
		if ( is_wp_error( $models ) ) {
			return $models;
		}

		return true;
	}

	/**
	 * Get all available providers.
	 *
	 * @return array List of provider IDs and names.
	 */
	public function get_available_providers() {
		return [
			'openai' => __( 'OpenAI', 'wp-autoplugin' ),
			'anthropic' => __( 'Anthropic', 'wp-autoplugin' ),
			'google' => __( 'Google', 'wp-autoplugin' ),
			'xai' => __( 'xAI', 'wp-autoplugin' ),
		];
	}
}