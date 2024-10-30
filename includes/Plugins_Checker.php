<?php

namespace MigrateStore;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class Plugins_Checker
{

	/**
	 * Check if WooCommerce is activated
	 */
	public function is_woocommerce_activated()
	{
		// Load plugin.php if not already loaded
		if (! function_exists('is_plugin_active_for_network')) {
			require_once(ABSPATH . '/wp-admin/includes/plugin.php');
		}

		// Check for WooCommerce in single site or network activated plugins
		if (
			! in_array(
				'woocommerce/woocommerce.php',
				apply_filters('active_plugins', get_option('active_plugins'))
			)
			&& ! is_plugin_active_for_network('woocommerce/woocommerce.php')
		) {

			add_action('admin_notices', array($this, 'woocommerce_not_activated_notice'));
			return false;
		}

		return true;
	}

	public function woocommerce_not_activated_notice()
	{
?>
		<div class="notice notice-error">
			<p><?php _e('Migrate Store is enabled but not effective. It requires WooCommerce to work.', 'migratestore'); ?></p>
		</div>
<?php
	}

	// We will check for the compatible plugins here.
}
