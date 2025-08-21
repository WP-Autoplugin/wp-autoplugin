# WP-Autoplugin

<p align="center">
  <img src="https://wp-autoplugin.com/logo192.png" alt="WP-Autoplugin Logo" width="128">
</p>

WP-Autoplugin is a free WordPress plugin that uses AI to assist in generating, fixing, and extending plugins on-demand. It enables users to quickly create functional plugins from simple descriptions, addressing specific needs without unnecessary bloat.

- Generate plugins using AI
- Fix and extend existing plugins
- Full control over the generation process
- Support for multiple AI models, and any OpenAI-compatible custom API
- View the list of generated plugins for easy management

---

WP-Autoplugin offers practical solutions for various WordPress development scenarios:

- **Lightweight Alternatives**: Create simple, focused plugins to replace large, feature-heavy plugins that may slow down your site or include unnecessary features and advertisements.
- **Custom Solutions**: Develop site-specific plugins tailored to your unique requirements, eliminating the need for complex workarounds or multiple plugins.
- **Developer Foundations**: Generate solid base plugins that developers can extend and build upon, streamlining the process of creating complex, custom plugins.
- **Professional Multi-File Plugins**: Create sophisticated plugins with proper file structure, organization, and scalability using complex plugin mode.

## Plugin Highlights

- **Completely Free**: No premium version, no ads, no account required.
- **Privacy-Focused**: No data collection or external communication (except for the AI API you choose).
- **BYOK (Bring Your Own Key)**: Use your own API key from the AI provider of your choice.
- **Flexible AI Models**: Choose from a variety of AI models to suit your needs, or set up custom models.
- **Use in Your Language**: The plugin is fully translatable and has built-in support for 10+ languages.

## How It Works

1. **Describe Your Plugin**: Provide a description of the plugin you want to create.
2. **AI Generation**: WP-Autoplugin uses AI to generate a development plan and write the code.
3. **Review and Install**: Review the generated plan and code, make any necessary changes, and install the plugin with a single click.

You can also use WP-Autoplugin to **fix bugs**, **add new features**, or **explain plugins** you've created with the tool. The **Explain Plugin** feature allows you to ask questions or obtain general overviews of generated plugins, helping you better understand their functionality and structure.

## Complex Plugin Generation

WP-Autoplugin's complex plugin mode enables the creation of sophisticated, multi-file plugins with:

- **Proper File Structure**: Organized directories and file hierarchies
- **Object-Oriented Design**: Well-structured classes and namespaces
- **Scalable Architecture**: Plugins designed for growth and maintenance
- **Professional Standards**: Code that follows WordPress development best practices

<details>
<summary>Click to view complex plugin generation screenshot</summary>

