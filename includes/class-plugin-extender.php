<?php
/**
 * Autoplugin Extender class.
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
 * Plugin Extender class.
 */
class Plugin_Extender {

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
	 * Prompt the AI to plan the extension of a WordPress plugin (single-file or multi-file).
	 *
	 * @param string|array $plugin_code_or_files The plugin code string OR array of [ path => contents ].
	 * @param string       $plugin_changes       The plugin changes to be made.
	 *
	 * @return string|WP_Error
	 */
	public function plan_plugin_extension( $plugin_code_or_files, $plugin_changes ) {
		$code_context = $this->build_code_context( $plugin_code_or_files );
		$prompt = <<<PROMPT
			I have a WordPress plugin I would like to extend. It may be a single file or a multi-file codebase. Here is the codebase:

			$code_context

			I want the following changes to be made to the plugin:
			```
			$plugin_changes
			```

			Please provide a concise technical specification and development plan for extending the plugin: what changes need to be made, how they should be implemented, and any other relevant details. Do not write actual code. Do not use Markdown formatting in your answer.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Prompt the AI to extend a WordPress plugin based on a plan.
	 * Supports single-file or multi-file output.
	 *
	 * @param string|array $plugin_code_or_files The plugin code string OR array of [ path => contents ].
	 * @param string       $plugin_changes       The plugin changes.
	 * @param string       $ai_plan              The AI plan for extending the plugin.
	 * @param bool         $is_complex           Whether this is a multi-file plugin.
	 *
	 * @return string|WP_Error
	 */
	public function extend_plugin( $plugin_code_or_files, $plugin_changes, $ai_plan, $is_complex = false ) {
		$code_context = $this->build_code_context( $plugin_code_or_files );
		$instructions = $is_complex
			? 'Output a JSON object with a "files" map of file paths to their complete, updated contents. Do not include any explanation.'
			: 'Output ONLY the complete, updated PHP code for the plugin file, without any explanation or markdown.';

		$prompt = <<<PROMPT
			I have a WordPress plugin I would like to extend. It may be single-file or multi-file. Here is the current codebase:

			$code_context

			Here is the plan for extending the plugin:
			```
			$ai_plan
			```

			Please implement the changes. $instructions

			If JSON is returned, use file paths relative to the plugin root and include the full content for each changed file.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Build a readable context for either a single string or multiple files.
	 *
	 * @param string|array $plugin_code_or_files Code string or [ path => contents ].
	 * @return string
	 */
	private function build_code_context( $plugin_code_or_files ) {
		if ( is_array( $plugin_code_or_files ) ) {
			$context = "Plugin Files:\n";
			foreach ( $plugin_code_or_files as $path => $contents ) {
				$lang = 'php';
				if ( preg_match( '/\.css$/i', $path ) ) { $lang = 'css'; }
				elseif ( preg_match( '/\.(js|mjs)$/i', $path ) ) { $lang = 'javascript'; }

				$lines = explode( "\n", (string) $contents );
				$max   = 2500;
				if ( count( $lines ) > $max ) {
					$contents = implode( "\n", array_slice( $lines, 0, $max ) ) . "\n/* ... truncated ... */";
				}

				$context .= "\nFile: {$path}\n```{$lang}\n{$contents}\n```\n";
			}
			return $context;
		}

		return "```php\n" . (string) $plugin_code_or_files . "\n```";
	}
}
