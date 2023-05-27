<?php

namespace MigrateWoo\Exporters;

class GeneralSettingsExporter {

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
		$settings     = [];
		foreach ( $option_names as $option_name ) {
			$settings[] = [
				'Option' => $option_name,
				'Value'  => get_option( $option_name ),
			];
		}

		return $settings;
	}

	public function export() {
		$settings      = $this->get_general_settings();
		$csv_data      = $this->format_csv_data( $settings );
		$csv_file_name = 'migratewoo_general_settings_' . date( 'Ymd_His' ) . '.csv';
		$this->download_csv( $csv_data, $csv_file_name );
	}

	private function format_csv_data( $data ) {
		$output = fopen( 'php://temp', 'w' );
		fputcsv( $output, array_keys( reset( $data ) ) );
		foreach ( $data as $row ) {
			fputcsv( $output, $row );
		}
		rewind( $output );

		return stream_get_contents( $output );
	}

	private function download_csv( $csv_data, $csv_file_name ) {
		header( "Content-Type: text/csv" );
		header( "Content-Disposition: attachment; filename={$csv_file_name}" );
		header( "Pragma: no-cache" );
		header( "Expires: 0" );
		echo $csv_data;
		exit;
	}

}
