<?php

/**
 * Plugin Name: MigrateStore
 * Plugin URI: https://migratestore.com
 * Description: MigrateStore is a plugin that allows you to export WooCommerce settings and import them into another website. Saving your time and effort.
 * Version: 1.0.0
 * Author: Nagdy
 * Author URI: https://nagdy.me
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: migratestore
 * Domain Path: /languages
 */

use MigrateStore\MigrateStore;
use MigrateStore\Plugins_Checker;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

const MIGRATESTORE_VERSION = '1.0.0';
define( 'MIGRATESTORE_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'MIGRATESTORE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MIGRATESTORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MIGRATESTORE_PLUGIN_DIR_PATH . 'lib/autoload.php';

$checker = new Plugins_Checker();
if ( $checker->is_woocommerce_activated() === false) {
	return;
}

$plugin = new MigrateStore();
