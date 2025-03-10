=== WP-Autoplugin ===
Contributors: balazspiller
Donate link: https://wp-autoplugin.com
Tags: ai, plugin generator, development, wordpress, automation
Requires at least: 6.0
Tested up to: 6.7.1
Stable tag: 1.2.1
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A free WordPress plugin that uses AI to generate, fix, and extend plugins on-demand.

== Description ==

WP-Autoplugin is a free and open-source WordPress plugin that leverages AI to assist users in generating plugins based on their descriptions. It also helps fix and extend existing plugins, enabling quick and efficient plugin development without unnecessary bloat.

**Features:**
- Generate WordPress plugins using AI.
- Fix bugs and extend functionality of plugins.
- Support for multiple AI models, including OpenAI, Anthropic, Google Gemini, with custom API support to use any OpenAI-compatible model.
- Full control over generated code.
- Privacy-focused: no data collection or external communication, except for the AI API you choose.
- Completely free, with no ads or account requirements.

**How It Works:**
1. Describe the plugin you want to create.
2. WP-Autoplugin generates a development plan and code using AI.
3. Review, modify, and install the generated plugin with ease.

For more details and screenshots, visit [https://wp-autoplugin.com](https://wp-autoplugin.com).

== Installation ==

1. Download the plugin from the WordPress Plugin Directory.
2. Upload the plugin zip file through the WordPress admin Plugins screen, or extract and upload the `wp-autoplugin` folder to `/wp-content/plugins/` via FTP.
3. Activate the plugin through the Plugins screen in WordPress.
4. Go to the WP-Autoplugin settings page and enter your API key(s).
5. Choose your preferred AI model in the settings.
6. Start generating and managing plugins!

== Screenshots ==

1. Generate plugin form.
2. Review generated plan.
3. Review generated code.
4. Autoplugins listing screen.
5. Fix plugin form.

== Frequently Asked Questions ==

= Do I need an API key to use WP-Autoplugin? =
Yes, you need an API key from a supported AI provider (e.g., OpenAI, Anthropic, Google AI Studio, or xAI). Your API key is stored on your server and is never shared externally.

= Is WP-Autoplugin free? =
Yes, the plugin is completely free with no ads or account requirements. However, API usage may incur costs depending on the provider you choose.

= Is the generated code production-ready? =
While WP-Autoplugin aims to generate high-quality code adhering to WordPress standards, we recommend testing the code thoroughly before using it on a production site.

== Changelog ==

= 1.2 =
* Added support for any OpenAI-compatible API with the custom models option
* Added translations for 10 more languages
* Fixed PHP notice on "Add New Plugin" screen.

= 1.1.2 =
* Added support for Google Gemini Flash 2.0 Thinking model
* Added support for xAI Grok-2-1212 model

= 1.1.1 =
* Added support for Claude 3.5 Sonnet-20241022, Gemini 2.0 Flash Experimental, and Gemini Experimental 1206 models
* Some refactoring and code cleanup

== License ==

This plugin is licensed under the GPLv3 or later. For details, see [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html).
