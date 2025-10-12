<?php
/**
 * Autoplugin Hooks Extender class.
 *
 * @package WP-Autoplugin
 * @since 1.0.0
 * @version 1.4
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks Extender class.
 */
class Hooks_Extender {

	/**
	 * The AI API in use.
	 *
	 * @var API
	 */
	private $ai_api;

	/**
	 * Default number of context lines to include before and after hook.
	 */
	const DEFAULT_CONTEXT_LINES = 3;

	/**
	 * Constructor.
	 *
	 * @param API $ai_api The AI API in use.
	 */
	public function __construct( $ai_api ) {
		$this->ai_api = $ai_api;
	}

	/**
	 * Plan a plugin extension using hooks.
	 *
	 * @param string $original_plugin The plugin name (e.g., 'Rank Math SEO').
	 * @param array  $hooks Array of hooks with name, type, and context.
	 * @param string $plugin_changes Description of the desired changes.
	 * @return string|WP_Error The AI-generated plan in JSON format.
	 */
	public function plan_plugin_hooks_extension( $original_plugin, $hooks, $plugin_changes, $prompt_images = [] ) {
		$hooks_list = '';
		foreach ( $hooks as $hook ) {
			$hooks_list .= "```\n{$hook['type']}: '{$hook['name']}'\n\nContext:\n{$hook['context']}\n```\n\n";
		}

		$plugin_mode = get_option( 'wp_autoplugin_plugin_mode', 'simple' );

		if ( 'complex' === $plugin_mode ) {
			$prompt = <<<PROMPT
			I want to extend a WordPress plugin ($original_plugin), preferably using the filter and action hooks available in its code. Here are the available hooks in the plugin:

			$hooks_list
			
			I want to make the following changes to the plugin's functionality:

			$plugin_changes

			In addition to the provided hooks, you may also make use of core WordPress hooks (actions and filters) if needed to achieve the desired changes.

			Please determine if the requested extension is technically feasible. If it is not feasible, explain why.

			If feasible, provide a technical specification and development plan for creating a new extension plugin that uses one or more of the hooks to achieve the desired changes. Include which hooks to use, how to use them, and any additional code or logic needed.

			Do not write the actual plugin code. Your response must be a valid JSON object with the following sections:
			{
				"technically_feasible": true,
				"explanation": "If not feasible, explain why (otherwise brief).",
				"hooks": [ "hook_name_1", "hook_name_2" ],
				"plan": "The development plan if feasible.",
				"plugin_name": "Name of the new extension plugin",
				"project_structure": {
					"directories": ["includes/", "assets/css/", "assets/js/"],
					"files": [
						{ "path": "extension-plugin.php", "type": "php", "description": "Main plugin file with headers and bootstrapping." }
					]
				}
			}

			Guidelines:
			- Keep structure minimal and only include necessary PHP/CSS/JS files
			- Ensure the plan focuses on using the selected hooks correctly
			- Do not include any code; only the JSON plan
			- Response must be a single JSON object without Markdown formatting
			PROMPT;
		} else {
			$prompt = <<<PROMPT
			I want to extend a WordPress plugin ($original_plugin), preferably using the filter and action hooks available in its code. Here are the available hooks in the plugin:

			$hooks_list
			
			I want to make the following changes to the plugin's functionality:

			$plugin_changes

			In addition to the provided hooks, you may also make use of core WordPress hooks (actions and filters) if needed to achieve the desired changes.

			Please determine if the requested extension is technically feasible. If it is not feasible, explain why.

			If feasible, provide a technical specification and development plan for creating a new plugin that uses one or more of the hooks to achieve the desired changes. Include which hooks to use, how to use them, and any additional code or logic needed.

			Do not write the actual plugin code. Your response should be a valid JSON object, with clear and concise text in each of the following sections:
			{
				"technically_feasible": true,
				"explanation": "If not feasible, explain why. If feasible, you can skip this.",
				"hooks": [ "hook_name_1", "hook_name_2" ],
				"plan": "The development plan if feasible.",
				"plugin_name": "Name of the new plugin"
			}

			Do not add any additional commentary. Make sure your response only contains a valid JSON object with the specified sections. Do not use Markdown formatting in your answer.
			PROMPT;
		}

		$params  = [ 'response_format' => [ 'type' => 'json_object' ] ];
		$payload = AI_Utils::get_multimodal_payload( $this->ai_api, $prompt, $prompt_images );
		if ( ! empty( $payload ) ) {
			$params = array_merge( $params, $payload );
		}

		$plan_data = $this->ai_api->send_prompt( $prompt, '', $params );

		return $plan_data;
	}

