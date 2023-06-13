<?php

/**
 * Plugin Name: MigrateWoo
 * Plugin URI: https://migratewoo.com
 * Description: MigrateWoo is a plugin that allows you to export WooCommerce settings and import them into another website. Saving your time and effort.
 * Version: 1.0.0
 * Author: Nagdy
 * Author URI: https://nagdy.me
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: migratewoo
 * Domain Path: /languages
 */

use MigrateWoo\MigrateWoo;
use MigrateWoo\Plugins_Checker;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

const MIGRATEWOO_VERSION = '1.0.0';
define( 'MIGRATEWOO_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'MIGRATEWOO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MIGRATEWOO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MIGRATEWOO_PLUGIN_DIR_PATH . 'lib/autoload.php';

$checker = new Plugins_Checker();
if ( $checker->is_woocommerce_activated() === false) {
	return;
}

$plugin = new MigrateWoo();
