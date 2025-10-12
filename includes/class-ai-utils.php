<?php
/**
 * AI helper utilities.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility methods shared across AI flows.
 */
class AI_Utils {

	/**
	 * Models that support multimodal prompts for image input.
	 *
	 * @return array
	 */
	public static function get_supported_image_models() {
		return [
			'gpt-4o',
			'gpt-4o-mini',
			'gpt-4.1',
			'gpt-4.1-mini',
			'gpt-4.1-nano',
			'gpt-5',
			'gpt-5-mini',
			'gpt-5-nano',
			'gpt-5-chat-latest',
			'gpt-5-codex',
		];
	}

	/**
	 * Parse JSON-encoded prompt images from a request.
	 *
	 * @param string|array $raw_images Raw JSON string or array payload.
	 * @param int          $max_images Maximum number of images to accept.
	 *
	 * Filters:
	 * - wp_autoplugin_max_prompt_image_bytes : int Maximum bytes per image (defaults to 5MB).
	 * - wp_autoplugin_prompt_image_mime_types: array Allowed MIME types.
	 * @return array[]
	 */
	public static function parse_prompt_images( $raw_images, $max_images = 6 ) {
		if ( empty( $raw_images ) ) {
			return [];
		}

		$data = $raw_images;
		if ( is_string( $raw_images ) ) {
			$data = json_decode( wp_unslash( $raw_images ), true );
		}

		if ( ! is_array( $data ) ) {
			return [];
		}

		$images          = [];
		$max_image_bytes = apply_filters( 'wp_autoplugin_max_prompt_image_bytes', 5 * 1024 * 1024 );
		$allowed_mimes = apply_filters(
			'wp_autoplugin_prompt_image_mime_types',
			[ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ]
		);
		$allowed_mimes = array_filter(
			array_map( 'sanitize_mime_type', (array) $allowed_mimes )
		);
		$max_image_bytes = is_numeric( $max_image_bytes ) ? (int) $max_image_bytes : 0;
		foreach ( $data as $item ) {
			if ( count( $images ) >= $max_images ) {
				break;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}

			$base64 = isset( $item['data'] ) ? trim( (string) $item['data'] ) : '';
			$mime   = isset( $item['mime'] ) ? trim( (string) $item['mime'] ) : '';
			$name   = isset( $item['name'] ) ? sanitize_file_name( (string) $item['name'] ) : '';

			if ( '' === $base64 || '' === $mime || 0 !== strpos( $mime, 'image/' ) ) {
				continue;
			}
			if ( ! preg_match( '/^[A-Za-z0-9+\/]+={0,2}$/', $base64 ) ) {
				continue;
			}

			$decoded = base64_decode( $base64, true );
			if ( false === $decoded ) {
				continue;
			}

			$bytes = strlen( $decoded );
			if ( $max_image_bytes > 0 && $bytes > $max_image_bytes ) {
				continue;
			}

			$sanitized_mime = sanitize_mime_type( $mime );
			if ( empty( $sanitized_mime ) ) {
				continue;
			}
			if ( ! in_array( $sanitized_mime, $allowed_mimes, true ) ) {
				continue;
			}

			$images[] = [
				'data' => base64_encode( $decoded ),
				'mime' => $sanitized_mime,
				'name' => $name,
			];
		}

		return $images;
	}

	/**
	 * Build OpenAI-style multimodal messages for text plus images.
	 *
	 * @param string $prompt         The user prompt text.
	 * @param array  $prompt_images  Array of parsed prompt images.
	 * @param string $system_message Optional system prompt.
	 * @return array
	 */
	public static function build_openai_multimodal_messages( $prompt, $prompt_images, $system_message = '' ) {
		$messages = [];
		if ( ! empty( $system_message ) ) {
			$messages[] = [
				'role'    => 'system',
				'content' => $system_message,
			];
		}

		$content = [
			[
				'type' => 'text',
				'text' => $prompt,
			],
		];

		foreach ( $prompt_images as $image ) {
			$mime = isset( $image['mime'] ) ? $image['mime'] : 'image/jpeg';
			$data = isset( $image['data'] ) ? $image['data'] : '';
			if ( empty( $data ) ) {
				continue;
			}

			$content[] = [
				'image_url' => 'data:' . $mime . ';base64,' . $data,
			];
		}

		$messages[] = [
			'role'    => 'user',
			'content' => $content,
		];

		return $messages;
	}

