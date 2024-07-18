<?php
/*
Plugin Name: WP-Autoplugin
Description: A plugin that generates other plugins on-demand using AI.
Version: 1.0.1
Author: Balazs Piller
Author URI: https://wp-autoplugin.com
Text Domain: wp-autoplugin
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants
define( 'WP_AUTOPLUGIN_VERSION', '1.0' );
define( 'WP_AUTOPLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_AUTOPLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once WP_AUTOPLUGIN_DIR . 'includes/class-openai-api.php';
require_once WP_AUTOPLUGIN_DIR . 'includes/class-anthropic-api.php';
require_once WP_AUTOPLUGIN_DIR . 'includes/class-plugin-generator.php';
require_once WP_AUTOPLUGIN_DIR . 'includes/class-plugin-installer.php';
require_once WP_AUTOPLUGIN_DIR . 'includes/class-plugin-fixer.php';
require_once WP_AUTOPLUGIN_DIR . 'includes/class-plugin-extender.php';
require_once WP_AUTOPLUGIN_DIR . 'admin/class-admin.php';

// Initialize the plugin
function wp_autoplugin_init() {
	$admin_pages = new \WP_Autoplugin\Admin();
}
add_action( 'plugins_loaded', 'wp_autoplugin_init' );
