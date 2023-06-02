<?php

namespace MigrateWoo\Exporters\WooCommerce;

use MigrateWoo\Exporters\AbstractExporter;

class ShippingOptionsExporter extends AbstractExporter {


	public function get_shipping_options() {

		$option_names = [
			'woocommerce_enable_shipping_calc',
			'woocommerce_shipping_cost_requires_address',
			'woocommerce_ship_to_destination',
			'woocommerce_shipping_debug_mode',
		];

		return $this->get_options_values( $option_names );
	}

	public function get_data() {
		return $this->get_shipping_options();
	}

	public function get_csv_filename() {
		return 'migratewoo_shipping_options_' . date( 'Ymd_His' ) . '.csv';
	}


}
