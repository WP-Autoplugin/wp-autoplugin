<?php
/**
 * WP-Autoplugin Admin class.
 *
 * @package WP-Autoplugin
 * @since 1.0.0
 * @version 1.4.3
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
			'gpt-4.1'           => 'GPT-4.1',
			'gpt-4.1-mini'      => 'GPT-4.1 mini',
			'gpt-4.1-nano'      => 'GPT-4.1 nano',
			'gpt-4o'            => 'GPT-4o',
			'gpt-4o-mini'       => 'GPT-4o mini',
			'chatgpt-4o-latest' => 'ChatGPT-4o-latest',
			'o1'                => 'o1',
			'o1-preview'        => 'o1-preview',
			'o3-mini-low'       => 'o3-mini-low',
			'o3-mini-medium'    => 'o3-mini-medium',
			'o3-mini-high'      => 'o3-mini-high',
			'o3-low'            => 'o3-low',
			'o3-medium'         => 'o3-medium',
			'o3-high'           => 'o3-high',
			'o4-mini-low'       => 'o4-mini-low',
			'o4-mini-medium'    => 'o4-mini-medium',
			'o4-mini-high'      => 'o4-mini-high',
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
			'gemini-2.5-pro-exp-03-25'            => 'Gemini 2.5 Pro Experimental 03-25',
			'gemini-2.5-flash-preview-04-17'      => 'Gemini 2.5 Flash Preview 04-17',
			'gemini-2.0-pro-exp-02-05'            => 'Gemini 2.0 Pro Experimental 02-05',
			'gemini-2.0-flash-thinking-exp'       => 'Gemini 2.0 Flash Thinking Experimental',
			'gemini-2.0-flash'                    => 'Gemini 2.0 Flash',
			'gemini-2.0-flash-lite'               => 'Gemini 2.0 Flash Lite',
			'gemini-2.0-flash-exp'                => 'Gemini 2.0 Flash Experimental',
			'gemini-2.0-flash-thinking-exp-01-21' => 'Gemini 2.0 Flash Thinking Experimental 01-21',
			'gemini-exp-1206'                     => 'Gemini Experimental 1206',
			'gemini-exp-1121'                     => 'Gemini Experimental 1121',
			'gemini-1.5-pro'                      => 'Gemini 1.5 Pro',
			'gemini-1.5-flash'                    => 'Gemini 1.5 Flash',
			'gemini-1.0-pro'                      => 'Gemini 1.0 Pro',
			'gemma-3-27b-it'                      => 'Gemma 3 27B',
		],
		'xAI'       => [
			'grok-3'      => 'Grok 3',
			'grok-3-mini' => 'Grok 3 Mini',
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
		add_action( 'init', [ $this, 'github_updater_init' ] );

		// Add "Extend Plugin" links to the plugin list table.
		add_filter( 'plugin_action_links', [ $this, 'add_extend_plugin_link' ], 20, 2 );

		// Add "Extend Theme" links to the plugin list table.
		add_action( 'admin_enqueue_scripts', [ $this, 'add_extend_theme_action_links' ] );

		// Add "Extend Theme" links to the theme list table (Multisite)
		add_filter( 'theme_action_links', [ $this, 'add_extend_theme_link' ], 20, 2 );

		// Add custom hook extraction config for Rank Math.
		add_filter( 'wp_autoplugin_hook_extraction_config', [ $this, 'add_rank_math_hook_extraction_config' ] );
	}

	/**
	 * Add custom hook extraction configuration for the Rank Math plugin, which uses a custom hook format.
	 *
	 * @param array $configs Array of custom hook extraction configurations.
	 * @return array
	 */
	public function add_rank_math_hook_extraction_config( $configs ) {
		$rank_math_config = [
			'regex_pattern'         => '/(?:->)?(do_filter|do_action|apply_filters)\s*\(\s*([\'"]([^\'"]+)[\'"]|\$[^,]+|\w+)\s*,/m',
			'method_to_type'        => [
				'do_filter' => 'filter',
				'do_action' => 'action',
			],
			'hook_name_transformer' => function ( $name, $regex_match ) {
				if ( strpos( $regex_match[0][0], '->' ) === 0 ) {
					return 'rank_math/' . $name;
				}
				return $name;
			},
		];

		$configs['seo-by-rank-math']     = $rank_math_config;
		$configs['seo-by-rank-math-pro'] = $rank_math_config;

		return $configs;
	}

	/**
	 * Add an "Extend Plugin" link to the plugin list table.
	 *
	 * @param array  $actions The plugin action links.
	 * @param string $plugin_file The plugin file.
	 *
	 * @return array
	 */
	public function add_extend_plugin_link( $actions, $plugin_file ) {
		$autoplugins = get_option( 'wp_autoplugins', [] );
		if ( in_array( $plugin_file, $autoplugins, true ) ) {
			$extend_url = admin_url( 'admin.php?page=wp-autoplugin-extend&plugin=' . rawurlencode( $plugin_file ) );
			$extend_url = wp_nonce_url( $extend_url, 'wp-autoplugin-extend-plugin', 'nonce' );
		} else {
			$extend_url = admin_url( 'admin.php?page=wp-autoplugin-extend-hooks&plugin=' . rawurlencode( $plugin_file ) );
			$extend_url = wp_nonce_url( $extend_url, 'wp-autoplugin-extend-hooks', 'nonce' );
		}
		$actions['extend_plugin'] = '<a href="' . esc_url( $extend_url ) . '">' . esc_html__( 'Extend Plugin', 'wp-autoplugin' ) . '</a>';
		return $actions;
	}

	/**
	 * Add an "Extend Theme" link to the theme list table (Multisite).
	 *
	 * @param array  $actions The theme action links.
	 * @param string $theme The theme slug.
	 *
	 * @return array
	 */
	public function add_extend_theme_link( $actions, $theme ) {
		$extend_url = admin_url( 'admin.php?page=wp-autoplugin-extend-theme&theme=' . rawurlencode( $theme ) );
		$extend_url = wp_nonce_url( $extend_url, 'wp-autoplugin-extend-theme', 'nonce' );
		$actions['extend_theme'] = '<a href="' . esc_url( $extend_url ) . '">' . esc_html__( 'Extend Theme', 'wp-autoplugin' ) . '</a>';
		return $actions;
	}

	/**
	 * Add "Extend Theme" action links to the plugin list table.
	 * Note: this is hooked to 'admin_enqueue_scripts'.
	 *
	 * @return void
	 */
	public function add_extend_theme_action_links( $hook ) {
		if ( 'themes.php' !== $hook ) {
			return;
		}

		wp_add_inline_script( 'theme', "
			jQuery(document).ready(function($) {
				console.log('Adding Extend Theme button to theme list table.');

				wp.themes.data.themes.forEach(function(theme) {
					if (theme.id) {
						const selector = '.theme[data-slug=\"' + theme.id + '\"] .theme-actions';
						const extendUrl = '" . esc_url( admin_url( 'admin.php?page=wp-autoplugin-extend-theme&theme=' ) ) . "' + encodeURIComponent(theme.id) + '&nonce=' + '" . esc_js( wp_create_nonce( 'wp-autoplugin-extend-theme' ) ) . "';
						const actionLink = $('<a class=\"button button-small\" style=\"vertical-align: text-top;\" href=\"' + extendUrl + '\">' + '" . esc_html__( 'Extend', 'wp-autoplugin' ) . "' + '</a>');
						$(selector).append(actionLink);
					}
				});
			});
		" );
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

		// Extend and Fix pages (they don't appear in the menu).
		add_submenu_page(
			'options.php',
			esc_html__( 'Extend Plugin', 'wp-autoplugin' ),
			esc_html__( 'Extend Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-extend',
			[ $this, 'render_extend_plugin_page' ]
		);

		add_submenu_page(
			'options.php',
			esc_html__( 'Fix Plugin', 'wp-autoplugin' ),
			esc_html__( 'Fix Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-fix',
			[ $this, 'render_fix_plugin_page' ]
		);

		add_submenu_page(
			'options.php',
			esc_html__( 'Explain Plugin', 'wp-autoplugin' ),
			esc_html__( 'Explain Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-explain',
			[ $this, 'render_explain_plugin_page' ]
		);

		add_submenu_page(
			'options.php',
			esc_html__( 'Extend Plugin with Hooks', 'wp-autoplugin' ),
			esc_html__( 'Extend Plugin with Hooks', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-extend-hooks',
			[ $this, 'render_extend_hooks_page' ]
		);

		add_submenu_page(
			'options.php',
			esc_html__( 'Extend Theme', 'wp-autoplugin' ),
			esc_html__( 'Extend Theme', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-extend-theme',
			[ $this, 'render_extend_theme_page' ]
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
	 * Display the explain plugin page.
	 *
	 * @return void
	 */
	public function render_explain_plugin_page() {
		$this->validate_plugin( 'wp-autoplugin-explain-plugin' );
		include WP_AUTOPLUGIN_DIR . 'views/page-explain-plugin.php';
	}

	/**
	 * Display the extend plugin with hooks page.
	 *
	 * @return void
	 */
	public function render_extend_hooks_page() {
		if ( ! isset( $_GET['plugin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_die( esc_html__( 'No plugin specified.', 'wp-autoplugin' ) );
		}
		$plugin_file = sanitize_text_field( wp_unslash( $_GET['plugin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$plugin_file = str_replace( '../', '', $plugin_file );
		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		if ( ! file_exists( $plugin_path ) ) {
			wp_die( esc_html__( 'The specified plugin does not exist.', 'wp-autoplugin' ) );
		}
		$plugin_data = get_plugin_data( $plugin_path );
		include WP_AUTOPLUGIN_DIR . 'views/page-extend-hooks.php';
	}

	/**
	 * Display the extend theme page.
	 *
	 * @return void
	 */
	public function render_extend_theme_page() {
		$this->validate_theme( 'wp-autoplugin-extend-theme' );
		include WP_AUTOPLUGIN_DIR . 'views/page-extend-theme.php';
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
	 * Check if the theme in the query string is valid and the user has permission.
	 *
	 * @param string $nonce The nonce action name.
	 *
	 * @return bool
	 */
	public function validate_theme( $nonce ) {
		if ( ! isset( $_GET['theme'] ) ) {
			wp_die( esc_html__( 'No theme specified.', 'wp-autoplugin' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-autoplugin' ) );
		}

		$nonce_value = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, $nonce ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-autoplugin' ) );
		}

		// Check if the theme exists.
		$theme_slug = sanitize_text_field( wp_unslash( $_GET['theme'] ) );
		$theme_path = get_theme_root() . '/' . $theme_slug;
		if ( ! is_dir( $theme_path ) ) {
			wp_die( esc_html__( 'The specified theme does not exist.', 'wp-autoplugin' ) );
		}

		return true;
	}

	/**
	 * Output a simple admin footer for WP-Autoplugin pages.
	 *
	 * @return void
	 */
	public function output_admin_footer() {
		$current_model = get_option( 'wp_autoplugin_model' );
		?>
		<div id="wp-autoplugin-footer">
			<p>
				<span class="dashicons dashicons-admin-plugins"></span>
				<span class="credits">
					<?php
					printf(
						// translators: %s: version number.
						esc_html__( 'WP-Autoplugin v%s', 'wp-autoplugin' ),
						esc_html( WP_AUTOPLUGIN_VERSION )
					);
					?>
				</span>
				<span class="separator">|</span>
				<span class="model">
					<span id="model-display">
						<?php
						$translated_model_string = wp_kses(
							// translators: %s: model name.
							__( 'Model: %s', 'wp-autoplugin' ),
							[ 'code' => [] ]
						);
						printf(
							$translated_model_string, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- It's escaped just above.
							'<code>' . esc_html( $current_model ) . '</code>'
						);
						?>
						<a href="#" id="change-model-link" style="text-decoration: none;"><?php esc_html_e( '(Change)', 'wp-autoplugin' ); ?></a>
					</span>
					<span id="model-change-form" style="display: none;">
						<select id="model-selector" style="width: 200px;">
							<?php
							// Loop through built-in models.
							foreach ( self::$models as $provider => $models ) {
								echo '<optgroup label="' . esc_attr( $provider ) . '">';
								foreach ( $models as $model_id => $model_name ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $model_id ),
										selected( $current_model, $model_id, false ),
										esc_html( $model_name )
									);
								}
								echo '</optgroup>';
							}

							// Add custom models if any.
							$custom_models = get_option( 'wp_autoplugin_custom_models', [] );
							if ( ! empty( $custom_models ) ) {
								echo '<optgroup label="' . esc_attr__( 'Custom Models', 'wp-autoplugin' ) . '">';
								foreach ( $custom_models as $custom_model ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $custom_model['name'] ),
										selected( $current_model, $custom_model['name'], false ),
										esc_html( $custom_model['name'] )
									);
								}
								echo '</optgroup>';
							}
							?>
						</select>
						<button id="save-model-change" class="button button-small"><?php esc_html_e( 'Save', 'wp-autoplugin' ); ?></button>
						<button id="cancel-model-change" class="button button-small"><?php esc_html_e( 'Cancel', 'wp-autoplugin' ); ?></button>
					</span>
				</span>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('#change-model-link').on('click', function(e) {
				e.preventDefault();
				$('#model-display').hide();
				$('#model-change-form').show();
			});
			
			$('#cancel-model-change').on('click', function(e) {
				e.preventDefault();
				$('#model-change-form').hide();
				$('#model-display').show();
			});
			
			$('#save-model-change').on('click', function(e) {
				e.preventDefault();
				var newModel = $('#model-selector').val();
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wp_autoplugin_change_model',
						nonce: '<?php echo wp_create_nonce( 'wp_autoplugin_nonce' ); ?>',
						model: newModel
					},
					success: function(response) {
						if (response.success) {
							$('#model-display code').text(newModel);
							$('#model-change-form').hide();
							$('#model-display').show();
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Failed to change model.', 'wp-autoplugin' ); ?>');
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'An error occurred while changing the model.', 'wp-autoplugin' ); ?>');
					}
				});
			});
		});
		</script>
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
