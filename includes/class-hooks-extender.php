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
	const DEFAULT_CONTEXT_LINES = 5;

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
			$hooks_list .= "- {$hook['type']} '{$hook['name']}': {$hook['context']}\n";
		}

		$prompt = <<<PROMPT
			I want to extend a WordPress plugin using its hooks. Here are the available hooks in the plugin:
			```
			$hooks_list
			```
			I want to make the following changes to the plugin's functionality:

			$plugin_changes

			Please provide a technical specification and development plan for creating a new plugin that uses one or more of these hooks to achieve the desired changes. Include which hooks to use, how to use them, and any additional code or logic needed.

			Also, determine if the requested extension is technically feasible with the available hooks. If it is not feasible, explain why.

			Your response should be in JSON format with the following structure:
			{
				"technically_feasible": true/false,
				"explanation": "If not feasible, explain why. If feasible, you can skip this.",
				"plan": "The development plan if feasible."
				"plugin_name": "Name of the new plugin",
			}
		PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Generate code for a new plugin using hooks based on a plan.
	 *
	 * @param array  $hooks Array of hooks with name, type, and context.
	 * @param string $ai_plan The AI-generated plan.
	 * @return string|WP_Error The generated plugin code.
	 */
	public function generate_hooks_extension_code( $hooks, $ai_plan ) {
		$hooks_list = '';
		foreach ( $hooks as $hook ) {
			$hooks_list .= "- {$hook['type']} '{$hook['name']}': {$hook['context']}\n";
		}

		$prompt = <<<PROMPT
			I need to create a new WordPress plugin that extends the functionality of an existing plugin using its hooks. Here are the available hooks in the original plugin:
			```
			$hooks_list
			```
			Here is the plan for the extension:

			$ai_plan

			Please write the complete code for the new plugin. The plugin should be contained within a single PHP file. Include the necessary plugin header, and ensure that it uses the specified hooks correctly to achieve the desired extension.

			Do not write any additional code or commentary. Make sure your response only contains the whole, updated code. Do not use Markdown formatting in your answer.
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

		if ( strpos( $plugin_file, '/' ) === false ) {
			// Single-file plugin.
			$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
			return self::process_file_hooks( $plugin_path );
		}

		// Multi-file plugin.
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

			$hooks = array_merge( $hooks, self::process_file_hooks( $file->getPathname() ) );
		}

		// Remove duplicates by `name` key.
		$unique_hooks = [];
		foreach ( $hooks as $hook ) {
			if ( ! isset( $unique_hooks[ $hook['name'] ] ) ) {
				$unique_hooks[ $hook['name'] ] = $hook;
			}
		}
		$hooks = array_values( $unique_hooks );

		return $hooks;
	}

	/**
	 * Process a single PHP file to extract hooks.
	 *
	 * @param string $file_path Path to the PHP file.
	 * @return array Array of hooks found in the file.
	 */
	private static function process_file_hooks( $file_path ) {
		$hooks = [];
		$content = file_get_contents( $file_path );
		$lines = explode( "\n", $content );

		// Regex to find the start of do_action/apply_filters/do_action_ref_array calls.
		preg_match_all(
			'/(apply_filters|do_action|do_action_ref_array)\s*\(\s*([\'"]([^\'"]+)[\'"]|\$[^,]+|\w+)\s*,/m',
			$content,
			$matches,
			PREG_OFFSET_CAPTURE | PREG_SET_ORDER
		);

		foreach ( $matches as $match ) {
			$hook_type = $match[1][0];
			$hook_name_raw = $match[2][0];
			$offset = $match[0][1];

			// Try extracting the actual hook name if it's a quoted string.
			if ( ! preg_match( '/[\'"]([^\'"]+)[\'"]/', $hook_name_raw, $name_match ) ) {
				// Hook name might be a variable or something else; skip or handle differently if you prefer.
				continue;
			}
			$hook_name = $name_match[1];

			// ---- Find the line number where this call begins ----
			$current_offset = 0;
			$hook_line = 0;
			foreach ( $lines as $i => $line ) {
				$current_offset += strlen( $line ) + 1; // +1 for newline
				if ( $current_offset > $offset ) {
					$hook_line = $i;
					break;
				}
			}

			// ---- Find statement end line (so we get the entire call, including arrays) ----
			$statement_end_line = self::find_statement_end_line( $lines, $hook_line );

			// ---- Find docblock start line if any directly above this call ----
			$docblock_start_line = self::find_docblock_start_line( $lines, $hook_line );

			 // Filter the number of context lines
			$context_lines_count = apply_filters(
				'wp_autoplugin_hook_context_lines',
				self::DEFAULT_CONTEXT_LINES
			);

			// The actual top we want is either (docblock_start_line) or (hook_line),
			// whichever is smaller, minus filtered number of context lines.
			$context_start = max( 0, min( $docblock_start_line, $hook_line ) - $context_lines_count );

			// And then filtered number of lines after the statement end line, if possible.
			$context_end = min( count( $lines ) - 1, $statement_end_line + $context_lines_count );

			$context_lines = array_slice( $lines, $context_start, $context_end - $context_start + 1 );
			$context = implode( "\n", $context_lines );

			$hooks[] = [
				'name' => $hook_name,
				'type' => ( $hook_type === 'apply_filters' ? 'filter' : 'action' ),
				'context' => $context,
			];
		}

		return $hooks;
	}

	/**
	 * Attempt to find the last line of the current statement by tracking parentheses
	 * or scanning until a semicolon outside parentheses. The simpler approach is
	 * to just keep scanning forward while the parentheses aren't balanced.
	 *
	 * @param string[] $lines   Array of file lines.
	 * @param int      $start   Line index where the hook call begins.
	 * @return int              Line index where the statement is deemed to end.
	 */
	private static function find_statement_end_line( array $lines, int $start ) {
		$open_parentheses = 0;
		$found_first_paren = false;
		$line_count = count( $lines );

		for ( $i = $start; $i < $line_count; $i++ ) {
			$line = $lines[$i];

			// Go character by character.
			for ( $j = 0, $len = strlen( $line ); $j < $len; $j++ ) {
				$char = $line[$j];

				// If we haven't encountered the opening '(' yet, detect it:
				if ( !$found_first_paren && $char === '(' ) {
					$found_first_paren = true;
					$open_parentheses = 1;
					continue;
				}

				if ( $found_first_paren ) {
					if ( $char === '(' ) {
						$open_parentheses++;
					} elseif ( $char === ')' ) {
						$open_parentheses--;
						// If parentheses are balanced and the next significant
						// character might be a semicolon, we can check for that.
					}
				}
			}

			// If parentheses are balanced (0) and we did find an opening one,
			// check if this line ends with a semicolon or the next line has
			// something else. For many WP calls, once parentheses close, that's it.
			if ( $found_first_paren && $open_parentheses <= 0 ) {
				// If there's a semicolon on this line or nothing that suggests continuation,
				// consider that the end. This is not bulletproof for all cases, but it works
				// for typical WP calls.
				if ( preg_match( '/;\s*$/', trim( $line ) ) ) {
					return $i;
				}
				// Keep scanning in case the semicolon is on the next line (rare multi-line).
			}
		}

		// If we never found a closing semicolon, we just return the last line we checked.
		return $line_count - 1;
	}

	/**
	 * Find a docblock that ends on or directly above $line_index. If we detect
	 * `/ ** ... * /` right above, return the top line of that docblock. Otherwise,
	* just return the $line_index itself (meaning no docblock).
	*
	* @param string[] $lines
	* @param int      $line_index
	* @return int
	*/
	private static function find_docblock_start_line( array $lines, int $line_index ) {
		// Step up from $line_index − 1 to see if there's a `* /`.
		// If there's a docblock, we want all lines from `/ **` to `* /`.
		$line_index = max( 0, $line_index - 1 );
		// If there's any blank line or code before `/ **`, we stop.

		// Move up until we find `* /` or the start of the docblock or stop if we see
		// a line that doesn't look like part of a docblock.
		$within_docblock = false;
		for ( ; $line_index >= 0; $line_index-- ) {
			$trimmed = trim( $lines[$line_index] );
			if ( !$within_docblock && preg_match( '/\*\/$/', $trimmed ) ) {
				$within_docblock = true;
				continue;
			}

			if ( $within_docblock ) {
				// If we've reached the opening of a docblock, return that line index.
				if ( strpos( $trimmed, '/'.'**' ) === 0 ) {
					return $line_index;
				}
				// If it's just a middle line of the docblock, keep going up.
				// If we find a line that doesn't match docblock syntax at all,
				// we break out. But typically we keep going until we see `/ **`.
				if ( strpos( $trimmed, '*' ) === 0 || $trimmed === '' ) {
					continue;
				} else {
					// We encountered something that isn't part of a docblock; break.
					break;
				}
			} else {
				// Not within a docblock, so if we see something that isn't empty or comment,
				// it means there's no docblock contiguous to this line.
				if ( $trimmed !== '' && strpos( $trimmed, '//' ) !== 0 ) {
					// Not a docblock. Return original line+1
					return $line_index + 1;
				}
			}
		}

		// If we come out of the loop, it means we never found a docblock or we started from 0.
		// If we found a docblock and `line_index` is at `/**`, it has returned by now,
		// otherwise, no docblock found contiguous.
		return $line_index + 1;
	}
}
