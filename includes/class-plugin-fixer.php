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
	 *
	 * @return string|WP_Error
	 */
	public function identify_issue( $plugin_code_or_files, $problem ) {
		$code_context = $this->build_code_context( $plugin_code_or_files );

		$prompt = <<<PROMPT
I have a WordPress plugin that needs fixing. Here is the current codebase:

$code_context

The problem I am encountering:
"""
$problem
"""

Your task is to analyze the plugin code and the reported problem, and provide a detailed plan in JSON format for how to fix the issue. The plan should include:
1. A detailed description of the fix to be applied.
2. A list of files that will be modified or added to implement the fix, with a brief description of each. Do not write the code yet.

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
- Only include the files that actually need to be modified or added to implement the fix.
- Do NOT include any code in this response. Only the JSON object above.
PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Generate fixed content for a single file using the full codebase and the plan.
	 *
	 * @param array  $plugin_code_or_files Map of path => contents.
	 * @param string $problem              Problem description.
	 * @param string $ai_plan              JSON plan string.
	 * @param array  $project_structure    Parsed plan project structure.
	 * @param array  $generated_files      Map of already fixed files in this run.
	 * @param array  $file_info            Target file info [path,type,description,action].
	 * @return string|\WP_Error
	 */
	public function fix_single_file( $plugin_code_or_files, $problem, $ai_plan, $project_structure, $generated_files, $file_info ) {
		$code_context = $this->build_code_context( $plugin_code_or_files );

		$file_path = isset( $file_info['path'] ) ? $file_info['path'] : '';
		$file_type = isset( $file_info['type'] ) ? $file_info['type'] : 'php';
		$action    = isset( $file_info['action'] ) ? $file_info['action'] : 'update';

		$lang = 'php';
		if ( 'css' === $file_type ) { $lang = 'css'; }
		elseif ( 'js' === $file_type ) { $lang = 'javascript'; }

		$generated_context = '';
		if ( is_array( $generated_files ) && ! empty( $generated_files ) ) {
			$generated_context = "Previously updated/added files in this fix run:\n";
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

		$prompt = <<<PROMPT
You are fixing an existing WordPress plugin codebase. Here is the current codebase:

$code_context

The problem:
```
$problem
```

Here is the fix plan in JSON:
```
$ai_plan
```

Here are files already updated during this run (if any):
$generated_context

Your task: Output ONLY the complete, final contents for the single target file below, implementing the fix described in the plan:
- Target file path: {$file_path}
- File type: {$file_type}
- Action: {$action}

Constraints:
- Do not output any explanation. Do not output any other files.
- Output only the code for {$file_path} wrapped in a proper code block for the file type (```{$lang}).
- If the file does not exist yet (action is ADD), create it with complete, working content.
PROMPT;

		return $this->ai_api->send_prompt( $prompt );
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
			I have a WordPress plugin that needs fixing. Here is the current codebase of the plugin:

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
		return AI_Utils::build_code_context( $plugin_code_or_files );
	}
}
