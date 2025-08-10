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
use WP_Autoplugin\Plugin_Explainer;

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
	 * The Admin object for accessing specialized model APIs.
	 *
	 * @var \WP_Autoplugin\Admin
	 */
	private $admin;

	/**
	 * Constructor sets the Admin instance and hooks into AJAX actions.
	 *
	 * @param \WP_Autoplugin\Admin $admin The Admin instance.
	 */
	public function __construct( $admin ) {
		$this->admin  = $admin;
		$this->ai_api = $admin->ai_api;

		// Register all needed AJAX actions.
		$actions = [
			'generate_plan',
			'generate_code',
			'generate_file',
			'review_code',
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
			'extract_theme_hooks',
			'generate_extend_theme_plan',
			'generate_extend_theme_code',
		];
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_wp_autoplugin_' . $action, [ $this, 'ajax_actions' ] );
		}

		// Add or remove custom model actions.
		add_action( 'wp_ajax_wp_autoplugin_add_model', [ $this, 'ajax_add_model' ] );
		add_action( 'wp_ajax_wp_autoplugin_remove_model', [ $this, 'ajax_remove_model' ] );
		add_action( 'wp_ajax_wp_autoplugin_change_model', [ $this, 'ajax_change_model' ] );
		add_action( 'wp_ajax_wp_autoplugin_change_models', [ $this, 'ajax_change_models' ] );
	}

	/**
	 * Build full codebase for a plugin (supports complex multi-file plugins).
	 *
	 * @param string $plugin_file Plugin main file path relative to plugins dir (e.g., slug/slug.php).
	 * @return array { is_complex: bool, files: [path=>contents], main_file: string }
	 */
	private function collect_plugin_codebase( $plugin_file ) {
		$plugin_file = str_replace( '../', '', $plugin_file );
		$plugin_root = dirname( $plugin_file );
		$abs_root    = WP_CONTENT_DIR . '/plugins/' . $plugin_root;
		$files_map   = [];

		// Only include php, css, js files
		$allowed_ext = [ 'php', 'css', 'js' ];

		if ( is_dir( $abs_root ) ) {
			// Exclude common non-source directories.
			$exclude_dirs = [ 'vendor', 'node_modules', '.git', 'tests', 'docs', 'build', 'dist' ];

			$dirIterator = new \RecursiveDirectoryIterator( $abs_root, \FilesystemIterator::SKIP_DOTS );
			$filter      = new \RecursiveCallbackFilterIterator(
				$dirIterator,
				function ( $current ) use ( $exclude_dirs ) {
					/** @var \SplFileInfo $current */
					if ( $current->isDir() ) {
						return ! in_array( $current->getFilename(), $exclude_dirs, true );
					}
					return true;
				}
			);
			$iterator = new \RecursiveIteratorIterator( $filter, \RecursiveIteratorIterator::LEAVES_ONLY );

			foreach ( $iterator as $file ) {
				/** @var \SplFileInfo $file */
				if ( ! $file->isFile() ) {
					continue;
				}
				$ext = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, $allowed_ext, true ) ) {
					continue;
				}
				$rel = ltrim( str_replace( $abs_root, '', $file->getPathname() ), '/' );
				$rel = $plugin_root . '/' . $rel;
				$contents = file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( false !== $contents ) {
					$files_map[ $rel ] = $contents;
				}
			}
		}

		// Fallback: if not a directory plugin, just read the single file
		if ( empty( $files_map ) ) {
			$single = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
			$contents = file_get_contents( $single ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $contents ) {
				$files_map[ $plugin_file ] = $contents;
			}
		}

		$is_complex = count( $files_map ) > 1;
		return [
			'is_complex' => $is_complex,
			'files'      => $files_map,
			'main_file'  => $plugin_file,
		];
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

		$planner_api = $this->admin->get_planner_api();
		$generator = new Plugin_Generator( $planner_api );
		$plan_data = $generator->generate_plugin_plan( $plan );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Strip out any code block fences like ```json ... ```.
		$plan_data  = preg_replace( '/^```(json)?\n(.*)\n```$/s', '$2', $plan_data );
		$plan_array = json_decode( $plan_data, true );
		if ( ! $plan_array ) {
			wp_send_json_error( esc_html__( 'Failed to decode the generated plan: ', 'wp-autoplugin' ) . $plan_data );
		}

		// Get token usage from the actual API that was used
		$token_usage = $planner_api->get_last_token_usage();

		wp_send_json_success( [
			'plan' => $plan_array,
			'token_usage' => $token_usage,
		] );
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

		$coder_api = $this->admin->get_coder_api();
		$generator = new Plugin_Generator( $coder_api );
		$code      = $generator->generate_plugin_code( $description );
		if ( is_wp_error( $code ) ) {
			wp_send_json_error( $code->get_error_message() );
		}

		// Strip out code fences like ```php ... ```.
		$code = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $code );

		// Get token usage from the actual API that was used
		$token_usage = $coder_api->get_last_token_usage();

		wp_send_json_success( [
			'code' => $code,
			'token_usage' => $token_usage,
		] );
	}

	/**
	 * AJAX handler for generating a single file for complex plugins.
	 *
	 * @return void
	 */
	public function ajax_generate_file() {
		$file_index = isset( $_POST['file_index'] ) ? intval( wp_unslash( $_POST['file_index'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
		$plugin_plan = isset( $_POST['plugin_plan'] ) ? wp_unslash( $_POST['plugin_plan'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cannot sanitize JSON data. Nonce verification is done in the parent method.
		$project_structure = isset( $_POST['project_structure'] ) ? wp_unslash( $_POST['project_structure'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cannot sanitize JSON data. Nonce verification is done in the parent method.
		$generated_files = isset( $_POST['generated_files'] ) ? wp_unslash( $_POST['generated_files'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cannot sanitize JSON data. Nonce verification is done in the parent method.

		// Decode JSON data
		$plugin_plan_array = json_decode( $plugin_plan, true );
		$project_structure_array = json_decode( $project_structure, true );
		$generated_files_array = json_decode( $generated_files, true );

		if ( ! $plugin_plan_array || ! $project_structure_array || ! isset( $project_structure_array['files'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid input data.', 'wp-autoplugin' ) );
		}

		$files = $project_structure_array['files'];
		if ( ! isset( $files[ $file_index ] ) ) {
			wp_send_json_error( esc_html__( 'File index out of range.', 'wp-autoplugin' ) );
		}

		$file_info = $files[ $file_index ];
		$coder_api = $this->admin->get_coder_api();
		$generator = new Plugin_Generator( $coder_api );
		$file_content = $generator->generate_plugin_file( $file_info, wp_json_encode( $plugin_plan_array ), $project_structure_array, $generated_files_array );

		if ( is_wp_error( $file_content ) ) {
			wp_send_json_error( $file_content->get_error_message() );
		}

		// Strip out code fences based on file type
		$file_type = $file_info['type'];
		if ( 'php' === $file_type ) {
			$file_content = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $file_content );
		} elseif ( 'css' === $file_type ) {
			$file_content = preg_replace( '/^```(css)\n(.*)\n```$/s', '$2', $file_content );
		} elseif ( 'js' === $file_type ) {
			$file_content = preg_replace( '/^```(js|javascript)\n(.*)\n```$/s', '$2', $file_content );
		}

		// Get token usage from the actual API that was used
		$token_usage = $coder_api->get_last_token_usage();

		wp_send_json_success( [
			'file_path' => $file_info['path'],
			'file_content' => $file_content,
			'file_type' => $file_type,
			'token_usage' => $token_usage,
		] );
	}

	/**
	 * AJAX handler for creating a plugin (i.e., writing the plugin files).
	 *
	 * @return void
	 */
	public function ajax_create_plugin() {
		$plugin_mode = get_option( 'wp_autoplugin_plugin_mode', 'simple' );
		
		if ( 'complex' === $plugin_mode ) {
			$this->ajax_create_complex_plugin();
		} else {
			$this->ajax_create_simple_plugin();
		}
	}

	/**
	 * AJAX handler for creating a simple single-file plugin.
	 *
	 * @return void
	 */
	private function ajax_create_simple_plugin() {
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
	 * AJAX handler for creating a complex multi-file plugin.
	 *
	 * @return void
	 */
	private function ajax_create_complex_plugin() {
		$plugin_name = isset( $_POST['plugin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
		$project_structure = isset( $_POST['project_structure'] ) ? wp_unslash( $_POST['project_structure'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cannot sanitize JSON data. Nonce verification is done in the parent method.
		$generated_files = isset( $_POST['generated_files'] ) ? wp_unslash( $_POST['generated_files'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cannot sanitize JSON data. Nonce verification is done in the parent method.

		// Decode JSON data
		$project_structure_array = json_decode( $project_structure, true );
		$generated_files_array = json_decode( $generated_files, true );

		if ( ! $project_structure_array || ! $generated_files_array ) {
			wp_send_json_error( esc_html__( 'Invalid input data.', 'wp-autoplugin' ) );
		}

		$installer = Plugin_Installer::get_instance();
		$result    = $installer->install_complex_plugin( $plugin_name, $project_structure_array, $generated_files_array );
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

		$codebase = $this->collect_plugin_codebase( $plugin_file );

		$problem            = isset( $_POST['plugin_issue'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';
		$check_other_issues = isset( $_POST['check_other_issues'] ) ? (bool) $_POST['check_other_issues'] : true; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.

		$reviewer_api = $this->admin->get_reviewer_api();
		$fixer       = new Plugin_Fixer( $reviewer_api );
		$plan_data   = $fixer->identify_issue( $codebase['files'], $problem, $check_other_issues );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Get token usage from the actual API that was used
		$token_usage = $reviewer_api->get_last_token_usage();

		wp_send_json_success( [
			'plan_data' => $plan_data,
			'token_usage' => $token_usage,
		] );
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

		$codebase = $this->collect_plugin_codebase( $plugin_file );

		$problem        = isset( $_POST['plugin_issue'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';
		$ai_description = isset( $_POST['plugin_plan'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$coder_api = $this->admin->get_coder_api();
		$fixer = new Plugin_Fixer( $coder_api );
		$code  = $fixer->fix_plugin( $codebase['files'], $problem, $ai_description, $codebase['is_complex'], $codebase['main_file'] );

		// For simple code responses, strip code fences. If JSON, keep as-is.
		$trimmed = is_string( $code ) ? ltrim( $code ) : '';
		if ( is_string( $code ) && $trimmed && $trimmed[0] !== '{' && $trimmed[0] !== '[' ) {
			$code = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $code );
		}

		// Get token usage from the actual API that was used
		$token_usage = $coder_api->get_last_token_usage();

		wp_send_json_success( [
			'code' => $code,
			'token_usage' => $token_usage,
		] );
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

		$maybe_json = is_string( $code ) ? trim( $code ) : '';
		if ( is_string( $code ) && $maybe_json && ( $maybe_json[0] === '{' || $maybe_json[0] === '[' ) ) {
			$decoded = json_decode( $code, true );
			if ( is_array( $decoded ) && isset( $decoded['files'] ) && is_array( $decoded['files'] ) ) {
				$result = $installer->update_existing_plugin_files( $plugin_file, $decoded['files'] );
			} else {
				$result = new \WP_Error( 'invalid_json', 'Invalid JSON response for multi-file update.' );
			}
		} else {
			$result = $installer->install_plugin( $code, $plugin_file );
		}
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

		$codebase = $this->collect_plugin_codebase( $plugin_file );

		$problem = isset( $_POST['plugin_issue'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$planner_api = $this->admin->get_planner_api();
		$extender    = new \WP_Autoplugin\Plugin_Extender( $planner_api );
		$plan_data   = $extender->plan_plugin_extension( $codebase['files'], $problem );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Get token usage from the actual API that was used
		$token_usage = $planner_api->get_last_token_usage();

		wp_send_json_success( [
			'plan_data' => $plan_data,
			'token_usage' => $token_usage,
		] );
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

		$codebase = $this->collect_plugin_codebase( $plugin_file );

		$problem        = isset( $_POST['plugin_issue'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';
		$ai_description = isset( $_POST['plugin_plan'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_plan'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$coder_api = $this->admin->get_coder_api();
		$extender  = new \WP_Autoplugin\Plugin_Extender( $coder_api );
		$code      = $extender->extend_plugin( $codebase['files'], $problem, $ai_description, $codebase['is_complex'] );

		// Get token usage from the actual API that was used
		$token_usage = $coder_api->get_last_token_usage();

		wp_send_json_success( [
			'code' => $code,
			'token_usage' => $token_usage,
		] );
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

		$maybe_json = is_string( $code ) ? trim( $code ) : '';
		if ( is_string( $code ) && $maybe_json && ( $maybe_json[0] === '{' || $maybe_json[0] === '[' ) ) {
			$decoded = json_decode( $code, true );
			if ( is_array( $decoded ) && isset( $decoded['files'] ) && is_array( $decoded['files'] ) ) {
				$result = $installer->update_existing_plugin_files( $plugin_file, $decoded['files'] );
			} else {
				$result = new \WP_Error( 'invalid_json', 'Invalid JSON response for multi-file update.' );
			}
		} else {
			$result = $installer->install_plugin( $code, $plugin_file );
		}
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

		$codebase = $this->collect_plugin_codebase( $plugin_file );

		$question = isset( $_POST['plugin_question'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_question'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$focus = isset( $_POST['explain_focus'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['explain_focus'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: 'general';

		$reviewer_api = $this->admin->get_reviewer_api();
		$explainer    = new Plugin_Explainer( $reviewer_api );
		if ( ! empty( $question ) ) {
			$explanation = $explainer->answer_plugin_question( $codebase['files'], $question );
		} elseif ( $focus !== 'general' ) {
			$explanation = $explainer->analyze_plugin_aspect( $codebase['files'], $focus );
		} else {
			$explanation = $explainer->explain_plugin( $codebase['files'] );
		}

		if ( is_wp_error( $explanation ) ) {
			wp_send_json_error( $explanation->get_error_message() );
		}

		// Get token usage from the actual API that was used
		$token_usage = $reviewer_api->get_last_token_usage();

		wp_send_json_success( [
			'explanation' => $explanation,
			'token_usage' => $token_usage,
		] );
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
		$planner_api = $this->admin->get_planner_api();
		$extender       = new \WP_Autoplugin\Hooks_Extender( $planner_api );
		$plan_data      = $extender->plan_plugin_hooks_extension( $original_plugin_name, $hooks, $plugin_changes );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Strip out any code block fences like ```json ... ```.
		$plan_data  = preg_replace( '/^```(json)?\n(.*)\n```$/s', '$2', $plan_data );
		$plan_array = json_decode( $plan_data, true );
		if ( ! $plan_array ) {
			wp_send_json_error( esc_html__( 'Failed to decode the generated plan.', 'wp-autoplugin' ) );
		}

		// Get token usage from the actual API that was used
		$token_usage = $planner_api->get_last_token_usage();

		wp_send_json_success( [
			'plan' => $plan_array,
			'token_usage' => $token_usage,
		] );
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

		$coder_api = $this->admin->get_coder_api();
		$extender = new \WP_Autoplugin\Hooks_Extender( $coder_api );
		$code     = $extender->generate_hooks_extension_code( $original_plugin_name, $hooks, $ai_plan, $plugin_name );

		if ( is_wp_error( $code ) ) {
			wp_send_json_error( $code->get_error_message() );
		}

		// Strip out code fences like ```php ... ```.
		$code = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $code );

		// Get token usage from the actual API that was used
		$token_usage = $coder_api->get_last_token_usage();

		wp_send_json_success( [
			'code' => $code,
			'token_usage' => $token_usage,
		] );
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
	 * AJAX handler for updating a custom model.
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
	 * AJAX handler for changing models used in different contexts.
	 *
	 * @return void
	 */
	public function ajax_change_models() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) ] );
		}

		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-autoplugin' ) ] );
		}

		$default_model = isset( $_POST['default_model'] ) ? sanitize_text_field( wp_unslash( $_POST['default_model'] ) ) : '';
		$planner_model = isset( $_POST['planner_model'] ) ? sanitize_text_field( wp_unslash( $_POST['planner_model'] ) ) : '';
		$coder_model   = isset( $_POST['coder_model'] ) ? sanitize_text_field( wp_unslash( $_POST['coder_model'] ) ) : '';
		$reviewer_model = isset( $_POST['reviewer_model'] ) ? sanitize_text_field( wp_unslash( $_POST['reviewer_model'] ) ) : '';

		if ( empty( $default_model ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Default model is required.', 'wp-autoplugin' ) ] );
		}

		// Validate all non-empty models.
		$models_to_validate = array_filter( [ $default_model, $planner_model, $coder_model, $reviewer_model ] );
		foreach ( $models_to_validate as $model ) {
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
				wp_send_json_error( [ 'message' => sprintf( esc_html__( 'Invalid model specified: %s', 'wp-autoplugin' ), $model ) ] );
			}
		}

		// Update all model settings.
		update_option( 'wp_autoplugin_model', $default_model );
		update_option( 'wp_autoplugin_planner_model', $planner_model );
		update_option( 'wp_autoplugin_coder_model', $coder_model );
		update_option( 'wp_autoplugin_reviewer_model', $reviewer_model );

		wp_send_json_success( [ 'message' => esc_html__( 'Models updated successfully.', 'wp-autoplugin' ) ] );
	}

	/**
	 * AJAX handler for extracting theme hooks.
	 *
	 * @return void
	 */
	public function ajax_extract_theme_hooks() {
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
	public function ajax_generate_extend_theme_plan() {
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
		$theme_data           = wp_get_theme( $theme_slug );
		$original_theme_name  = $theme_data->get( 'Name' );

		$theme_changes = sanitize_text_field( wp_unslash( $_POST['theme_issue'] ) );
		$planner_api = $this->admin->get_planner_api();
		$extender      = new \WP_Autoplugin\Hooks_Extender( $planner_api );
		$plan_data     = $extender->plan_theme_hooks_extension( $original_theme_name, $hooks, $theme_changes );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Strip out any code block fences like ```json ... ```.
		$plan_data  = preg_replace( '/^```(json)?\n(.*)\n```$/s', '$2', $plan_data );
		$plan_array = json_decode( $plan_data, true );
		if ( ! $plan_array ) {
			wp_send_json_error( esc_html__( 'Failed to decode the generated plan.', 'wp-autoplugin' ) );
		}

		// Get token usage from the actual API that was used
		$token_usage = $planner_api->get_last_token_usage();

		wp_send_json_success( [
			'plan' => $plan_array,
			'token_usage' => $token_usage,
		] );
	}

	/**
	 * AJAX handler for generating code to extend theme hooks.
	 *
	 * @return void
	 */
	public function ajax_generate_extend_theme_code() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) );
		}
		check_ajax_referer( 'wp_autoplugin_generate', 'security' );

		if ( ! isset( $_POST['theme_slug'] ) || ! isset( $_POST['theme_plan'] ) || ! isset( $_POST['hooks'] ) ) {
			wp_send_json_error( esc_html__( 'Missing required parameters.', 'wp-autoplugin' ) );
		}

		$theme_slug = sanitize_text_field( wp_unslash( $_POST['theme_slug'] ) );
		$ai_plan    = sanitize_text_field( wp_unslash( $_POST['theme_plan'] ) );
		$hooks      = \WP_Autoplugin\Hooks_Extender::get_theme_hooks( $theme_slug );
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

		$coder_api = $this->admin->get_coder_api();
		$extender = new \WP_Autoplugin\Hooks_Extender( $coder_api );
		$code     = $extender->generate_theme_extension_code( $original_theme_name, $hooks, $ai_plan, $plugin_name );

		if ( is_wp_error( $code ) ) {
			wp_send_json_error( $code->get_error_message() );
		}

		// Strip out code fences like ```php ... ```.
		$code = preg_replace( '/^```(php)\n(.*)\n```$/s', '$2', $code );

		// Get token usage from the actual API that was used
		$token_usage = $coder_api->get_last_token_usage();

		wp_send_json_success( [
			'code' => $code,
			'token_usage' => $token_usage,
		] );
	}

	/**
	 * AJAX handler for reviewing generated code and suggesting improvements.
	 *
	 * @return void
	 */
	public function ajax_review_code() {
		$plugin_plan = isset( $_POST['plugin_plan'] ) ? wp_unslash( $_POST['plugin_plan'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cannot sanitize JSON data. Nonce verification is done in the parent method.
		$project_structure = isset( $_POST['project_structure'] ) ? wp_unslash( $_POST['project_structure'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cannot sanitize JSON data. Nonce verification is done in the parent method.
		$generated_files = isset( $_POST['generated_files'] ) ? wp_unslash( $_POST['generated_files'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cannot sanitize JSON data. Nonce verification is done in the parent method.

		// Decode JSON data.
		$project_structure_array = json_decode( $project_structure, true );
		$generated_files_array = json_decode( $generated_files, true );

		if ( ! $project_structure_array || ! $generated_files_array ) {
			wp_send_json_error( esc_html__( 'Invalid input data.', 'wp-autoplugin' ) );
		}

		$reviewer_api = $this->admin->get_reviewer_api();
		$generator = new Plugin_Generator( $reviewer_api );
		$review_result = $generator->review_generated_code( $plugin_plan, $project_structure_array, $generated_files_array );

		if ( is_wp_error( $review_result ) ) {
			wp_send_json_error( $review_result->get_error_message() );
		}

		// Strip out any code block fences like ```json ... ```.
		$review_result = preg_replace( '/^```(json)?\n(.*)\n```$/s', '$2', $review_result );
		$review_result = json_decode( $review_result, true );
		if ( ! $review_result ) {
			wp_send_json_error( esc_html__( 'Failed to decode the review result.', 'wp-autoplugin' ) );
		}

		// Get token usage from the actual API that was used
		$token_usage = $reviewer_api->get_last_token_usage();

		wp_send_json_success( [
			'review_data' => $review_result,
			'token_usage' => $token_usage,
		] );
	}
}
