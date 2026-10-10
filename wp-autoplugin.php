<?php
/**
 * Plugin Name: WP-Autoplugin
 * Plugin URI: https://wp-autoplugin.com
 * Description: A plugin that generates other plugins on-demand using AI.
 * Version: 2.0.2
 * Requires at least: 6.6
 * Requires PHP: 8.0
 * Update URI: https://wp-autoplugin.com/updates/wp-autoplugin
 * Author: Balázs Piller
 * Author URI: https://wp-autoplugin.com
 * Text Domain: wp-autoplugin
 * Domain Path: /languages
 *
 * @package WP-Autoplugin
 * @since 1.0.0
 * @version 2.0.2
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'WP_AUTOPLUGIN_VERSION', '2.0.2' );
define( 'WP_AUTOPLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_AUTOPLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the autoloader.
require_once WP_AUTOPLUGIN_DIR . 'vendor/autoload.php';

// Register the bundled Action Scheduler before plugins_loaded version arbitration runs.
require_once WP_AUTOPLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';

register_activation_hook( __FILE__, [ \WP_Autoplugin\V2\Infrastructure\Database\Installer::class, 'activate' ] );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function wp_autoplugin_init() {
	load_plugin_textdomain( 'wp-autoplugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	$application = new \WP_Autoplugin\V2\Application();
	$application->boot();
}
add_action( 'plugins_loaded', 'wp_autoplugin_init' );
