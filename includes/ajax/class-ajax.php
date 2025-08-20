<?php
/**
 * WP-Autoplugin Admin AJAX class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Admin;

use WP_Autoplugin\Ajax\Explainer;
use WP_Autoplugin\Ajax\Extender;
use WP_Autoplugin\Ajax\Fixer;
use WP_Autoplugin\Ajax\Generator;
use WP_Autoplugin\Ajax\Hooks_Extender;
use WP_Autoplugin\Ajax\Model;
use WP_Autoplugin\Ajax\Theme_Extender;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles all AJAX requests.
 */
class Ajax {
	/**
	 * The Admin object for accessing specialized model APIs.
	 *
	 * @var \WP_Autoplugin\Admin
	 */
	private $admin;

	/**
	 * AJAX handlers.
	 *
	 * @var array
	 */
	private $handlers = [];

	/**
	 * Constructor sets the Admin instance and hooks into AJAX actions.
	 *
	 * @param \WP_Autoplugin\Admin $admin The Admin instance.
	 */
	public function __construct( $admin ) {
		$this->admin = $admin;

		$this->init_handlers();
		$this->register_actions();
	}

	/**
	 * Initialize AJAX handlers.
	 */
	private function init_handlers() {
		$this->handlers = [
			'generator'      => new Generator( $this->admin ),
			'fixer'          => new Fixer( $this->admin ),
			'extender'       => new Extender( $this->admin ),
			'hooks_extender' => new Hooks_Extender( $this->admin ),
			'theme_extender' => new Theme_Extender( $this->admin ),
			'explainer'      => new Explainer( $this->admin ),
			'model'          => new Model( $this->admin ),
		];
	}

	/**
	 * Register all needed AJAX actions.
	 */
	private function register_actions() {
		$actions = [
			'generator'      => [ 'generate_plan', 'generate_code', 'generate_file', 'review_code', 'create_plugin' ],
			'fixer'          => [ 'generate_fix_plan', 'generate_fix_code', 'fix_plugin', 'generate_fix_file' ],
			'extender'       => [ 'generate_extend_plan', 'generate_extend_code', 'extend_plugin', 'generate_extend_file' ],
			'hooks_extender' => [ 'extract_hooks', 'generate_extend_hooks_plan', 'generate_extend_hooks_code', 'generate_extend_hooks_file' ],
			'theme_extender' => [ 'extract_theme_hooks', 'generate_extend_theme_plan', 'generate_extend_theme_code', 'generate_extend_theme_file' ],
			'explainer'      => [ 'explain_plugin' ],
			'model'          => [ 'add_model', 'remove_model', 'change_model', 'change_models' ],
		];

		foreach ( $actions as $handler_key => $methods ) {
			foreach ( $methods as $method ) {
				add_action( 'wp_ajax_wp_autoplugin_' . $method, [ $this, 'ajax_actions' ] );
			}
		}
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

		$action_input = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$method_name  = str_replace( 'wp_autoplugin_', '', $action_input );

		foreach ( $this->handlers as $handler ) {
			if ( method_exists( $handler, $method_name ) ) {
				$handler->$method_name();
				return;
			}
		}

		wp_send_json_error( esc_html__( 'Invalid AJAX action.', 'wp-autoplugin' ) );
	}
}
