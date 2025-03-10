<?php
/**
 * WP-Autoplugin Admin class.
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
 * Admin class.
 */
class Admin {

	/**
	 * The API object.
	 *
	 * @var API
	 */
	private $ai_api;

	/**
	 * The current bulk action.
	 *
	 * @var string
	 */
	private $action;

	/**
	 * The built-in models.
	 *
	 * @var array
	 */
	public static $models = array(
		'OpenAI'    => [
			'gpt-4.5-preview'   => 'GPT-4.5 Preview',
			'gpt-4o'            => 'GPT-4o',
			'gpt-4o-mini'       => 'GPT-4o mini',
			'chatgpt-4o-latest' => 'ChatGPT-4o-latest',
			'o1'                => 'o1',
			'o1-preview'        => 'o1-preview',
			'o3-mini-low'       => 'o3-mini-low',
			'o3-mini-medium'    => 'o3-mini-medium',
			'o3-mini-high'      => 'o3-mini-high',
			'gpt-4-turbo'       => 'GPT-4 Turbo',
			'gpt-3.5-turbo'     => 'GPT-3.5 Turbo',
		],
		'Anthropic' => [
			'claude-3-7-sonnet-latest'   => 'Claude 3.7 Sonnet-latest',
			'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet-20250219',
			'claude-3-7-sonnet-thinking' => 'Claude 3.7 Sonnet Thinking',
			'claude-3-5-sonnet-latest'   => 'Claude 3.5 Sonnet-latest',
			'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet-20241022',
			'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet-20240620',
			'claude-3-5-haiku-latest'    => 'Claude 3.5 Haiku-latest',
			'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku-20241022',
			'claude-3-opus-20240229'     => 'Claude 3 Opus-20240229',
			'claude-3-sonnet-20240229'   => 'Claude 3 Sonnet-20240229',
			'claude-3-haiku-20240307'    => 'Claude 3 Haiku-20240307',
		],
		'Google'    => [
			'gemini-2.0-pro-exp-02-05'            => 'Gemini 2.0 Pro Experimental 02-05',
			'gemini-2.0-flash-thinking-exp'       => 'Gemini 2.0 Flash Thinking Experimental',
			'gemini-2.0-flash-exp'                => 'Gemini 2.0 Flash Experimental',
			'gemini-2.0-flash-thinking-exp-01-21' => 'Gemini 2.0 Flash Thinking Experimental 01-21',
			'gemini-exp-1206'                     => 'Gemini Experimental 1206',
			'gemini-exp-1121'                     => 'Gemini Experimental 1121',
			'gemini-1.5-pro'                      => 'Gemini 1.5 Pro',
			'gemini-1.5-flash'                    => 'Gemini 1.5 Flash',
			'gemini-1.0-pro'                      => 'Gemini 1.0 Pro',
		],
		'xAI'       => [
			'grok-2'                => 'Grok 2',
			'grok-beta'             => 'Grok Beta',
			'grok-2-1212'           => 'Grok 2-1212',
		],
	);