	/**
	 * Generate code for a new plugin using hooks based on a plan.
	 *
	 * @param string $original_plugin The plugin name (e.g., 'Rank Math SEO').
	 * @param array  $hooks Array of hooks with name, type, and context.
	 * @param string $ai_plan The AI-generated plan.
	 * @param string $plugin_name The name of the new plugin.
	 * @return string|WP_Error The generated plugin code.
	 */
	public function generate_hooks_extension_code( $original_plugin, $hooks, $ai_plan, $plugin_name ) {
		$hooks_list = '';
		foreach ( $hooks as $hook ) {
			$hooks_list .= "```\n{$hook['type']}: '{$hook['name']}'\n\nContext:\n{$hook['context']}\n```\n\n";
		}

		$prompt = <<<PROMPT
			I need to create a new WordPress plugin that extends the functionality of an existing plugin ($original_plugin) using its hooks. Here are the hooks we can use to achieve the desired extension:

			$hooks_list

			Here is the plan for the extension:

			$ai_plan

			The name of the new plugin is: "$plugin_name".

			Please write the complete code for the new plugin. The plugin should be contained within a single PHP file. Include the necessary plugin header, and ensure that it uses the specified hooks correctly to achieve the desired extension.

			Do not use Markdown formatting in your answer. Ensure the response does not contain any explanation or commentary, ONLY the complete, working code without any placeholders. "Add X here" comments are not allowed in the code, you need to write out the full, working code.

			Important: all code should be self-contained within one PHP file and follow WordPress coding standards. Use inline Javascript and CSS, inside the main PHP file. Additional CSS or JS files cannot be included. Use appropriate WP hooks, actions, and filters as necessary. Always use "WP-Autoplugin" for the Author of the plugin, with Author URI: https://wp-autoplugin.com. Do not add the final closing "?>" tag in the PHP file.
			PROMPT;

		$plan_data = $this->ai_api->send_prompt( $prompt );

		return $plan_data;
	}

