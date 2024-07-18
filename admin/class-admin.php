<?php
namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	private $ai_api;

	private $action;

	public function __construct() {

		$openai_api_key = get_option( 'wp_autoplugin_openai_api_key' );
		$anthropic_api_key = get_option( 'wp_autoplugin_anthropic_api_key' );
		$model = get_option( 'wp_autoplugin_model' );

		if ( ! empty( $openai_api_key ) && in_array( $model, array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' ), true ) ) {
			$this->ai_api = new OpenAI_API();
			$this->ai_api->set_api_key( $openai_api_key );
			$this->ai_api->set_model( $model );
		} elseif ( ! empty( $anthropic_api_key ) && in_array( $model, array( 'claude-3-5-sonnet-20240620', 'claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307' ), true ) ) {
			$this->ai_api = new Anthropic_API();
			$this->ai_api->set_api_key( $anthropic_api_key );
			$this->ai_api->set_model( $model );
		} else {
			$this->ai_api = null;
		}

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

		// Show notices.
		add_action( 'admin_notices', array( $this, 'show_notices' ) );

		// Process bulk actions.
		add_action( 'admin_init', array( $this, 'process_bulk_action' ) );
	}

	public function ajax_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You are not allowed to access this page.' );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		// If the ai_api is not set, we cannot proceed.
		if ( ! $this->ai_api ) {
			wp_send_json_error( 'API key or model not set. Please configure the plugin settings.' );
		}

		$action = str_replace( 'wp_autoplugin_', '', $_POST['action'] );
		if ( method_exists( $this, $action ) ) {
			$this->$action();
		}
	}

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
			null,
			__( 'Extend Plugin', 'wp-autoplugin' ),
			__( 'Extend Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-extend',
			array( $this, 'render_extend_plugin_page' )
		);

		add_submenu_page(
			null,
			__( 'Fix Plugin', 'wp-autoplugin' ),
			__( 'Fix Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-fix',
			array( $this, 'render_fix_plugin_page' )
		);
	}

	public function register_settings() {
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_openai_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_anthropic_api_key' );
		register_setting( 'wp_autoplugin_settings', 'wp_autoplugin_model' );
	}

	public function render_settings_page() {
		include WP_AUTOPLUGIN_DIR . 'admin/views/page-settings.php';
	}

	public function render_generate_plugin_page() {
		include WP_AUTOPLUGIN_DIR . 'admin/views/page-generate-plugin.php';
	}

	public function render_list_plugins_page() {
		include WP_AUTOPLUGIN_DIR . 'admin/views/page-list-plugins.php';
	}

	public function render_extend_plugin_page() {
		include WP_AUTOPLUGIN_DIR . 'admin/views/page-extend-plugin.php';
	}

	public function render_fix_plugin_page() {

		if ( ! isset( $_GET['plugin'] ) ) {
			wp_die( __( 'No plugin specified.', 'wp-autoplugin' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-autoplugin' ) );
		}

		// Check nonce.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'wp-autoplugin-fix-plugin' ) ) {
			wp_die( __( 'Security check failed.', 'wp-autoplugin' ) );
		}

		// Check if the plugin exists.
		if ( ! file_exists( WP_CONTENT_DIR . '/plugins/' . $_GET['plugin'] ) ) {
			wp_die( __( 'The specified plugin does not exist.', 'wp-autoplugin' ) );
		}

		include WP_AUTOPLUGIN_DIR . 'admin/views/page-fix-plugin.php';
	}

	public function enqueue_scripts() {
		// Enqueue generator.js and generator.css only on the plugin generation page.
		$screen = get_current_screen();
		wp_register_script( 'wp-autoplugin-utils', WP_AUTOPLUGIN_URL . 'admin/assets/js/utils.js', array(), WP_AUTOPLUGIN_VERSION, true );
		if ( $screen->id === 'toplevel_page_wp-autoplugin' ) {
			wp_enqueue_script( 'wp-autoplugin', WP_AUTOPLUGIN_URL . 'admin/assets/js/list-plugins.js', array(), WP_AUTOPLUGIN_VERSION, true );
			wp_enqueue_style( 'wp-autoplugin', WP_AUTOPLUGIN_URL . 'admin/assets/css/list-plugins.css', array(), WP_AUTOPLUGIN_VERSION );
		} elseif ( $screen->id === 'wp-autoplugin_page_wp-autoplugin-generate' ) {
			// Settings for the CodeMirror editor for PHP code
			$settings = wp_enqueue_code_editor(array(
				'type' => 'application/x-httpd-php',
			));

			// Enqueue the code editor if the current user's browser supports it
			if (false !== $settings) {
				wp_enqueue_script('wp-theme-plugin-editor');
				wp_enqueue_style('wp-codemirror');
			}

			wp_enqueue_script( 'wp-autoplugin-generator', WP_AUTOPLUGIN_URL . 'admin/assets/js/generator.js', array( 'wp-autoplugin-utils' ), WP_AUTOPLUGIN_VERSION, true );
			wp_localize_script( 'wp-autoplugin-generator', 'wp_autoplugin', array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wp_autoplugin_generate' ),
				'fix_url'      => admin_url( 'admin.php?page=wp-autoplugin-fix&nonce=' . wp_create_nonce( 'wp-autoplugin-fix-plugin' ) ),
				'activate_url' => admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ),
				'testing_plan' => '',
			) );
			wp_enqueue_style( 'wp-autoplugin-generator', WP_AUTOPLUGIN_URL . 'admin/assets/css/generator.css', array(), WP_AUTOPLUGIN_VERSION );
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

			wp_enqueue_script( 'wp-autoplugin-fix', WP_AUTOPLUGIN_URL . 'admin/assets/js/fixer.js', array( 'wp-autoplugin-utils' ), WP_AUTOPLUGIN_VERSION, true );
			wp_localize_script( 'wp-autoplugin-fix', 'wp_autoplugin', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wp_autoplugin_generate' ),
				'activate_url' => admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ),
				'is_plugin_active' => $is_plugin_active,
			) );
			wp_enqueue_style( 'wp-autoplugin-fix', WP_AUTOPLUGIN_URL . 'admin/assets/css/fixer.css', array(), WP_AUTOPLUGIN_VERSION );
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

			wp_enqueue_script( 'wp-autoplugin-extend', WP_AUTOPLUGIN_URL . 'admin/assets/js/extender.js', array( 'wp-autoplugin-utils' ), WP_AUTOPLUGIN_VERSION, true );
			wp_localize_script( 'wp-autoplugin-extend', 'wp_autoplugin', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wp_autoplugin_generate' ),
				'activate_url' => admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ),
				'is_plugin_active' => $is_plugin_active,
			) );
			wp_enqueue_style( 'wp-autoplugin-extend', WP_AUTOPLUGIN_URL . 'admin/assets/css/extender.css', array(), WP_AUTOPLUGIN_VERSION );
		}
	}

	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=wp-autoplugin-settings' ) . '">' . __( 'Settings', 'wp-autoplugin' ) . '</a>';
		$generate_link = '<a href="' . admin_url( 'admin.php?page=wp-autoplugin-generate' ) . '">' . __( 'Generate New Plugin', 'wp-autoplugin' ) . '</a>';
		array_unshift( $links, $settings_link, $generate_link );
		return $links;
	}

	public function generate_plan() {
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

	public function generate_code() {
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

	public function create_plugin() {
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

	public function generate_fix_plan() {
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

	public function generate_fix_code() {
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

	public function fix_plugin() {
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

	public function generate_extend_plan() {
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

	public function generate_extend_code() {
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

	public function extend_plugin() {
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

	public function show_notices() {
		// Show notices only on the plugin list page.
		$screen = get_current_screen();
		if ( $screen->id !== 'toplevel_page_wp-autoplugin' ) {
			return;
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

	public function output_admin_footer() {
		?>
		<div id="wp-autoplugin-footer">
			<p>
				<span class="dashicons dashicons-admin-plugins"></span>
				<span class="credits">
					<strong><?php printf( __( 'WP-Autoplugin v%s', 'wp-autoplugin' ), WP_AUTOPLUGIN_VERSION ); ?></strong>
				</span>
				<span class="separator">|</span>
				<span class="model">
					<?php printf( __( 'Model: %s', 'wp-autoplugin' ), '<code>' . get_option( 'wp_autoplugin_model' ) . '</code>' ); ?>
				</span>
			</p>
		<?php
	}

	// Handle the bulk actions.
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

	public function current_action() {
		if ( ! is_null( $this->action ) ) {
			return $this->action;
		}

		$this->action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';

		return $this->action;
	}

	public function get_bulk_actions_input() {
		$plugins = array();
		if ( isset( $_REQUEST['plugin'] ) ) {
			$plugins = (array) $_REQUEST['plugin'];
		}

		return $plugins;
	}

	public static function add_notice( $message, $type = 'error' ) {
		$notices = get_option( 'wp_autoplugin_notices', array() );
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		update_option( 'wp_autoplugin_notices', $notices );
	}
}
