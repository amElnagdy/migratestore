<?php

namespace MigrateWoo\Exporters;

class ShippingZonesExporter {

	use ExportTrait;

	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	private function get_data( $query ) {
		return $this->wpdb->get_results( $query, ARRAY_A );
	}

	protected function format_csv_data( $data ) {
		$output = fopen( 'php://temp', 'w' );
		foreach ( $data as $row ) {
			fputcsv( $output, $row );
		}
		rewind( $output );

		return stream_get_contents( $output );
	}

	public function export() {
		$queries = [
			"woocommerce_shipping_zones"          => "SELECT * FROM {$this->wpdb->prefix}woocommerce_shipping_zones",
			"woocommerce_shipping_zone_methods"   => "SELECT * FROM {$this->wpdb->prefix}woocommerce_shipping_zone_methods",
			"woocommerce_shipping_zone_locations" => "SELECT * FROM {$this->wpdb->prefix}woocommerce_shipping_zone_locations",
			"options"                             => "SELECT * FROM {$this->wpdb->prefix}options WHERE option_name LIKE 'woocommerce_table_rate%' OR option_name LIKE 'woocommerce_free%' OR option_name LIKE 'woocommerce_local_pickup_%' OR option_name LIKE 'woocommerce_flat_%'"
		];

		$csv_data = '';
		foreach ( $queries as $name => $query ) {
			$csv_data .= "\"$name\"\n";
			$data     = $this->get_data( $query );
			$csv_data .= $this->format_csv_data( $data );
		}

		$csv_file_name = $this->get_csv_filename();
		$this->download_csv( $csv_data, $csv_file_name );
	}

	public function get_csv_filename() {
		return 'migratewoo_shipping_zones' . date( 'Ymd_His' ) . '.csv';
	}
}