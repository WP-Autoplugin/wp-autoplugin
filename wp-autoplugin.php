<?php
/*
Plugin Name: WP-Autoplugin
Description: A plugin that generates other plugins on-demand using AI.
Version: 1.1.1
Author: Balázs Piller
Author URI: https://wp-autoplugin.com
Text Domain: wp-autoplugin
Domain Path: /languages
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'WP_AUTOPLUGIN_VERSION', '1.1.1' );
define( 'WP_AUTOPLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_AUTOPLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the autoloader.
require_once WP_AUTOPLUGIN_DIR . 'vendor/autoload.php';

// Initialize the plugin.
function wp_autoplugin_init() {
	load_plugin_textdomain( 'wp-autoplugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	$admin_pages = new \WP_Autoplugin\Admin();
}
add_action( 'plugins_loaded', 'wp_autoplugin_init' );
