<?php
/**
 * WP-Autoplugin Menu class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles the admin menu.
 */
class Menu {

	/**
	 * The Admin instance.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Constructor.
	 */
	public function __construct( $admin ) {
		$this->admin = $admin;
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
	}

	/**
	 * Initialize the admin menu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			esc_html__( 'WP-Autoplugin', 'wp-autoplugin' ),
			esc_html__( 'WP-Autoplugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin',
			[ $this, 'render_list_plugins_page' ],
			'dashicons-admin-plugins',
			100
		);

		add_submenu_page(
			'wp-autoplugin',
			esc_html__( 'Generate New Plugin', 'wp-autoplugin' ),
			esc_html__( 'Generate New Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-generate',
			[ $this, 'render_generate_plugin_page' ]
		);

		add_submenu_page(
			'wp-autoplugin',
			esc_html__( 'Settings', 'wp-autoplugin' ),
			esc_html__( 'Settings', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-settings',
			[ $this, 'render_settings_page' ]
		);

		// Extend and Fix pages (they don't appear in the menu).
		add_submenu_page(
			'options.php',
			esc_html__( 'Extend Plugin', 'wp-autoplugin' ),
			esc_html__( 'Extend Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-extend',
			[ $this, 'render_extend_plugin_page' ]
		);

		add_submenu_page(
			'options.php',
			esc_html__( 'Fix Plugin', 'wp-autoplugin' ),
			esc_html__( 'Fix Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-fix',
			[ $this, 'render_fix_plugin_page' ]
		);

		add_submenu_page(
			'options.php',
			esc_html__( 'Explain Plugin', 'wp-autoplugin' ),
			esc_html__( 'Explain Plugin', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-explain',
			[ $this, 'render_explain_plugin_page' ]
		);

		add_submenu_page(
			'options.php',
			esc_html__( 'Create Extension', 'wp-autoplugin' ),
			esc_html__( 'Create Extension', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-extend-hooks',
			[ $this, 'render_extend_hooks_page' ]
		);

		add_submenu_page(
			'options.php',
			esc_html__( 'Extend Theme', 'wp-autoplugin' ),
			esc_html__( 'Extend Theme', 'wp-autoplugin' ),
			'manage_options',
			'wp-autoplugin-extend-theme',
			[ $this, 'render_extend_theme_page' ]
		);
	}

	/**
	 * Display the list of Autoplugins.
	 *
	 * @return void
	 */
	public function render_list_plugins_page() {
		include WP_AUTOPLUGIN_DIR . 'views/page-list-plugins.php';
	}

	/**
	 * Display the plugin generation page.
	 *
	 * @return void
	 */
	public function render_generate_plugin_page() {
		include WP_AUTOPLUGIN_DIR . 'views/page-generate-plugin.php';
	}

	/**
	 * Display the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		include WP_AUTOPLUGIN_DIR . 'views/page-settings.php';
	}

	/**
	 * Display the extend plugin page.
	 *
	 * @return void
	 */
	public function render_extend_plugin_page() {
		$this->validate_plugin( 'wp-autoplugin-extend-plugin' );
		include WP_AUTOPLUGIN_DIR . 'views/page-extend-plugin.php';
	}

	/**
	 * Display the fix plugin page.
	 *
	 * @return void
	 */
	public function render_fix_plugin_page() {
		$this->validate_plugin( 'wp-autoplugin-fix-plugin' );
		include WP_AUTOPLUGIN_DIR . 'views/page-fix-plugin.php';
	}

	/**
	 * Display the explain plugin page.
	 *
	 * @return void
	 */
	public function render_explain_plugin_page() {
		$this->validate_plugin( 'wp-autoplugin-explain-plugin' );
		include WP_AUTOPLUGIN_DIR . 'views/page-explain-plugin.php';
	}

	/**
	 * Display the extend plugin with hooks page.
	 *
	 * @return void
	 */
	public function render_extend_hooks_page() {
		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-autoplugin' ) );
		}

		// Required params and nonce.
		if ( ! isset( $_GET['plugin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_die( esc_html__( 'No plugin specified.', 'wp-autoplugin' ) );
		}
		$nonce_value = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, 'wp-autoplugin-extend-hooks' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-autoplugin' ) );
		}

		// Sanitize and constrain plugin path inside plugins directory.
		$plugin_file  = sanitize_text_field( wp_unslash( $_GET['plugin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$plugin_file  = ltrim( str_replace( [ '..\\', '../', '\\' ], '/', $plugin_file ), '/' );
		$plugin_path  = wp_normalize_path( WP_PLUGIN_DIR . '/' . $plugin_file );
		$plugins_base = wp_normalize_path( trailingslashit( WP_PLUGIN_DIR ) );
		if ( strpos( $plugin_path, $plugins_base ) !== 0 || ! file_exists( $plugin_path ) ) {
			wp_die( esc_html__( 'The specified plugin does not exist.', 'wp-autoplugin' ) );
		}

		$plugin_data = get_plugin_data( $plugin_path );
		include WP_AUTOPLUGIN_DIR . 'views/page-extend-hooks.php';
	}

	/**
	 * Display the extend theme page.
	 *
	 * @return void
	 */
	public function render_extend_theme_page() {
		$this->validate_theme( 'wp-autoplugin-extend-theme' );
		include WP_AUTOPLUGIN_DIR . 'views/page-extend-theme.php';
	}

	/**
	 * Validate plugin access and existence.
	 *
	 * @param string $nonce_action Nonce action name.
	 * @return void
	 */
	protected function validate_plugin( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-autoplugin' ) );
		}

		if ( ! isset( $_GET['plugin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_die( esc_html__( 'No plugin specified.', 'wp-autoplugin' ) );
		}
		$nonce_value = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-autoplugin' ) );
		}

		$plugin_file  = sanitize_text_field( wp_unslash( $_GET['plugin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$plugin_file  = ltrim( str_replace( [ '..\\', '../', '\\' ], '/', $plugin_file ), '/' );
		$plugin_path  = wp_normalize_path( WP_PLUGIN_DIR . '/' . $plugin_file );
		$plugins_base = wp_normalize_path( trailingslashit( WP_PLUGIN_DIR ) );
		if ( strpos( $plugin_path, $plugins_base ) !== 0 || ! file_exists( $plugin_path ) ) {
			wp_die( esc_html__( 'The specified plugin does not exist.', 'wp-autoplugin' ) );
		}
	}

	/**
	 * Validate theme access and existence.
	 *
	 * @param string $nonce_action Nonce action name.
	 * @return void
	 */
	protected function validate_theme( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-autoplugin' ) );
		}

		if ( ! isset( $_GET['theme'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_die( esc_html__( 'No theme specified.', 'wp-autoplugin' ) );
		}
		$nonce_value = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-autoplugin' ) );
		}

		$theme_slug = sanitize_text_field( wp_unslash( $_GET['theme'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$theme      = wp_get_theme( $theme_slug );
		if ( ! $theme->exists() ) {
			wp_die( esc_html__( 'The specified theme does not exist.', 'wp-autoplugin' ) );
		}
	}
}
