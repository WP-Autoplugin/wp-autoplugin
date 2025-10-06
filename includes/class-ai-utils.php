<?php
/**
 * AI helper utilities.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility methods shared across AI flows.
 */
class AI_Utils {

	/**
	 * Build a readable context for either a single string or multiple files.
	 * Wraps contents in language-specific code fences and truncates long files.
	 *
	 * @param string|array $plugin_code_or_files Code string or [ path => contents ].
	 * @param int          $max_lines_per_file   Max lines included per file.
	 * @return string
	 */
	public static function build_code_context( $plugin_code_or_files, $max_lines_per_file = 2500 ) {
		if ( is_array( $plugin_code_or_files ) ) {
			$context = "Plugin Files:\n";
			foreach ( $plugin_code_or_files as $path => $contents ) {
				$lang = 'php';
				if ( preg_match( '/\\.css$/i', (string) $path ) ) {
					$lang = 'css';
				} elseif ( preg_match( '/\\.(js|mjs)$/i', (string) $path ) ) {
					$lang = 'javascript';
				}

				$lines = explode( "\n", (string) $contents );
				if ( count( $lines ) > $max_lines_per_file ) {
					$contents = implode( "\n", array_slice( $lines, 0, $max_lines_per_file ) ) . "\n/* ... truncated ... */";
				}

				$context .= "\nFile: {$path}\n```{$lang}\n{$contents}\n```\n";
			}
			return $context;
		}

		// Single-file code (assumed PHP by default)
		return "```php\n" . (string) $plugin_code_or_files . "\n```";
	}

	/**
	 * Remove surrounding triple-backtick code fences from a string.
	 * If $lang is provided, only removes that language; otherwise tries common types.
	 *
	 * @param string      $content The content possibly wrapped in code fences.
	 * @param string|null $lang    Optional language (php|css|js|javascript|json).
	 * @return string
	 */
	public static function strip_code_fences( $content, $lang = null ) {
		$content = (string) $content;
		if ( $lang ) {
			$pattern = '/^```(' . preg_quote( $lang, '/' ) . ')\n(.*)\n```$/s';
			return (string) preg_replace( $pattern, '$2', $content );
		}
		// Try common languages.
		$patterns = [
			'/^```(php)\n(.*)\n```$/s',
			'/^```(css)\n(.*)\n```$/s',
			'/^```(js|javascript)\n(.*)\n```$/s',
			'/^```(json)\n(.*)\n```$/s',
		];
		foreach ( $patterns as $pattern ) {
			$replaced = preg_replace( $pattern, '$2', $content );
			if ( $replaced !== null && $replaced !== $content ) {
				return (string) $replaced;
			}
		}
		return $content;
	}
}