	/**
	 * Build OpenAI Responses API multimodal input payload.
	 *
	 * @param string $prompt         The user prompt text.
	 * @param array  $prompt_images  Array of parsed prompt images.
	 * @param string $system_message Optional system prompt (handled separately via instructions).
	 * @return array
	 */
	public static function build_openai_responses_multimodal_input( $prompt, $prompt_images, $system_message = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$content = [];
		$prompt  = (string) $prompt;

		if ( '' !== $prompt ) {
			$content[] = [
				'type' => 'input_text',
				'text' => $prompt,
			];
		}

		foreach ( $prompt_images as $image ) {
			$mime = isset( $image['mime'] ) ? $image['mime'] : 'image/jpeg';
			$data = isset( $image['data'] ) ? $image['data'] : '';
			if ( empty( $data ) ) {
				continue;
			}

			$content[] = [
				'type'      => 'input_image',
				'image_url' => 'data:' . $mime . ';base64,' . $data,
			];
		}

		if ( empty( $content ) ) {
			$content[] = [
				'type' => 'input_text',
				'text' => '',
			];
		}

		return [
			[
				'role'    => 'user',
				'content' => $content,
			],
		];
	}

	/**
	 * Build Gemini-style multimodal contents array for text plus images.
	 *
	 * @param string $prompt        The user prompt text.
	 * @param array  $prompt_images Array of parsed prompt images.
	 * @return array
	 */
	public static function build_gemini_multimodal_contents( $prompt, $prompt_images ) {
		$parts = [];

		foreach ( $prompt_images as $image ) {
			$mime = isset( $image['mime'] ) ? $image['mime'] : 'image/jpeg';
			$data = isset( $image['data'] ) ? $image['data'] : '';
			if ( empty( $data ) ) {
				continue;
			}

			$parts[] = [
				'inline_data' => [
					'mime_type' => $mime,
					'data'      => $data,
				],
			];
		}

		$parts[] = [
			'text' => $prompt,
		];

		return [
			[
				'parts' => $parts,
			],
		];
	}

	/**
	 * Build Anthropic-style content array for text plus images.
	 *
	 * @param string $prompt        The user prompt text.
	 * @param array  $prompt_images Array of parsed prompt images.
	 * @return array
	 */
	public static function build_anthropic_multimodal_content( $prompt, $prompt_images ) {
		$content = [];

		foreach ( $prompt_images as $image ) {
			$mime = isset( $image['mime'] ) ? $image['mime'] : 'image/jpeg';
			$data = isset( $image['data'] ) ? $image['data'] : '';
			if ( empty( $data ) ) {
				continue;
			}

			$content[] = [
				'type'   => 'image',
				'source' => [
					'type'       => 'base64',
					'media_type' => $mime,
					'data'       => $data,
				],
			];
		}

		$content[] = [
			'type' => 'text',
			'text' => $prompt,
		];

		return $content;
	}

	/**
	 * Determine whether the provided API instance supports prompt images.
	 *
	 * @param API $api The API instance.
	 * @return bool
	 */
	public static function api_supports_prompt_images( $api ) {
		if ( $api instanceof \WP_Autoplugin\OpenAI_API ) {
			$model = method_exists( $api, 'get_selected_model' ) ? $api->get_selected_model() : '';
			return in_array( $model, self::get_supported_image_models(), true );
		}

		if ( $api instanceof \WP_Autoplugin\Google_Gemini_API ) {
			return true;
		}

		if ( $api instanceof \WP_Autoplugin\Anthropic_API ) {
			return true;
		}

		return false;
	}

