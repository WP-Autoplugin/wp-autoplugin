<?php
/**
 * WP-Autoplugin Admin Scripts class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that enqueues admin scripts and styles.
 */
class Scripts {

	/**
	 * Constructor hooks for scripts and inline CSS.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_head', [ $this, 'admin_css' ] );
	}

	/**
	 * Enqueue scripts/styles depending on the current admin page.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		// A small utility script, used on multiple pages.
		wp_register_script(
			'wp-autoplugin-utils',
			WP_AUTOPLUGIN_URL . 'assets/admin/js/utils.js',
			[],
			WP_AUTOPLUGIN_VERSION,
			true
		);

		// Common scripts.
		wp_enqueue_script(
			'wp-autoplugin-common',
			WP_AUTOPLUGIN_URL . 'assets/admin/js/common.js',
			[ 'wp-autoplugin-utils' ],
			WP_AUTOPLUGIN_VERSION,
			true
		);

		// The main list page.
		if ( $screen->id === 'toplevel_page_wp-autoplugin' ) {
			wp_enqueue_script(
				'wp-autoplugin',
				WP_AUTOPLUGIN_URL . 'assets/admin/js/list-plugins.js',
				[],
				WP_AUTOPLUGIN_VERSION,
				true
			);
			wp_enqueue_style(
				'wp-autoplugin',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/list-plugins.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);
		} elseif ( $screen->id === 'wp-autoplugin_page_wp-autoplugin-generate' ) {
			// Code editor (CodeMirror) for displaying plugin code.
			$settings = wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php' ] );
			if ( false !== $settings ) {
				wp_enqueue_script( 'wp-theme-plugin-editor' );
				wp_enqueue_style( 'wp-codemirror' );
			}

			wp_enqueue_script(
				'wp-autoplugin-generator',
				WP_AUTOPLUGIN_URL . 'assets/admin/js/generator.js',
				[ 'wp-autoplugin-utils' ],
				WP_AUTOPLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'wp-autoplugin-generator',
				'wp_autoplugin',
				[
					'ajax_url'        => esc_url( admin_url( 'admin-ajax.php' ) ),
					'nonce'           => wp_create_nonce( 'wp_autoplugin_generate' ),
					'fix_url'         => esc_url(
						admin_url(
							'admin.php?page=wp-autoplugin-fix&nonce=' .
							wp_create_nonce( 'wp-autoplugin-fix-plugin' )
						)
					),
					'activate_url'    => esc_url(
						admin_url(
							'admin.php?page=wp-autoplugin&action=activate&nonce=' .
							wp_create_nonce( 'wp-autoplugin-activate-plugin' )
						)
					),
					'testing_plan'    => '',
					'messages'        => [
						'empty_description'     => esc_html__( 'Please enter a plugin description.', 'wp-autoplugin' ),
						'generating_plan'       => esc_html__( 'Generating a plan for your plugin.', 'wp-autoplugin' ),
						'plan_generation_error' => esc_html__( 'Error generating the plugin plan.', 'wp-autoplugin' ),
						'generating_code'       => esc_html__( 'Generating code.', 'wp-autoplugin' ),
						'code_generation_error' => esc_html__( 'Error generating the plugin code.', 'wp-autoplugin' ),
						'plugin_creation_error' => esc_html__( 'Error creating the plugin.', 'wp-autoplugin' ),
						'creating_plugin'       => esc_html__( 'Installing the plugin.', 'wp-autoplugin' ),
						'plugin_created'        => esc_html__( 'Plugin successfully installed.', 'wp-autoplugin' ),
						'how_to_test'           => esc_html__( 'How to test it?', 'wp-autoplugin' ),
						'use_fixer'             => esc_html__( 'If you notice any issues, use the Fix button in the Autoplugins list.', 'wp-autoplugin' ),
						'activate'              => esc_html__( 'Activate Plugin', 'wp-autoplugin' ),
					],
					'plugin_examples' => [
						esc_html__( 'A simple contact form with honeypot spam protection.', 'wp-autoplugin' ),
						esc_html__( 'A custom post type for testimonials.', 'wp-autoplugin' ),
						esc_html__( 'A widget that displays recent posts.', 'wp-autoplugin' ),
						// ... more example descriptions ...
						esc_html__( 'A simple image compression tool.', 'wp-autoplugin' ),
					],
				]
			);

			wp_enqueue_style(
				'wp-autoplugin-generator',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/generator.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);
		} elseif ( $screen->id === 'admin_page_wp-autoplugin-fix' ) {
			// Code editor for Fix page.
			$settings = wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php' ] );
			if ( false !== $settings ) {
				wp_enqueue_script( 'wp-theme-plugin-editor' );
				wp_enqueue_style( 'wp-codemirror' );
			}

			$is_plugin_active = false;
			if ( isset( $_GET['plugin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Nonce verification is not needed here.
				$plugin_file      = sanitize_text_field( wp_unslash( $_GET['plugin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- Nonce verification is not needed here.
				$plugin_file      = str_replace( '../', '', $plugin_file );
				$is_plugin_active = is_plugin_active( $plugin_file );
			}

			wp_enqueue_script(
				'wp-autoplugin-fix',
				WP_AUTOPLUGIN_URL . 'assets/admin/js/fixer.js',
				[ 'wp-autoplugin-utils' ],
				WP_AUTOPLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'wp-autoplugin-fix',
				'wp_autoplugin',
				[
					'ajax_url'         => esc_url( admin_url( 'admin-ajax.php' ) ),
					'nonce'            => wp_create_nonce( 'wp_autoplugin_generate' ),
					'activate_url'     => esc_url(
						admin_url(
							'admin.php?page=wp-autoplugin&action=activate&nonce=' .
							wp_create_nonce( 'wp-autoplugin-activate-plugin' )
						)
					),
					'is_plugin_active' => $is_plugin_active,
					'messages'         => [
						'empty_description'     => esc_html__( 'Please enter a plugin description.', 'wp-autoplugin' ),
						'generating_plan'       => esc_html__( 'Generating a plan for your plugin.', 'wp-autoplugin' ),
						'plan_generation_error' => esc_html__( 'Error generating the plan.', 'wp-autoplugin' ),
						'plugin_creation_error' => esc_html__( 'Error creating the fixed plugin.', 'wp-autoplugin' ),
						'generating_code'       => esc_html__( 'Generating the fixed plugin code.', 'wp-autoplugin' ),
						'code_generation_error' => esc_html__( 'Error generating the fixed code.', 'wp-autoplugin' ),
						'activate'              => esc_html__( 'Activate Plugin', 'wp-autoplugin' ),
						'creating_plugin'       => esc_html__( 'Installing the plugin.', 'wp-autoplugin' ),
						'plugin_created'        => esc_html__( 'Plugin successfully installed.', 'wp-autoplugin' ),
						'code_updated'          => esc_html__( 'The plugin code has been updated.', 'wp-autoplugin' ),
					],
				]
			);

			wp_enqueue_style(
				'wp-autoplugin-fix',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/fixer.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);
		} elseif ( $screen->id === 'admin_page_wp-autoplugin-extend' ) {
			// Code editor for Extend page.
			$settings = wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php' ] );
			if ( false !== $settings ) {
				wp_enqueue_script( 'wp-theme-plugin-editor' );
				wp_enqueue_style( 'wp-codemirror' );
			}

			$is_plugin_active = false;
			if ( isset( $_GET['plugin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Nonce verification is not needed here.
				$plugin_file      = sanitize_text_field( wp_unslash( $_GET['plugin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- Nonce verification is not needed here.
				$plugin_file      = str_replace( '../', '', $plugin_file );
				$is_plugin_active = is_plugin_active( $plugin_file );
			}

			wp_enqueue_script(
				'wp-autoplugin-extend',
				WP_AUTOPLUGIN_URL . 'assets/admin/js/extender.js',
				[ 'wp-autoplugin-utils' ],
				WP_AUTOPLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'wp-autoplugin-extend',
				'wp_autoplugin',
				[
					'ajax_url'         => esc_url( admin_url( 'admin-ajax.php' ) ),
					'nonce'            => wp_create_nonce( 'wp_autoplugin_generate' ),
					'activate_url'     => esc_url(
						admin_url(
							'admin.php?page=wp-autoplugin&action=activate&nonce=' .
							wp_create_nonce( 'wp-autoplugin-activate-plugin' )
						)
					),
					'is_plugin_active' => $is_plugin_active,
					'messages'         => [
						'empty_description'     => esc_html__( 'Please describe the changes you want to make to the plugin.', 'wp-autoplugin' ),
						'generating_plan'       => esc_html__( 'Generating a plan for your plugin.', 'wp-autoplugin' ),
						'plan_generation_error' => esc_html__( 'Error generating the development plan.', 'wp-autoplugin' ),
						'generating_code'       => esc_html__( 'Generating the extended plugin code.', 'wp-autoplugin' ),
						'code_generation_error' => esc_html__( 'Error generating the extended code.', 'wp-autoplugin' ),
						'plugin_creation_error' => esc_html__( 'Error creating the extended plugin.', 'wp-autoplugin' ),
						'code_updated'          => esc_html__( 'The plugin code has been updated.', 'wp-autoplugin' ),
						'activate'              => esc_html__( 'Activate Plugin', 'wp-autoplugin' ),
						'creating_plugin'       => esc_html__( 'Creating the plugin.', 'wp-autoplugin' ),
					],
				]
			);

			wp_enqueue_style(
				'wp-autoplugin-extend',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/extender.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);
		} elseif ( $screen->id === 'admin_page_wp-autoplugin-explain' ) {
			// Enqueue marked.js, purify.min.js for markdown rendering.
			wp_enqueue_script(
				'wp-autoplugin-marked',
				WP_AUTOPLUGIN_URL . 'assets/admin/js/marked.min.js',
				[],
				WP_AUTOPLUGIN_VERSION,
				true
			);
			wp_enqueue_script(
				'wp-autoplugin-purify',
				WP_AUTOPLUGIN_URL . 'assets/admin/js/purify.min.js',
				[],
				WP_AUTOPLUGIN_VERSION,
				true
			);

			// Enqueue scripts and styles for the Explain Plugin page.
			wp_enqueue_script(
				'wp-autoplugin-explainer',
				WP_AUTOPLUGIN_URL . 'assets/admin/js/explainer.js',
				[ 'wp-autoplugin-utils' ],
				WP_AUTOPLUGIN_VERSION,
				true
			);
			wp_localize_script(
				'wp-autoplugin-explainer',
				'wp_autoplugin',
				[
					'ajax_url' => esc_url( admin_url( 'admin-ajax.php' ) ),
					'nonce'    => wp_create_nonce( 'wp_autoplugin_generate' ),
					'messages' => [
						'generating_explanation' => esc_html__( 'Generating explanation...', 'wp-autoplugin' ),
						'explanation_error'      => esc_html__( 'Error generating explanation.', 'wp-autoplugin' ),
						'security_focus'         => esc_html__( 'Security Analysis', 'wp-autoplugin' ),
						'performance_focus'      => esc_html__( 'Performance Review', 'wp-autoplugin' ),
						'code_quality_focus'     => esc_html__( 'Code Quality Analysis', 'wp-autoplugin' ),
						'usage_focus'            => esc_html__( 'Usage Instructions', 'wp-autoplugin' ),
						'general_explanation'    => esc_html__( 'General Explanation', 'wp-autoplugin' ),
						'copied'                 => esc_html__( 'Explanation copied to clipboard!', 'wp-autoplugin' ),
						'copy_failed'            => esc_html__( 'Failed to copy explanation.', 'wp-autoplugin' ),
					],
				]
			);
			wp_enqueue_style(
				'wp-autoplugin-explainer',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/explainer.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);
		} elseif ( $screen->id === 'admin_page_wp-autoplugin-extend-hooks' ) {
			$settings = wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php' ] );
			if ( false !== $settings ) {
				wp_enqueue_script( 'wp-theme-plugin-editor' );
				wp_enqueue_style( 'wp-codemirror' );
			}

			$is_plugin_active = false;
			if ( isset( $_GET['plugin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Nonce verification is not needed here.
				$plugin_file      = sanitize_text_field( wp_unslash( $_GET['plugin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- Nonce verification is not needed here.
				$plugin_file      = str_replace( '../', '', $plugin_file );
				$is_plugin_active = is_plugin_active( $plugin_file );
			}

			wp_enqueue_script(
				'wp-autoplugin-extend-hooks',
				WP_AUTOPLUGIN_URL . 'assets/admin/js/hooks-extender.js',
				[ 'wp-autoplugin-utils' ],
				WP_AUTOPLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'wp-autoplugin-extend-hooks',
				'wp_autoplugin',
				[
					'ajax_url'         => esc_url( admin_url( 'admin-ajax.php' ) ),
					'nonce'            => wp_create_nonce( 'wp_autoplugin_generate' ),
					'activate_url'     => esc_url(
						admin_url(
							'admin.php?page=wp-autoplugin&action=activate&nonce=' .
							wp_create_nonce( 'wp-autoplugin-activate-plugin' )
						)
					),
					'is_plugin_active' => $is_plugin_active,
					'messages'         => [
						'empty_description'       => esc_html__( 'Please describe the changes you want to make to the plugin.', 'wp-autoplugin' ),
						'generating_plan'         => esc_html__( 'Generating a plan for your plugin.', 'wp-autoplugin' ),
						'plan_generation_error'   => esc_html__( 'Error generating the development plan.', 'wp-autoplugin' ),
						'generating_code'         => esc_html__( 'Generating the extension plugin code.', 'wp-autoplugin' ),
						'code_generation_error'   => esc_html__( 'Error generating the extension code.', 'wp-autoplugin' ),
						'plugin_creation_error'   => esc_html__( 'Error creating the extension plugin.', 'wp-autoplugin' ),
						'code_updated'            => esc_html__( 'The extension plugin has been created.', 'wp-autoplugin' ),
						'activate'                => esc_html__( 'Activate Plugin', 'wp-autoplugin' ),
						'creating_plugin'         => esc_html__( 'Creating the extension plugin.', 'wp-autoplugin' ),
						'plugin_created'          => esc_html__( 'Plugin successfully installed.', 'wp-autoplugin' ),
						'plugin_activation_error' => esc_html__( 'Error activating the plugin.', 'wp-autoplugin' ),
					],
				]
			);

			wp_enqueue_style(
				'wp-autoplugin-extend-hooks',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/extender.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);
		} elseif ( $screen->id === 'admin_page_wp-autoplugin-extend-theme' ) {
			$settings = wp_enqueue_code_editor( [ 'type' => 'application/x-httpd-php' ] );
			if ( false !== $settings ) {
				wp_enqueue_script( 'wp-theme-plugin-editor' );
				wp_enqueue_style( 'wp-codemirror' );
			}

			wp_enqueue_script(
				'wp-autoplugin-extend-theme',
				WP_AUTOPLUGIN_URL . 'assets/admin/js/theme-extender.js',
				[ 'wp-autoplugin-utils' ],
				WP_AUTOPLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'wp-autoplugin-extend-theme',
				'wp_autoplugin',
				[
					'ajax_url'         => esc_url( admin_url( 'admin-ajax.php' ) ),
					'nonce'            => wp_create_nonce( 'wp_autoplugin_generate' ),
					'activate_url'     => esc_url(
						admin_url(
							'admin.php?page=wp-autoplugin&action=activate&nonce=' .
							wp_create_nonce( 'wp-autoplugin-activate-plugin' )
						)
					),
					'messages'         => [
						'empty_description'       => esc_html__( 'Please describe the changes you want to make to the theme.', 'wp-autoplugin' ),
						'generating_plan'         => esc_html__( 'Generating a plan for your theme extension.', 'wp-autoplugin' ),
						'plan_generation_error'   => esc_html__( 'Error generating the development plan.', 'wp-autoplugin' ),
						'generating_code'         => esc_html__( 'Generating the extension plugin code.', 'wp-autoplugin' ),
						'code_generation_error'   => esc_html__( 'Error generating the extension code.', 'wp-autoplugin' ),
						'plugin_creation_error'   => esc_html__( 'Error creating the extension plugin.', 'wp-autoplugin' ),
						'code_updated'            => esc_html__( 'The extension plugin has been created.', 'wp-autoplugin' ),
						'activate'                => esc_html__( 'Activate Plugin', 'wp-autoplugin' ),
						'creating_plugin'         => esc_html__( 'Creating the extension plugin.', 'wp-autoplugin' ),
						'plugin_created'          => esc_html__( 'Plugin successfully installed.', 'wp-autoplugin' ),
						'plugin_activation_error' => esc_html__( 'Error activating the plugin.', 'wp-autoplugin' ),
						'extracting_hooks'        => esc_html__( 'Extracting theme hooks, please wait...', 'wp-autoplugin' ),
						'no_hooks_found'          => esc_html__( 'No hooks found in the theme code.', 'wp-autoplugin' ),
					],
				]
			);

			wp_enqueue_style(
				'wp-autoplugin-extend-theme',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/extender.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);
		}
	}

	/**
	 * Add inline CSS to fix the menu icon in the admin.
	 *
	 * @return void
	 */
	public function admin_css() {
		?>
		<style>
			li.toplevel_page_wp-autoplugin .wp-menu-image::after {
				content: "";
				display: block;
				width: 20px;
				height: 20px;
				border: 2px solid;
				border-radius: 100px;
				position: absolute;
				top: 5px;
				left: 6px;
			}
			li.toplevel_page_wp-autoplugin:not(.wp-menu-open) a:not(:hover) .wp-menu-image::after {
				color: #a7aaad;
				color: rgba(240, 246, 252, 0.6);
			}
		</style>
		<?php
	}
}
