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

		// If the plugin name is a file path, save the code to that file.
		$plugin_file = '';
		if ( strpos( $plugin_name, '/' ) !== false && substr( $plugin_name, -4 ) === '.php' ) {
			$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_name;

			// We're overwriting the file, so we need to check if it exists and is writable.
			if ( ! file_exists( $plugin_file ) || ! is_writable( $plugin_file ) ) {
				return \WP_Error( 'file_creation_error', 'Error updating plugin file.' );
			}
		} else {
			$plugin_name = sanitize_title( $plugin_name, 'wp-autoplugin-' . md5( $code ) );
			$plugin_dir  = WP_PLUGIN_DIR . '/' . $plugin_name . '/';
			if ( ! file_exists( $plugin_dir ) ) {
				mkdir( $plugin_dir, 0755, true );
			}

			$plugin_file = $plugin_dir . 'index.php';
		}

		$result = file_put_contents( $plugin_file, $code );

		if ( $result === false ) {
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
	 * Try to activate a plugin and catch fatal errors.
	 *
	 * @param string $plugin The plugin file.
	 * @return void
	 */
	public function activate_plugin( $plugin ) {
		// Check if the plugin is in the list of autoplugins
		$autoplugins = get_option( 'wp_autoplugins', [] );
		if ( ! in_array( $plugin, $autoplugins ) ) {
			wp_send_json_error( 'Plugin not found.' );
		}

		// Make sure to hide any PHP errors from the user for this request.
		@ini_set( 'display_startup_errors', 0 );
		@ini_set( 'display_errors', 0 );
		@error_reporting( 0 );

		// Start output buffering
		ob_start();

		// Register shutdown function to catch fatal errors
		register_shutdown_function(
			function () use ( $plugin ) {
				$error = error_get_last();
				if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
						// Capture the fatal error message
						$error_message = $error['message'];
						// Put the error message in an option and redirect with meta (because we can't use headers after output)
						update_option(
							'wp_autoplugin_fatal_error',
							[
								'plugin' => $plugin,
								'error'  => $error_message,
							]
						);
						echo '<meta http-equiv="refresh" content="0;url=' . admin_url( 'admin.php?page=wp-autoplugin' ) . '">';
						exit;
				}
			}
		);

		try {
			// Set a custom error handler
			set_error_handler(
				function ( $errno, $errstr, $errfile, $errline ) {
					throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
				}
			);

			// Attempt to activate the plugin
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			activate_plugin( $plugin, admin_url( 'admin.php?page=wp-autoplugin&plugin=' . rawurlencode( $plugin ) ) );

			// Clear the output buffer
			ob_end_clean();

			// Restore the previous error handler
			restore_error_handler();

		} catch ( Exception $e ) {
			// Get the buffered output
			$output = ob_get_clean();

			// Restore the previous error handler
			restore_error_handler();

			// Get the error message
			$error_message = $e->getMessage();

			// Put the error message in an option and redirect
			update_option( 'wp_autoplugin_fatal_error', $error_message );
			wp_redirect( admin_url( 'admin.php?page=wp-autoplugin' ) );
		}

		// Clear the output buffer
		ob_end_clean();

		// Restore the previous error handler
		restore_error_handler();

		// Redirect to the plugins page
		Admin::add_notice( 'Plugin activated successfully.', 'success' );
		wp_redirect( admin_url( 'admin.php?page=wp-autoplugin' ) );
	}

	/**
	 * Deactivate a plugin and redirect to the autoplugins list page.
	 *
	 * @param string $plugin The plugin file.
	 *
	 * @return void
	 */
	public function deactivate_plugin( $plugin ) {
		// Check if the plugin is in the list of autoplugins
		$autoplugins = get_option( 'wp_autoplugins', [] );
		if ( ! in_array( $plugin, $autoplugins ) ) {
			wp_send_json_error( 'Plugin not found.' );
		}

		// Deactivate the plugin
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( $plugin );

		// Redirect to the plugins page
		Admin::add_notice( 'Plugin deactivated successfully.', 'success' );
		wp_redirect( admin_url( 'admin.php?page=wp-autoplugin' ) );
	}

	/**
	 * Delete a plugin.
	 *
	 * @param string $plugin The plugin file.
	 *
	 * @return void
	 */
	public function delete_plugin( $plugin ) {
		// Check if the plugin is in the list of autoplugins
		$autoplugins = get_option( 'wp_autoplugins', [] );
		if ( ! in_array( $plugin, $autoplugins ) ) {
			wp_send_json_error( 'Plugin not found.' );
		}

		// Deactivate the plugin
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( $plugin );

		// Delete the plugin
		$deleted = delete_plugins( [ $plugin ] );

		if ( is_wp_error( $deleted ) ) {
			Admin::add_notice( 'Error deleting plugin: ' . $deleted->get_error_message(), 'error' );
		} else {
			// Remove the plugin from the list of autoplugins
			$autoplugins = array_diff( $autoplugins, [ $plugin ] );
			update_option( 'wp_autoplugins', $autoplugins );

			Admin::add_notice( 'Plugin deleted successfully.', 'success' );
		}
	}
}