	/**
	 * Plan a theme extension using hooks.
	 *
	 * @param string $original_theme_name The theme name (e.g., 'Twenty Twenty-Four').
	 * @param array  $hooks Array of hooks with name, type, and context.
	 * @param string $theme_changes Description of the desired changes.
	 * @return string|WP_Error The AI-generated plan in JSON format.
	 */
	public function plan_theme_hooks_extension( $original_theme_name, $hooks, $theme_changes, $prompt_images = [] ) {
		$hooks_list = '';
		foreach ( $hooks as $hook ) {
			$hooks_list .= "```\n{$hook['type']}: '{$hook['name']}'\n\nContext:\n{$hook['context']}\n```\n\n";
		}

		if ( empty( $hooks_list ) ) {
			$hooks_list = esc_html__( '(No custom hooks found in the theme code.)', 'wp-autoplugin' );
		}

		$plugin_mode = get_option( 'wp_autoplugin_plugin_mode', 'simple' );

		if ( 'complex' === $plugin_mode ) {
			$prompt = <<<PROMPT
			I want to extend a WordPress theme ('$original_theme_name'), preferably using hooks available in the theme and core WordPress hooks.
			Your task is to create a plan for this extension. Here are the theme hooks we can use:

			$hooks_list

			I want to make the following changes to the theme's functionality:

			$theme_changes

			Please determine if the requested extension is technically feasible with the available hooks. If it is not feasible, explain why.

			If feasible, provide a technical specification and development plan for creating a new extension plugin that uses one or more of the hooks to achieve the desired changes. Include which hooks to use, how to use them, and any additional code or logic needed.

			Do not write the actual code. Your response must be a single JSON object with the following structure:
			{
				"technically_feasible": true,
				"explanation": "If not feasible, explain why (otherwise brief)",
				"hooks": [ "hook_name_1", "hook_name_2" ],
				"plan": "The development plan if feasible.",
				"plugin_name": "Name of the new extension plugin",
				"project_structure": {
					"directories": ["includes/", "assets/css/", "assets/js/"],
					"files": [
						{ "path": "extension-plugin.php", "type": "php", "description": "Main plugin file with headers and bootstrapping." }
					]
				}
			}

			Guidelines:
			- Keep structure minimal and only include necessary PHP/CSS/JS files
			- Ensure the plan focuses on using the selected hooks correctly
			- Do not include any code; only the JSON plan
			- Response must be a single JSON object without Markdown formatting
			PROMPT;
		} else {
			$prompt = <<<PROMPT
			I want to extend a WordPress theme ('$original_theme_name'), preferably using hooks available in the theme.
			Your task is to create a plan for this extension. Here are the theme hooks we can use:

			$hooks_list

			I want to make the following changes to the theme's functionality:

			$theme_changes

			In addition to the provided theme hooks, you may also make use of core WordPress hooks (actions and filters) if needed to achieve the desired changes.

			Please determine if the requested extension is technically feasible with the available theme and core hooks. If it is not feasible, explain why.

			If feasible, provide a technical specification and development plan for creating a plugin that uses one or more of the hooks to achieve the desired changes. Include which hooks to use, how to use them, and any additional code or logic needed.

			Do not write the actual code. Your response should be a valid JSON object, with clear and concise text in each of the following sections:
			{
				"technically_feasible": true,
				"explanation": "If not feasible, explain why. If feasible, you can skip this.",
				"hooks": [ "hook_name_1", "hook_name_2" ],
				"plan": "The development plan if feasible.",
				"plugin_name": "Name of the new plugin"
			}

			Do not add any additional commentary. Do not write example code. Make sure your response only contains a valid JSON object with the specified sections. Do not use Markdown formatting in your answer.
			PROMPT;
		}

		$params  = [ 'response_format' => [ 'type' => 'json_object' ] ];
		$payload = AI_Utils::get_multimodal_payload( $this->ai_api, $prompt, $prompt_images );
		if ( ! empty( $payload ) ) {
			$params = array_merge( $params, $payload );
		}

		$plan_data = $this->ai_api->send_prompt( $prompt, '', $params );

		return $plan_data;
	}

	/**
	 * Generate code for a new plugin using theme hooks based on a plan.
	 *
	 * @param string $original_theme_name The theme name (e.g., 'Twenty Twenty-Four').
	 * @param array  $hooks Array of hooks with name, type, and context.
	 * @param string $ai_plan The AI-generated plan.
	 * @param string $plugin_name The name of the new plugin.
	 * @return string|WP_Error The generated plugin code.
	 */
	public function generate_theme_extension_code( $original_theme_name, $hooks, $ai_plan, $plugin_name ) {
		$hooks_list = '';
		foreach ( $hooks as $hook ) {
			$hooks_list .= "```\n{$hook['type']}: '{$hook['name']}'\n\nContext:\n{$hook['context']}\n```\n\n";
		}

		$prompt = <<<PROMPT
			I need to create a new WordPress plugin that extends the functionality of an existing theme ('$original_theme_name') using its hooks. Here are the hooks we can use to achieve the desired extension:

			$hooks_list

			Here is the plan for the extension:

			$ai_plan

			The name of the new plugin is: "$plugin_name".

			Please write the complete code for the new plugin. The plugin should be contained within a single PHP file. Include the necessary plugin header, and ensure that it uses the specified hooks correctly to achieve the desired extension.

			Do not use Markdown formatting in your answer. Ensure the response does not contain any explanation or commentary, ONLY the complete, working code without any placeholders. "Add X here" comments are not allowed in the code, you need to write out the full, working code.

			Important: all code should be self-contained within one PHP file and follow WordPress coding standards. Use inline Javascript and CSS, inside the main PHP file. Additional CSS or JS files cannot be included. Use appropriate WP hooks, actions, and filters as necessary. Always use "WP-Autoplugin" for the Author of the plugin, with Author URI: https://wp-autoplugin.com. Do not add the final closing "?>" tag in the PHP file.
			PROMPT;

		$plugin_code = $this->ai_api->send_prompt( $prompt );

		return $plugin_code;
	}

