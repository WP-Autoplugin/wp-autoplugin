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
I have a WordPress plugin I would like to modify. Here is the plugin codebase:

$code_context

I want the following changes to be made to the plugin:
"""
$plugin_changes
"""

Your task is to analyze the plugin code and the requested changes, and provide a detailed plan in JSON format for how to implement these changes. The plan should include:
1. A detailed description of the changes to be made, including any new features or modifications.
2. A list of files that will be modified or added, with a brief description of each. Do not write the code yet.

Provide a machine-readable plan in strict JSON (no markdown code fences, no commentary). Include:
```
{
  "plan_details": string,
  "project_structure": {
	"files": [
	  { "path": string, "type": "php"|"js"|"css", "description": string, "action": "update"|"add" }
	]
  }
}
```

Notes:
- Only include files in `project_structure.files` that will be modified or added.
- Do NOT include any code in your response. Only the JSON object above.
PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Generate the updated content for a single file as part of an extension, using full codebase context.
	 *
	 * @param array  $plugin_code_or_files Map of path => contents of the current plugin codebase.
	 * @param string $ai_plan              The JSON plan string describing the extension.
	 * @param array  $project_structure    Parsed project structure array with files list.
	 * @param array  $generated_files      Map of already generated files for this extension run.
	 * @param array  $file_info            Target file info: [ path, type, description, action ].
	 * @return string|\WP_Error
	 */
	public function extend_single_file( $plugin_code_or_files, $ai_plan, $project_structure, $generated_files, $file_info ) {
		$code_context = $this->build_code_context( $plugin_code_or_files );

		$file_path = isset( $file_info['path'] ) ? $file_info['path'] : '';
		$file_type = isset( $file_info['type'] ) ? $file_info['type'] : 'php';
		$action    = isset( $file_info['action'] ) ? $file_info['action'] : 'update';

		$lang = 'php';
		if ( 'css' === $file_type ) { $lang = 'css'; }
		elseif ( 'js' === $file_type ) { $lang = 'javascript'; }

		$generated_context = '';
		if ( is_array( $generated_files ) && ! empty( $generated_files ) ) {
			$generated_context = "Previously updated/added files in this extension run:\n";
			foreach ( $generated_files as $path => $contents ) {
				$gLang = 'php';
				if ( preg_match( '/\\.css$/i', $path ) ) { $gLang = 'css'; }
				elseif ( preg_match( '/\\.(js|mjs)$/i', $path ) ) { $gLang = 'javascript'; }
				$lines = explode( "\n", (string) $contents );
				$max   = 1200;
				if ( count( $lines ) > $max ) {
					$contents = implode( "\n", array_slice( $lines, 0, $max ) ) . "\n/* ... truncated ... */";
				}
				$generated_context .= "\nFile: {$path}\n```{$gLang}\n{$contents}\n```\n";
			}
		}

		// To do: Make sure the "increment version number" instruction is
		// only applied when editing the main plugin file.

		$prompt = <<<PROMPT
You are extending an existing WordPress plugin codebase. Here is the current codebase:

$code_context

Here is the extension plan in JSON:
```
$ai_plan
```

Here is the list of files selected for this extension and any content already produced during this run:
$generated_context

Your task: Output ONLY the complete, final contents for the single target file below, implementing the extension described in the plan:
- Target file path: {$file_path}
- File type: {$file_type}
- Action: {$action}

Format your response as follows:
- Do not output any explanation. Do not output any other files.
- Output only the code for {$file_path} wrapped in a proper code block for the file type (```{$lang}).
- If the file does not exist yet (action is ADD), create it with complete, working content.
- If the file is being updated (action is UPDATE), ensure it contains all necessary code, not just the changes.
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
			? 'Output a JSON object with a "files" map of file paths to their complete, updated contents. Do not include any explanation. Use file paths relative to the plugin root and include the full content for each changed file.'
			: 'Output ONLY the complete, updated PHP code for the plugin file, without any explanation or markdown.';

		$prompt = <<<PROMPT
			I have a WordPress plugin I would like to extend. Here is the current codebase:

			$code_context

			Here is the plan for extending the plugin:
			"""
			$ai_plan
			"""

			Please implement the changes. $instructions
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
		return AI_Utils::build_code_context( $plugin_code_or_files );
	}
}
