<?php

namespace MigrateWoo\Exporters;


class GeneralSettingsExporter {

	use ExportTrait;

	public function get_general_settings() {
		$option_names = [
			'woocommerce_store_address',
			'woocommerce_store_address_2',
			'woocommerce_store_city',
			'woocommerce_default_country',
			'woocommerce_store_postcode',
			'woocommerce_allowed_countries',
			'woocommerce_all_except_countries',
			'woocommerce_specific_allowed_countries',
			'woocommerce_ship_to_countries',
			'woocommerce_specific_ship_to_countries',
			'woocommerce_default_customer_address',
			'woocommerce_calc_taxes',
			'woocommerce_enable_coupons',
			'woocommerce_calc_discounts_sequentially',
			'woocommerce_currency',
			'woocommerce_currency_pos',
			'woocommerce_price_thousand_sep',
			'woocommerce_price_decimal_sep',
			'woocommerce_price_num_decimals',
		];
		return $this->get_options_values($option_names);
	}

	public function export() {
		$settings      = $this->get_general_settings();
		$csv_data      = $this->format_csv_data( $settings );
		$csv_file_name = 'migratewoo_general_settings_' . date( 'Ymd_His' ) . '.csv';
		$this->download_csv( $csv_data, $csv_file_name );
	}

}
