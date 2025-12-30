<?php
/**
 * Plugin Name: Abilities Inspector
 * Description: Browse registered Abilities and disable/enable them via a simple UI.
 * Version: 0.3.1
 * Author: Balázs Piller
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Text Domain: abilities-inspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABIN_VERSION', '0.3.1' );
define( 'ABIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ABIN_URL', plugin_dir_url( __FILE__ ) );

require_once ABIN_PATH . 'includes/class-abin-store.php';
require_once ABIN_PATH . 'includes/class-abin-origin.php';
require_once ABIN_PATH . 'includes/class-abin-disable-filter.php';
require_once ABIN_PATH . 'includes/class-abin-usage.php';
require_once ABIN_PATH . 'includes/class-abin-admin.php';

WP_ABIN_Origin::init();

add_action( 'plugins_loaded', function() {
	WP_ABIN_Disable_Filter::init();
	WP_ABIN_Usage::init();
	WP_ABIN_Admin::init();
} );
