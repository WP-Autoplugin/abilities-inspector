<?php
/**
 * Plugin Name: Abilities Explorer
 * Description: Browse registered Abilities and disable/enable them via a simple UI.
 * Version: 0.2.0
 * Author: ChatGPT
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Text Domain: abilities-explorer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABEX_VERSION', '0.2.0' );
define( 'ABEX_PATH', plugin_dir_path( __FILE__ ) );
define( 'ABEX_URL', plugin_dir_url( __FILE__ ) );

require_once ABEX_PATH . 'includes/class-abex-store.php';
require_once ABEX_PATH . 'includes/class-abex-disable-filter.php';
require_once ABEX_PATH . 'includes/class-abex-admin.php';

add_action( 'plugins_loaded', function() {
	WP_ABEX_Disable_Filter::init();
	WP_ABEX_Admin::init();
} );
