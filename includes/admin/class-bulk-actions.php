<?php
/**
 * WP-Autoplugin Admin Bulk Actions class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Admin;

use WP_Autoplugin\Plugin_Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles bulk actions (activate, deactivate, delete) on the list of Autoplugins.
 */
class Bulk_Actions {

	/**
	 * The current bulk action string.
	 *
	 * @var string
	 */
	private $action;

	/**
	 * Constructor hooks into 'admin_init' to process bulk actions.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'process_bulk_action' ] );
	}

	/**
	 * Process the bulk action if on the WP-Autoplugin page.
	 *
	 * @return void
	 */
	public function process_bulk_action() {
		if ( ! is_admin() || empty( $_GET['page'] ) || $_GET['page'] !== 'wp-autoplugin' || empty( $_REQUEST['action'] ) ) {
			return;
		}

		$nonce_value = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, 'wp-autoplugin-activate-plugin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-autoplugin' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-autoplugin' ) );
		}

		$installer = Plugin_Installer::get_instance();
		$plugins   = $this->get_bulk_actions_input();
		$action    = $this->current_action();

		if ( 'activate' === $action ) {
			foreach ( $plugins as $plugin ) {
				$installer->activate_plugin( $plugin );
			}
		} elseif ( 'deactivate' === $action ) {
			foreach ( $plugins as $plugin ) {
				$installer->deactivate_plugin( $plugin );
			}
		} elseif ( 'delete' === $action ) {
			foreach ( $plugins as $plugin ) {
				$installer->delete_plugin( $plugin );
			}
		}
	}

	/**
	 * Determine the current user action from $_REQUEST.
	 *
	 * @return string
	 */
	public function current_action() {
		if ( ! is_null( $this->action ) ) {
			return $this->action;
		}

		$this->action = isset( $_REQUEST['action'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )
			: '';

		return $this->action;
	}

	/**
	 * Get the list of plugins selected for the bulk action.
	 *
	 * @return array
	 */
	public function get_bulk_actions_input() {
		$plugins = [];
		if ( isset( $_REQUEST['plugin'] ) ) {
			$plugins = array_map( 'sanitize_text_field', wp_unslash( (array) $_REQUEST['plugin'] ) );
		}
		return $plugins;
	}
}
