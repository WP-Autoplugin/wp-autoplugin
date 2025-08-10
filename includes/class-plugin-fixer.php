<?php
/**
 * Autoplugin Fixer class.
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
 * Plugin Fixer class.
 */
class Plugin_Fixer {

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
	 * Prompt the AI to identify an issue in a WordPress plugin (single-file or multi-file).
	 *
	 * @param string|array $plugin_code_or_files The plugin code string OR array of [ path => contents ].
	 * @param string       $problem              The problem encountered.
	 * @param bool         $check_other_issues   Whether to check for other issues in the code.
	 *
	 * @return string|WP_Error
	 */
	public function identify_issue( $plugin_code_or_files, $problem, $check_other_issues = true ) {
		$code_context = $this->build_code_context( $plugin_code_or_files );

		$base_prompt = <<<PROMPT
			I have a WordPress plugin that needs fixing. The plugin may be a single file or a multi-file codebase. Here is the codebase:

			$code_context

			The problem I am encountering:
			```
			$problem
			```

			Please provide an explanation of the issues and a possible solution. Do not write the fixed code. Your response should only contain clear and concise text in the following sections:

			- Issue Description: Describe the issue in detail, including the impact on the plugin's functionality.
			- Proposed Fix: Provide a detailed explanation of how to fix the issues, along with any additional recommendations or best practices.

			Make sure your response only contains the specified sections, without any additional commentary. Do not include any code. Do not use Markdown formatting in your answer.
			PROMPT;

		if ( ! $check_other_issues ) {
			$base_prompt = <<<PROMPT
				I have a WordPress plugin that needs fixing. The plugin may be a single file or a multi-file codebase. Here is the codebase:

				$code_context

				The problem I am encountering:
				```
				$problem
				```

				Please provide a detailed technical explanation of the issues and a possible solution. Do not write the fixed code. Your response should only contain clear and concise text in the following sections:

				- Issue Description: Describe the issue in detail, including the impact on the plugin's functionality. Focus only on the issue at hand.
				- Proposed Fix: Provide a detailed explanation of how to fix the issues.

				Make sure your response only contains the specified sections, without any additional commentary. Do not include any code. Do not use Markdown formatting in your answer.
				PROMPT;
		}

		return $this->ai_api->send_prompt( $base_prompt, '' );
	}

	/**
	 * Prompt the AI to fix a WordPress plugin (single-file or multi-file).
	 *
	 * @param string|array $plugin_code_or_files The plugin code string OR array of [ path => contents ].
	 * @param string       $problem              The problem encountered.
	 * @param string       $ai_description       The developer's description and solution.
	 * @param bool         $is_complex           Whether this is a multi-file plugin.
	 * @param string       $main_file            Main plugin file path (relative), used for simple output if needed.
	 *
	 * @return string|WP_Error
	 */
	public function fix_plugin( $plugin_code_or_files, $problem, $ai_description, $is_complex = false, $main_file = '' ) {
		$code_context = $this->build_code_context( $plugin_code_or_files );

		$instructions = $is_complex
			? 'Output a JSON object with a "files" map of file paths to their complete, updated contents. Do not include any explanation.'
			: 'Output ONLY the complete, fixed PHP code for the plugin file, without any explanation or markdown.';

		$prompt = <<<PROMPT
			I have a WordPress plugin that needs fixing. The plugin may be single-file or multi-file. Here is the current codebase:

			$code_context

			The problem I am encountering:
			```
			$problem
			```

			Here is a description of the issue and the solution provided by the developer:
			```
			$ai_description
			```

			Please provide the fixed code. $instructions

			If JSON is returned, use file paths relative to the plugin root and include the full content for each changed file. If a single file is sufficient, return only that file's complete code.
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

				// Limit extremely large files to avoid token overflow
				$lines = explode( "\n", (string) $contents );
				$max   = 2500;
				if ( count( $lines ) > $max ) {
					$contents = implode( "\n", array_slice( $lines, 0, $max ) ) . "\n/* ... truncated ... */";
				}

				$context .= "\nFile: {$path}\n```{$lang}\n{$contents}\n```\n";
			}
			return $context;
		}

		// Single-file code
		return "```php\n" . (string) $plugin_code_or_files . "\n```";
	}
}
