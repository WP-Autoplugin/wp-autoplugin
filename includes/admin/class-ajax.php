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
use WP_Autoplugin\Plugin_Optimizer;

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

			'generate_fix_plan',
			'generate_fix_code',
			'fix_plugin',

			'generate_extend_plan',
			'generate_extend_code',
			'extend_plugin',

			'explain_plugin',

			'extract_hooks',
			'generate_extend_hooks_plan',
			'generate_extend_hooks_code',
		];
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_wp_autoplugin_' . $action, [ $this, 'ajax_actions' ] );
		}

		// Add or remove custom model actions.
		add_action( 'wp_ajax_wp_autoplugin_add_model', [ $this, 'ajax_add_model' ] );
		add_action( 'wp_ajax_wp_autoplugin_remove_model', [ $this, 'ajax_remove_model' ] );
		add_action( 'wp_ajax_wp_autoplugin_change_model', [ $this, 'ajax_change_model' ] );

		// Plugin Optimizer actions
		add_action( 'wp_ajax_autoplugin_get_optimization_plan', [ $this, 'handle_get_optimization_plan' ] );
		add_action( 'wp_ajax_autoplugin_apply_optimization', [ $this, 'handle_apply_optimization' ] );
		add_action( 'wp_ajax_autoplugin_get_plugin_content', [ $this, 'handle_get_plugin_content' ] );
		add_action( 'wp_ajax_autoplugin_revert_plugin', [ $this, 'handle_revert_plugin' ] );
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
		$plan = isset( $_POST['plugin_description'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_description'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$generator = new Plugin_Generator( $this->ai_api );
		$plan_data = $generator->generate_plugin_plan( $plan );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Strip out any code block fences like ```json ... ```.
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
		$description = isset( $_POST['plugin_plan'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$generator = new Plugin_Generator( $this->ai_api );
		$code      = $generator->generate_plugin_code( $description );
		if ( is_wp_error( $code ) ) {
			wp_send_json_error( $code->get_error_message() );
		}

		// Strip out code fences like ```php ... ```.
		$code = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $code );

		wp_send_json_success( $code );
	}

	/**
	 * AJAX handler for creating a plugin (i.e., writing the plugin files).
	 *
	 * @return void
	 */
	public function ajax_create_plugin() {
		$code        = isset( $_POST['plugin_code'] ) ? wp_unslash( $_POST['plugin_code'] ) : ''; // phpcs:ignore -- This cannot be sanitized, as it's the plugin code. Nonce verification is done in the parent method.
		$plugin_name = isset( $_POST['plugin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.

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
		$plugin_file = isset( $_POST['plugin_file'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$problem            = isset( $_POST['plugin_issue'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';
		$check_other_issues = isset( $_POST['check_other_issues'] ) ? (bool) $_POST['check_other_issues'] : true; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.

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
		$plugin_file = isset( $_POST['plugin_file'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$problem        = isset( $_POST['plugin_issue'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';
		$ai_description = isset( $_POST['plugin_plan'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$fixer = new Plugin_Fixer( $this->ai_api );
		$code  = $fixer->fix_plugin( $plugin_code, $problem, $ai_description );

		// Strip out code fences like ```php ... ```.
		$code = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $code );

		wp_send_json_success( $code );
	}

	/**
	 * AJAX handler for installing the fixed plugin.
	 *
	 * @return void
	 */
	public function ajax_fix_plugin() {
		$code        = isset( $_POST['plugin_code'] ) ? wp_unslash( $_POST['plugin_code'] ) : ''; // phpcs:ignore -- This cannot be sanitized, as it's the plugin code. Nonce verification is done in the parent method.
		$plugin_file = isset( $_POST['plugin_file'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
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
		$plugin_file = isset( $_POST['plugin_file'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$problem = isset( $_POST['plugin_issue'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
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
		$plugin_file = isset( $_POST['plugin_file'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$problem        = isset( $_POST['plugin_issue'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';
		$ai_description = isset( $_POST['plugin_plan'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
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
		$code        = isset( $_POST['plugin_code'] ) ? wp_unslash( $_POST['plugin_code'] ) : ''; // phpcs:ignore -- This cannot be sanitized, as it's the plugin code. Nonce verification is done in the parent method.
		$plugin_file = isset( $_POST['plugin_file'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
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
		$plugin_file = isset( $_POST['plugin_file'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
		$plugin_code = file_get_contents( $plugin_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.
		if ( false === $plugin_code ) {
			wp_send_json_error( esc_html__( 'Failed to read the plugin file.', 'wp-autoplugin' ) );
		}

		$question = isset( $_POST['plugin_question'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_question'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$focus = isset( $_POST['explain_focus'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['explain_focus'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
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

		$model = isset( $_POST['model'] ) && is_array( $_POST['model'] ) ? wp_unslash( $_POST['model'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization is done later, see below.
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

		if ( ! isset( $_POST['plugin_file'] ) || ! isset( $_POST['plugin_issue'] ) ) {
			wp_send_json_error( esc_html__( 'Missing required parameters.', 'wp-autoplugin' ) );
		}

		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) );
		$hooks       = \WP_Autoplugin\Hooks_Extender::get_plugin_hooks( $plugin_file );
		if ( empty( $hooks ) ) {
			wp_send_json_error( esc_html__( 'No hooks found in the plugin.', 'wp-autoplugin' ) );
		}

		// Get original plugin name from the plugin file.
		$plugin_data          = get_plugin_data( WP_CONTENT_DIR . '/plugins/' . $plugin_file );
		$original_plugin_name = $plugin_data['Name'];

		$plugin_changes = sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) );
		$extender       = new \WP_Autoplugin\Hooks_Extender( $this->ai_api );
		$plan_data      = $extender->plan_plugin_hooks_extension( $original_plugin_name, $hooks, $plugin_changes );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Strip out any code block fences like ```json ... ```.
		$plan_data  = preg_replace( '/^```(json)\n(.*)\n```$/s', '$2', $plan_data );
		$plan_array = json_decode( $plan_data, true );
		if ( ! $plan_array ) {
			wp_send_json_error( esc_html__( 'Failed to decode the generated plan.', 'wp-autoplugin' ) );
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

		if ( ! isset( $_POST['plugin_file'] ) || ! isset( $_POST['plugin_plan'] ) || ! isset( $_POST['hooks'] ) ) {
			wp_send_json_error( esc_html__( 'Missing required parameters.', 'wp-autoplugin' ) );
		}

		$plugin_file = sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) );
		$ai_plan     = sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) );
		$hooks       = \WP_Autoplugin\Hooks_Extender::get_plugin_hooks( $plugin_file );
		$hooks_param = json_decode( sanitize_text_field( wp_unslash( $_POST['hooks'] ) ), true );

		// The $hooks_param is an array of hook names. Keep only the hooks that are in the plan.
		$hooks = array_filter(
			$hooks,
			function ( $hook ) use ( $hooks_param ) {
				return in_array( $hook['name'], $hooks_param, true );
			}
		);

		$plugin_name = isset( $_POST['plugin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_name'] ) ) : '';

		// Get original plugin name from the plugin file.
		$plugin_data          = get_plugin_data( WP_CONTENT_DIR . '/plugins/' . $plugin_file );
		$original_plugin_name = $plugin_data['Name'];

		$extender = new \WP_Autoplugin\Hooks_Extender( $this->ai_api );
		$code     = $extender->generate_hooks_extension_code( $original_plugin_name, $hooks, $ai_plan, $plugin_name );

		if ( is_wp_error( $code ) ) {
			wp_send_json_error( $code->get_error_message() );
		}

		// Strip out code fences like ```php ... ```.
		$code = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $code );

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
		wp_send_json_success( $hooks ); // Returns empty array if no hooks found.
	}

	/**
	 * AJAX handler for changing the current model.
	 *
	 * @return void
	 */
	public function ajax_change_model() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) ] );
		}

		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-autoplugin' ) ] );
		}

		$model = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		if ( empty( $model ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No model specified.', 'wp-autoplugin' ) ] );
		}

		// Validate the model exists in one of the providers or custom models.
		$valid_model = false;

		// Check built-in models.
		foreach ( \WP_Autoplugin\Admin::$models as $provider => $models ) {
			if ( array_key_exists( $model, $models ) ) {
				$valid_model = true;
				break;
			}
		}

		// Check custom models.
		if ( ! $valid_model ) {
			$custom_models = get_option( 'wp_autoplugin_custom_models', [] );
			foreach ( $custom_models as $custom_model ) {
				if ( $custom_model['name'] === $model ) {
					$valid_model = true;
					break;
				}
			}
		}

		if ( ! $valid_model ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid model specified.', 'wp-autoplugin' ) ] );
		}

		// Update the model setting.
		update_option( 'wp_autoplugin_model', $model );

		wp_send_json_success( [ 'message' => esc_html__( 'Model changed successfully.', 'wp-autoplugin' ) ] );
	}

	/**
	 * AJAX handler for getting a plugin optimization plan.
	 *
	 * @return void
	 */
	public function handle_get_optimization_plan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-autoplugin' ) ], 403 );
		}
		check_ajax_referer( 'wp_autoplugin_optimizer_nonce', 'security' );

		$plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) : '';
		$plugin_code = isset( $_POST['plugin_code'] ) ? wp_unslash( $_POST['plugin_code'] ) : ''; // Code itself should not be sanitized beyond unslashing.

		if ( empty( $plugin_file ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Plugin file not specified.', 'wp-autoplugin' ) ] );
		}
		if ( empty( $plugin_code ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Plugin code not provided.', 'wp-autoplugin' ) ] );
		}

		// Basic validation for plugin_file path
		if ( strpos( $plugin_file, '..' ) !== false || ! preg_match( '/^.+\.php$/', $plugin_file ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid plugin file path.', 'wp-autoplugin' ) ] );
		}

		$optimizer = new Plugin_Optimizer( $this->ai_api );
		$ai_plan   = $optimizer->plan_plugin_optimization( $plugin_code );

		if ( is_wp_error( $ai_plan ) ) {
			wp_send_json_error( [ 'message' => $ai_plan->get_error_message() ] );
		}

		wp_send_json_success( [ 'plan' => $ai_plan ] );
	}

	/**
	 * AJAX handler for applying a plugin optimization.
	 *
	 * @return void
	 */
	public function handle_apply_optimization() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-autoplugin' ) ], 403 );
		}
		check_ajax_referer( 'wp_autoplugin_optimizer_nonce', 'security' );

		$plugin_file   = isset( $_POST['plugin_file'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) : '';
		$original_code = isset( $_POST['plugin_code'] ) ? wp_unslash( $_POST['plugin_code'] ) : ''; // Original code
		$ai_plan       = isset( $_POST['ai_plan'] ) ? wp_unslash( $_POST['ai_plan'] ) : ''; // AI plan, potentially multi-line

		$backup_dir_path = WP_CONTENT_DIR . '/wp-autoplugin-backups/plugin-optimizer/';

		if ( empty( $plugin_file ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Plugin file not specified.', 'wp-autoplugin' ) ] );
		}
		if ( empty( $original_code ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Original plugin code not provided.', 'wp-autoplugin' ) ] );
		}
		if ( empty( $ai_plan ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'AI optimization plan not provided.', 'wp-autoplugin' ) ] );
		}

		// Basic validation for plugin_file path
		if ( strpos( $plugin_file, '..' ) !== false || ! preg_match( '/^.+\.php$/', $plugin_file ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid plugin file path.', 'wp-autoplugin' ) ] );
		}

		$optimizer     = new Plugin_Optimizer( $this->ai_api );
		$optimized_code = $optimizer->optimize_plugin( $original_code, $ai_plan );

		if ( is_wp_error( $optimized_code ) ) {
			wp_send_json_error( [ 'message' => $optimized_code->get_error_message() ] );
		}

		if ( empty( $optimized_code ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'AI returned empty optimized code.', 'wp-autoplugin' ) ] );
		}

		// Strip out code fences like ```php ... ``` just in case.
		$new_code = preg_replace( '/^```(php)?\n?(.*)\n?```$/s', '$2', $optimized_code );
		if ( empty( trim( $new_code ) ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'AI returned empty optimized code after stripping fences.', 'wp-autoplugin' ) ] );
		}

		// Ensure backup directory exists and is protected.
		if ( ! is_dir( $backup_dir_path ) ) {
			if ( ! wp_mkdir_p( $backup_dir_path ) ) {
				wp_send_json_error( [ 'message' => esc_html__( 'Backup directory could not be created.', 'wp-autoplugin' ) . ' ' . $backup_dir_path ] );
			}
		}
		if ( ! is_writable( $backup_dir_path ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Backup directory is not writable.', 'wp-autoplugin' ) . ' ' . $backup_dir_path ] );
		}
		if ( ! file_exists( $backup_dir_path . 'index.php' ) ) {
			// @codingStandardsIgnoreStart
			file_put_contents( $backup_dir_path . 'index.php', '<?php // Silence is golden.' );
			// @codingStandardsIgnoreEnd
		}

		// Backup original plugin code.
		$plugin_slug     = strpos( $plugin_file, '/' ) === false ? basename( $plugin_file, '.php' ) : dirname( $plugin_file );
		$plugin_slug     = sanitize_file_name( $plugin_slug ); // Sanitize slug
		$timestamp       = time();
		$backup_filename = $plugin_slug . '-' . $timestamp . '.php.bak';
		$backup_filepath = $backup_dir_path . $backup_filename;

		// @codingStandardsIgnoreStart
		$backup_write_result = file_put_contents( $backup_filepath, $original_code );
		// @codingStandardsIgnoreEnd

		if ( false === $backup_write_result ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to create plugin backup. Optimization aborted.', 'wp-autoplugin' ) ] );
		}

		// Store backup information.
		$optimizer_backups = get_option( 'wp_autoplugin_optimizer_backups', [] );
		if ( ! is_array( $optimizer_backups ) ) { // Ensure it's an array
			$optimizer_backups = [];
		}
		$optimizer_backups[ $plugin_file ] = [
			'plugin_file' => $plugin_file,
			'backup_path' => $backup_filepath,
			'timestamp'   => $timestamp,
			'plugin_slug' => $plugin_slug,
		];
		update_option( 'wp_autoplugin_optimizer_backups', $optimizer_backups );

		// Proceed with overwriting original plugin.
		$full_plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

		if ( ! is_writable( dirname( $full_plugin_path ) ) || ( file_exists( $full_plugin_path ) && ! is_writable( $full_plugin_path ) ) ) {
			// Attempt to roll back backup information storage if original file is not writable
			// This is a best-effort, as the backup file itself is already written.
			unset( $optimizer_backups[ $plugin_file ] );
			update_option( 'wp_autoplugin_optimizer_backups', $optimizer_backups );
			wp_send_json_error( [ 'message' => esc_html__( 'Plugin directory or file is not writable. Backup created, but optimization aborted.', 'wp-autoplugin' ) . ' ' . $full_plugin_path ] );
		}

		// @codingStandardsIgnoreStart
		$write_result = file_put_contents( $full_plugin_path, $new_code );
		// @codingStandardsIgnoreEnd

		if ( false === $write_result ) {
			// Attempt to roll back backup information storage if write fails
			unset( $optimizer_backups[ $plugin_file ] );
			update_option( 'wp_autoplugin_optimizer_backups', $optimizer_backups );
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to write optimized code to plugin file. Backup of original created.', 'wp-autoplugin' ) ] );
		}

		// Clear opcache for the specific file if opcache is enabled.
		if ( function_exists( 'opcache_invalidate' ) && ini_get( 'opcache.enable' ) ) {
			opcache_invalidate( $full_plugin_path, true );
		}
		// Also try to clear APCu cache if available.
		if ( function_exists( 'apcu_clear_cache' ) ) {
			apcu_clear_cache();
		}

		wp_send_json_success( [ 'message' => esc_html__( 'Plugin optimized and updated successfully. A backup of the original version has been created.', 'wp-autoplugin' ) ] );
	}

	/**
	 * AJAX handler for getting plugin content.
	 *
	 * @return void
	 */
	public function handle_get_plugin_content() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-autoplugin' ) ], 403 );
		}
		// Using the same nonce as other optimizer actions for simplicity, can be a dedicated one if needed.
		check_ajax_referer( 'wp_autoplugin_optimizer_nonce', 'security' );

		$plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) : '';

		if ( empty( $plugin_file ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Plugin file not specified.', 'wp-autoplugin' ) ] );
		}

		// Basic validation for plugin_file path to prevent directory traversal.
		if ( strpos( $plugin_file, '..' ) !== false || ! preg_match( '/^.+\.php$/', $plugin_file ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid plugin file path.', 'wp-autoplugin' ) ] );
		}

		$full_plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

		if ( ! file_exists( $full_plugin_path ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Plugin file not found.', 'wp-autoplugin' ) . ' ' . $full_plugin_path ] );
		}

		if ( ! is_readable( $full_plugin_path ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Plugin file is not readable.', 'wp-autoplugin' ) ] );
		}

		// @codingStandardsIgnoreStart
		$file_content = file_get_contents( $full_plugin_path );
		// @codingStandardsIgnoreEnd

		if ( false === $file_content ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to read plugin file content.', 'wp-autoplugin' ) ] );
		}

		wp_send_json_success( [ 'content' => $file_content ] );
	}

	/**
	 * AJAX handler for reverting a plugin to its backup.
	 *
	 * @return void
	 */
	public function handle_revert_plugin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-autoplugin' ) ], 403 );
		}
		// Using 'security' as the key for nonce, assuming JS sends it as `_ajax_nonce` which check_ajax_referer expects.
		// Or, if JS sends it as 'security', then it's fine. The problem description used '_ajax_nonce' for JS.
		check_ajax_referer( 'wp_autoplugin_optimizer_nonce', 'security' );


		$plugin_file_to_revert = isset( $_POST['plugin_file'] ) ? sanitize_text_field( stripslashes( $_POST['plugin_file'] ) ) : '';

		if ( empty( $plugin_file_to_revert ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Plugin file not specified.', 'wp-autoplugin' ) ] );
		}

		$optimizer_backups = get_option( 'wp_autoplugin_optimizer_backups', [] );
		if ( ! is_array( $optimizer_backups ) ) {
			$optimizer_backups = []; // Ensure it's an array
		}

		if ( ! isset( $optimizer_backups[ $plugin_file_to_revert ] ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No backup found for this plugin.', 'wp-autoplugin' ) ] );
		}

		$backup_info = $optimizer_backups[ $plugin_file_to_revert ];
		$backup_filepath = $backup_info['backup_path'];
		// Ensure the backup path is within the defined backup directory for security
		$backup_dir_path = WP_CONTENT_DIR . '/wp-autoplugin-backups/plugin-optimizer/';
		if ( strpos( realpath( $backup_filepath ), realpath( $backup_dir_path ) ) !== 0 ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid backup file path detected.', 'wp-autoplugin' ) ] );
		}


		$original_plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file_to_revert;

		if ( ! is_readable( $backup_filepath ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Backup file not found or is not readable.', 'wp-autoplugin' ) . ' Path: ' . $backup_filepath ] );
		}

		// @codingStandardsIgnoreStart
		$backup_content = file_get_contents( $backup_filepath );
		// @codingStandardsIgnoreEnd
		if ( false === $backup_content ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to read backup file content.', 'wp-autoplugin' ) ] );
		}

		// Check if original plugin directory is writable, then if file is writable if it exists.
		if ( ! is_writable( dirname( $original_plugin_path ) ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Original plugin directory is not writable. Cannot revert.', 'wp-autoplugin' ) ] );
		}
		if ( file_exists( $original_plugin_path ) && ! is_writable( $original_plugin_path ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Original plugin file is not writable. Cannot revert.', 'wp-autoplugin' ) ] );
		}

		// @codingStandardsIgnoreStart
		$write_success = file_put_contents( $original_plugin_path, $backup_content );
		// @codingStandardsIgnoreEnd

		if ( false === $write_success ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to write reverted code to plugin file.', 'wp-autoplugin' ) ] );
		}

		// Cleanup: Delete the backup file and remove from options.
		// @codingStandardsIgnoreStart
		if ( ! unlink( $backup_filepath ) ) {
			// Log this or notify admin, but proceed with removing from options as the main file is restored.
			error_log( 'WP Autoplugin: Could not delete backup file: ' . $backup_filepath );
		}
		// @codingStandardsIgnoreEnd

		unset( $optimizer_backups[ $plugin_file_to_revert ] );
		update_option( 'wp_autoplugin_optimizer_backups', $optimizer_backups );

		// Clear caches.
		if ( function_exists( 'opcache_invalidate' ) && ini_get( 'opcache.enable' ) ) {
			opcache_invalidate( $original_plugin_path, true );
		}
		if ( function_exists( 'apcu_clear_cache' ) ) {
			apcu_clear_cache();
		}

		wp_send_json_success( [ 'message' => esc_html__( 'Plugin reverted successfully to the backed-up version.', 'wp-autoplugin' ) ] );
	}
}
