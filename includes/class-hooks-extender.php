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
	 * @param array  $hooks Array of hooks with name, type, and context.
	 * @param string $plugin_changes Description of the desired changes.
	 * @return string|WP_Error The AI-generated plan in JSON format.
	 */
	public function plan_plugin_hooks_extension( $hooks, $plugin_changes ) {
		$hooks_list = '';
		foreach ( $hooks as $hook ) {
			$hooks_list .= "```\n{$hook['type']}: '{$hook['name']}'\n\nContext:\n{$hook['context']}\n```\n\n";
		}

		$prompt = <<<PROMPT
			I want to extend a WordPress plugin using its hooks. Here are the available hooks in the plugin:

			$hooks_list
			
			I want to make the following changes to the plugin's functionality:

			$plugin_changes

			Please provide a technical specification and development plan for creating a new plugin that uses one or more of these hooks to achieve the desired changes. Include which hooks to use, how to use them, and any additional code or logic needed.

			Also, determine if the requested extension is technically feasible with the available hooks. If it is not feasible, explain why.

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

		error_log( '------------------- PROMPT -------------------' );
		error_log( print_r( $prompt, true ) );

		$plan_data = $this->ai_api->send_prompt( $prompt );
		error_log( '------------------- AI RESPONSE -------------------' );
		error_log( print_r( $plan_data, true ) );

		return $plan_data;
	}

	/**
	 * Generate code for a new plugin using hooks based on a plan.
	 *
	 * @param array  $hooks Array of hooks with name, type, and context.
	 * @param string $ai_plan The AI-generated plan.
	 * @param string $plugin_name The name of the new plugin.
	 * @return string|WP_Error The generated plugin code.
	 */
	public function generate_hooks_extension_code( $hooks, $ai_plan, $plugin_name ) {
		$hooks_list = '';
		foreach ( $hooks as $hook ) {
			$hooks_list .= "```\n{$hook['type']}: '{$hook['name']}'\n\nContext:\n{$hook['context']}\n```\n\n";
		}

		$prompt = <<<PROMPT
			I need to create a new WordPress plugin that extends the functionality of an existing plugin using its hooks. Here are the available hooks in the original plugin:

			$hooks_list

			Here is the plan for the extension:

			$ai_plan

			The name of the new plugin is: "$plugin_name".

			Please write the complete code for the new plugin. The plugin should be contained within a single PHP file. Include the necessary plugin header, and ensure that it uses the specified hooks correctly to achieve the desired extension.

			Do not use Markdown formatting in your answer. Ensure the response does not contain any explanation or commentary, ONLY the complete, working code without any placeholders. "Add X here" comments are not allowed in the code, you need to write out the full, working code.

			Important: all code should be self-contained within one PHP file and follow WordPress coding standards. Use inline Javascript and CSS, inside the main PHP file. Additional CSS or JS files cannot be included. Use appropriate WP hooks, actions, and filters as necessary. Always use "WP-Autoplugin" for the Author of the plugin, with Author URI: https://wp-autoplugin.com. Do not add the final closing "?>" tag in the PHP file.
		PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Collect all action and filter hooks from a plugin's PHP files.
	 *
	 * @param string $plugin_file The plugin file (e.g., 'my-plugin/my-plugin.php').
	 * @return array Array of hooks with name, type, and context.
	 */
	public static function get_plugin_hooks( $plugin_file ) {
		$hooks = [];
		$excluded_dirs = array(
			'vendor',
			'node_modules',
			'.git',
			'tests',
			'docs',
			'build',
			'dist',
		);

		// Determine plugin slug
		$plugin_slug = strpos( $plugin_file, '/' ) !== false ? dirname( $plugin_file ) : $plugin_file;

		// Get custom extraction config for the plugin, if any
		$config = self::get_extraction_config( $plugin_slug );

		if ( $config ) {
			$regex_pattern = $config['regex_pattern'];
			$method_to_type = $config['method_to_type'];
			$hook_name_transformer = $config['hook_name_transformer'] ?? null;
		} else {
			// Default configuration for standard WordPress hooks
			$regex_pattern = '/(apply_filters|do_action|do_action_ref_array)\s*\(\s*([\'"]([^\'"]+)[\'"]|\$[^,]+|\w+)\s*,/m';
			$method_to_type = [
				'apply_filters' => 'filter',
				'do_action' => 'action',
				'do_action_ref_array' => 'action',
			];
			$hook_name_transformer = null;
		}

		// Single-file plugin
		if ( strpos( $plugin_file, '/' ) === false ) {
			$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
			$hooks = self::process_file_hooks( $plugin_path, $regex_pattern, $method_to_type, $hook_name_transformer );
		} else {
			// Multi-file plugin
			$plugin_dir = dirname( $plugin_file );
			$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_dir;

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $plugin_path, \RecursiveDirectoryIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
					continue;
				}

				// Check if file is in an excluded directory
				$relative_path = str_replace( $plugin_path, '', $file->getPath() );
				$skip_file = false;
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

		// Remove duplicates by `name` key
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
			$hook_name_transformer = function( $name ) { return $name; };
		}

		$hooks = [];
		$content = file_get_contents( $file_path );
		$lines = explode( "\n", $content );

		preg_match_all( $regex_pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$method_name = $match[1][0];
			if ( ! isset( $method_to_type[ $method_name ] ) ) {
				continue;
			}
			$hook_type = $method_to_type[ $method_name ];

			// Extract hook name (assuming it's a quoted string)
			if ( ! isset( $match[3] ) ) {
				continue; // Skip if not a quoted string
			}
			$hook_name = $match[3][0];
			$hook_name = call_user_func( $hook_name_transformer, $hook_name, $match );

			// Find the line number where this call begins
			$offset = $match[0][1];
			$current_offset = 0;
			$hook_line = 0;
			foreach ( $lines as $i => $line ) {
				$current_offset += strlen( $line ) + 1; // +1 for newline
				if ( $current_offset > $offset ) {
					$hook_line = $i;
					break;
				}
			}

			// Find statement end line
			$statement_end_line = self::find_statement_end_line( $lines, $hook_line );

			// Find docblock start line if any
			$docblock_start_line = self::find_docblock_start_line( $lines, $hook_line );

			// Filter the number of context lines
			$context_lines_count = apply_filters(
				'wp_autoplugin_hook_context_lines',
				self::DEFAULT_CONTEXT_LINES
			);

			// Determine context boundaries
			$context_start = max( 0, min( $docblock_start_line, $hook_line ) - $context_lines_count );
			$context_end = min( count( $lines ) - 1, $statement_end_line + $context_lines_count );

			$context_lines = array_slice( $lines, $context_start, $context_end - $context_start + 1 );
			$context = implode( "\n", $context_lines );

			$hooks[] = [
				'name' => $hook_name,
				'type' => $hook_type,
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
		$open_parentheses = 0;
		$found_first_paren = false;
		$line_count = count( $lines );

		for ( $i = $start; $i < $line_count; $i++ ) {
			$line = $lines[ $i ];

			for ( $j = 0, $len = strlen( $line ); $j < $len; $j++ ) {
				$char = $line[ $j ];

				if ( ! $found_first_paren && $char === '(' ) {
					$found_first_paren = true;
					$open_parentheses = 1;
					continue;
				}

				if ( $found_first_paren ) {
					if ( $char === '(' ) {
						$open_parentheses++;
					} elseif ( $char === ')' ) {
						$open_parentheses--;
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
			} else {
				if ( $trimmed !== '' && strpos( $trimmed, '//' ) !== 0 ) {
					return $line_index + 1;
				}
			}
		}

		return $line_index + 1;
	}
}