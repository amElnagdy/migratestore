<?php

/**
 * Plugin Name: MigrateWoo
 * Plugin URI: https://migratewoo.com
 * Description: MigrateWoo is a plugin that allows you to migrate your WooCommerce store to a new theme or a new platform.
 * Version: 1.0.0
 * Author: Nagdy
 * Author URI: https://nagdy.me
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: migratewoo
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.5.2
 */

use MigrateWoo\MigrateWoo;
use MigrateWoo\Checker;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

const MIGRATEWOO_VERSION = '1.0.0';
define( 'MIGRATEWOO_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'MIGRATEWOO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MIGRATEWOO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MIGRATEWOO_PLUGIN_DIR_PATH . 'lib/autoload.php';

$checker = new Checker();
if ( $checker->is_woocommerce_activated() === false) {
	return;
}

$plugin = new MigrateWoo();