	/**
	 * Generate a single file for a complex theme-hooks-based extension plugin.
	 *
	 * @param string $original_theme_name   The original theme name.
	 * @param array  $hooks                 Array of hooks with name, type, and context (filtered to those used).
	 * @param array  $file_info             File info from project_structure (path, type, description).
	 * @param array  $project_structure     Full project structure array.
	 * @param array  $ai_plan_array         The full JSON plan array.
	 * @param array  $generated_files       Map of already generated files for context.
	 * @return string|\WP_Error            File contents.
	 */
	public function generate_theme_extension_file( $original_theme_name, $hooks, $file_info, $project_structure, $ai_plan_array, $generated_files = [] ) {
		$file_type        = isset( $file_info['type'] ) ? $file_info['type'] : 'php';
		$file_path        = isset( $file_info['path'] ) ? $file_info['path'] : 'plugin.php';
		$file_description = isset( $file_info['description'] ) ? $file_info['description'] : '';

		$context   = $this->build_file_context( is_array( $generated_files ) ? $generated_files : [], is_array( $project_structure ) ? $project_structure : [] );
		$plan_json = wp_json_encode( $ai_plan_array );

		$hooks_list = '';
		foreach ( $hooks as $hook ) {
			$hooks_list .= "\n- {$hook['type']}: '{$hook['name']}'\nContext:\n{$hook['context']}\n";
		}

		if ( 'php' === $file_type ) {
			$prompt       = "Generate only the PHP code for a single file in a multi-file WordPress extension plugin that extends the theme \"$original_theme_name\" using its hooks.\n\n";
			$prompt      .= "File Path: $file_path\n";
			$prompt      .= "File Purpose: $file_description\n\n";
			$prompt      .= "Plugin Plan (JSON):\n" . $plan_json . "\n\n";
			$prompt      .= "Available Hooks to use:\n$hooks_list\n\n";
			$prompt      .= "Project Context:\n$context\n\n";
			$prompt      .= "Requirements:\n";
			$prompt      .= "- Follow WordPress coding standards; use tabs for indentation\n";
			$prompt      .= "- Correctly use the specified hooks in this file's implementation\n";
			$is_main_file = ( false === strpos( $file_path, '/' ) ) && ( substr( $file_path, -4 ) === '.php' );
			if ( $is_main_file ) {
				$prompt .= "- This is the main plugin file; include a proper WordPress plugin header\n";
			} else {
				$prompt .= "- This is a supporting PHP file; do not include a plugin header\n";
			}
			$prompt .= "- Do not include placeholders; provide complete working code\n";
			$prompt .= "- Do not output any explanation or markdown; return only raw PHP code for $file_path";

			return $this->ai_api->send_prompt( $prompt );
		} elseif ( 'css' === $file_type ) {
			$prompt  = "Generate only the CSS code for the following file in a WordPress extension plugin:\n\n";
			$prompt .= "File Path: $file_path\n";
			$prompt .= "File Purpose: $file_description\n\n";
			$prompt .= 'Do not include explanations or markdown. Return only raw CSS.';
			return $this->ai_api->send_prompt( $prompt );
		} elseif ( 'js' === $file_type ) {
			$prompt  = "Generate only the JavaScript code for the following file in a WordPress extension plugin:\n\n";
			$prompt .= "File Path: $file_path\n";
			$prompt .= "File Purpose: $file_description\n\n";
			$prompt .= 'Use jQuery if needed. Do not include explanations or markdown. Return only raw JavaScript.';
			return $this->ai_api->send_prompt( $prompt );
		}

		return new \WP_Error( 'invalid_file_type', 'Unsupported file type: ' . $file_type );
	}

