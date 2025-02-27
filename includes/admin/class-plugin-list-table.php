<?php
/**
 * Autoplugin List Table.
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
 * Plugin List Table class.
 */
class Plugin_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'plugin',
			'plural'   => 'plugins',
			'ajax'     => false,
		) );
	}

	/**
	 * Set the columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'plugin'      => __( 'Plugin', 'wp-autoplugin' ),
		);
	}

	/**
	 * Do not render unknown columns.
	 *
	 * @param array  $item        The current item.
	 * @param string $column_name The current column name.
	 *
	 * @return array
	 */
	public function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param array $item The current item.
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		return '<input type="checkbox" />';
	}

	/**
	 * Render the primary column.
	 *
	 * @param array $item The current item.
	 *
	 * @return string
	 */
	public function column_plugin( $item ) {
		$actions = array();
		if ( $item['is_active'] ) {
			$url = wp_nonce_url( admin_url( 'admin.php?page=wp-autoplugin&action=deactivate&plugin=' . $item['plugin_path'] ), 'wp-autoplugin-activate-plugin', 'nonce' );
			$actions['deactivate'] = sprintf( '<a href="%s">%s</a>', $url, __( 'Deactivate', 'wp-autoplugin' ) );
		} else {
			$url = wp_nonce_url( admin_url( 'admin.php?page=wp-autoplugin&action=activate&plugin=' . $item['plugin_path'] ), 'wp-autoplugin-activate-plugin', 'nonce' );
			$actions['activate'] = sprintf( '<a href="%s">%s</a>', $url, __( 'Activate', 'wp-autoplugin' ) );
		}

		$actions['fix'] = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=wp-autoplugin-fix&plugin=' . $item['plugin_path'] ), 'wp-autoplugin-fix-plugin', 'nonce' ), __( 'Fix', 'wp-autoplugin' ) );
		$actions['extend'] = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=wp-autoplugin-extend&plugin=' . $item['plugin_path'] ), 'wp-autoplugin-extend-plugin', 'nonce' ), __( 'Extend', 'wp-autoplugin' ) );
		$actions['delete'] = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=wp-autoplugin&action=delete&plugin=' . $item['plugin_path'] ), 'wp-autoplugin-activate-plugin', 'nonce' ), __( 'Delete', 'wp-autoplugin' ) );

		// Replicate the default Plugin List Table column rendering.
		return sprintf( '<strong>%1$s</strong> v%2$s %3$s %4$s', $item['Name'], $item['Version'], '<p>' . $item['Description'] . '</p>', $this->row_actions( $actions ) );
	}

	/**
	 * Prepare the list items (the plugins).
	 *
	 * @return void
	 */
	public function prepare_items() {

		// Set the columns
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->items = $this->get_plugins( false );
	}

	/**
	 * Get the list and details of installed plugins that were generated by WP-Autoplugin.
	 *
	 * @param bool $all Whether to get all plugins or just the active ones.
	 *
	 * @return array
	 */
	public function get_plugins( $all = true ) {
		$plugins = array();
		$autoplugins = get_option( 'wp_autoplugins', array() );
		$autoplugins_clean = array();
		foreach ( $autoplugins as $plugin_path ) {
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ) ) {
				continue;
			}

			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
			if ( empty( $plugin_data['Name'] ) ) {
				continue;
			}

			$plugin_data['plugin_path'] = $plugin_path;
			$plugin_data['is_active']   = is_plugin_active( $plugin_path );
			$autoplugins_clean[]        = $plugin_path;

			if ( ! $all ) {
				if ( isset( $_REQUEST['status'] ) && 'active' === $_REQUEST['status'] && ! $plugin_data['is_active'] ) {
					continue;
				} elseif ( isset( $_REQUEST['status'] ) && 'inactive' === $_REQUEST['status'] && $plugin_data['is_active'] ) {
					continue;
				}
			}

			$plugins[] = $plugin_data;
		}

		// Update the option with the clean list of plugins
		if ( $autoplugins_clean !== $autoplugins ) {
			update_option( 'wp_autoplugins', $autoplugins_clean );
		}

		return $plugins;
	}

	/**
	 * Add custom classes to some rows.
	 * This is used to highlight active plugins.
	 *
	 * @param array $item The current item.
	 * @return void
	 */
	public function single_row( $item ) {
		$class = $item['is_active'] ? 'active-plugin' : '';
		echo '<tr class="' . $class . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Empty table message.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No plugins found.', 'wp-autoplugin' );
	}

	/**
	 * Search plugins.
	 *
	 * @param string $text     The search box text.
	 * @param string $input_id The search box input ID.
	 * @return void
	 */
	public function search_box( $text, $input_id ) {
		$input_id = $input_id . '-search-input';
		parent::search_box( $text, $input_id );
	}

	/**
	 * Display the table navigation.
	 *
	 * @param string $which The location of the navigation.
	 * @return void
	 */
	public function display_tablenav( $which ) {
		// Display the filter links above the table (Active/Inactive)
		if ( 'top' === $which ) {
			echo '<ul class="subsubsub">';
			$plugins = $this->get_plugins();
			$active_count = 0;
			$inactive_count = 0;
			foreach ( $plugins as $plugin ) {
				if ( $plugin['is_active'] ) {
					$active_count++;
				} else {
					$inactive_count++;
				}
			}

			$active_url = add_query_arg( 'status', 'active' );
			$inactive_url = add_query_arg( 'status', 'inactive' );
			$all_url = remove_query_arg( 'status' );

			echo '<li class="all"><a href="' . esc_url( $all_url ) . '" class="' . ( empty( $_REQUEST['status'] ) ? 'current' : '' ) . '">' . __( 'All', 'wp-autoplugin' ) . ' <span class="count">(' . count( $plugins ) . ')</span></a> |</li>';
			echo '<li class="active"><a href="' . esc_url( $active_url ) . '" class="' . ( isset( $_REQUEST['status'] ) && 'active' === $_REQUEST['status'] ? 'current' : '' ) . '">' . __( 'Active', 'wp-autoplugin' ) . ' <span class="count">(' . $active_count . ')</span></a> |</li>';
			echo '<li class="inactive"><a href="' . esc_url( $inactive_url ) . '" class="' . ( isset( $_REQUEST['status'] ) && 'inactive' === $_REQUEST['status'] ? 'current' : '' ) . '">' . __( 'Inactive', 'wp-autoplugin' ) . ' <span class="count">(' . $inactive_count . ')</span></a></li>';
			echo '</ul>';
		}
	}
}
