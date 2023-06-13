<?php

namespace MigrateWoo;

class Plugins_Checker {

	/**
	 * Check if WooCommerce is activated
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
			<p><?php _e( 'MigrateWoo is enabled but not effective. It requires WooCommerce to work.', 'migratewoo' ); ?></p>
		</div>
		<?php
	}

	// We will check for the compatible plugins here.
}
