<?php

/**
 * Plugin Name: Migrate Store: Export and Import WooCommerce Settings
 * Plugin URI: https://migratestore.com
 * Description: Migrate Store is a plugin that allows you to export WooCommerce settings and import them into another website. Saving your time and effort.
 * Version: 1.1.6
 * Author: Nagdy
 * Author URI: https://nagdy.me
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: migratestore
 * Domain Path: /languages
 * WC requires at least: 7.9
 * WC tested up to: 9.7
 */

use MigrateStore\MigrateStore;
use MigrateStore\Plugins_Checker;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

const MIGRATESTORE_VERSION = '1.1.6';
define( 'MIGRATESTORE_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'MIGRATESTORE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MIGRATESTORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MIGRATESTORE_PLUGIN_DIR_PATH . 'lib/autoload.php';

// Check dependencies before initializing the plugin
$checker = new Plugins_Checker();
if ( ! $checker->check_dependencies() ) {
	// Optionally deactivate the plugin if dependencies aren't met
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	deactivate_plugins( plugin_basename( __FILE__ ) );
	// Return early to prevent plugin initialization
	return;
}

$plugin = new MigrateStore();

/**
 * Declare compatibility with WooCommerce Custom Order Tables.
 */
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);
