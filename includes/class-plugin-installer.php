<?php
/**
 * Autoplugin Installer class.
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
 * Plugin Installer class.
 */
class Plugin_Installer {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin_Installer
	 */
	private static $instance;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin_Installer
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Install a plugin from code.
	 *
	 * @param string $code        The plugin code.
	 * @param string $plugin_name The plugin name.
	 *
	 * @return string|WP_Error
	 */
	public function install_plugin( $code, $plugin_name ) {
		// If DISALLOW_FILE_MODS is set, we can't install plugins.
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return \WP_Error( 'file_mods_disabled', 'Plugin installation is disabled.' );
		}

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$plugin_file = '';
		if ( strpos( $plugin_name, '/' ) !== false && substr( $plugin_name, -4 ) === '.php' ) {
			$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_name;
			// If file exists, check if writable using WP_Filesystem.
			if ( $wp_filesystem->exists( $plugin_file ) /* && additional writable check if needed. */ ) {
				// File exists, proceed without additional operations.
				$dummy = true;
			} else {
				return \WP_Error( 'file_creation_error', 'Error updating plugin file.' );
			}
		} else {
			$plugin_name = sanitize_title( $plugin_name, 'wp-autoplugin-' . md5( $code ) );
			$plugin_dir  = WP_PLUGIN_DIR . '/' . $plugin_name . '/';
			if ( ! $wp_filesystem->exists( $plugin_dir ) ) {
				$wp_filesystem->mkdir( $plugin_dir, 0755, true );
			}
			$plugin_file = $plugin_dir . 'index.php';
		}

		$result = $wp_filesystem->put_contents( $plugin_file, $code, FS_CHMOD_FILE );
		if ( false === $result ) {
			return \WP_Error( 'file_creation_error', 'Error creating plugin file.' );
		}

		// Add the plugin to the list of autoplugins.
		$autoplugins   = get_option( 'wp_autoplugins', [] );
		$autoplugins[] = $plugin_name . '/index.php';
		$autoplugins   = array_values( array_unique( $autoplugins ) );
		update_option( 'wp_autoplugins', $autoplugins );

