<?php
/*
Plugin Name: WP-Autoplugin
Description: A plugin that generates other plugins on-demand using AI.
Version: 1.0.5
Author: Balazs Piller
Author URI: https://wp-autoplugin.com
Text Domain: wp-autoplugin
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants
define( 'WP_AUTOPLUGIN_VERSION', '1.0.5' );
define( 'WP_AUTOPLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_AUTOPLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the autoloader
require_once WP_AUTOPLUGIN_DIR . 'vendor/autoload.php';

// Initialize the plugin
function wp_autoplugin_init() {
	$admin_pages = new \WP_Autoplugin\Admin();
}
add_action( 'plugins_loaded', 'wp_autoplugin_init' );
