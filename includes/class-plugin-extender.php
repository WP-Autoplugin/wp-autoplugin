<?php
namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin_Extender {
	private $ai_api;

	public function __construct( $ai_api ) {
		$this->ai_api = $ai_api;
	}

	public function plan_plugin_extension( $plugin_code, $plugin_changes ) {
		$prompt = "I have a WordPress plugin file I would like to extend. Here is the code:

```php
$plugin_code
```

I want the following changes to be made to the plugin:
$plugin_changes

Please provide a technical specification and development plan for extending the plugin: what changes need to be made, how they should be implemented, and any other relevant details. Remember, the plugin must be contained within a single PHP file.
Note: Do not write the actual plugin code, only provide the plan for extending the plugin. Do not use Markdown formatting in your answer. Your response should be clear and concise.";

		return $this->ai_api->send_prompt( $prompt );
	}

	public function extend_plugin( $plugin_code, $plugin_changes, $ai_plan ) {
		$prompt = "I have a WordPress plugin file I would like to extend. Here is the code:

```php
$plugin_code
```

Here is the plan for extending the plugin, provided by the developer:
$ai_plan

Please write the complete, extended code for the plugin. Your response should be valid PHP code that implements the changes described in the plan. Note: the plugin must be contained within a single PHP file. Don't forget to increment the version number in the plugin header.
Do not write any additional code or commentary. Make sure your response only contains the whole, updated code. Do not use Markdown formatting in your answer.";

		return $this->ai_api->send_prompt( $prompt );
	}
}