	/**
	 * Get provider-specific payload overrides for multimodal prompts.
	 *
	 * @param API    $api           The API instance.
	 * @param string $prompt        The user prompt text.
	 * @param array  $prompt_images Parsed prompt images.
	 * @param string $system_message Optional system message.
	 * @return array
	 */
	public static function get_multimodal_payload( $api, $prompt, $prompt_images, $system_message = '' ) {
		if ( empty( $prompt_images ) || ! self::api_supports_prompt_images( $api ) ) {
			return [];
		}

		if ( $api instanceof \WP_Autoplugin\OpenAI_Responses_API ) {
			return [
				'input' => self::build_openai_responses_multimodal_input( $prompt, $prompt_images, $system_message ),
			];
		}

		if ( $api instanceof \WP_Autoplugin\OpenAI_API ) {
			return [
				'messages' => self::build_openai_multimodal_messages( $prompt, $prompt_images, $system_message ),
			];
		}

		if ( $api instanceof \WP_Autoplugin\Google_Gemini_API ) {
			return [
				'contents' => self::build_gemini_multimodal_contents( $prompt, $prompt_images ),
			];
		}

		if ( $api instanceof \WP_Autoplugin\Anthropic_API ) {
			return [
				'messages' => [
					[
						'role'    => 'user',
						'content' => self::build_anthropic_multimodal_content( $prompt, $prompt_images ),
					],
				],
			];
		}

		return [];
	}

	/**
	 * Build a readable context for either a single string or multiple files.
	 * Wraps contents in language-specific code fences and truncates long files.
	 *
	 * @param string|array $plugin_code_or_files Code string or [ path => contents ].
	 * @param int          $max_lines_per_file   Max lines included per file.
	 * @return string
	 */
	public static function build_code_context( $plugin_code_or_files, $max_lines_per_file = 2500 ) {
		if ( is_array( $plugin_code_or_files ) ) {
			$context = "Plugin Files:\n";
			foreach ( $plugin_code_or_files as $path => $contents ) {
				$lang = 'php';
				if ( preg_match( '/\\.css$/i', (string) $path ) ) {
					$lang = 'css';
				} elseif ( preg_match( '/\\.(js|mjs)$/i', (string) $path ) ) {
					$lang = 'javascript';
				}

				$lines = explode( "\n", (string) $contents );
				if ( count( $lines ) > $max_lines_per_file ) {
					$contents = implode( "\n", array_slice( $lines, 0, $max_lines_per_file ) ) . "\n/* ... truncated ... */";
				}

				$context .= "\nFile: {$path}\n```{$lang}\n{$contents}\n```\n";
			}
			return $context;
		}

		// Single-file code (assumed PHP by default)
		return "```php\n" . (string) $plugin_code_or_files . "\n```";
	}

	/**
	 * Remove surrounding triple-backtick code fences from a string.
	 * If $lang is provided, only removes that language; otherwise tries common types.
	 *
	 * @param string      $content The content possibly wrapped in code fences.
	 * @param string|null $lang    Optional language (php|css|js|javascript|json).
	 * @return string
	 */
	public static function strip_code_fences( $content, $lang = null ) {
		$content = (string) $content;
		if ( $lang ) {
			$pattern = '/^```(' . preg_quote( $lang, '/' ) . ')\n(.*)\n```$/s';
			return (string) preg_replace( $pattern, '$2', $content );
		}
		// Try common languages.
		$patterns = [
			'/^```(php)\n(.*)\n```$/s',
			'/^```(css)\n(.*)\n```$/s',
			'/^```(js|javascript)\n(.*)\n```$/s',
			'/^```(json)\n(.*)\n```$/s',
		];
		foreach ( $patterns as $pattern ) {
			$replaced = preg_replace( $pattern, '$2', $content );
			if ( $replaced !== null && $replaced !== $content ) {
				return (string) $replaced;
			}
		}
		return $content;
	}
}
