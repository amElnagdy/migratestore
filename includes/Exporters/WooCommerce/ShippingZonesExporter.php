<?php

namespace MigrateWoo\Exporters\WooCommerce;

use MigrateWoo\Exporters\AbstractExporter;

class ShippingZonesExporter extends AbstractExporter {


	private $wpdb;
	private $query;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	public function get_data() {
		return $this->wpdb->get_results( $this->query, ARRAY_A );
	}

	public function format_csv_data( $data ) {
		$output = fopen( 'php://temp', 'w' );
		foreach ( $data as $row ) {
			fputcsv( $output, $row );
		}
		rewind( $output );

		return stream_get_contents( $output );
	}

	public function export() {
		$queries = [
			"woocommerce_shipping_zones" => "SELECT * FROM {$this->wpdb->prefix}woocommerce_shipping_zones",
			"woocommerce_shipping_zone_methods" => "SELECT * FROM {$this->wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id IN ('flat_rate', 'free_shipping', 'local_pickup')",
			"woocommerce_shipping_zone_locations" => "SELECT * FROM {$this->wpdb->prefix}woocommerce_shipping_zone_locations",
			"options" => "SELECT option_name, option_value FROM {$this->wpdb->options} WHERE option_name LIKE 'woocommerce_free%' OR option_name LIKE 'woocommerce_local_pickup_%' OR option_name LIKE 'woocommerce_flat_%'"
		];

		$csv_data = '';
		foreach ( $queries as $name => $query ) {
			$csv_data .= "\"$name\"\n";

			// Add headers for each section
			switch ($name) {
				case "woocommerce_shipping_zones":
					$csv_data .= "zone_id,zone_name,zone_order\n";
					break;
				case "woocommerce_shipping_zone_methods":
					$csv_data .= "zone_id,instance_id,method_id,method_order,is_enabled\n";
					break;
				case "woocommerce_shipping_zone_locations":
					$csv_data .= "location_id,zone_id,location_code,location_type\n";
					break;
				case "options":
					$csv_data .= "option_name,option_value\n";
					break;
			}

			$this->query = $query;
			$data        = $this->get_data();
			$csv_data    .= $this->format_csv_data( $data );
		}

		$csv_file_name = $this->get_csv_filename();
		$this->download_csv( $csv_data, $csv_file_name );
	}

	public function get_csv_filename() {
		return 'migratewoo_zones' . date( 'Ymd_His' ) . '.csv';
	}
}
