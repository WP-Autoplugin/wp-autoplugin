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
	 * Prompt the AI to plan the extension of a WordPress plugin.
	 *
	 * @param string $plugin_code    The plugin code.
	 * @param string $plugin_changes The plugin changes to be made.
	 *
	 * @return string|WP_Error
	 */
	public function plan_plugin_extension( $plugin_code, $plugin_changes ) {
		$prompt = <<<PROMPT
			I have a WordPress plugin file I would like to extend. Here is the code:
			
			```php
			$plugin_code
			```

			I want the following changes to be made to the plugin:
			```
			$plugin_changes
			```

			Please provide a technical specification and development plan for extending the plugin: what changes need to be made, how they should be implemented, and any other relevant details. Remember, the plugin must be contained within a single PHP file.
			Note: Do not write the actual plugin code, only provide the plan for extending the plugin. Do not use Markdown formatting in your answer. Your response should be clear and concise.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}

	/**
	 * Prompt the AI to extend a WordPress plugin based on a plan.
	 *
	 * @param string $plugin_code    The plugin code.
	 * @param string $plugin_changes The plugin changes.
	 * @param string $ai_plan        The AI plan for extending the plugin.
	 *
	 * @return string|WP_Error
	 */
	public function extend_plugin( $plugin_code, $plugin_changes, $ai_plan ) {
		$prompt = <<<PROMPT
			I have a WordPress plugin file I would like to extend. Here is the code:
			
			```php
			$plugin_code
			```

			Here is the plan for extending the plugin, provided by the developer:
			```
			$ai_plan
			```

			Please write the complete, extended code for the plugin. Your response should be valid PHP code that implements the changes described in the plan. Note: the plugin must be contained within a single PHP file. Don't forget to increment the version number in the plugin header.
			Do not write any additional code or commentary. Make sure your response only contains the whole, updated code. Do not use Markdown formatting in your answer.
			PROMPT;

		return $this->ai_api->send_prompt( $prompt );
	}
}