	/**
	 * Constructor: set up API, add actions and filters.
	 *
	 * @return void
	 */
	public function __construct() {
		$model = get_option( 'wp_autoplugin_model' );

		$this->ai_api = $this->get_api( $model );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add settings link on the plugins page.
		add_filter( 'plugin_action_links_' . plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' ), array( $this, 'add_settings_link' ) );

		// AJAX actions.
		$actions = array(
			// Generate plugin.
			'generate_plan',
			'generate_code',
			'create_plugin',

			// Fix plugin.
			'generate_fix_plan',
			'generate_fix_code',
			'fix_plugin',

			// Extend plugin.
			'generate_extend_plan',
			'generate_extend_code',
			'extend_plugin',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_wp_autoplugin_' . $action, array( $this, 'ajax_actions' ) );
		}

		// Add or delete custom model AJAX action.
		add_action( 'wp_ajax_wp_autoplugin_add_model', array( $this, 'ajax_add_model' ) );
		add_action( 'wp_ajax_wp_autoplugin_remove_model', array( $this, 'ajax_remove_model' ) );

		// Show notices.
		add_action( 'admin_notices', array( $this, 'show_notices' ) );

		// Process bulk actions.
		add_action( 'admin_init', array( $this, 'process_bulk_action' ) );

		// Github updater.
		add_action( 'init', array( $this, 'github_updater_init' ) );
	}

	/**
	 * Get the API object based on the model.
	 *
	 * @param string $model The model to use.
	 *
	 * @return API|null
	 */
	public function get_api( $model ) {
		$openai_api_key    = get_option( 'wp_autoplugin_openai_api_key' );
		$anthropic_api_key = get_option( 'wp_autoplugin_anthropic_api_key' );
		$google_api_key    = get_option( 'wp_autoplugin_google_api_key' );
		$xai_api_key       = get_option( 'wp_autoplugin_xai_api_key' );
		$custom_models     = get_option( 'wp_autoplugin_custom_models', array() );

		$api = null;

		if ( ! empty( $openai_api_key ) && array_key_exists( $model, self::$models['OpenAI'] ) ) {
			$api = new OpenAI_API();
			$api->set_api_key( $openai_api_key );
			$api->set_model( $model );
		} elseif ( ! empty( $anthropic_api_key ) && array_key_exists( $model, self::$models['Anthropic'] ) ) {
			$api = new Anthropic_API();
			$api->set_api_key( $anthropic_api_key );
			$api->set_model( $model );
		} elseif ( ! empty( $google_api_key ) && array_key_exists( $model, self::$models['Google'] ) ) {
			$api = new Google_Gemini_API();
			$api->set_api_key( $google_api_key );
			$api->set_model( $model );
		} elseif ( ! empty( $xai_api_key ) && array_key_exists( $model, self::$models['xAI'] ) ) {
			$api = new XAI_API();
			$api->set_api_key( $xai_api_key );
			$api->set_model( $model );
		}

		// Check custom models:
		if ( ! empty( $custom_models ) ) {
			foreach ( $custom_models as $custom_model ) {
				// If the "modelParameter" in the DB matches the user’s selected $model.
				if ( $custom_model['name'] === $model ) {
					$api = new Custom_API();
					$api->set_custom_config(
						$custom_model['url'],
						$custom_model['apiKey'],
						$custom_model['modelParameter'],
						$custom_model['headers']
					);
					return $api;
				}
			}
		}

		// If nothing matches, $api will be null.
		return $api;
	}

	/**
	 * Initialize the AJAX actions.
	 *
	 * @return void
	 */
	public function ajax_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You are not allowed to access this page.' );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		// If the ai_api is not set, we cannot proceed.
		if ( ! $this->ai_api ) {
			wp_send_json_error( 'API key or model not set. Please configure the plugin settings.' );
		}

		$action = str_replace( 'wp_autoplugin_', 'ajax_', $_POST['action'] );
		if ( method_exists( $this, $action ) ) {
			$this->$action();
		}
	}

	/**
	 * Initialize the admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'WP-Autoplugin', 'wp-autoplugin' ),
			__( 'WP-Autoplugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin',
			array( $this, 'render_list_plugins_page' ),
			'dashicons-admin-plugins',
			100
		);

		add_submenu_page(
			'wp-autoplugin',
			__( 'Generate New Plugin', 'wp-autoplugin' ),
			__( 'Generate New Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-generate',
			array( $this, 'render_generate_plugin_page' )
		);

		add_submenu_page(
			'wp-autoplugin',
			__( 'Settings', 'wp-autoplugin' ),
			__( 'Settings', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-settings',
			array( $this, 'render_settings_page' )
		);

		// Add "Extend" and "Fix" submenu pages without a menu link.
		add_submenu_page(
			'',
			__( 'Extend Plugin', 'wp-autoplugin' ),
			__( 'Extend Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-extend',
			array( $this, 'render_extend_plugin_page' )
		);

		add_submenu_page(
			'',
			__( 'Fix Plugin', 'wp-autoplugin' ),
			__( 'Fix Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-fix',
			array( $this, 'render_fix_plugin_page' )
		);
	}

	/**
	 * Register the plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_openai_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_anthropic_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_google_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_xai_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_model' );
	}

	/**
	 * Display the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		include WP_AUTOPLUGIN_DIR . 'views/page-settings.php';
	}

	/**
	 * Display the plugin generation page.
	 *
	 * @return void
	 */
	public function render_generate_plugin_page() {
		include WP_AUTOPLUGIN_DIR . 'views/page-generate-plugin.php';
	}

	/**
	 * Display the list of Autoplugins.
	 *
	 * @return void
	 */
	public function render_list_plugins_page() {
		include WP_AUTOPLUGIN_DIR . 'views/page-list-plugins.php';
	}

	/**
	 * Display the extend plugin page.
	 *
	 * @return void
	 */
	public function render_extend_plugin_page() {
		$this->validate_plugin( 'wp-autoplugin-extend-plugin' );
		include WP_AUTOPLUGIN_DIR . 'views/page-extend-plugin.php';
	}