	/**
	 * Generate a single file for a complex hooks-based extension plugin.
	 *
	 * @param string $original_plugin    The original plugin name.
	 * @param array  $hooks              Array of hooks with name, type, and context (filtered to those used).
	 * @param array  $file_info          File info from project_structure (path, type, description).
	 * @param array  $project_structure  Full project structure array.
	 * @param array  $ai_plan_array      The full JSON plan array.
	 * @param array  $generated_files    Map of already generated files for context.
	 * @return string|\WP_Error         File contents.
	 */
	public function generate_hooks_extension_file( $original_plugin, $hooks, $file_info, $project_structure, $ai_plan_array, $generated_files = [] ) {
		$file_type        = isset( $file_info['type'] ) ? $file_info['type'] : 'php';
		$file_path        = isset( $file_info['path'] ) ? $file_info['path'] : 'plugin.php';
		$file_description = isset( $file_info['description'] ) ? $file_info['description'] : '';

		$context   = $this->build_file_context( is_array( $generated_files ) ? $generated_files : [], is_array( $project_structure ) ? $project_structure : [] );
		$plan_json = wp_json_encode( $ai_plan_array );

		$hooks_list = '';
		foreach ( $hooks as $hook ) {
			$hooks_list .= "\n- {$hook['type']}: '{$hook['name']}'\nContext:\n{$hook['context']}\n";
		}

		if ( 'php' === $file_type ) {
			$prompt  = "Generate only the PHP code for a single file in a multi-file WordPress extension plugin that extends \"$original_plugin\" using its hooks.\n\n";
			$prompt .= "File Path: $file_path\n";
			$prompt .= "File Purpose: $file_description\n\n";
			$prompt .= "Plugin Plan (JSON):\n" . $plan_json . "\n\n";
			$prompt .= "Available Hooks to use:\n$hooks_list\n\n";
			$prompt .= "Project Context:\n$context\n\n";
			$prompt .= "Requirements:\n";
			$prompt .= "- Follow WordPress coding standards; use tabs for indentation\n";
			$prompt .= "- Correctly use the specified hooks in this file's implementation\n";
			// Detect main file (simple heuristic: top-level PHP file)
			$is_main_file = ( false === strpos( $file_path, '/' ) ) && ( substr( $file_path, -4 ) === '.php' );
			if ( $is_main_file ) {
				$prompt .= "- This is the main plugin file; include a proper WordPress plugin header\n";
			} else {
				$prompt .= "- This is a supporting PHP file; do not include a plugin header\n";
			}
			$prompt .= "- Do not include placeholders; provide complete working code\n";
			$prompt .= "- Do not output any explanation or markdown; return only raw PHP code for $file_path";

			return $this->ai_api->send_prompt( $prompt );
		} elseif ( 'css' === $file_type ) {
			$prompt  = "Generate only the CSS code for the following file in a WordPress extension plugin:\n\n";
			$prompt .= "File Path: $file_path\n";
			$prompt .= "File Purpose: $file_description\n\n";
			$prompt .= 'Do not include explanations or markdown. Return only raw CSS.';
			return $this->ai_api->send_prompt( $prompt );
		} elseif ( 'js' === $file_type ) {
			$prompt  = "Generate only the JavaScript code for the following file in a WordPress extension plugin:\n\n";
			$prompt .= "File Path: $file_path\n";
			$prompt .= "File Purpose: $file_description\n\n";
			$prompt .= 'Use jQuery if needed. Do not include explanations or markdown. Return only raw JavaScript.';
			return $this->ai_api->send_prompt( $prompt );
		}

		return new \WP_Error( 'invalid_file_type', 'Unsupported file type: ' . $file_type );
	}

