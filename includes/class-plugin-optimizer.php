<?php
/**
 * WP Autoplugin Plugin Optimizer.
 *
 * @package WP_Autoplugin
 */

namespace WP_Autoplugin;

/**
 * Class Plugin_Optimizer
 *
 * Handles the optimization of plugin code using an AI API.
 */
class Plugin_Optimizer {

    /**
     * The AI API instance.
     *
     * @var API
     */
    private $ai_api;

    /**
     * Constructor.
     *
     * @param API $ai_api The AI API instance.
     */
    public function __construct( API $ai_api ) {
        $this->ai_api = $ai_api;
    }

    /**
     * Analyzes the plugin code and provides an optimization plan.
     *
     * @param string $plugin_code The plugin's PHP code.
     * @return string The AI's response (optimization plan).
     */
    public function plan_plugin_optimization( $plugin_code ) {
        $prompt = "I have a WordPress plugin file. I need you to analyze its code for performance bottlenecks and provide a detailed technical plan for optimizing it. The plan should identify specific areas for improvement (e.g., inefficient loops, redundant database queries, slow functions, opportunities for caching) and suggest concrete changes.\n\nHere is the plugin code:\n\n```php\n" . $plugin_code . "\n```\n\nPlease provide a technical specification and development plan for optimizing the plugin. Remember, the plugin must be contained within a single PHP file after optimization.\nNote: Do not write the actual plugin code, only provide the plan for optimizing the plugin. Do not use Markdown formatting in your answer. Your response should be clear and concise.";

        return $this->ai_api->send_prompt( $prompt );
    }

    /**
     * Rewrites the plugin code based on the AI-generated optimization plan.
     *
     * @param string $plugin_code The original plugin's PHP code.
     * @param string $ai_plan     The AI-generated optimization plan.
     * @return string The AI's response (optimized code).
     */
    public function optimize_plugin( $plugin_code, $ai_plan ) {
        $prompt = "I have a WordPress plugin file and a plan for optimizing its performance.\n\nHere is the original plugin code:\n\n```php\n" . $plugin_code . "\n```\n\nHere is the optimization plan, provided by an AI:\n\n```\n" . $ai_plan . "\n```\n\nPlease write the complete, optimized code for the plugin. Your response should be valid PHP code that implements the optimizations described in the plan, while ensuring the plugin's original functionality remains unchanged. Note: the plugin must be contained within a single PHP file. Don't forget to increment the version number in the plugin header if it exists.\nDo not write any additional code or commentary. Make sure your response only contains the whole, updated code. Do not use Markdown formatting in your answer.";

        return $this->ai_api->send_prompt( $prompt );
    }
}
