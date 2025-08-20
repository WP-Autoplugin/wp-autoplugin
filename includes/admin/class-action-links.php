<?php
/**
 * WP-Autoplugin Action Links class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles the admin action links.
 */
class Action_Links {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'plugin_action_links', [ $this, 'add_extend_plugin_link' ], 20, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'add_extend_theme_action_links' ] );
		add_filter( 'theme_action_links', [ $this, 'add_extend_theme_link' ], 20, 2 );
		add_filter( 'wp_autoplugin_hook_extraction_config', [ $this, 'add_rank_math_hook_extraction_config' ] );
	}

	/**
	 * Add an "Extend Plugin" link to the plugin list table.
 *
	 * @param array  $actions The plugin action links.
	 * @param string $plugin_file The plugin file.
	 *
	 * @return array
	 */
	public function add_extend_plugin_link( $actions, $plugin_file ) {
		$autoplugins = get_option( 'wp_autoplugins', [] );
		if ( in_array( $plugin_file, $autoplugins, true ) ) {
			$extend_url = admin_url( 'admin.php?page=wp-autoplugin-extend&plugin=' . rawurlencode( $plugin_file ) );
			$extend_url = wp_nonce_url( $extend_url, 'wp-autoplugin-extend-plugin', 'nonce' );
			$link_text  = esc_html__( 'Modify (Patch)', 'wp-autoplugin' );
		} else {
			$extend_url = admin_url( 'admin.php?page=wp-autoplugin-extend-hooks&plugin=' . rawurlencode( $plugin_file ) );
			$extend_url = wp_nonce_url( $extend_url, 'wp-autoplugin-extend-hooks', 'nonce' );
			$link_text  = esc_html__( 'Create Extension', 'wp-autoplugin' );
		}
		$actions['extend_plugin'] = '<a href="' . esc_url( $extend_url ) . '">' . $link_text . '</a>';
		return $actions;
	}

	/**
	 * Add an "Extend Theme" link to the theme list table (Multisite).
 *
	 * @param array  $actions The theme action links.
	 * @param string $theme The theme slug.
	 *
	 * @return array
	 */
	public function add_extend_theme_link( $actions, $theme ) {
		$extend_url = admin_url( 'admin.php?page=wp-autoplugin-extend-theme&theme=' . rawurlencode( $theme ) );
		$extend_url = wp_nonce_url( $extend_url, 'wp-autoplugin-extend-theme', 'nonce' );
		$actions['extend_theme'] = '<a href="' . esc_url( $extend_url ) . '">' . esc_html__( 'Extend Theme', 'wp-autoplugin' ) . '</a>';
		return $actions;
	}

	/**
	 * Add "Extend Theme" action links to the plugin list table.
	 * Note: this is hooked to 'admin_enqueue_scripts'.
	 *
	 * @return void
	 */
	public function add_extend_theme_action_links( $hook ) {
		if ( 'themes.php' !== $hook ) {
			return;
		}

		wp_add_inline_script( 'theme', "
			jQuery(document).ready(function($) {
				console.log('Adding Extend Theme button to theme list table.');

				wp.themes.data.themes.forEach(function(theme) {
					if (theme.id) {
						const selector = '.theme[data-slug=\"' + theme.id + '\"] .theme-actions';
						const extendUrl = '" . esc_url( admin_url( 'admin.php?page=wp-autoplugin-extend-theme&theme=' ) ) . "' + encodeURIComponent(theme.id) + '&nonce=' + '" . esc_js( wp_create_nonce( 'wp-autoplugin-extend-theme' ) ) . "';
						const actionLink = $('<a class=\"button button-small\" style=\"vertical-align: text-top;\" href=\"' + extendUrl + '\">' + '" . esc_html__( 'Extend', 'wp-autoplugin' ) . "' + '</a>');
						$(selector).append(actionLink);
					}
				});
			});
		" );
	}

	/**
	 * Add custom hook extraction configuration for the Rank Math plugin, which uses a custom hook format.
	 *
	 * @param array $configs Array of custom hook extraction configurations.
	 * @return array
	 */
	public function add_rank_math_hook_extraction_config( $configs ) {
		$rank_math_config = [
			'regex_pattern'         => '/(?:->)?(do_filter|do_action|apply_filters)\s*\(\s*([\'"].*?[\'"].*|\$[^,]+|\w+)\s*,/m',
			'method_to_type'        => [
				'do_filter' => 'filter',
				'do_action' => 'action',
			],
			'hook_name_transformer' => function ( $name, $regex_match ) {
				if ( strpos( $regex_match[0][0], '->' ) === 0 ) {
					return 'rank_math/' . $name;
				}
				return $name;
			},
		];

		$configs['seo-by-rank-math']     = $rank_math_config;
		$configs['seo-by-rank-math-pro'] = $rank_math_config;

		return $configs;
	}
}