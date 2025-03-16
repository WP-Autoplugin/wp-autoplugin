<?php
/**
 * WP-Autoplugin Admin AJAX class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Admin;

use WP_Autoplugin\API;
use WP_Autoplugin\Plugin_Generator;
use WP_Autoplugin\Plugin_Fixer;
use WP_Autoplugin\Plugin_Extender;
use WP_Autoplugin\Plugin_Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles all AJAX requests.
 */
class Ajax {

	/**
	 * The AI API object.
	 *
	 * @var API
	 */
	private $ai_api;

	/**
	 * Constructor sets the AI API and hooks into AJAX actions.
	 *
	 * @param API $ai_api The AI API instance.
	 */
	public function __construct( $ai_api ) {
		$this->ai_api = $ai_api;

		// Register all needed AJAX actions.
		$actions = [
			'generate_plan',
			'generate_code',
			'create_plugin',
			'extract_hooks',

			'generate_fix_plan',
			'generate_fix_code',
			'fix_plugin',

			'generate_extend_plan',
			'generate_extend_code',
			'extend_plugin',

			'explain_plugin',

			'generate_extend_hooks_plan',
			'generate_extend_hooks_code',
		];
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_wp_autoplugin_' . $action, [ $this, 'ajax_actions' ] );
		}

		// Add or remove custom model actions.
		add_action( 'wp_ajax_wp_autoplugin_add_model', [ $this, 'ajax_add_model' ] );
		add_action( 'wp_ajax_wp_autoplugin_remove_model', [ $this, 'ajax_remove_model' ] );
	}

	/**
	 * Catch-all AJAX entry point that routes to the relevant method.
	 *
	 * @return void
	 */
	public function ajax_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		$action_input = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$method       = str_replace( 'wp_autoplugin_', 'ajax_', $action_input );

		if ( method_exists( $this, $method ) ) {
			$this->$method();
		} else {
			wp_send_json_error( esc_html__( 'Invalid AJAX action.', 'wp-autoplugin' ) );
		}
	}

	/**
	 * AJAX handler for generating a plugin plan.
	 *
	 * @return void
	 */
	public function ajax_generate_plan() {
		$plan = isset( $_POST['plugin_description'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_description'] ) )
			: '';

		$generator = new Plugin_Generator( $this->ai_api );
		$plan_data = $generator->generate_plugin_plan( $plan );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Strip out any code block fences like ```json ... ```
		$plan_data  = preg_replace( '/^```(json)\n(.*)\n```$/s', '$2', $plan_data );
		$plan_array = json_decode( $plan_data, true );
		if ( ! $plan_array ) {
			wp_send_json_error( esc_html__( 'Failed to decode the generated plan: ', 'wp-autoplugin' ) . $plan_data );
		}

		wp_send_json_success( $plan_array );
	}

	/**
	 * AJAX handler for generating plugin code.
	 *
	 * @return void
	 */
	public function ajax_generate_code() {
		$description = isset( $_POST['plugin_plan'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) )
			: '';

		$generator = new Plugin_Generator( $this->ai_api );
		$code      = $generator->generate_plugin_code( $description );
		if ( is_wp_error( $code ) ) {
			wp_send_json_error( $code->get_error_message() );
		}

		// Strip out code fences like ```php ... ```
		$code = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $code );

		wp_send_json_success( $code );
	}

	/**
	 * AJAX handler for creating a plugin (i.e., writing the plugin files).
	 *
	 * @return void
	 */
	public function ajax_create_plugin() {
		$code        = isset( $_POST['plugin_code'] ) ? wp_unslash( $_POST['plugin_code'] ) : '';
		$plugin_name = isset( $_POST['plugin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_name'] ) ) : '';

		$installer = Plugin_Installer::get_instance();
		$result    = $installer->install_plugin( $code, $plugin_name );
		if ( is_wp_error( $result ) ) {
			wp_send_json(
				[
					'success'    => false,
					'data'       => $result->get_error_message(),
					'error_type' => 'install_error',
				]
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for generating a fix plan for a plugin.
	 *
	 * @return void
	 */
	public function ajax_generate_fix_plan() {
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path ); // local file read
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$problem            = isset( $_POST['plugin_issue'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) )
			: '';
		$check_other_issues = isset( $_POST['check_other_issues'] ) ? (bool) $_POST['check_other_issues'] : true;

		$fixer     = new Plugin_Fixer( $this->ai_api );
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
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path );
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$problem        = isset( $_POST['plugin_issue'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) )
			: '';
		$ai_description = isset( $_POST['plugin_plan'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) )
			: '';

		$fixer = new Plugin_Fixer( $this->ai_api );
		$code  = $fixer->fix_plugin( $plugin_code, $problem, $ai_description );

		wp_send_json_success( $code );
	}

	/**
	 * AJAX handler for installing the fixed plugin.
	 *
	 * @return void
	 */
	public function ajax_fix_plugin() {
		$code        = isset( $_POST['plugin_code'] ) ? wp_unslash( $_POST['plugin_code'] ) : '';
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$installer = Plugin_Installer::get_instance();
		$result    = $installer->install_plugin( $code, $plugin_file );
		if ( is_wp_error( $result ) ) {
			wp_send_json(
				[
					'success'    => false,
					'data'       => $result->get_error_message(),
					'error_type' => 'install_error',
				]
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for generating an extension plan for a plugin.
	 *
	 * @return void
	 */
	public function ajax_generate_extend_plan() {
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path );
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$problem   = isset( $_POST['plugin_issue'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) )
			: '';

		$extender  = new Plugin_Extender( $this->ai_api );
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
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path );
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$problem        = isset( $_POST['plugin_issue'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) )
			: '';
		$ai_description = isset( $_POST['plugin_plan'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) )
			: '';

		$extender = new Plugin_Extender( $this->ai_api );
		$code     = $extender->extend_plugin( $plugin_code, $problem, $ai_description );

		wp_send_json_success( $code );
	}

	/**
	 * AJAX handler for installing an extended plugin.
	 *
	 * @return void
	 */
	public function ajax_extend_plugin() {
		$code        = isset( $_POST['plugin_code'] ) ? wp_unslash( $_POST['plugin_code'] ) : '';
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$installer = Plugin_Installer::get_instance();
		$result    = $installer->install_plugin( $code, $plugin_file );
		if ( is_wp_error( $result ) ) {
			wp_send_json(
				[
					'success'    => false,
					'data'       => $result->get_error_message(),
					'error_type' => 'install_error',
				]
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for explaining a plugin.
	 *
	 * @return void
	 */
	public function ajax_explain_plugin() {
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path );
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$question = isset( $_POST['plugin_question'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_question'] ) )
			: '';

		$focus = isset( $_POST['explain_focus'] )
			? sanitize_text_field( wp_unslash( $_POST['explain_focus'] ) )
			: 'general';

		$explainer = new \WP_Autoplugin\Plugin_Explainer( $this->ai_api );
		if ( ! empty( $question ) ) {
			$explanation = $explainer->answer_plugin_question( $plugin_code, $question );
		} elseif ( $focus !== 'general' ) {
			$explanation = $explainer->analyze_plugin_aspect( $plugin_code, $focus );
		} else {
			$explanation = $explainer->explain_plugin( $plugin_code );
		}

		if ( is_wp_error( $explanation ) ) {
			wp_send_json_error( $explanation->get_error_message() );
		}

		wp_send_json_success( $explanation );
	}

	/**
	 * AJAX handler for adding a custom model.
	 *
	 * @return void
	 */
	public function ajax_add_model() {
		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-autoplugin' ) ] );
		}

		$model = isset( $_POST['model'] ) && is_array( $_POST['model'] ) ? wp_unslash( $_POST['model'] ) : null;
		if ( ! $model || ! isset( $model['name'] ) || ! isset( $model['url'] ) || ! isset( $model['apiKey'] ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid model data.', 'wp-autoplugin' ) ] );
		}

		$new_model = [
			'name'           => sanitize_text_field( $model['name'] ),
			'url'            => esc_url_raw( $model['url'] ),
			'modelParameter' => isset( $model['modelParameter'] ) ? sanitize_text_field( $model['modelParameter'] ) : '',
			'apiKey'         => sanitize_text_field( $model['apiKey'] ),
			'headers'        => ( isset( $model['headers'] ) && is_array( $model['headers'] ) )
				? array_map( 'sanitize_text_field', $model['headers'] )
				: [],
		];

		$models = get_option( 'wp_autoplugin_custom_models', [] );
		if ( ! is_array( $models ) ) {
			$models = [];
		}

		$models[] = $new_model;
		update_option( 'wp_autoplugin_custom_models', $models );

		wp_send_json_success(
			[
				'models'  => $models,
				'message' => esc_html__( 'Model added successfully.', 'wp-autoplugin' ),
			]
		);
	}

	/**
	 * AJAX handler for removing a custom model.
	 *
	 * @return void
	 */
	public function ajax_remove_model() {
		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-autoplugin' ) ] );
		}

		$models = get_option( 'wp_autoplugin_custom_models', [] );
		if ( ! is_array( $models ) ) {
			$models = [];
		}

		$index = isset( $_POST['index'] ) ? intval( wp_unslash( $_POST['index'] ) ) : null;
		if ( ! is_int( $index ) || $index >= count( $models ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid model index.', 'wp-autoplugin' ) ] );
		}

		if ( isset( $models[ $index ] ) ) {
			unset( $models[ $index ] );
		}

		update_option( 'wp_autoplugin_custom_models', $models );

		wp_send_json_success(
			[
				'models'  => $models,
				'message' => esc_html__( 'Model removed successfully.', 'wp-autoplugin' ),
			]
		);
	}

	/**
	 * AJAX handler for generating a plan to extend plugin hooks.
	 *
	 * @return void
	 */
	public function ajax_generate_extend_hooks_plan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) );
		$hooks = \WP_Autoplugin\Hooks_Extender::get_plugin_hooks( $plugin_file );
		if ( empty( $hooks ) ) {
			wp_send_json_error( esc_html__( 'No hooks found in the plugin.', 'wp-autoplugin' ) );
		}
		$plugin_changes = sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) );
		$extender = new \WP_Autoplugin\Hooks_Extender( $this->ai_api );
		$plan_data = $extender->plan_plugin_hooks_extension( $hooks, $plugin_changes );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Strip out any code block fences like ```json ... ```
		$plan_data  = preg_replace( '/^```(json)\n(.*)\n```$/s', '$2', $plan_data );
		$plan_array = json_decode( $plan_data, true );
		if ( ! $plan_array ) {
			wp_send_json_error( esc_html__( 'Failed to decode the generated plan.', 'wp-autoplugin' ) );
		}
		if ( ! $plan_array['technically_feasible'] ) {
			wp_send_json_error( $plan_array['explanation'] );
		}
		wp_send_json_success( $plan_array );
	}

	/**
	 * AJAX handler for generating code to extend plugin hooks.
	 *
	 * @return void
	 */
	public function ajax_generate_extend_hooks_code() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) );
		$ai_plan = sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) );
		$hooks = \WP_Autoplugin\Hooks_Extender::get_plugin_hooks( $plugin_file );
		$hooks_param = isset( $_POST['hooks'] ) ? json_decode( wp_unslash( $_POST['hooks'] ), true ) : [];

		// The $hooks_param is an array of hook names. Keep only the hooks that are in the plan.
		$hooks = array_filter( $hooks, function( $hook ) use ( $hooks_param ) {
			return in_array( $hook['name'], $hooks_param, true );
		} );

		$plugin_name = isset( $_POST['plugin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_name'] ) ) : '';

		$extender = new \WP_Autoplugin\Hooks_Extender( $this->ai_api );
		$code = $extender->generate_hooks_extension_code( $hooks, $ai_plan, $plugin_name );
		if ( is_wp_error( $code ) ) {
			wp_send_json_error( $code->get_error_message() );
		}
		wp_send_json_success( $code );
	}

	/**
	 * AJAX handler for extracting plugin hooks.
	 *
	 * @return void
	 */
	public function ajax_extract_hooks() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		$plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) : '';
		if ( empty( $plugin_file ) ) {
			wp_send_json_error( esc_html__( 'No plugin file specified.', 'wp-autoplugin' ) );
		}

		$hooks = \WP_Autoplugin\Hooks_Extender::get_plugin_hooks( $plugin_file );
		wp_send_json_success( $hooks ); // Returns empty array if no hooks found
	}
}
