<?php

namespace MigrateWoo\Exporters\WooCommerce;


use MigrateWoo\Exporters\AbstractExporter;

class TaxOptionsExporter extends AbstractExporter {


	public function get_tax_options() {
		$option_names = [
			'woocommerce_prices_include_tax',
			'woocommerce_tax_based_on',
			'woocommerce_shipping_tax_class',
			'woocommerce_tax_round_at_subtotal',
			'woocommerce_tax_classes',
			'woocommerce_tax_display_shop',
			'woocommerce_tax_display_cart',
			'woocommerce_price_display_suffix',
			'woocommerce_tax_total_display',
		];

		return $this->get_options_values( $option_names );
	}


	public function get_data() {
		return $this->get_tax_options();
	}

	public function get_csv_filename() {
		return 'migratewoo_tax_options_' . date( 'Ymd_His' ) . '.csv';
	}

}