		return $plugin_name . '/index.php';
	}

	/**
	 * Remove common directory prefix from file paths.
	 *
	 * @param array $project_structure The project structure.
	 * @param array $generated_files The generated files array.
	 * @return array Array containing normalized project structure and files.
	 */
	private function normalize_file_paths( $project_structure, $generated_files ) {
		if ( ! isset( $project_structure['files'] ) || empty( $project_structure['files'] ) ) {
			return array( $project_structure, $generated_files );
		}

		$file_paths = array_column( $project_structure['files'], 'path' );
		
		// Find common prefix
		$common_prefix = '';
		if ( count( $file_paths ) > 1 ) {
			$first_path = $file_paths[0];
			$prefix_parts = explode( '/', $first_path );
			
			// Check if first part is common to all paths
			if ( strpos( $first_path, '/' ) !== false ) {
				$potential_prefix = $prefix_parts[0] . '/';
				$all_have_prefix = true;
				
				foreach ( $file_paths as $path ) {
					if ( strpos( $path, $potential_prefix ) !== 0 ) {
						$all_have_prefix = false;
						break;
					}
				}
				
				if ( $all_have_prefix ) {
					$common_prefix = $potential_prefix;
				}
			}
		}

		// Remove common prefix if found
		if ( ! empty( $common_prefix ) ) {
			$normalized_generated_files = array();
			
			foreach ( $project_structure['files'] as &$file_info ) {
				$old_path = $file_info['path'];
				$new_path = substr( $file_info['path'], strlen( $common_prefix ) );
				$file_info['path'] = $new_path;
				
				// Update generated_files keys
				if ( isset( $generated_files[ $old_path ] ) ) {
					$normalized_generated_files[ $new_path ] = $generated_files[ $old_path ];
				}
			}
			
			$generated_files = $normalized_generated_files;
			
			// Also update directories if they exist
			if ( isset( $project_structure['directories'] ) ) {
				foreach ( $project_structure['directories'] as &$directory ) {
					if ( strpos( $directory, $common_prefix ) === 0 ) {
						$directory = substr( $directory, strlen( $common_prefix ) );
					}
				}
				// Remove empty directories after prefix removal
				$project_structure['directories'] = array_filter( $project_structure['directories'] );
			}
		}

		return array( $project_structure, $generated_files );
	}

	/**
	 * Install a complex multi-file plugin.
	 *
	 * @param string $plugin_name The plugin name.
	 * @param array  $project_structure The project structure.
	 * @param array  $generated_files The generated files.
	 *
	 * @return string|WP_Error
	 */
	public function install_complex_plugin( $plugin_name, $project_structure, $generated_files ) {
		// If DISALLOW_FILE_MODS is set, we can't install plugins.
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return new \WP_Error( 'file_mods_disabled', 'Plugin installation is disabled.' );
		}

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Normalize file paths to remove common directory prefix
		list( $project_structure, $generated_files ) = $this->normalize_file_paths( $project_structure, $generated_files );

		$plugin_name = sanitize_title( $plugin_name, 'wp-autoplugin-' . md5( wp_json_encode( $generated_files ) ) );
		$plugin_dir  = WP_PLUGIN_DIR . '/' . $plugin_name . '/';

		// Create plugin directory if it doesn't exist
		if ( ! $wp_filesystem->exists( $plugin_dir ) ) {
			$wp_filesystem->mkdir( $plugin_dir, 0755, true );
		}

		// Create subdirectories
		if ( isset( $project_structure['directories'] ) ) {
			foreach ( $project_structure['directories'] as $directory ) {
				$dir_path = $plugin_dir . $directory;
				if ( ! $wp_filesystem->exists( $dir_path ) ) {
					$wp_filesystem->mkdir( $dir_path, 0755, true );
				}
			}
		}

		// Write all files
		$main_file = '';
		if ( isset( $project_structure['files'] ) ) {
			foreach ( $project_structure['files'] as $file_info ) {
				$file_path = $plugin_dir . $file_info['path'];
				$file_content = isset( $generated_files[ $file_info['path'] ] ) ? $generated_files[ $file_info['path'] ] : '';

				if ( empty( $file_content ) ) {
					return new \WP_Error( 'missing_file_content', 'Missing content for file: ' . $file_info['path'] );
				}

				// Create directory for the file if it doesn't exist
				$file_dir = dirname( $file_path );
				if ( ! $wp_filesystem->exists( $file_dir ) ) {
					wp_mkdir_p( $file_dir );
				}

				$result = $wp_filesystem->put_contents( $file_path, $file_content, FS_CHMOD_FILE );
				if ( false === $result ) {
					return new \WP_Error( 'file_creation_error', 'Error creating file: ' . $file_info['path'] );
				}

				// Identify the main plugin file (should be in root and end with .php)
				if ( ! strpos( $file_info['path'], '/' ) && 'php' === $file_info['type'] ) {
					$main_file = $file_info['path'];
				}
			}
		}

		if ( empty( $main_file ) ) {
			return new \WP_Error( 'no_main_file', 'No main plugin file found.' );
		}

		// Add the plugin to the list of autoplugins
		$autoplugins   = get_option( 'wp_autoplugins', [] );
		$autoplugins[] = $plugin_name . '/' . $main_file;
		$autoplugins   = array_values( array_unique( $autoplugins ) );
		update_option( 'wp_autoplugins', $autoplugins );

		return $plugin_name . '/' . $main_file;
	}

	/**
	 * Try to activate a plugin and catch fatal errors.
	 *
	 * @param string $plugin The plugin file.
	 * @return void
	 */
	public function activate_plugin( $plugin ) {
		$autoplugins = get_option( 'wp_autoplugins', [] );
		// Use strict in_array check.
		if ( ! in_array( $plugin, $autoplugins, true ) ) {
			wp_send_json_error( 'Plugin not found.' );
		}

		// Hide PHP errors without silencing.
		ini_set( 'display_startup_errors', 0 ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		ini_set( 'display_errors', 0 ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
		error_reporting( 0 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting,WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting

		ob_start();

		register_shutdown_function(
			function () use ( $plugin ) {
				$error = error_get_last();
				if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
					// Capture the fatal error message.
					$error_message = $error['message'];
					update_option(
						'wp_autoplugin_fatal_error',
						[
							'plugin' => $plugin,
							'error'  => $error_message,
						]
					);
					echo '<meta http-equiv="refresh" content="0;url=' . esc_url( admin_url( 'admin.php?page=wp-autoplugin' ) ) . '">';
					exit;
				}
			}
		);

		try {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			set_error_handler(
				function ( $errno, $errstr, $errfile, $errline ) {
					throw new \ErrorException( esc_html( $errstr ), 0, esc_html( $errno ), esc_html( $errfile ), esc_html( $errline ) );
				}
			);

			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			activate_plugin( $plugin, admin_url( 'admin.php?page=wp-autoplugin&plugin=' . rawurlencode( $plugin ) ) );

			ob_end_clean();
			restore_error_handler();

		} catch ( \ErrorException $e ) {
			$output = ob_get_clean();
			restore_error_handler();
			$error_message = $e->getMessage();
			update_option( 'wp_autoplugin_fatal_error', $error_message );
			wp_safe_redirect( esc_url( admin_url( 'admin.php?page=wp-autoplugin' ) ) );
			exit;
		}

		ob_end_clean();
		restore_error_handler();

		Admin\Notices::add_notice( 'Plugin activated successfully.', 'success' );
		wp_safe_redirect( esc_url( admin_url( 'admin.php?page=wp-autoplugin' ) ) );
		exit;
	}

	/**
	 * Deactivate a plugin and redirect to the autoplugins list page.
	 *
	 * @param string $plugin The plugin file.
	 *
	 * @return void
	 */
	public function deactivate_plugin( $plugin ) {
		$autoplugins = get_option( 'wp_autoplugins', [] );
		if ( ! in_array( $plugin, $autoplugins, true ) ) {
			wp_send_json_error( 'Plugin not found.' ); // Plugin not found.
		}

		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( $plugin );

		Admin\Notices::add_notice( 'Plugin deactivated successfully.', 'success' );
		wp_safe_redirect( esc_url( admin_url( 'admin.php?page=wp-autoplugin' ) ) );
		exit;
	}

	/**
	 * Delete a plugin.
	 *
	 * @param string $plugin The plugin file.
	 *
	 * @return void
	 */
	public function delete_plugin( $plugin ) {
		$autoplugins = get_option( 'wp_autoplugins', [] );
		if ( ! in_array( $plugin, $autoplugins, true ) ) {
			wp_send_json_error( 'Plugin not found.' ); // Plugin not found.
		}

		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( $plugin );

		$deleted = delete_plugins( [ $plugin ] );
		if ( is_wp_error( $deleted ) ) {
			Admin\Notices::add_notice( 'Error deleting plugin: ' . $deleted->get_error_message(), 'error' );
		} else {
			$autoplugins = array_diff( $autoplugins, [ $plugin ] );
			update_option( 'wp_autoplugins', $autoplugins );
			Admin\Notices::add_notice( 'Plugin deleted successfully.', 'success' );
		}
		wp_safe_redirect( esc_url( admin_url( 'admin.php?page=wp-autoplugin' ) ) );
		exit;
	}
}
