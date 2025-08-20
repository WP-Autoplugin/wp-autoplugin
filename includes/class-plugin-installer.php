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
			return new \WP_Error( 'file_mods_disabled', __( 'Plugin installation is disabled.', 'wp-autoplugin' ) );
		}

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$plugin_file = '';
		if ( strpos( $plugin_name, '/' ) !== false && substr( $plugin_name, -4 ) === '.php' ) {
			// Treat as update to an existing plugin file path relative to WP_PLUGIN_DIR.
			$clean_rel   = wp_normalize_path( $plugin_name );
			if ( strpos( $clean_rel, '../' ) !== false ) {
				return new \WP_Error( 'invalid_path', __( 'Plugin path cannot contain "../".', 'wp-autoplugin' ) );
			}
			$plugin_file = WP_PLUGIN_DIR . '/' . $clean_rel;
			if ( ! $wp_filesystem->exists( $plugin_file ) ) {
				return new \WP_Error( 'file_not_found', __( 'Error updating plugin file: file does not exist.', 'wp-autoplugin' ) );
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
			return new \WP_Error( 'file_creation_error', __( 'Error creating plugin file.', 'wp-autoplugin' ) );
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
			return new \WP_Error( 'file_mods_disabled', __( 'Plugin installation is disabled.', 'wp-autoplugin' ) );
		}

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Normalize file paths to remove common directory prefix.
		list( $project_structure, $generated_files ) = $this->normalize_file_paths( $project_structure, $generated_files );

		$plugin_name = sanitize_title( $plugin_name, 'wp-autoplugin-' . md5( wp_json_encode( $generated_files ) ) );
		$plugin_dir  = wp_normalize_path( WP_PLUGIN_DIR . '/' . $plugin_name . '/' );

		// Create plugin directory if it doesn't exist.
		if ( ! $wp_filesystem->exists( $plugin_dir ) ) {
			$wp_filesystem->mkdir( $plugin_dir, 0755, true );
		}

		// Create subdirectories.
		if ( isset( $project_structure['directories'] ) ) {
			foreach ( $project_structure['directories'] as $directory ) {
				$directory = wp_normalize_path( $directory );
				if ( strpos( $directory, '../' ) !== false ) {
					return new \WP_Error( 'invalid_path', __( 'Invalid directory path.', 'wp-autoplugin' ) );
				}
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
				$file_path = wp_normalize_path( $file_info['path'] );
				if ( strpos( $file_path, '../' ) !== false ) {
					return new \WP_Error( 'invalid_path', __( 'Invalid file path.', 'wp-autoplugin' ) );
				}
				$file_path = $plugin_dir . $file_path;
				$file_content = isset( $generated_files[ $file_info['path'] ] ) ? $generated_files[ $file_info['path'] ] : '';

				if ( empty( $file_content ) ) {
					// Translators: %s: file path.
					return new \WP_Error( 'missing_file_content', sprintf( __( 'Missing content for file: %s', 'wp-autoplugin' ), $file_info['path'] ) );
				}

				// Create directory for the file if it doesn't exist
				$file_dir = dirname( $file_path );
				if ( ! $wp_filesystem->exists( $file_dir ) ) {
					wp_mkdir_p( $file_dir );
				}

				$result = $wp_filesystem->put_contents( $file_path, $file_content, FS_CHMOD_FILE );
				if ( false === $result ) {
					// Translators: %s: file path.
					return new \WP_Error( 'file_creation_error', sprintf( __( 'Error creating file: %s', 'wp-autoplugin' ), $file_info['path'] ) );
				}

				// Identify the main plugin file (should be in root and end with .php)
				if ( ! strpos( $file_info['path'], '/' ) && 'php' === $file_info['type'] ) {
					$main_file = $file_info['path'];
				}
			}
		}

		if ( empty( $main_file ) ) {
			// Fallback to find the first php file in the root directory
			if ( isset( $project_structure['files'] ) ) {
				foreach ( $project_structure['files'] as $file_info ) {
					if ( ! strpos( $file_info['path'], '/' ) && 'php' === $file_info['type'] ) {
						$main_file = $file_info['path'];
						break;
					}
				}
			}
		}

		if ( empty( $main_file ) ) {
			return new \WP_Error( 'no_main_file', __( 'No main plugin file found.', 'wp-autoplugin' ) );
		}

		// Add the plugin to the list of autoplugins
		$autoplugins   = get_option( 'wp_autoplugins', [] );
		$autoplugins[] = $plugin_name . '/' . $main_file;
		$autoplugins   = array_values( array_unique( $autoplugins ) );
		update_option( 'wp_autoplugins', $autoplugins );

		return $plugin_name . '/' . $main_file;
	}

	/**
	 * Update an existing plugin (directory) with multiple files.
	 *
	 * @param string $plugin_file Main plugin file relative path (e.g., slug/slug.php).
	 * @param array  $files_map   Map of relative file paths => full contents.
	 * @return string|\WP_Error  Returns the main plugin file on success.
	 */
	public function update_existing_plugin_files( $plugin_file, $files_map ) {
		// If DISALLOW_FILE_MODS is set, we can't modify plugins.
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return new \WP_Error( 'file_mods_disabled', __( 'Plugin modification is disabled.', 'wp-autoplugin' ) );
		}

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Sanitize and constrain plugin root inside plugins directory.
		$plugin_file     = wp_normalize_path( $plugin_file );
		if ( strpos( $plugin_file, '../' ) !== false ) {
			return new \WP_Error( 'invalid_path', __( 'Plugin path cannot contain "../".', 'wp-autoplugin' ) );
		}
		$plugin_root_rel = dirname( $plugin_file );
		$plugin_root_abs = wp_normalize_path( WP_PLUGIN_DIR . '/' . $plugin_root_rel . '/' );

		if ( ! $wp_filesystem->is_dir( $plugin_root_abs ) ) {
			return new \WP_Error( 'invalid_plugin_dir', __( 'Target plugin directory does not exist.', 'wp-autoplugin' ) );
		}

		$allowed_ext = [ 'php', 'css', 'js' ];

		foreach ( $files_map as $rel_path => $contents ) {
			$rel_path = wp_normalize_path( $rel_path );
			if ( strpos( $rel_path, '../' ) !== false ) {
				return new \WP_Error( 'invalid_path', __( 'Invalid file path.', 'wp-autoplugin' ) );
			}
			// Ensure path stays inside plugin directory
			if ( strpos( $rel_path, $plugin_root_rel . '/' ) === 0 ) {
				$rel_path = substr( $rel_path, strlen( $plugin_root_rel . '/' ) );
			}
			$target_path = $plugin_root_abs . $rel_path;

			$ext = strtolower( pathinfo( $target_path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed_ext, true ) ) {
				// Translators: %s: file path.
				return new \WP_Error( 'invalid_file_type', sprintf( __( 'Unsupported file type for update: %s', 'wp-autoplugin' ), $rel_path ) );
			}

			// Ensure directory exists
			$dir = dirname( $target_path );
			if ( ! $wp_filesystem->exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			$result = $wp_filesystem->put_contents( $target_path, (string) $contents, FS_CHMOD_FILE );
			if ( false === $result ) {
				// Translators: %s: file path.
				return new \WP_Error( 'file_write_error', sprintf( __( 'Failed to write file: %s', 'wp-autoplugin' ), $rel_path ) );
			}
		}

		return $plugin_file;
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
			wp_send_json_error( esc_html__( 'Plugin not found.', 'wp-autoplugin' ) );
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

		Admin\Notices::add_notice( esc_html__( 'Plugin activated successfully.', 'wp-autoplugin' ), 'success' );
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
			wp_send_json_error( esc_html__( 'Plugin not found.', 'wp-autoplugin' ) ); // Plugin not found.
		}

		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( $plugin );

		Admin\Notices::add_notice( esc_html__( 'Plugin deactivated successfully.', 'wp-autoplugin' ), 'success' );
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
			wp_send_json_error( esc_html__( 'Plugin not found.', 'wp-autoplugin' ) ); // Plugin not found.
		}

		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( $plugin );

		$deleted = delete_plugins( [ $plugin ] );
		if ( is_wp_error( $deleted ) ) {
			// Translators: %s: error message.
			Admin\Notices::add_notice( sprintf( esc_html__( 'Error deleting plugin: %s', 'wp-autoplugin' ), $deleted->get_error_message() ), 'error' );
		} else {
			$autoplugins = array_diff( $autoplugins, [ $plugin ] );
			update_option( 'wp_autoplugins', $autoplugins );
			Admin\Notices::add_notice( esc_html__( 'Plugin deleted successfully.', 'wp-autoplugin' ), 'success' );
		}
		wp_safe_redirect( esc_url( admin_url( 'admin.php?page=wp-autoplugin' ) ) );
		exit;
	}
}
