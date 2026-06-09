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
	 * Get localized messages for JavaScript.
	 *
	 * @return array
	 */
	private function get_localized_messages() {
		return [
			'empty_description'              => esc_html__( 'Please enter a plugin description.', 'wp-autoplugin' ),
			'generating_plan'                => esc_html__( 'Generating a plan for your plugin.', 'wp-autoplugin' ),
			'plan_generation_error'          => esc_html__( 'Error generating the plugin plan.', 'wp-autoplugin' ),
			'generating_code'                => esc_html__( 'Generating code.', 'wp-autoplugin' ),
			'code_generation_error'          => esc_html__( 'Error generating the plugin code.', 'wp-autoplugin' ),
			'plugin_creation_error'          => esc_html__( 'Error creating the plugin.', 'wp-autoplugin' ),
			'creating_plugin'                => esc_html__( 'Installing the plugin.', 'wp-autoplugin' ),
			'plugin_created'                 => esc_html__( 'Plugin successfully installed.', 'wp-autoplugin' ),
			'how_to_test'                    => esc_html__( 'How to test it?', 'wp-autoplugin' ),
			'use_fixer'                      => esc_html__( 'If you notice any issues, use the Fix button in the Autoplugins list.', 'wp-autoplugin' ),
			'activate'                       => esc_html__( 'Activate Plugin', 'wp-autoplugin' ),
			'code_updated'                   => esc_html__( 'The plugin code has been updated.', 'wp-autoplugin' ),
			'generating_explanation'         => esc_html__( 'Generating explanation...', 'wp-autoplugin' ),
			'explanation_error'              => esc_html__( 'Error generating explanation.', 'wp-autoplugin' ),
			'security_focus'                 => esc_html__( 'Security Analysis', 'wp-autoplugin' ),
			'performance_focus'              => esc_html__( 'Performance Review', 'wp-autoplugin' ),
			'code_quality_focus'             => esc_html__( 'Code Quality Analysis', 'wp-autoplugin' ),
			'usage_focus'                    => esc_html__( 'Usage Instructions', 'wp-autoplugin' ),
			'general_explanation'            => esc_html__( 'General Explanation', 'wp-autoplugin' ),
			'copied'                         => esc_html__( 'Explanation copied to clipboard!', 'wp-autoplugin' ),
			'copy_failed'                    => esc_html__( 'Failed to copy explanation.', 'wp-autoplugin' ),
			'empty_changes_description'      => esc_html__( 'Please describe the changes you want to make to the plugin.', 'wp-autoplugin' ),
			'plan_generation_error_dev'      => esc_html__( 'Error generating the development plan.', 'wp-autoplugin' ),
			'generating_extended_code'       => esc_html__( 'Generating the extended plugin code.', 'wp-autoplugin' ),
			'code_generation_error_extended' => esc_html__( 'Error generating the extended code.', 'wp-autoplugin' ),
			'plugin_creation_error_extended' => esc_html__( 'Error creating the extended plugin.', 'wp-autoplugin' ),
			'creating_extended_plugin'       => esc_html__( 'Creating the extension plugin.', 'wp-autoplugin' ),
			'plugin_activation_error'        => esc_html__( 'Error activating the plugin.', 'wp-autoplugin' ),
			'extracting_hooks'               => esc_html__( 'Extracting hooks, please wait...', 'wp-autoplugin' ),
			'no_hooks_found'                 => esc_html__( 'No hooks found in the codebase. Cannot extend the plugin.', 'wp-autoplugin' ),
			'drop_files_to_attach'           => esc_html__( 'Drop files to attach', 'wp-autoplugin' ),
			'attached_image'                 => esc_html__( 'Attached image', 'wp-autoplugin' ),
			'remove_image'                   => esc_html__( 'Remove image', 'wp-autoplugin' ),
			'delete_confirmation'            => esc_html__( 'Are you sure you want to delete this plugin?', 'wp-autoplugin' ),
			'error_parsing_explanation'      => esc_html__( 'Error parsing explanation. Please try again.', 'wp-autoplugin' ),
			// File generation error messages
			// translators: 1: File name.
			'error_generating_file'          => esc_html__( 'Error generating %s:', 'wp-autoplugin' ),
			'retry_current_file'             => esc_html__( 'Retry Current File', 'wp-autoplugin' ),
			'retry_from_here'                => esc_html__( 'Retry From Here', 'wp-autoplugin' ),
			'skip_this_file'                 => esc_html__( 'Skip This File', 'wp-autoplugin' ),
			'skipped'                        => esc_html__( 'Skipped', 'wp-autoplugin' ),
			'file_skipped_comment'           => esc_html__( 'This file was skipped during generation', 'wp-autoplugin' ),
			// translators: 1: File name.
			'please_add_code'                => esc_html__( 'Please add your %s code here', 'wp-autoplugin' ),
			'ai_reviewing_codebase'          => esc_html__( 'AI is reviewing the complete codebase...', 'wp-autoplugin' ),
			'review_error'                   => esc_html__( 'Review Error:', 'wp-autoplugin' ),
			'applying_suggestions'           => esc_html__( 'Applying suggestions...', 'wp-autoplugin' ),
			// Theme extender specific messages
			// translators: %d: Total number of hooks found.
			'hooks_found_in_theme'           => esc_html__( '%d hooks found in the theme code', 'wp-autoplugin' ),
			'no_theme_hooks_found'           => esc_html__( 'No hooks found in the theme code. You can still create an extension, but you may need to add hooks manually.', 'wp-autoplugin' ),
			'error_extracting_hooks'         => esc_html__( 'Error extracting hooks:', 'wp-autoplugin' ),
			'copied_to_clipboard'            => esc_html__( 'Copied!', 'wp-autoplugin' ),
			'no_files_to_generate'           => esc_html__( 'Error: No files to generate.', 'wp-autoplugin' ),
			// translators: 1: Plugin/Theme name, 2: Current item number, 3: Total number of items.
			'generating_file_progress'       => esc_html__( 'Generating %1$s (%2$d of %3$d)...', 'wp-autoplugin' ),
			// Plugin hooks extender specific messages
			// translators: %d: Total number of hooks found.
			'hooks_found_in_plugin'          => esc_html__( '%d hooks found in the plugin code', 'wp-autoplugin' ),
			'no_plugin_hooks_found'          => esc_html__( 'No hooks found in the plugin code. Cannot proceed with extension.', 'wp-autoplugin' ),
		];
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

		$localized_data = [
			'ajax_url'               => esc_url( admin_url( 'admin-ajax.php' ) ),
			'nonce'                  => wp_create_nonce( 'wp_autoplugin_generate' ),
			'messages'               => $this->get_localized_messages(),
			'supported_image_models' => \WP_Autoplugin\AI_Utils::get_supported_image_models(),
			'field_labels'           => [
				'plugin_name'                  => esc_html__( 'Plugin Name', 'wp-autoplugin' ),
				'design_and_architecture'      => esc_html__( 'Design And Architecture', 'wp-autoplugin' ),
				'detailed_feature_description' => esc_html__( 'Detailed Feature Description', 'wp-autoplugin' ),
				'user_interface'               => esc_html__( 'User Interface', 'wp-autoplugin' ),
				'user_flows'                   => esc_html__( 'User Flows', 'wp-autoplugin' ),
				'security_considerations'      => esc_html__( 'Security Considerations', 'wp-autoplugin' ),
				'testing_plan'                 => esc_html__( 'Testing Plan', 'wp-autoplugin' ),
				'project_structure'            => esc_html__( 'Project Structure', 'wp-autoplugin' ),
				'technically_feasible'         => esc_html__( 'Technically Feasible', 'wp-autoplugin' ),
				'explanation'                  => esc_html__( 'Explanation', 'wp-autoplugin' ),
				'hooks'                        => esc_html__( 'Hooks', 'wp-autoplugin' ),
				'plan'                         => esc_html__( 'Plan', 'wp-autoplugin' ),
			],
		];

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

			$localized_data['fix_url']         = esc_url( admin_url( 'admin.php?page=wp-autoplugin-fix&nonce=' . wp_create_nonce( 'wp-autoplugin-fix-plugin' ) ) );
			$localized_data['activate_url']    = esc_url( admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ) );
			$localized_data['testing_plan']    = '';
			$localized_data['plugin_examples'] = [
				esc_html__( 'A simple contact form with honeypot spam protection', 'wp-autoplugin' ),
				esc_html__( 'A custom post type for testimonials', 'wp-autoplugin' ),
				esc_html__( 'A widget that displays recent posts', 'wp-autoplugin' ),
				esc_html__( 'A shortcode that shows a random quote', 'wp-autoplugin' ),
				esc_html__( 'A user profile widget displaying avatar, bio, and website link', 'wp-autoplugin' ),
				esc_html__( 'A custom post type for managing FAQs', 'wp-autoplugin' ),
				esc_html__( 'A post views counter that tracks and displays view counts', 'wp-autoplugin' ),
				esc_html__( 'Maintenance mode with a countdown timer to site return', 'wp-autoplugin' ),
				esc_html__( 'An admin quick links widget for the dashboard', 'wp-autoplugin' ),
				esc_html__( 'Hide the admin bar for non-admin users', 'wp-autoplugin' ),
				esc_html__( 'Hide specific menu items in the admin area', 'wp-autoplugin' ),
				esc_html__( 'A social media share buttons plugin for posts', 'wp-autoplugin' ),
				esc_html__( 'A custom footer credit remover', 'wp-autoplugin' ),
				esc_html__( 'A plugin to add custom CSS to the WordPress login page', 'wp-autoplugin' ),
				esc_html__( 'A related posts display below single post content', 'wp-autoplugin' ),
				esc_html__( 'A custom excerpt length controller', 'wp-autoplugin' ),
				esc_html__( 'A "Back to Top" button for long pages', 'wp-autoplugin' ),
				esc_html__( 'A plugin to disable comments on specific post types', 'wp-autoplugin' ),
				esc_html__( 'A simple Google Analytics integration', 'wp-autoplugin' ),
				esc_html__( 'An author box display below posts', 'wp-autoplugin' ),
				esc_html__( 'A custom breadcrumb generator', 'wp-autoplugin' ),
				esc_html__( 'A plugin to add nofollow to external links', 'wp-autoplugin' ),
				esc_html__( 'A simple cookie consent banner', 'wp-autoplugin' ),
				esc_html__( 'A post expiration date setter', 'wp-autoplugin' ),
				esc_html__( 'A basic XML sitemap generator', 'wp-autoplugin' ),
				esc_html__( 'A custom login URL creator for added security', 'wp-autoplugin' ),
				esc_html__( 'A simple contact information display shortcode', 'wp-autoplugin' ),
				esc_html__( 'A plugin to add estimated reading time to posts', 'wp-autoplugin' ),
				esc_html__( 'A custom RSS feed footer', 'wp-autoplugin' ),
				esc_html__( 'A simple post duplication tool', 'wp-autoplugin' ),
				esc_html__( 'A basic schema markup generator', 'wp-autoplugin' ),
				esc_html__( 'A plugin to add custom admin footer text', 'wp-autoplugin' ),
				esc_html__( 'A plugin to add custom taxonomies easily', 'wp-autoplugin' ),
				esc_html__( 'A simple email obfuscator to prevent spam', 'wp-autoplugin' ),
				esc_html__( 'A basic redirection manager', 'wp-autoplugin' ),
				esc_html__( 'A plugin to add custom fields to user profiles', 'wp-autoplugin' ),
				esc_html__( 'A simple image compression tool', 'wp-autoplugin' ),
			];

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

			$localized_data['activate_url']     = esc_url( admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ) );
			$localized_data['is_plugin_active'] = $is_plugin_active;

			wp_enqueue_style(
				'wp-autoplugin-fix',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/fixer.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);

			// Reuse generator styles for multi-file editor UI.
			wp_enqueue_style(
				'wp-autoplugin-generator-shared',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/generator.css',
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

			$localized_data['activate_url']     = esc_url( admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ) );
			$localized_data['is_plugin_active'] = $is_plugin_active;

			wp_enqueue_style(
				'wp-autoplugin-extend',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/extender.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);

			// Reuse generator styles for multi-file editor UI.
			wp_enqueue_style(
				'wp-autoplugin-generator-shared',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/generator.css',
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

			$localized_data['activate_url']     = esc_url( admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ) );
			$localized_data['is_plugin_active'] = $is_plugin_active;

			wp_enqueue_style(
				'wp-autoplugin-extend-hooks',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/extender.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);

			// Reuse generator styles so Project Structure table matches other flows.
			wp_enqueue_style(
				'wp-autoplugin-generator-shared',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/generator.css',
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

			$localized_data['activate_url'] = esc_url( admin_url( 'admin.php?page=wp-autoplugin&action=activate&nonce=' . wp_create_nonce( 'wp-autoplugin-activate-plugin' ) ) );

			wp_enqueue_style(
				'wp-autoplugin-extend-theme',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/extender.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);

			// Reuse generator styles for multi-file editor UI.
			wp_enqueue_style(
				'wp-autoplugin-generator-shared',
				WP_AUTOPLUGIN_URL . 'assets/admin/css/generator.css',
				[],
				WP_AUTOPLUGIN_VERSION
			);
		}

		// Footer script with localized data.
		wp_enqueue_script(
			'wp-autoplugin-footer',
			WP_AUTOPLUGIN_URL . 'assets/admin/js/footer.js',
			[ 'jquery' ],
			WP_AUTOPLUGIN_VERSION,
			true
		);

		$api_handler = new \WP_Autoplugin\Admin\API_Handler();

		wp_localize_script(
			'wp-autoplugin-common',
			'wp_autoplugin',
			$localized_data
		);

		$default_step = 'default';

		// Set default step based on page context.
		if ( $screen ) {
			switch ( $screen->id ) {
				case 'wp-autoplugin_page_wp-autoplugin-generate':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-fix':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-extend':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-extend-hooks':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-extend-theme':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-explain':
					$default_step = 'askQuestion';
					break;
			}
		}

		wp_localize_script(
			'wp-autoplugin-footer',
			'wpAutopluginFooter',
			[
				'nonce'                    => wp_create_nonce( 'wp_autoplugin_nonce' ),
				'models'                   => [
					'default'  => get_option( 'wp_autoplugin_model' ),
					'planner'  => $api_handler->get_planner_model(),
					'coder'    => $api_handler->get_coder_model(),
					'reviewer' => $api_handler->get_reviewer_model(),
				],
				'default_step'             => $default_step,
				'no_token_data'            => esc_html__( 'No token usage data available yet.', 'wp-autoplugin' ),
				'total_usage'              => esc_html__( 'Total Usage', 'wp-autoplugin' ),
				'step_breakdown'           => esc_html__( 'Step Breakdown', 'wp-autoplugin' ),
				'error_saving_models'      => esc_html__( 'Failed to save models.', 'wp-autoplugin' ),
				'error_saving_models_ajax' => esc_html__( 'An error occurred while saving models.', 'wp-autoplugin' ),
			]
		);
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