	/**
	 * Display the fix plugin page.
	 *
	 * @return void
	 */
	public function render_fix_plugin_page() {
		$this->validate_plugin( 'wp-autoplugin-fix-plugin' );
		include WP_AUTOPLUGIN_DIR . 'views/page-fix-plugin.php';
	}

	/**
	 * Check if the plugin is valid: exists, is an Autoplugin, and the user has the right permissions.
	 *
	 * @param string $nonce The nonce to check.
	 *
	 * @return bool
	 */
	public function validate_plugin( $nonce ) {
		if ( ! isset( $_GET['plugin'] ) ) {
			wp_die( __( 'No plugin specified.', 'wp-autoplugin' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-autoplugin' ) );
		}

		// Check nonce.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], $nonce ) ) {
			wp_die( __( 'Security check failed.', 'wp-autoplugin' ) );
		}

		// Check if the plugin exists.
		if ( ! file_exists( WP_CONTENT_DIR . '/plugins/' . $_GET['plugin'] ) ) {
			wp_die( __( 'The specified plugin does not exist.', 'wp-autoplugin' ) );
		}

		// Check if it's a WP-Autoplugin generated plugin.
		$plugins = get_option( 'wp_autoplugins', array() );
		if ( ! in_array( $_GET['plugin'], $plugins, true ) ) {
			wp_die( __( 'The specified plugin does not exist.', 'wp-autoplugin' ) );
		}

		return true;
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		// Enqueue generator.js and generator.css only on the plugin generation page.
		$screen = get_current_screen();
		wp_register_script( 'wp-autoplugin-utils', WP_AUTOPLUGIN_URL . 'assets/admin/js/utils.js', array(), WP_AUTOPLUGIN_VERSION, true );
		if ( $screen->id === 'toplevel_page_wp-autoplugin' ) {
			wp_enqueue_script( 'wp-autoplugin', WP_AUTOPLUGIN_URL . 'assets/admin/js/list-plugins.js', array(), WP_AUTOPLUGIN_VERSION, true );
			wp_enqueue_style( 'wp-autoplugin', WP_AUTOPLUGIN_URL . 'assets/admin/css/list-plugins.css', array(), WP_AUTOPLUGIN_VERSION );
		} elseif ( $screen->id === 'wp-autoplugin_page_wp-autoplugin-generate' ) {
			// Settings for the CodeMirror editor for PHP code
			$settings = wp_enqueue_code_editor( array(
				'type' => 'application/x-httpd-php',
			));

			// Enqueue the code editor if the current user's browser supports it
			if ( false !== $settings ) {
				wp_enqueue_script( 'wp-theme-plugin-editor' );
				wp_enqueue_style( 'wp-codemirror' );
			}

			wp_enqueue_script( 'wp-autoplugin-generator', WP_AUTOPLUGIN_URL . 'assets/admin/js/generator.js', array( 'wp-autoplugin-utils' ), WP_AUTOPLUGIN_VERSION, true );
			wp_localize_script( 'wp-autoplugin-generator', 'wp_autoplugin', array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wp_autoplugin_generate' ),
				'fix_url'         => admin_url( 'admin.php?page=wp-autoplugin-fix&nonce=' . wp_create_nonce( 'wp-autoplugin-fix-plugin' ) ),
				'activate_url'    => admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ),
				'testing_plan'    => '',

				// i18n strings.
				'messages'        => [
					'empty_description'     => __( 'Please enter a plugin description.', 'wp-autoplugin' ),
					'generating_plan'       => __( 'Generating a plan for your plugin', 'wp-autoplugin' ),
					'plan_generation_error' => __( 'Error generating the plugin plan.', 'wp-autoplugin' ),
					'generating_code'       => __( 'Generating code', 'wp-autoplugin' ),
					'code_generation_error' => __( 'Error generating the plugin code.', 'wp-autoplugin' ),
					'plugin_creation_error' => __( 'Error creating the plugin.', 'wp-autoplugin' ),
					'creating_plugin'       => __( 'Installing the plugin', 'wp-autoplugin' ),
					'plugin_created'        => __( 'Plugin successfully installed.', 'wp-autoplugin' ),
					'how_to_test'           => __( 'How to test it?', 'wp-autoplugin' ),
					'use_fixer'             => __( 'If you notice any issues, use the Fix button in the Autoplugins list.', 'wp-autoplugin' ),
					'activate'              => __( 'Activate Plugin', 'wp-autoplugin' ),
				],

				// Plugin ideas.
				'plugin_examples' => [
					__( 'A simple contact form with honeypot spam protection', 'wp-autoplugin' ),
					__( 'A custom post type for testimonials', 'wp-autoplugin' ),
					__( 'A widget that displays recent posts', 'wp-autoplugin' ),
					__( 'A shortcode that shows a random quote', 'wp-autoplugin' ),
					__( 'A user profile widget displaying avatar, bio, and website link', 'wp-autoplugin' ),
					__( 'A custom post type for managing FAQs', 'wp-autoplugin' ),
					__( 'A post views counter that tracks and displays view counts', 'wp-autoplugin' ),
					__( 'Maintenance mode with a countdown timer to site return', 'wp-autoplugin' ),
					__( 'An admin quick links widget for the dashboard', 'wp-autoplugin' ),
					__( 'Hide the admin bar for non-admin users', 'wp-autoplugin' ),
					__( 'Hide specific menu items in the admin area', 'wp-autoplugin' ),
					__( 'A social media share buttons plugin for posts', 'wp-autoplugin' ),
					__( 'A custom footer credit remover', 'wp-autoplugin' ),
					__( 'A plugin to add custom CSS to the WordPress login page', 'wp-autoplugin' ),
					__( 'A related posts display below single post content', 'wp-autoplugin' ),
					__( 'A custom excerpt length controller', 'wp-autoplugin' ),
					__( 'A "Back to Top" button for long pages', 'wp-autoplugin' ),
					__( 'A plugin to disable comments on specific post types', 'wp-autoplugin' ),
					__( 'A simple Google Analytics integration', 'wp-autoplugin' ),
					__( 'An author box display below posts', 'wp-autoplugin' ),
					__( 'A custom breadcrumb generator', 'wp-autoplugin' ),
					__( 'A plugin to add nofollow to external links', 'wp-autoplugin' ),
					__( 'A simple cookie consent banner', 'wp-autoplugin' ),
					__( 'A post expiration date setter', 'wp-autoplugin' ),
					__( 'A basic XML sitemap generator', 'wp-autoplugin' ),
					__( 'A custom login URL creator for added security', 'wp-autoplugin' ),
					__( 'A simple contact information display shortcode', 'wp-autoplugin' ),
					__( 'A plugin to add estimated reading time to posts', 'wp-autoplugin' ),
					__( 'A custom RSS feed footer', 'wp-autoplugin' ),
					__( 'A simple post duplication tool', 'wp-autoplugin' ),
					__( 'A basic schema markup generator', 'wp-autoplugin' ),
					__( 'A plugin to add custom admin footer text', 'wp-autoplugin' ),
					__( 'A plugin to add custom taxonomies easily', 'wp-autoplugin' ),
					__( 'A simple email obfuscator to prevent spam', 'wp-autoplugin' ),
					__( 'A basic redirection manager', 'wp-autoplugin' ),
					__( 'A plugin to add custom fields to user profiles', 'wp-autoplugin' ),
					__( 'A simple image compression tool', 'wp-autoplugin' ),
				],
			) );
			wp_enqueue_style( 'wp-autoplugin-generator', WP_AUTOPLUGIN_URL . 'assets/admin/css/generator.css', array(), WP_AUTOPLUGIN_VERSION );
		} elseif ( $screen->id === 'admin_page_wp-autoplugin-fix' ) {
			// Settings for the CodeMirror editor for PHP code
			$settings = wp_enqueue_code_editor(array(
				'type' => 'application/x-httpd-php',
			));

			// Enqueue the code editor if the current user's browser supports it
			if (false !== $settings) {
				wp_enqueue_script('wp-theme-plugin-editor');
				wp_enqueue_style('wp-codemirror');
			}

			$is_plugin_active = false;
			if ( isset( $_GET['plugin'] ) ) {
				$plugin_file = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
				$plugin_file = str_replace( '../', '', $plugin_file );
				$is_plugin_active = is_plugin_active( $plugin_file );
			}

			wp_enqueue_script( 'wp-autoplugin-fix', WP_AUTOPLUGIN_URL . 'assets/admin/js/fixer.js', array( 'wp-autoplugin-utils' ), WP_AUTOPLUGIN_VERSION, true );
			wp_localize_script( 'wp-autoplugin-fix', 'wp_autoplugin', array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'wp_autoplugin_generate' ),
				'activate_url'     => admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ),
				'is_plugin_active' => $is_plugin_active,

