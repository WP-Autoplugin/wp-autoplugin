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
	 * Prompt the AI to identify an issue in a WordPress plugin.
	 *
	 * @param string $plugin_code        The plugin code.
	 * @param string $problem            The problem encountered.
	 * @param bool   $check_other_issues Whether to check for other issues in the code.
	 *
	 * @return string|WP_Error
	 */
	public function identify_issue( $plugin_code, $problem, $check_other_issues = true ) {
		$prompt = <<<PROMPT
			I have a WordPress plugin file that needs fixing. Here is the code:
			
			```php
			$plugin_code
			```
			
			The problem I am encountering:
			```
			$problem
			```
			
			Please provide an explanation of the issues and a possible solution. Do not write the fixed code. Your response should only contain clear and concise text in the following sections:
			
			- Issue Description: Describe the issue in detail, including the impact on the plugin's functionality. Also list other potential issues in the code.
			- Proposed Fix: Provide a detailed explanation of how to fix the issues, along with any additional recommendations or best practices.
			
			Make sure your response only contains the specified sections, without any additional commentary. Do not include any code. Do not use Markdown formatting in your answer.
			PROMPT;

		if ( ! $check_other_issues ) {
			$prompt = <<<PROMPT
				I have a WordPress plugin file that needs fixing. Here is the code:
				
				```php
				$plugin_code
				```
				
				The problem I am encountering:
				```
				$problem
				```
				
				Please provide a detailed technical explanation of the issues and a possible solution. Do not write the fixed code. Your response should only contain clear and concise text in the following sections:
				
				- Issue Description: Describe the issue in detail, including the impact on the plugin's functionality. Focus on the issue at hand and DO NOT look for other potential issues in the code.
				- Proposed Fix: Provide a detailed explanation of how to fix the issues.
				
				Make sure your response only contains the specified sections, without any additional commentary. Do not include any code. Do not use Markdown formatting in your answer.
				PROMPT;
		}

		return $this->ai_api->send_prompt( $prompt, '' );
	}

	/**
	 * Prompt the AI to fix a WordPress plugin.
	 *
	 * @param string $plugin_code    The plugin code.
	 * @param string $problem        The problem encountered.
	 * @param string $ai_description The developer's description and solution.
	 *
	 * @return string|WP_Error
	 */
	public function fix_plugin( $plugin_code, $problem, $ai_description ) {
		$prompt = <<<PROMPT
			I have a WordPress plugin file that needs fixing. Here is the code:
			
			```php
			$plugin_code
			```
			
			The problem I am encountering: $problem
			
			Here is a description of the issue and the solution provided by the developer:
			```
			$ai_description
			```

			###
			
			Please write the fixed, complete code for the plugin. Your response should be valid PHP code that addresses the issue described above. Note: the plugin must be contained within a single PHP file. Don't forget to increment the version number in the plugin header.
			Do not write any additional code or comments. Make sure your response only contains the whole, fixed code. Do not use Markdown formatting in your answer.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}
}
