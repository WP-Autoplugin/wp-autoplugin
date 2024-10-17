<?php
/**
 * Main API class.
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
 * API class.
 */
class API {

	/**
	 * Deal with the indentations in the prompts that make them more readable in the code, but less desirable for the AI.
	 * We count the number of spaces in the first non-empty line of the prompt and remove that number of spaces from all lines.
	 * Except for in between code blocks, where we keep the indentation starting from line 2.
	 *
	 * @param string $prompt The prompt to trim.
	 * @return string The trimmed prompt.
	 */
	public function trim_prompt( $prompt ) {
		$indent = 0;
		$in_code_block = false;
		$lines = explode( "\n", $prompt );

		// Find the indentation level of the first non-empty line.
		$first_line = trim( $lines[0] );
		if ( empty( $first_line ) ) {
			$first_line = trim( $lines[1] );
		}
		$indent = strspn( $first_line, "\t" );

		// Trim exactly that number of tabs in every line, except in code blocks starting from line 2.
		$trimmed_lines = array();
		$first_line    = true;
		foreach ( $lines as $line ) {
			if ( $first_line ) {
				$trimmed_lines[] = preg_replace( '/^\t{' . $indent . '}/', '', $line );
				$first_line = false;
				continue;
			}

			if ( $in_code_block ) {
				$trimmed_lines[] = $line;
				if ( strpos( $line, '```' ) !== false ) {
					$in_code_block = false;
				}
			} else {
				$trimmed_lines[] = preg_replace( '/^\t{' . $indent . '}/', '', $line );
				if ( strpos( $line, '```' ) !== false ) {
					$in_code_block = true;
					$first_line = true;
				}
			}
		}

		$prompt = implode( "\n", $trimmed_lines );

		// Remove any extra newlines at the beginning and end of the prompt.
		$prompt = trim( $prompt );

		return $prompt;
	}
}
