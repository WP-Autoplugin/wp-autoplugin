<?php
/**
 * WP-Autoplugin AJAX Explainer class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Ajax;

use WP_Autoplugin\Plugin_Explainer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles AJAX requests for explaining plugins.
 */
class Explainer {
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
	 * AJAX handler for explaining a plugin.
	 *
	 * @return void
	 */
	public function explain_plugin() {
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

		$reviewer_api = $this->admin->api_handler->get_reviewer_api();
		$explainer    = new Plugin_Explainer( $reviewer_api );
		if ( ! empty( $question ) ) {
			$explanation = $explainer->answer_plugin_question( $codebase['files'], $question );
		} elseif ( 'general' !== $focus ) {
			$explanation = $explainer->analyze_plugin_aspect( $codebase['files'], $focus );
		} else {
			$explanation = $explainer->explain_plugin( $codebase['files'] );
		}

		if ( is_wp_error( $explanation ) ) {
			wp_send_json_error( $explanation->get_error_message() );
		}

		// Get token usage from the actual API that was used.
		$token_usage = $reviewer_api->get_last_token_usage();

		wp_send_json_success(
			[
				'explanation' => $explanation,
				'token_usage' => $token_usage,
			]
		);
	}
}
