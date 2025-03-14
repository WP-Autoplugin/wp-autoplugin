<?php
/**
 * Autoplugin Explainer class.
 *
 * @package WP-Autoplugin
 * @since 1.3
 * @version 1.3
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Explainer class.
 */
class Plugin_Explainer {

	/**
	 * The AI API in use.
	 *
	 * @var API
	 */
	private $ai_api;

	/**
	 * Constructor.
	 *
	 * @param API $ai_api The AI API in use.
	 */
	public function __construct( $ai_api ) {
		$this->ai_api = $ai_api;
	}

	/**
	 * Generate a general explanation of a WordPress plugin.
	 *
	 * @param string $plugin_code The plugin code.
	 *
	 * @return string|WP_Error
	 */
	public function explain_plugin( $plugin_code ) {
		$prompt = <<<PROMPT
			I have a WordPress plugin file I would like you to analyze and explain. Here is the code:
			
			```php
			$plugin_code
			```

			Please provide a comprehensive explanation of this plugin's functionality, including:
			1. Overview and main purpose
			2. Key features and functionality
			3. Basic technical implementation
			4. How to use the plugin (from a user's perspective)

			Format your response in clear sections with headings. Be thorough but concise. Use Markdown.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Answer a specific question about a WordPress plugin.
	 *
	 * @param string $plugin_code The plugin code.
	 * @param string $question    The specific question about the plugin.
	 *
	 * @return string|WP_Error
	 */
	public function answer_plugin_question( $plugin_code, $question ) {
		$prompt = <<<PROMPT
			I have a WordPress plugin file and a specific question about it. Here is the code:
			
			```php
			$plugin_code
			```

			My question is:
			```
			$question
			```

			Please answer this question. If the question cannot be answered based on the provided code, explain why and what additional information might be needed.

			Be thorough but concise. Use Markdown for formatting.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Analyze specific aspects of a WordPress plugin.
	 *
	 * @param string $plugin_code The plugin code.
	 * @param string $aspect      The specific aspect to analyze (e.g., 'security', 'performance', 'code-quality').
	 *
	 * @return string|WP_Error
	 */
	public function analyze_plugin_aspect( $plugin_code, $aspect ) {
		$aspect_prompts = [
			'security'     => 'security considerations, potential vulnerabilities, and security best practices',
			'performance'  => 'performance implications, optimization opportunities, and potential bottlenecks',
			'code-quality' => 'code quality, adherence to WordPress coding standards, and potential improvements',
			'usage'        => 'how end-users would interact with this plugin, including admin settings and frontend features',
		];

		$aspect_prompt = isset( $aspect_prompts[ $aspect ] ) ? 'Please analyze this plugin with a focus on ' . $aspect_prompts[ $aspect ] . '.' : 'Provide a general analysis.';

		$prompt = <<<PROMPT
			I have a WordPress plugin file I would like you to analyze. Here is the code:
			
			```php
			$plugin_code
			```

			$aspect_prompt

			Format your response in clear sections with meaningful headings. Be thorough but concise. Use Markdown.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}
}