	/**
	 * Build context string from generated files and project structure.
	 *
	 * @param array $generated_files Previously generated files.
	 * @param array $project_structure The project structure.
	 * @return string
	 */
	private function build_file_context( $generated_files, $project_structure ) {
		$context = "Project Structure:\n";

		if ( isset( $project_structure['directories'] ) && is_array( $project_structure['directories'] ) ) {
			$context .= 'Directories: ' . implode( ', ', $project_structure['directories'] ) . "\n";
		}

		if ( isset( $project_structure['files'] ) && is_array( $project_structure['files'] ) ) {
			$context .= "Files:\n";
			foreach ( $project_structure['files'] as $file ) {
				$path     = isset( $file['path'] ) ? $file['path'] : '';
				$type     = isset( $file['type'] ) ? $file['type'] : '';
				$desc     = isset( $file['description'] ) ? $file['description'] : '';
				$context .= "- $path ($type): $desc\n";
			}
		}

		if ( ! empty( $generated_files ) && is_array( $generated_files ) ) {
			$context .= "\nPreviously Generated Files:\n";
			foreach ( $generated_files as $file_path => $file_content ) {
				$context .= "File: $file_path\n";
				$lines    = explode( "\n", (string) $file_content );
				$snippet  = implode( "\n", array_slice( $lines, 0, 200 ) );
				$context .= "```\n$snippet\n```\n";
			}
		}

		return $context;
	}

