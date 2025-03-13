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
 * Main Admin class that brings all admin functionalities together.
 */
class Admin {

	/**
	 * The API object.
	 *
	 * @var API
	 */
	private $ai_api;

	/**
	 * The built-in models.
	 *
	 * @var array
	 */
	public static $models = [
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
			'grok-2'      => 'Grok 2',
			'grok-beta'   => 'Grok Beta',
			'grok-2-1212' => 'Grok 2-1212',
		],
	];

	/**
	 * Constructor: set up API, instantiate sub-classes.
	 *
	 * @return void
	 */
	public function __construct() {
		$model        = get_option( 'wp_autoplugin_model' );
		$this->ai_api = $this->get_api( $model );

		// Instantiate other admin components (each handles its own hooks).
		new Admin\Settings();
		new Admin\Scripts();
		new Admin\Ajax( $this->ai_api );
		new Admin\Bulk_Actions();
		new Admin\Notices();

		// Set up the main admin menu.
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );

		// Initialize the GitHub updater.
		$this->github_updater_init();
	}

	/**
	 * Get the API object based on the selected model or a custom model.
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
		$custom_models     = get_option( 'wp_autoplugin_custom_models', [] );

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

		// Check custom models.
		if ( ! empty( $custom_models ) ) {
			foreach ( $custom_models as $custom_model ) {
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

		return $api;
	}

	/**
	 * Initialize the admin menu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			esc_html__( 'WP-Autoplugin', 'wp-autoplugin' ),
			esc_html__( 'WP-Autoplugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin',
			[ $this, 'render_list_plugins_page' ],
			'dashicons-admin-plugins',
			100
		);

		add_submenu_page(
			'wp-autoplugin',
			esc_html__( 'Generate New Plugin', 'wp-autoplugin' ),
			esc_html__( 'Generate New Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-generate',
			[ $this, 'render_generate_plugin_page' ]
		);

		add_submenu_page(
			'wp-autoplugin',
			esc_html__( 'Settings', 'wp-autoplugin' ),
			esc_html__( 'Settings', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-settings',
			[ $this, 'render_settings_page' ]
		);

		// Extend and Fix pages (they don't appear in the menu since the parent slug is an empty string).
		add_submenu_page(
			'',
			esc_html__( 'Extend Plugin', 'wp-autoplugin' ),
			esc_html__( 'Extend Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-extend',
			[ $this, 'render_extend_plugin_page' ]
		);

		add_submenu_page(
			'',
			esc_html__( 'Fix Plugin', 'wp-autoplugin' ),
			esc_html__( 'Fix Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-fix',
			[ $this, 'render_fix_plugin_page' ]
		);
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
	 * Display the plugin generation page.
	 *
	 * @return void
	 */
	public function render_generate_plugin_page() {
		include WP_AUTOPLUGIN_DIR . 'views/page-generate-plugin.php';
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
	 * Check if the plugin in the query string is valid and the user has permission.
	 *
	 * @param string $nonce The nonce action name.
	 *
	 * @return bool
	 */
	public function validate_plugin( $nonce ) {
		if ( ! isset( $_GET['plugin'] ) ) {
			wp_die( esc_html__( 'No plugin specified.', 'wp-autoplugin' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-autoplugin' ) );
		}

		$nonce_value = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, $nonce ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-autoplugin' ) );
		}

		// Check if the plugin file exists in /wp-content/plugins/.
		$plugin_path = WP_CONTENT_DIR . '/plugins/' . sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
		if ( ! file_exists( $plugin_path ) ) {
			wp_die( esc_html__( 'The specified plugin does not exist.', 'wp-autoplugin' ) );
		}

		// Check if it's a WP-Autoplugin generated plugin in the DB.
		$plugins = get_option( 'wp_autoplugins', [] );
		if ( ! in_array( sanitize_text_field( wp_unslash( $_GET['plugin'] ) ), $plugins, true ) ) {
			wp_die( esc_html__( 'The specified plugin does not exist.', 'wp-autoplugin' ) );
		}

		return true;
	}

	/**
	 * Output a simple admin footer for WP-Autoplugin pages.
	 *
	 * @return void
	 */
	public function output_admin_footer() {
		?>
		<div id="wp-autoplugin-footer">
			<p>
				<span class="dashicons dashicons-admin-plugins"></span>
				<span class="credits">
					<?php
					printf(
						esc_html__( 'WP-Autoplugin v%s', 'wp-autoplugin' ),
						esc_html( WP_AUTOPLUGIN_VERSION )
					);
					?>
				</span>
				<span class="separator">|</span>
				<span class="model">
					<?php
					// translators: %s: model name
					$translated_model_string = wp_kses(
						__( 'Model: %s', 'wp-autoplugin' ),
						[ 'code' => [] ]
					);
					printf(
						$translated_model_string,
						'<code>' . esc_html( get_option( 'wp_autoplugin_model' ) ) . '</code>'
					);
					?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Initialize the GitHub updater.
	 *
	 * @return void
	 */
	public function github_updater_init() {
		if ( ! is_admin() ) {
			return;
		}

		$config = [
			'slug'               => plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' ),
			'proper_folder_name' => dirname( plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' ) ),
			'api_url'            => 'https://api.github.com/repos/WP-Autoplugin/wp-autoplugin',
			'raw_url'            => 'https://raw.githubusercontent.com/WP-Autoplugin/wp-autoplugin/main/',
			'github_url'         => 'https://github.com/WP-Autoplugin/wp-autoplugin',
			'zip_url'            => 'https://github.com/WP-Autoplugin/wp-autoplugin/archive/refs/heads/main.zip',
			'requires'           => '6.0',
			'tested'             => '6.6.2',
			'description'        => esc_html__( 'A plugin that generates other plugins on-demand using AI.', 'wp-autoplugin' ),
			'homepage'           => 'https://github.com/WP-Autoplugin/wp-autoplugin',
			'version'            => WP_AUTOPLUGIN_VERSION,
		];

		// Instantiate the updater class.
		new GitHub_Updater( $config );
	}
}