				// i18n strings.
				'messages'        => [
					'empty_description'     => __( 'Please enter a plugin description.', 'wp-autoplugin' ),
					'generating_plan'       => __( 'Generating a plan for your plugin', 'wp-autoplugin' ),
					'plan_generation_error' => __( 'Error generating the plan.', 'wp-autoplugin' ),
					'plugin_creation_error' => __( 'Error creating the fixed plugin.', 'wp-autoplugin' ),
					'generating_code'       => __( 'Generating the fixed plugin code', 'wp-autoplugin' ),
					'code_generation_error' => __( 'Error generating the fixed code.', 'wp-autoplugin' ),
					'code_updated'          => __( 'The plugin code has been updated.', 'wp-autoplugin' ),
					'activate'              => __( 'Activate Plugin', 'wp-autoplugin' ),
					'creating_plugin'       => __( 'Installing the fix', 'wp-autoplugin' ),
				],
			) );
			wp_enqueue_style( 'wp-autoplugin-fix', WP_AUTOPLUGIN_URL . 'assets/admin/css/fixer.css', array(), WP_AUTOPLUGIN_VERSION );
		} elseif ( $screen->id === 'admin_page_wp-autoplugin-extend' ) {
			// Settings for the CodeMirror editor for PHP code
			$settings = wp_enqueue_code_editor(array(
				'type' => 'application/x-httpd-php',
			));

			// Enqueue the code editor if the current user's browser supports it
			if (false !== $settings) {
				wp_enqueue_script('wp-theme-plugin-editor');
				wp_enqueue_style('wp-codemirror');
			}

			$is_plugin_active = false;
			if ( isset( $_GET['plugin'] ) ) {
				$plugin_file = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
				$plugin_file = str_replace( '../', '', $plugin_file );
				$is_plugin_active = is_plugin_active( $plugin_file );
			}

			wp_enqueue_script( 'wp-autoplugin-extend', WP_AUTOPLUGIN_URL . 'assets/admin/js/extender.js', array( 'wp-autoplugin-utils' ), WP_AUTOPLUGIN_VERSION, true );
			wp_localize_script( 'wp-autoplugin-extend', 'wp_autoplugin', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wp_autoplugin_generate' ),
				'activate_url' => admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ),
				'is_plugin_active' => $is_plugin_active,

				// i18n strings.
				'messages'        => [
					'empty_description'     => __( 'Please describe the changes you want to make to the plugin.', 'wp-autoplugin' ),
					'generating_plan'       => __( 'Generating a plan for your plugin', 'wp-autoplugin' ),
					'plan_generation_error' => __( 'Error generating the development plan.', 'wp-autoplugin' ),
					'generating_code'       => __( 'Generating the extended plugin code', 'wp-autoplugin' ),
					'code_generation_error' => __( 'Error generating the extended code.', 'wp-autoplugin' ),
					'plugin_creation_error' => __( 'Error creating the extended plugin.', 'wp-autoplugin' ),
					'code_updated'          => __( 'The plugin code has been updated.', 'wp-autoplugin' ),
					'activate'              => __( 'Activate Plugin', 'wp-autoplugin' ),
					'creating_plugin'       => __( 'Creating the plugin', 'wp-autoplugin' ),
				],
			) );
			wp_enqueue_style( 'wp-autoplugin-extend', WP_AUTOPLUGIN_URL . 'assets/admin/css/extender.css', array(), WP_AUTOPLUGIN_VERSION );
		}
	}

	/**
	 * Add settings link to the plugin list.
	 *
	 * @param array $links The existing links.
	 *
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=wp-autoplugin-settings' ) . '">' . __( 'Settings', 'wp-autoplugin' ) . '</a>';
		$generate_link = '<a href="' . admin_url( 'admin.php?page=wp-autoplugin-generate' ) . '">' . __( 'Generate Plugin', 'wp-autoplugin' ) . '</a>';
		array_unshift( $links, $settings_link, $generate_link );
		return $links;
	}

	/**
	 * AJAX handler for generating a plugin plan.
	 *
	 * @return void
	 */
	public function ajax_generate_plan() {
		$plan = sanitize_text_field( wp_unslash( $_POST['plugin_description'] ) );
		$generator = new Plugin_Generator( $this->ai_api );
		$plan_data = $generator->generate_plugin_plan( $plan );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}
		// let's strip out any wrapping markup, for example ```json\n{ "key": "value" }\n```
		$plan_data = preg_replace( '/^```(json)\n(.*)\n```$/s', '$2', $plan_data );
		$plan_array = json_decode( $plan_data, true );
		if ( ! $plan_array ) {
			wp_send_json_error( 'Failed to decode the generated plan: ' . $plan_data );
		}
		wp_send_json_success( $plan_array );
	}

	/**
	 * AJAX handler for generating plugin code.
	 *
	 * @return void
	 */
	public function ajax_generate_code() {
		$description = sanitize_text_field( $_POST['plugin_plan'] );
		$generator = new Plugin_Generator( $this->ai_api );
		$code = $generator->generate_plugin_code( $description );
		if ( is_wp_error( $code ) ) {
			wp_send_json_error( $code->get_error_message() );
		}
		// let's strip out any wrapping markup, for example ```php\n// code here\n```
		$code = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $code );

		wp_send_json_success( $code );
	}

	/**
	 * AJAX handler for creating a plugin.
	 *
	 * @return void
	 */
	public function ajax_create_plugin() {
		$code = wp_unslash( $_POST['plugin_code'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- We cannot and should not sanitize this.
		$plugin_name = sanitize_text_field( $_POST['plugin_name'] );
		$installer = Plugin_Installer::get_instance();
		$result = $installer->install_plugin( $code, $plugin_name );
		if ( is_wp_error( $result ) ) {
			wp_send_json( array(
				'success'    => false,
				'data'       => $result->get_error_message(),
				'error_type' => 'install_error',
			) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for generating a fix plan.
	 *
	 * @return void
	 */
	public function ajax_generate_fix_plan() {
		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) );
		// Load the plugin code.
		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path );
		if ( false === $plugin_code ) {
			wp_send_json_error( 'Failed to read the plugin file.' );
		}

		$problem = sanitize_text_field( $_POST['plugin_issue'] );
		$check_other_issues = isset( $_POST['check_other_issues'] ) ? (bool) $_POST['check_other_issues'] : true;
		$fixer = new Plugin_Fixer( $this->ai_api );
		$plan_data = $fixer->identify_issue( $plugin_code, $problem, $check_other_issues );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		wp_send_json_success( $plan_data );
	}

	/**
	 * AJAX handler for generating fixed code for a plugin.
	 *
	 * @return void
	 */
	public function ajax_generate_fix_code() {
		// Use plugin_issue, plugin_file, plugin_plan
		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) );
		// Load the plugin code.
		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path );
		if ( false === $plugin_code ) {
			wp_send_json_error( 'Failed to read the plugin file.' );
		}

		$problem = sanitize_text_field( $_POST['plugin_issue'] );
		$ai_description = sanitize_text_field( $_POST['plugin_plan'] );
		$fixer = new Plugin_Fixer( $this->ai_api );
		$code = $fixer->fix_plugin( $plugin_code, $problem, $ai_description );

		wp_send_json_success( $code );
	}

	/**
	 * AJAX handler for installing a fixed plugin.
	 *
	 * @return void
	 */
	public function ajax_fix_plugin() {
		$code = wp_unslash( $_POST['plugin_code'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- We cannot and should not sanitize this.
		$plugin_file = sanitize_text_field( $_POST['plugin_file'] );
		$installer = Plugin_Installer::get_instance();
		$result = $installer->install_plugin( $code, $plugin_file );
		if ( is_wp_error( $result ) ) {
			wp_send_json( array(
				'success'    => false,
				'data'       => $result->get_error_message(),
				'error_type' => 'install_error',
			) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for generating an extension plan.
	 *
	 * @return void
	 */
	public function ajax_generate_extend_plan() {
		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) );
		// Load the plugin code.
		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path );
		if ( false === $plugin_code ) {
			wp_send_json_error( 'Failed to read the plugin file.' );
		}

		$problem = sanitize_text_field( $_POST['plugin_issue'] );
		$extender = new Plugin_Extender( $this->ai_api );
		$plan_data = $extender->plan_plugin_extension( $plugin_code, $problem );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		wp_send_json_success( $plan_data );
	}

	/**
	 * AJAX handler for generating extended code for a plugin.
	 *
	 * @return void
	 */
	public function ajax_generate_extend_code() {
		// Use plugin_issue, plugin_file, plugin_plan
		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) );
		// Load the plugin code.
		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path );
		if ( false === $plugin_code ) {
			wp_send_json_error( 'Failed to read the plugin file.' );
		}

		$problem = sanitize_text_field( $_POST['plugin_issue'] );
		$ai_description = sanitize_text_field( $_POST['plugin_plan'] );
		$extender = new Plugin_Extender( $this->ai_api );
		$code = $extender->extend_plugin( $plugin_code, $problem, $ai_description );

		wp_send_json_success( $code );
	}

	/**
	 * AJAX handler for installing an extended plugin.
	 *
	 * @return void
	 */
	public function ajax_extend_plugin() {
		$code = wp_unslash( $_POST['plugin_code'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- We cannot and should not sanitize this.
		$plugin_file = sanitize_text_field( $_POST['plugin_file'] );
		$installer = Plugin_Installer::get_instance();
		$result = $installer->install_plugin( $code, $plugin_file );
		if ( is_wp_error( $result ) ) {
			wp_send_json( array(
				'success'    => false,
				'data'       => $result->get_error_message(),
				'error_type' => 'install_error',
			) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for adding a custom model.
	 *
	 * @return void
	 */
	public function ajax_add_model() {
		// Verify nonce  
		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed', 'wp-autoplugin' ),
			) );
		}

		// Get and validate model data
		$model = isset( $_POST['model'] ) ? $_POST['model'] : null;
		if ( ! $model || ! isset( $model['name'] ) || ! isset( $model['url'] ) || ! isset( $model['apiKey'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid model data', 'wp-autoplugin' ),
			) );
		}

		// Sanitize input
		$new_model = array(
			'name'           => sanitize_text_field( $model['name'] ),
			'url'            => esc_url_raw( $model['url'] ),
			'modelParameter' => sanitize_text_field( $model['modelParameter'] ),
			'apiKey'         => sanitize_text_field( $model['apiKey'] ),
			'headers'        => array_map(
				'sanitize_text_field',
				isset( $model['headers'] ) ? (array) $model['headers'] : array()
			),
		);

		// Get existing models
		$models = get_option( 'wp_autoplugin_custom_models', array() );
		if ( ! is_array( $models ) ) {
			$models = array();
		}

		// Add new model
		$models[] = $new_model;

		// Update option
		update_option( 'wp_autoplugin_custom_models', $models );

		// Send success response
		wp_send_json_success( array(
			'models'  => $models,
			'message' => __( 'Model added successfully', 'wp-autoplugin' ),
		) );
	}

	/**
	 * AJAX handler for removing a custom model.
	 *
	 * @return void
	 */
	public function ajax_remove_model() {
		// Verify nonce
		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed', 'wp-autoplugin' ),
			) );
		}

		// Get existing models
		$models = get_option( 'wp_autoplugin_custom_models', array() );
		if ( ! is_array( $models ) ) {
			$models = array();
		}

		// Get and validate model index
		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : null;
		if ( ! is_int( $index ) || $index >= count( $models ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid model index', 'wp-autoplugin' ),
			) );
		}

		// Remove model
		if ( isset( $models[ $index ] ) ) {
			unset( $models[ $index ] );
		}

		// Update option
		update_option( 'wp_autoplugin_custom_models', $models );

		// Send success response
		wp_send_json_success( array(
			'models'  => $models,
			'message' => __( 'Model removed successfully', 'wp-autoplugin' ),
		) );
	}

	/**
	 * Show admin notices.
	 * This function is hooked to the `admin_notices` action.
	 *
	 * @return void
	 */
	public function show_notices() {
		// Show notices only on the plugin list page.
		$screen = get_current_screen();
		if ( $screen->id !== 'toplevel_page_wp-autoplugin' ) {
			return;
		}

		// Show a notice if DISALLOW_FILE_MODS is defined.
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			?>
			<div class="notice notice-error">
				<p><?php _e( 'The <code>DISALLOW_FILE_MODS</code> constant is defined in your wp-config.php file, which prevents WP-Autoplugin from installing or updating plugins on your site.', 'wp-autoplugin' ); ?></p>
			</div>
			<?php
		}

		// Show a notice if there was a fatal error while activating a plugin.
		$error = get_option( 'wp_autoplugin_fatal_error' );
		if ( $error && is_array( $error ) ) {
			$fix_url = add_query_arg( array(
				'nonce'         => wp_create_nonce( 'wp-autoplugin-fix-plugin' ),
				'plugin'        => urlencode( $error['plugin'] ),
				'error_message' => urlencode( $error['error'] ),
			), admin_url( 'admin.php?page=wp-autoplugin-fix' ) );
			?>
			<div class="notice notice-error">
				<p><?php _e( 'The plugin could not be activated due to a fatal error.', 'wp-autoplugin' ); ?></p>
				<pre><?php echo esc_html( $error['error'] ); ?></pre>
				<?php /* translators: %s: fix URL */ ?>
				<p><?php printf( __( 'You can <a href="%s">fix the error automatically</a>.', 'wp-autoplugin' ), esc_url( $fix_url ) ); ?></p>
			</div>
			<?php
			delete_option( 'wp_autoplugin_fatal_error' );
		}

		// Show any other notices.
		$notices = get_option( 'wp_autoplugin_notices', array() );
		foreach ( $notices as $notice ) {
			?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
			<?php
		}
		delete_option( 'wp_autoplugin_notices' );
	}

	/**
	 * Output the admin footer for the plugin.
	 *
	 * @return void
	 */
	public function output_admin_footer() {
		?>
		<div id="wp-autoplugin-footer">
			<p>
				<span class="dashicons dashicons-admin-plugins"></span>
				<span class="credits">
					<?php /* translators: %s: plugin version */ ?>
					<strong><?php printf( __( 'WP-Autoplugin v%s', 'wp-autoplugin' ), WP_AUTOPLUGIN_VERSION ); ?></strong>
				</span>
				<span class="separator">|</span>
				<span class="model">
					<?php /* translators: %s: model name */ ?>
					<?php printf( __( 'Model: %s', 'wp-autoplugin' ), '<code>' . get_option( 'wp_autoplugin_model' ) . '</code>' ); ?>
				</span>
			</p>
		<?php
	}

	/**
	 * Handle the bulk actions: activate, deactivate, delete plugins.
	 *
	 * @return void
	 */
	public function process_bulk_action() {
		if ( ! is_admin() || empty( $_GET['page'] ) || $_GET['page'] !== 'wp-autoplugin' || empty( $_REQUEST['action'] ) ) {
			return;
		}

		// Check the nonce.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'wp-autoplugin-activate-plugin' ) ) {
			wp_die( __( 'Security check failed.', 'wp-autoplugin' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-autoplugin' ) );
		}

		$installer = Plugin_Installer::get_instance();
		$plugins   = $this->get_bulk_actions_input();
		$action    = $this->current_action();
		if ( 'activate' === $action ) {
			foreach ( $plugins as $plugin ) {
				$installer->activate_plugin( $plugin );
			}
		} elseif ( 'deactivate' === $action ) {
			foreach ( $plugins as $plugin ) {
				$installer->deactivate_plugin( $plugin );
			}
		} elseif ( 'delete' === $action ) {
			foreach ( $plugins as $plugin ) {
				$installer->delete_plugin( $plugin );
			}
		}
	}

	/**
	 * Get the current user action for the bulk actions.
	 *
	 * @return string
	 */
	public function current_action() {
		if ( ! is_null( $this->action ) ) {
			return $this->action;
		}

		$this->action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';

		return $this->action;
	}

	/**
	 * Get the list of plugins for the bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions_input() {
		$plugins = array();
		if ( isset( $_REQUEST['plugin'] ) ) {
			$plugins = (array) $_REQUEST['plugin'];
		}

		return $plugins;
	}

	/**
	 * Add a notice to be displayed in the admin.
	 *
	 * @param string $message The message to display.
	 * @param string $type    The type of notice: error, warning, success, info.
	 *
	 * @return void
	 */
	public static function add_notice( $message, $type = 'error' ) {
		$notices = get_option( 'wp_autoplugin_notices', array() );
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		update_option( 'wp_autoplugin_notices', $notices );
	}

	/**
	 * Initialize the Github updater.
	 *
	 * @return void
	 */
	public function github_updater_init() {
		if ( ! is_admin() ) {
			return;
		}

		$config = array(
			'slug'               => plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' ),
			'proper_folder_name' => dirname( plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' ) ),
			'api_url'            => 'https://api.github.com/repos/WP-Autoplugin/wp-autoplugin',
			'raw_url'            => 'https://raw.githubusercontent.com/WP-Autoplugin/wp-autoplugin/main/',
			'github_url'         => 'https://github.com/WP-Autoplugin/wp-autoplugin',
			'zip_url'            => 'https://github.com/WP-Autoplugin/wp-autoplugin/archive/refs/heads/main.zip',
			'requires'           => '6.0',
			'tested'             => '6.6.2',
			'description'        => 'A plugin that generates other plugins on-demand using AI.',
			'homepage'           => 'https://github.com/WP-Autoplugin/wp-autoplugin',
			'version'            => WP_AUTOPLUGIN_VERSION,
		);

		// Instantiate the updater class.
		new GitHub_Updater( $config );
	}
}
