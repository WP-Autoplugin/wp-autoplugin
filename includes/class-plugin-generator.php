<?php
/**
 * Autoplugin Generator class.
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
 * Plugin Generator class.
 */
class Plugin_Generator {

	/**
	 * AI API in use.
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
	 * Prompt the AI to generate a plan for a WordPress plugin.
	 *
	 * @param string $input The plugin features.
	 *
	 * @return string|WP_Error
	 */
	public function generate_plugin_plan( $input ) {
		$plugin_mode = get_option( 'wp_autoplugin_plugin_mode', 'simple' );
		
		if ( 'complex' === $plugin_mode ) {
			return $this->generate_complex_plugin_plan( $input );
		} else {
			return $this->generate_simple_plugin_plan( $input );
		}
	}

	/**
	 * Generate a plan for a simple single-file plugin.
	 *
	 * @param string $input The plugin features.
	 *
	 * @return string|WP_Error
	 */
	private function generate_simple_plugin_plan( $input ) {
		$prompt = <<<PROMPT
			Generate a detailed technical specification and development plan for a WordPress plugin with the following features:

			```
			$input
			```

			The plugin must be contained within a single file, including all necessary code. Do not write the actual plugin code. Your response should be a valid JSON object, with clear and concise text in each of the following sections:

			- plugin_name: Provide a concise name for the plugin.
			- design_and_architecture: Outline the overall design and architecture of the plugin, including data flow and major components.
			- detailed_feature_description: Provide a detailed description of each feature, explaining how it should be implemented.
			- user_interface: Describe the user interface elements and how users will interact with the plugin.
			- security_considerations: Discuss any security measures that need to be incorporated to ensure the plugin's safety.
			- testing_plan: Outline a plan for testing the plugin to ensure it functions correctly. There will be no test suite for the plugin, you just have to explain how the plugin works, so it can be tested correctly. The user may not be technical, so the plan should be clear and easy to follow.

			Do not add any additional commentary. Make sure your response only contains a valid JSON object with the specified sections. Do not use Markdown formatting in your answer.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt, '', [ 'response_format' => [ 'type' => 'json_object' ] ] );
	}

	/**
	 * Generate a plan for a complex multi-file plugin.
	 *
	 * @param string $input The plugin features.
	 *
	 * @return string|WP_Error
	 */
	private function generate_complex_plugin_plan( $input ) {
		$prompt = <<<PROMPT
			Generate a detailed technical specification and development plan for a WordPress plugin with the following features:
			
			```
			$input
			```
			
			This will be a complex multi-file plugin with proper directory structure. Do not write the actual plugin code. Your response should be a valid JSON object, with clear and concise text in each of the following sections:
			
			- plugin_name: Provide a concise name for the plugin.
			- design_and_architecture: Outline the overall design and architecture of the plugin, including data flow and major components. Keep it simple and avoid unnecessary complexity.
			- detailed_feature_description: Provide a detailed description of each feature, explaining how it should be implemented with minimal code.
			- user_interface: Describe the user interface elements and how users will interact with the plugin. Focus on essential UI elements only.
			- user_flows: Describe the main steps users will follow to accomplish key tasks with the plugin. Outline each flow as a sequence of actions, focusing on typical scenarios and making them easy to understand for non-technical users.
			- security_considerations: Discuss any security measures that need to be incorporated to ensure the plugin's safety.
			- testing_plan: Outline a plan for testing the plugin to ensure it functions correctly. There will be no test suite for the plugin, you just have to explain how the plugin works, so it can be tested correctly. The user may not be technical, so the plan should be clear and easy to follow.
			- project_structure: Define the file and directory structure for this plugin. This should include:
				- directories: Array of directory paths needed for the plugin (e.g., "includes/", "assets/css/", "assets/js/", "admin/")
				- files: Array of file objects, each containing:
					- path: The file path relative to plugin root
					- type: File type (php, css, or js only)
					- description: Brief description of the file's purpose
				
				Only include PHP, CSS, and JS files. No other file types. Ensure proper WordPress plugin structure with a main plugin file, and organize code logically into separate files. Keep the file structure minimal and only create files that are absolutely necessary.
			
			IMPORTANT GUIDELINES:
			- Avoid over-engineering: Write the minimum amount of code necessary to accomplish the user's specifications
			- The plugin must be self-contained and cannot use external libraries or require build steps
			- Only use external dependencies if explicitly specified in the user's requirements
			- Keep the implementation simple and straightforward
			- Focus on core functionality rather than adding unnecessary features
			
			Do not add any additional commentary. Make sure your response only contains a valid JSON object with the specified sections. Do not use Markdown formatting in your answer.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt, '', [ 'response_format' => [ 'type' => 'json_object' ] ] );
	}

	/**
	 * Prompt the AI to generate a WordPress plugin code based on a plan.
	 *
	 * @param string $plan The plugin plan.
	 *
	 * @return string|WP_Error
	 */
	public function generate_plugin_code( $plan ) {
		$plugin_mode = get_option( 'wp_autoplugin_plugin_mode', 'simple' );
		
		if ( 'complex' === $plugin_mode ) {
			// For complex mode, this method shouldn't be used directly
			// Instead, use generate_plugin_file() for individual files
			return new \WP_Error( 'invalid_mode', 'Use generate_plugin_file() for complex mode plugins.' );
		}
		
		$prompt = <<<PROMPT
			Build a single-file WordPress plugin based on the specification below. Do not use Markdown formatting in your answer. Ensure the response does not contain any explanation or commentary, ONLY the complete, working code without any placeholders. "Add X here" comments are not allowed in the code, you need to write out the full, working code.

			```
			$plan
			```

			Important: all code should be self-contained within one PHP file and follow WordPress coding standards. Use inline Javascript and CSS, inside the main PHP file. Additional CSS or JS files cannot be included. Use appropriate WP hooks, actions, and filters as necessary. Always use "WP-Autoplugin" for the Author of the plugin, with Author URI: https://wp-autoplugin.com. Do not add the final closing "?>" tag in the PHP file.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Generate a single file for a complex plugin.
	 *
	 * @param array  $file_info The file information from project structure.
	 * @param string $plan The complete plugin plan.
	 * @param array  $project_structure The complete project structure.
	 * @param array  $generated_files Previously generated files.
	 *
	 * @return string|WP_Error
	 */
	public function generate_plugin_file( $file_info, $plan, $project_structure, $generated_files = [] ) {
		$file_type = $file_info['type'];
		$file_path = $file_info['path'];
		$file_description = $file_info['description'];
		
		$context = $this->build_file_context( $generated_files, $project_structure );
		
		if ( 'php' === $file_type ) {
			return $this->generate_php_file( $file_path, $file_description, $plan, $context );
		} elseif ( 'css' === $file_type ) {
			return $this->generate_css_file( $file_path, $file_description, $plan, $context );
		} elseif ( 'js' === $file_type ) {
			return $this->generate_js_file( $file_path, $file_description, $plan, $context );
		}
		
		return new \WP_Error( 'invalid_file_type', 'Unsupported file type: ' . $file_type );
	}

	/**
	 * Generate a PHP file for the complex plugin.
	 *
	 * @param string $file_path The file path.
	 * @param string $file_description The file description.
	 * @param string $plan The plugin plan.
	 * @param string $context The context of other files.
	 *
	 * @return string|WP_Error
	 */
	private function generate_php_file( $file_path, $file_description, $plan, $context ) {
		$is_main_file = basename( $file_path ) === basename( $file_path, '.php' ) . '.php' && ! strpos( $file_path, '/' );

		$prompt = <<<PROMPT
			Generate a PHP file for a WordPress plugin with the following specifications:

			File Path: $file_path
			File Purpose: $file_description
			
			Plugin Plan:
			```
			$plan
			```

			$context

			Requirements:
			- Follow WordPress coding standards and use tabs for indentation
			- Use appropriate PHP namespaces and class structures
			- Include proper WordPress security measures (nonces, capability checks, sanitization)
			- Use "WP-Autoplugin" as the plugin author with Author URI: https://wp-autoplugin.com
			- Do not add the final closing "?>" tag in PHP files
			- Ensure the code is complete and functional – do not add placeholders
			- Ensure the code complements the overall plugin plan and works seamlessly with other files
			PROMPT;

		if ( $is_main_file ) {
			$prompt .= "- This is the main plugin file, so include the WordPress plugin header\n";
		} else {
			$prompt .= "- This is a supporting file, do not include the WordPress plugin header\n";
		}

		$prompt .= "\nReturn ONLY the PHP code ($file_path) without any explanation or markdown formatting.";

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Generate a CSS file for the complex plugin.
	 *
	 * @param string $file_path The file path.
	 * @param string $file_description The file description.
	 * @param string $plan The plugin plan.
	 * @param string $context The context of other files.
	 *
	 * @return string|WP_Error
	 */
	private function generate_css_file( $file_path, $file_description, $plan, $context ) {
		$prompt = <<<PROMPT
			Generate a CSS file for a WordPress plugin with the following specifications:

			File Path: $file_path
			File Purpose: $file_description
			
			Plugin Plan:
			```
			$plan
			```

			$context

			Requirements:
			- Follow WordPress CSS guidelines
			- Use appropriate CSS selectors that won't conflict with themes or other plugins
			- Include responsive design considerations
			- Use meaningful class names and comments
			- Ensure cross-browser compatibility
			
			Return ONLY the CSS code without any explanation or markdown formatting.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Generate a JavaScript file for the complex plugin.
	 *
	 * @param string $file_path The file path.
	 * @param string $file_description The file description.
	 * @param string $plan The plugin plan.
	 * @param string $context The context of other files.
	 *
	 * @return string|WP_Error
	 */
	private function generate_js_file( $file_path, $file_description, $plan, $context ) {
		$prompt = <<<PROMPT
			Generate a JavaScript file for a WordPress plugin with the following specifications:

			File Path: $file_path
			File Purpose: $file_description
			
			Plugin Plan:
			```
			$plan
			```

			$context

			Requirements:
			- Follow WordPress JavaScript guidelines
			- Use jQuery if needed (it's available in WordPress)
			- Include proper error handling
			- Use meaningful function names and comments
			- Ensure compatibility with WordPress admin and frontend
			- Use WordPress localization if needed
			
			Return ONLY the JavaScript code without any explanation or markdown formatting.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Build context string from generated files and project structure.
	 *
	 * @param array $generated_files Previously generated files.
	 * @param array $project_structure The project structure.
	 *
	 * @return string
	 */
	private function build_file_context( $generated_files, $project_structure ) {
		$context = "Project Structure:\n";

		if ( isset( $project_structure['directories'] ) ) {
			$context .= "Directories: " . implode( ', ', $project_structure['directories'] ) . "\n";
		}

		if ( isset( $project_structure['files'] ) ) {
			$context .= "Files:\n";
			foreach ( $project_structure['files'] as $file ) {
				$context .= "- {$file['path']} ({$file['type']}): {$file['description']}\n";
			}
		}

		if ( ! empty( $generated_files ) ) {
			$context .= "\nPreviously Generated Files:\n";
			$file_count = count( $generated_files );
			$lines_limit = $file_count > 5 ? 1000 : 2000; // Reduce context for more files

			foreach ( $generated_files as $file_path => $file_content ) {
				$context .= "File: $file_path\n";
				$lines = explode( "\n", $file_content );
				$lines_count = count( $lines );
				if ( $lines_count > $lines_limit ) {
					$context .= "Content (truncated):\n";
					$context .= "```\n";
					$context .= join( "\n", array_slice( explode( "\n", $file_content ), 0, $lines_limit ) );
					$context .= "\n```\n";
					$context .= "Content truncated to first $lines_limit lines of $lines_count total lines.\n";
				} else {
					$context .= "Content:\n";
					$context .= "```\n";
					$context .= $file_content;
					$context .= "\n```\n";
				}
			}
		}

		return $context;
	}

	/**
	 * Review the complete generated codebase and suggest improvements.
	 *
	 * @param string $plugin_plan The original plugin plan.
	 * @param array  $project_structure The project structure.
	 * @param array  $generated_files All generated files.
	 *
	 * @return string|WP_Error JSON response with suggested updates/additions.
	 */
	public function review_generated_code( $plugin_plan, $project_structure, $generated_files ) {
		$context = $this->build_file_context( $generated_files, $project_structure );

		$prompt = <<<PROMPT
			You are reviewing a complete WordPress plugin codebase for critical errors that would prevent it from working. Your goal is to identify issues that would break the plugin's functionality.

			Plugin Plan:
			```
			$plugin_plan
			```

			$context

			Analyze the codebase for critical issues only:
			- Syntax errors in PHP, CSS, or JavaScript
			- Missing required WordPress functions or hooks
			- Incorrect file references or dependencies between files
			- Critical security vulnerabilities that would cause immediate problems
			- Function/class name conflicts or undefined references
			- Missing essential WordPress plugin requirements (like proper plugin headers)
			
			If the plugin appears to work correctly as specified in the plan, respond with an empty suggestions array.
			
			Your response should be a valid JSON object with the following structure:
			{
				"review_summary": "Brief summary - either 'No critical issues found' or describe the problems",
				"suggestions": [
					{
						"action": "UPDATE",
						"file_path": "path/to/file.php",
						"file_type": "php|css|js",
						"reason": "Critical issue that prevents plugin from working",
						"description": "Specific fix needed to resolve the issue"
					}
				]
			}
			
			Guidelines:
			- ONLY suggest fixes for issues that would prevent the plugin from functioning
			- Do NOT suggest improvements, additional features, or optimizations
			- Do NOT suggest new files unless something is critically missing
			- Focus on making the existing code work, not making it better
			- If the plugin will likely work as intended, return empty suggestions array
			- Maximum 5 suggestions for only the most critical issues
			
			Return ONLY the JSON response without any explanation or markdown formatting.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt, '', [ 'response_format' => [ 'type' => 'json_object' ] ] );
	}
}
