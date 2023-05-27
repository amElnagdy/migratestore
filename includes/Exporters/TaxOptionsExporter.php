<?php

namespace MigrateWoo\Exporters;

class TaxOptionsExporter {

	public function get_tax_options() {
		$option_names = [
			'woocommerce_prices_include_tax	',
			'woocommerce_tax_based_on',
			'woocommerce_shipping_tax_class',
			'woocommerce_tax_round_at_subtotal',
			'woocommerce_tax_classes',
			'woocommerce_tax_display_shop',
			'woocommerce_tax_display_cart',
			'woocommerce_price_display_suffix',
			'woocommerce_tax_total_display',
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
		$settings      = $this->get_tax_options();
		$csv_data      = $this->format_csv_data( $settings );
		$csv_file_name = 'migratewoo_tax_options_' . date( 'Ymd_His' ) . '.csv';
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
