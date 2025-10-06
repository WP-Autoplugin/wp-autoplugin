<?php
/**
 * WP-Autoplugin AJAX Theme Extender class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles AJAX requests for extending theme hooks.
 */
class Theme_Extender {
	/**
	 * The Admin object for accessing specialized model APIs.
	 *
	 * @var \WP_Autoplugin\Admin\Admin
	 */
	private $admin;

	/**
	 * Constructor.
	 *
	 * @param \WP_Autoplugin\Admin\Admin $admin The admin instance.
	 */
	public function __construct( $admin ) {
		$this->admin = $admin;
	}

	/**
	 * AJAX handler for extracting theme hooks.
	 *
	 * @return void
	 */
	public function extract_theme_hooks() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		$theme_slug = isset( $_POST['theme_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_slug'] ) ) : '';
		if ( empty( $theme_slug ) ) {
			wp_send_json_error( esc_html__( 'No theme slug specified.', 'wp-autoplugin' ) );
		}

		$hooks = \WP_Autoplugin\Hooks_Extender::get_theme_hooks( $theme_slug );
		wp_send_json_success( $hooks ); // Returns empty array if no hooks found.
	}

	/**
	 * AJAX handler for generating a plan to extend theme hooks.
	 *
	 * @return void
	 */
	public function generate_extend_theme_plan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		if ( ! isset( $_POST['theme_slug'] ) || ! isset( $_POST['theme_issue'] ) ) {
			wp_send_json_error( esc_html__( 'Missing required parameters.', 'wp-autoplugin' ) );
		}

		$theme_slug = sanitize_text_field( wp_unslash( $_POST['theme_slug'] ) );
		$hooks      = \WP_Autoplugin\Hooks_Extender::get_theme_hooks( $theme_slug );
		if ( empty( $hooks ) ) {
			wp_send_json_error( esc_html__( 'No hooks found in the theme.', 'wp-autoplugin' ) );
		}

		// Get original theme name.
		$theme_data          = wp_get_theme( $theme_slug );
		$original_theme_name = $theme_data->get( 'Name' );

		$theme_changes = sanitize_text_field( wp_unslash( $_POST['theme_issue'] ) );
		$planner_api   = $this->admin->api_handler->get_planner_api();
		$extender      = new \WP_Autoplugin\Hooks_Extender( $planner_api );
		$plan_data     = $extender->plan_theme_hooks_extension( $original_theme_name, $hooks, $theme_changes );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Strip out any code block fences like ```json ... ```.
		$plan_data  = \WP_Autoplugin\AI_Utils::strip_code_fences( $plan_data, 'json' );
		$plan_array = json_decode( $plan_data, true );
		if ( ! $plan_array ) {
			wp_send_json_error( esc_html__( 'Failed to decode the generated plan.', 'wp-autoplugin' ) );
		}

		// Get token usage from the actual API that was used.
		$token_usage = $planner_api->get_last_token_usage();

		wp_send_json_success(
			[
				'plan'        => $plan_array,
				'token_usage' => $token_usage,
			]
		);
	}

	/**
	 * AJAX handler for generating code to extend theme hooks.
	 *
	 * @return void
	 */
	public function generate_extend_theme_code() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		if ( ! isset( $_POST['theme_slug'] ) || ! isset( $_POST['theme_plan'] ) || ! isset( $_POST['hooks'] ) ) {
			wp_send_json_error( esc_html__( 'Missing required parameters.', 'wp-autoplugin' ) );
		}

		$theme_slug  = sanitize_text_field( wp_unslash( $_POST['theme_slug'] ) );
		$ai_plan     = sanitize_text_field( wp_unslash( $_POST['theme_plan'] ) );
		$hooks       = \WP_Autoplugin\Hooks_Extender::get_theme_hooks( $theme_slug );
		$hooks_param = json_decode( sanitize_text_field( wp_unslash( $_POST['hooks'] ) ), true );

		// The $hooks_param is an array of hook names. Keep only the hooks that are in the plan.
		$hooks = array_filter(
			$hooks,
			function ( $hook ) use ( $hooks_param ) {
				return in_array( $hook['name'], $hooks_param, true );
			}
		);

		$plugin_name = isset( $_POST['plugin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_name'] ) ) : '';

		// Get original theme name.
		$theme_data          = wp_get_theme( $theme_slug );
		$original_theme_name = $theme_data->get( 'Name' );

		$coder_api = $this->admin->api_handler->get_coder_api();
		$extender  = new \WP_Autoplugin\Hooks_Extender( $coder_api );
		$code      = $extender->generate_theme_extension_code( $original_theme_name, $hooks, $ai_plan, $plugin_name );

		if ( is_wp_error( $code ) ) {
			wp_send_json_error( $code->get_error_message() );
		}

		// Strip out code fences like ```php ... ```.
		$code = \WP_Autoplugin\AI_Utils::strip_code_fences( $code, 'php' );

		// Get token usage from the actual API that was used.
		$token_usage = $coder_api->get_last_token_usage();

		wp_send_json_success(
			[
				'code'        => $code,
				'token_usage' => $token_usage,
			]
		);
	}

	/**
	 * AJAX handler for generating a single file for theme-hooks-based extension (complex flow).
	 *
	 * Expected POST:
	 * - theme_slug (string)
	 * - file_index (int)
	 * - theme_plan (JSON string)
	 * - project_structure (JSON string)
	 * - generated_files (JSON string)
	 * - hooks (JSON array of hook names)
	 */
	public function generate_extend_theme_file() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		$theme_slug        = isset( $_POST['theme_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_slug'] ) ) : '';
		$file_index        = isset( $_POST['file_index'] ) ? intval( wp_unslash( $_POST['file_index'] ) ) : 0;
		$theme_plan        = isset( $_POST['theme_plan'] ) ? wp_unslash( $_POST['theme_plan'] ) : '';
		$project_structure = isset( $_POST['project_structure'] ) ? wp_unslash( $_POST['project_structure'] ) : '';
		$generated_files   = isset( $_POST['generated_files'] ) ? wp_unslash( $_POST['generated_files'] ) : '';
		$hooks_param       = isset( $_POST['hooks'] ) ? wp_unslash( $_POST['hooks'] ) : '[]';

		$theme_plan_array        = json_decode( $theme_plan, true );
		$project_structure_array = json_decode( $project_structure, true );
		$generated_files_array   = json_decode( $generated_files, true );
		$hook_names              = json_decode( $hooks_param, true );

		if ( ! $project_structure_array || ! isset( $project_structure_array['files'] ) || ! $theme_plan_array ) {
			wp_send_json_error( esc_html__( 'Invalid input data.', 'wp-autoplugin' ) );
		}

		$files = $project_structure_array['files'];
		if ( ! isset( $files[ $file_index ] ) ) {
			wp_send_json_error( esc_html__( 'File index out of range.', 'wp-autoplugin' ) );
		}

		// Load hooks for this theme and filter to selected ones.
		$all_hooks      = \WP_Autoplugin\Hooks_Extender::get_theme_hooks( $theme_slug );
		$selected_hooks = [];
		if ( is_array( $hook_names ) && ! empty( $hook_names ) ) {
			$selected_hooks = array_values(
				array_filter(
					$all_hooks,
					function ( $hook ) use ( $hook_names ) {
						return in_array( $hook['name'], $hook_names, true );
					}
				)
			);
		} else {
			$selected_hooks = $all_hooks;
		}

		// Original theme name.
		$theme_data          = wp_get_theme( $theme_slug );
		$original_theme_name = $theme_data->get( 'Name' );

		$coder_api    = $this->admin->api_handler->get_coder_api();
		$extender     = new \WP_Autoplugin\Hooks_Extender( $coder_api );
		$file_info    = $files[ $file_index ];
		$file_content = $extender->generate_theme_extension_file( $original_theme_name, $selected_hooks, $file_info, $project_structure_array, $theme_plan_array, is_array( $generated_files_array ) ? $generated_files_array : [] );

		if ( is_wp_error( $file_content ) ) {
			wp_send_json_error( $file_content->get_error_message() );
		}

		$file_type    = isset( $file_info['type'] ) ? $file_info['type'] : 'php';
		$file_content = \WP_Autoplugin\AI_Utils::strip_code_fences( $file_content );

		$token_usage = $coder_api->get_last_token_usage();

		wp_send_json_success(
			[
				'file_path'    => $file_info['path'],
				'file_content' => $file_content,
				'file_type'    => $file_type,
				'token_usage'  => $token_usage,
			]
		);
	}
}