	/**
	 * Collect all action and filter hooks from a plugin's PHP files.
	 *
	 * @param string $plugin_file The plugin file (e.g., 'my-plugin/my-plugin.php').
	 * @return array Array of hooks with name, type, and context.
	 */
	public static function get_plugin_hooks( $plugin_file ) {
		$hooks         = [];
		$excluded_dirs = [
			'vendor',
			'node_modules',
			'.git',
			'tests',
			'docs',
			'build',
			'dist',
		];

		// Determine plugin slug.
		$plugin_slug = strpos( $plugin_file, '/' ) !== false ? dirname( $plugin_file ) : $plugin_file;

		// Get custom extraction config for the plugin, if any.
		$config = self::get_extraction_config( $plugin_slug );

		if ( $config ) {
			$regex_pattern         = $config['regex_pattern'];
			$method_to_type        = $config['method_to_type'];
			$hook_name_transformer = $config['hook_name_transformer'] ?? null;
		} else {
			// Default configuration for standard WordPress hooks.
			$regex_pattern         = '/(apply_filters|do_action|do_action_ref_array)\s*\(\s*([\'"]([^\'"]+)[\'"]|\$[^,]+|\w+)\s*,/m';
			$method_to_type        = [
				'apply_filters'       => 'filter',
				'do_action'           => 'action',
				'do_action_ref_array' => 'action',
			];
			$hook_name_transformer = null;
		}

		// Single-file plugin.
		if ( strpos( $plugin_file, '/' ) === false ) {
			$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
			$hooks       = self::process_file_hooks( $plugin_path, $regex_pattern, $method_to_type, $hook_name_transformer );
		} else {
			// Multi-file plugin.
			$plugin_dir  = dirname( $plugin_file );
			$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_dir;

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $plugin_path, \RecursiveDirectoryIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
					continue;
				}

				// Check if file is in an excluded directory.
				$relative_path = str_replace( $plugin_path, '', $file->getPath() );
				$skip_file     = false;
				foreach ( $excluded_dirs as $excluded_dir ) {
					if ( strpos( $relative_path, '/' . $excluded_dir . '/' ) !== false ) {
						$skip_file = true;
						break;
					}
				}
				if ( $skip_file ) {
					continue;
				}

				$hooks = array_merge( $hooks, self::process_file_hooks( $file->getPathname(), $regex_pattern, $method_to_type, $hook_name_transformer ) );
			}
		}

		// Remove duplicates by `name` key.
		$unique_hooks = [];
		foreach ( $hooks as $hook ) {
			if ( ! isset( $unique_hooks[ $hook['name'] ] ) ) {
				$unique_hooks[ $hook['name'] ] = $hook;
			}
		}
		return array_values( $unique_hooks );
	}

	/**
	 * Collect all action and filter hooks from a theme's PHP files.
	 *
	 * @param string $theme_slug The theme slug (e.g., 'twentytwentyfour').
	 * @return array Array of hooks with name, type, and context.
	 */
	public static function get_theme_hooks( $theme_slug ) {
		$hooks         = [];
		$excluded_dirs = [
			'vendor',
			'node_modules',
			'.git',
			'tests',
			'docs',
			'build',
			'dist',
		];

		// Get custom extraction config for the theme, if any.
		$config = self::get_extraction_config( $theme_slug );

		if ( $config ) {
			$regex_pattern         = $config['regex_pattern'];
			$method_to_type        = $config['method_to_type'];
			$hook_name_transformer = $config['hook_name_transformer'] ?? null;
		} else {
			// Default configuration for standard WordPress hooks.
			$regex_pattern         = '/(apply_filters|do_action|do_action_ref_array)\s*\(\s*([\'"]([^\'"]+)[\'"]|\$[^,]+|\w+)\s*,/m';
			$method_to_type        = [
				'apply_filters'       => 'filter',
				'do_action'           => 'action',
				'do_action_ref_array' => 'action',
			];
			$hook_name_transformer = null;
		}

		// Theme directory path.
		$theme_path = get_theme_root() . '/' . $theme_slug;

		if ( ! is_dir( $theme_path ) ) {
			return $hooks;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $theme_path, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
				continue;
			}

			// Check if file is in an excluded directory.
			$relative_path = str_replace( $theme_path, '', $file->getPath() );
			$skip_file     = false;
			foreach ( $excluded_dirs as $excluded_dir ) {
				if ( strpos( $relative_path, '/' . $excluded_dir . '/' ) !== false ) {
					$skip_file = true;
					break;
				}
			}
			if ( $skip_file ) {
				continue;
			}

			$hooks = array_merge( $hooks, self::process_file_hooks( $file->getPathname(), $regex_pattern, $method_to_type, $hook_name_transformer ) );
		}

		// Remove duplicates by `name` key.
		$unique_hooks = [];
		foreach ( $hooks as $hook ) {
			if ( ! isset( $unique_hooks[ $hook['name'] ] ) ) {
				$unique_hooks[ $hook['name'] ] = $hook;
			}
		}
		return array_values( $unique_hooks );
	}

	/**
	 * Get custom hook extraction configuration for a plugin.
	 *
	 * @param string $plugin_slug The plugin slug (e.g., 'seo-by-rank-math').
	 * @return array|null Custom config array or null if none exists.
	 */
	private static function get_extraction_config( $plugin_slug ) {
		$configs = apply_filters( 'wp_autoplugin_hook_extraction_config', [] );
		return isset( $configs[ $plugin_slug ] ) ? $configs[ $plugin_slug ] : null;
	}

	/**
	 * Process a single PHP file to extract hooks.
	 *
	 * @param string   $file_path            Path to the PHP file.
	 * @param string   $regex_pattern        Regex pattern to match hook calls.
	 * @param array    $method_to_type       Mapping of method names to hook types.
	 * @param callable $hook_name_transformer Optional function to transform hook names.
	 * @return array Array of hooks found in the file.
	 */
	private static function process_file_hooks( $file_path, $regex_pattern, $method_to_type, $hook_name_transformer = null ) {
		if ( $hook_name_transformer === null ) {
			$hook_name_transformer = function ( $name ) {
				return $name;
			};
		}

		$hooks   = [];
		$content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- We need to read the file content.
		$lines   = explode( "\n", $content );

		preg_match_all( $regex_pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$method_name = $match[1][0];
			if ( ! isset( $method_to_type[ $method_name ] ) ) {
				continue;
			}
			$hook_type = $method_to_type[ $method_name ];

			// Extract hook name (assuming it's a quoted string).
			if ( ! isset( $match[3] ) ) {
				continue; // Skip if not a quoted string.
			}
			$hook_name = $match[3][0];
			$hook_name = call_user_func( $hook_name_transformer, $hook_name, $match );

			// Find the line number where this call begins.
			$offset         = $match[0][1];
			$current_offset = 0;
			$hook_line      = 0;
			foreach ( $lines as $i => $line ) {
				$current_offset += strlen( $line ) + 1; // +1 for newline.
				if ( $current_offset > $offset ) {
					$hook_line = $i;
					break;
				}
			}

			// Find statement end line.
			$statement_end_line = self::find_statement_end_line( $lines, $hook_line );

			// Find docblock start line if any.
			$docblock_start_line = self::find_docblock_start_line( $lines, $hook_line );

			// Filter the number of context lines.
			$context_lines_count = apply_filters(
				'wp_autoplugin_hook_context_lines',
				self::DEFAULT_CONTEXT_LINES
			);

			// Determine context boundaries.
			$context_start = max( 0, min( $docblock_start_line, $hook_line ) - $context_lines_count );
			$context_end   = min( count( $lines ) - 1, $statement_end_line + $context_lines_count );

			$context_lines = array_slice( $lines, $context_start, $context_end - $context_start + 1 );
			$context       = implode( "\n", $context_lines );

			$hooks[] = [
				'name'    => $hook_name,
				'type'    => $hook_type,
				'context' => $context,
			];
		}

		return $hooks;
	}

	/**
	 * Attempt to find the last line of the current statement by tracking parentheses
	 * or scanning until a semicolon outside parentheses.
	 *
	 * @param string[] $lines Array of file lines.
	 * @param int      $start Line index where the hook call begins.
	 * @return int Line index where the statement is deemed to end.
	 */
	private static function find_statement_end_line( array $lines, int $start ) {
		$open_parentheses  = 0;
		$found_first_paren = false;
		$line_count        = count( $lines );

		for ( $i = $start; $i < $line_count; $i++ ) {
			$line = $lines[ $i ];

			for ( $j = 0, $len = strlen( $line ); $j < $len; $j++ ) {
				$char = $line[ $j ];

				if ( ! $found_first_paren && $char === '(' ) {
					$found_first_paren = true;
					$open_parentheses  = 1;
					continue;
				}

				if ( $found_first_paren ) {
					if ( $char === '(' ) {
						++$open_parentheses;
					} elseif ( $char === ')' ) {
						--$open_parentheses;
					}
				}
			}

			if ( $found_first_paren && $open_parentheses <= 0 ) {
				if ( preg_match( '/;\s*$/', trim( $line ) ) ) {
					return $i;
				}
			}
		}

		return $line_count - 1;
	}

	/**
	 * Find a docblock that ends on or directly above $line_index.
	 *
	 * @param string[] $lines      Array of file lines.
	 * @param int      $line_index Line index where the hook call begins.
	 * @return int
	 */
	private static function find_docblock_start_line( array $lines, int $line_index ) {
		$line_index = max( 0, $line_index - 1 );

		$within_docblock = false;
		for ( ; $line_index >= 0; $line_index-- ) {
			$trimmed = trim( $lines[ $line_index ] );
			if ( ! $within_docblock && preg_match( '/\*\/$/', $trimmed ) ) {
				$within_docblock = true;
				continue;
			}

			if ( $within_docblock ) {
				if ( strpos( $trimmed, '/**' ) === 0 ) {
					return $line_index;
				}
				if ( strpos( $trimmed, '*' ) === 0 || $trimmed === '' ) {
					continue;
				} else {
					break;
				}
			} elseif ( $trimmed !== '' && strpos( $trimmed, '//' ) !== 0 ) {
					return $line_index + 1;
			}
		}

		return $line_index + 1;
	}
}