![Complex plugin generation interface](https://wp-autoplugin.com/screenshot-6.png)

</details>

## Specialized Models Configuration

Optimize your plugin generation workflow by assigning different AI models to specific tasks:

- **Planner Model**: Handles plugin analysis and extension planning
- **Coder Model**: Focuses on code generation and implementation
- **Reviewer Model**: Provides code explanations and reviews

This approach allows you to:
- Use reasoning models for planning complex architectures
- Employ fast, cost-effective models for simple coding tasks
- Leverage specialized models for code review and explanations
- Optimize both performance and API costs

## Token Usage Tracking

Monitor your API consumption with detailed usage information:
- Real-time token count display during generation
- Per-step token usage breakdown
- Duration of each API request

This helps you:
- Control API costs effectively
- Choose the most cost-efficient models for your needs
- Understand the token impact of different generation modes

<details>
<summary>Click to view token usage information screenshot</summary>

![Token usage information](https://wp-autoplugin.com/screenshot-7.png)

</details>

## Extend Third-Party Plugins and Themes with Hooks

WP-Autoplugin allows you to easily extend **any plugin** or **theme** directly from the WordPress admin dashboard:

- Click on the "**Extend Plugin**" action link for the plugin you'd like to enhance, or look for the "**Extend**" button on the Appearance > Themes page.
- WP-Autoplugin will analyze the selected plugin or theme, extracting available action and filter hooks along with relevant contextual details.
- Provide a description of the desired extension; WP-Autoplugin assesses the technical feasibility using available hooks.
- A new extension plugin will be generated based on your description, allowing seamless integration with the existing functionality.

Demo video: [Extend a third-party plugin with WP-Autoplugin](https://www.youtube.com/watch?v=_9RnFcEGncY)

## Auto-detect Fatal Errors

When you activate an AI-generated plugin, WP-Autoplugin will automatically detect fatal errors and deactivate the plugin to prevent site crashes. A message with the error details will be displayed, along with a link to fix the issue automatically with AI.

## Supported Models

WP-Autoplugin supports 30+ AI models, including:

- Claude 4.1 Opus
- Claude 4 Sonnet
- Claude 3.7 Sonnet
- Claude 3.5 Sonnet
- Claude 3.5 Haiku
- o3
- o4-mini
- GPT-5
- GPT-5-mini
- GPT-5-nano
- GPT-4o
- GPT-4o mini
- Google Gemini 2.5 Pro
- Google Gemini 2.5 Flash
- Google Gemini 2.5 Flash Lite
- xAI Grok 4

While WP-Autoplugin is free to use, you may need to pay for API usage based on your chosen model.

## Custom Models

WP-Autoplugin supports custom models: you can plug in any OpenAI-compatible API by providing the endpoint URL, model name, and API key. This feature allows you to use any model you have access to, including locally hosted models, or custom models you've trained yourself.

## BYOK (Bring Your Own Key)

To use WP-Autoplugin, you'll need an API key from an AI provider. Insert your key in the plugin settings to get started. Your API key remains on your server and is not shared with anyone.

Some AI platforms currently offer free plans and include SOTA models, like **Gemini 2.5 Pro** through [Google AI Studio](https://aistudio.google.com/). Refer to the respective websites for pricing information.

## AI-Generated Plugins

Plugins created by WP-Autoplugin are standard WordPress plugins:

- They function independently and will continue to work even if WP-Autoplugin is deactivated or deleted.
- You can install them on other WordPress sites without WP-Autoplugin.
- While WP-Autoplugin provides a convenient listing screen for generated plugins, they can also be managed from the standard WordPress Plugins screen.

## Code Quality and Security

WP-Autoplugin aims to generate code that adheres to WordPress coding standards. However, it's important to treat AI-generated code with the same caution you would apply to any third-party code. We strongly recommend:

- Reviewing and testing all generated code before use in a production environment.
- Conducting thorough testing on a staging site before deployment.
- Considering a professional security audit for critical applications.

## Installation

1. Download the latest release from the [Releases](https://github.com/WP-Autoplugin/wp-autoplugin/releases) page.
2. Upload the plugin zip file through the 'Plugins' screen in WordPress, or unzip the file and upload the `wp-autoplugin` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Go to the WP-Autoplugin settings page and enter your API key(s).
5. Choose your preferred AI model in the settings.
6. Start generating plugins!

## Screenshots

<details>
<summary>Click to view screenshots</summary>

1. Generate plugin form
![Generate plugin form](https://wp-autoplugin.com/screenshot-1.png)

2. Review generated plan
![Review generated plan](https://wp-autoplugin.com/screenshot-2.png)

3. Review generated code
![Review generated code](https://wp-autoplugin.com/screenshot-3.png)

4. Autoplugins listing screen
![Autoplugins listing screen](https://wp-autoplugin.com/screenshot-4.png)

5. Fix plugin form
![Fix plugin form](https://wp-autoplugin.com/screenshot-5.png)

</details>

Or watch the [WP-Autoplugin demo video on Youtube](https://www.youtube.com/watch?v=b36elwTLfa4) that shows how it generates a plugin and fixes a bug.

## Translations

WP-Autoplugin is fully translatable. If you would like to contribute a translation, please create a pull request with the translation files. Currently, the plugin includes translations for the following languages:
- English - `en_US`
- Français (French) - `fr_FR`
- Español (Spanish) - `es_ES`
- Deutsch (German) - `de_DE`
- Português (Portuguese) - `pt_PT`
- Italiano (Italian) - `it_IT`
- Magyar (Hungarian) - `hu_HU`
- Nederlands (Dutch) - `nl_NL`
- Polski (Polish) - `pl_PL`
- Türkçe (Turkish) - `tr_TR`
- Русский (Russian) - `ru_RU`

## Licensing

WP-Autoplugin is licensed under the GPLv3 or later.

## Changelog

### 1.6
- Added complex plugin mode for multi-file plugin generation
- Added specialized model settings to delegate tasks to specific AI models
- Added detailed token usage tracking and display
- Improved plugin generation architecture
- Enhanced UI with better progress indicators

### 1.5
- Added Extend Themes feature to extend existing themes with hooks
- Added support for new AI models

### 1.4.3
- Added support for Google Gemini 2.5 Pro model
- Fixed minor UI issue in the Fix Plugin form
- Improved code generation step for the Fix Plugin feature to avoid fatal errors

### 1.4.2 
- Added a "Copy Hooks" button to use the extracted hooks with any LLM
- Added CMD/CTRL + Enter shortcut to submit the forms
- Fixed minor UI issues

### 1.4.1
- Added option to change model at every step
- Fixed minor issues with the Extend Plugin feature

### 1.4
- Analyze and extend any third-party plugin

### 1.3
- Added new Explain Plugin feature
- Refactored admin-side PHP & JS codes
- Added new Google models

### 1.2.1
- Added support for reasoning models (o1, o3-mini, Claude 3.7 Sonnet Thinking)
- Fixed PHPCS issues throughout the codebase

### 1.2
- Added support for any OpenAI-compatible API with the custom models option
- Added translations for 10 more languages
- Fixed PHP notice on "Add New Plugin" screen.

### 1.1.2
- Added support for Google Gemini Flash 2.0 Thinking model
- Added support for xAI Grok-2-1212 model

### 1.1.1
- Added support for Claude 3.5 Sonnet-20241022, Gemini 2.0 Flash Experimental, and Gemini Experimental 1206 models
- Some refactoring and code cleanup

### 1.1
- Added support for Claude 3.5 Haiku
- Added support for xAI and its only current model, Grok-beta
- Fixed an issue that prevented the code from being edited in the code editor
- Fixed wrong model name for "chatgpt-4o-latest"
- Improved the generator prompt for better code generation on lower-end models
- Improved inline documentation

### 1.0.6
- Fixed Github updater class
- Fixed a few bugs
- Added i18n support and Hungarian translation
- Adjusted prompt for better code generation
- Added docblocks to the code

### 1.0.5
- Added update from GitHub feature
- Reorganized files and folders and added Composer support
- Fixed small bugs

### 1.0.4
- Added support for Google Gemini models

### 1.0.3
- Added support for gpt-4o-2024-08-06
- Cleaned up prompts for better readability

### 1.0.2
- Added support for GPT-4o mini
- Added support for the high-limit (8192 tokens) version of Claude 3.5 Sonnet

### 1.0.1
- Fixed max_tokens value for OpenAI models

### 1.0
- Initial release
