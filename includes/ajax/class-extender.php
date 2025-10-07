<?php
/**
 * WP-Autoplugin AJAX Extender class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Ajax;

use WP_Autoplugin\Plugin_Extender;
use WP_Autoplugin\Plugin_Installer;
use WP_Autoplugin\AI_Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles AJAX requests for extending plugins.
 */
class Extender {
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

		// Only include php, css, js files.
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
			$iterator    = new \RecursiveIteratorIterator( $filter, \RecursiveIteratorIterator::LEAVES_ONLY );

			foreach ( $iterator as $file ) {
				/** @var \SplFileInfo $file */
				if ( ! $file->isFile() ) {
					continue;
				}
				$ext = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, $allowed_ext, true ) ) {
					continue;
				}
				$rel      = ltrim( str_replace( $abs_root, '', $file->getPathname() ), '/' );
				$rel      = $plugin_root . '/' . $rel;
				$contents = file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( false !== $contents ) {
					$files_map[ $rel ] = $contents;
				}
			}
		}

		// Fallback: if not a directory plugin, just read the single file.
		if ( empty( $files_map ) ) {
			$single   = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
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
	 * AJAX handler for generating an extension plan for a plugin.
	 *
	 * @return void
	 */
	public function generate_extend_plan() {
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$codebase = $this->collect_plugin_codebase( $plugin_file );

		$problem = isset( $_POST['plugin_issue'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			? sanitize_text_field( wp_unslash( $_POST['plugin_issue'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in the parent method.
			: '';

		$planner_api   = $this->admin->api_handler->get_planner_api();
		$extender      = new \WP_Autoplugin\Plugin_Extender( $planner_api );
		$prompt_images = isset( $_POST['prompt_images'] ) ? AI_Utils::parse_prompt_images( $_POST['prompt_images'] ) : [];
		$plan_data     = $extender->plan_plugin_extension( $codebase['files'], $problem, $prompt_images );
		if ( is_wp_error( $plan_data ) ) {
			wp_send_json_error( $plan_data->get_error_message() );
		}

		// Get token usage from the actual API that was used.
		$token_usage = $planner_api->get_last_token_usage();

		// Strip code fences if model returns ```json blocks.
		$plan_data = \WP_Autoplugin\AI_Utils::strip_code_fences( $plan_data, 'json' );

		wp_send_json_success(
			[
				'plan_data'   => $plan_data,
				'token_usage' => $token_usage,
			]
		);
	}

	/**
	 * AJAX handler for generating extended code for a plugin.
	 *
	 * @return void
	 */
	public function generate_extend_code() {
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

		$coder_api = $this->admin->api_handler->get_coder_api();
		$extender  = new \WP_Autoplugin\Plugin_Extender( $coder_api );
		$code      = $extender->extend_plugin( $codebase['files'], $problem, $ai_description, $codebase['is_complex'] );

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
	 * AJAX handler for generating a single file for plugin extension (complex flow).
	 *
	 * Expected POST:
	 * - plugin_file (slug/main.php)
	 * - file_index (int)
	 * - plugin_plan (JSON string)
	 * - project_structure (JSON string)
	 * - generated_files (JSON string)
	 */
	public function generate_extend_file() {
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$codebase = $this->collect_plugin_codebase( $plugin_file );

		$file_index        = isset( $_POST['file_index'] ) ? intval( wp_unslash( $_POST['file_index'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$plugin_plan       = isset( $_POST['plugin_plan'] ) ? wp_unslash( $_POST['plugin_plan'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$project_structure = isset( $_POST['project_structure'] ) ? wp_unslash( $_POST['project_structure'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$generated_files   = isset( $_POST['generated_files'] ) ? wp_unslash( $_POST['generated_files'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$project_structure_array = json_decode( $project_structure, true );
		$generated_files_array   = json_decode( $generated_files, true );

		if ( ! $project_structure_array || ! isset( $project_structure_array['files'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid input data.', 'wp-autoplugin' ) );
		}

		$files = $project_structure_array['files'];
		if ( ! isset( $files[ $file_index ] ) ) {
			wp_send_json_error( esc_html__( 'File index out of range.', 'wp-autoplugin' ) );
		}

		$file_info    = $files[ $file_index ];
		$coder_api    = $this->admin->api_handler->get_coder_api();
		$extender     = new \WP_Autoplugin\Plugin_Extender( $coder_api );
		$file_content = $extender->extend_single_file( $codebase['files'], $plugin_plan, $project_structure_array, is_array( $generated_files_array ) ? $generated_files_array : [], $file_info );
		if ( is_wp_error( $file_content ) ) {
			wp_send_json_error( $file_content->get_error_message() );
		}

		// Strip out code fences.
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

	/**
	 * AJAX handler for installing an extended plugin.
	 *
	 * @return void
	 */
	public function extend_plugin() {
		$code        = isset( $_POST['plugin_code'] ) ? wp_unslash( $_POST['plugin_code'] ) : '';
		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$installer = Plugin_Installer::get_instance();

		$maybe_json = is_string( $code ) ? trim( $code ) : '';
		if ( is_string( $code ) && $maybe_json && ( '{' === $maybe_json[0] || '[' === $maybe_json[0] ) ) {
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
}
