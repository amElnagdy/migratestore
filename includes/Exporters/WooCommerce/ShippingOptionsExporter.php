<?php

namespace MigrateStore\Exporters\WooCommerce;

use MigrateStore\Exporters\AbstractExporter;

class ShippingOptionsExporter extends AbstractExporter {


	public function get_data() {

		$option_names = [
			'woocommerce_enable_shipping_calc',
			'woocommerce_shipping_cost_requires_address',
			'woocommerce_ship_to_destination',
			'woocommerce_shipping_debug_mode',
		];

		return $this->get_options_values( $option_names );
	}

	public function get_json_filename() {
		return 'migratestore_shipping_options_' . date( 'Ymd_His' ) . '.json';
	}


}
