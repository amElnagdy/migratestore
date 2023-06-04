<?php

namespace MigrateWoo;

class Plugins_Checker {

	/**
	 * Check if WooCommerce is activated
	 *
	 *PS: This is only the working way!!
	 */
	public function is_woocommerce_activated() {
		if ( ! in_array( 'woocommerce/woocommerce.php',
			apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

			add_action( 'admin_notices', array($this, 'woocommerce_not_activated_notice') );

			return false;
		}

		return true;
	}

	public function woocommerce_not_activated_notice() {
		?>
		<div class="notice notice-error">
			<p><?php _e( 'MigrateWoo requires WooCommerce to be activated.', 'migratewoo' ); ?></p>
		</div>
		<?php
	}

	// We will check for the compatible plugins here.
}
