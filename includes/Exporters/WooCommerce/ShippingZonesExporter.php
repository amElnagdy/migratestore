<?php

namespace MigrateStore\Exporters\WooCommerce;

use MigrateStore\Exporters\AbstractExporter;

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
		return json_encode( $data);
	}
    
    public function export() {
        global $wpdb;
        
        $queries = [
            "woocommerce_shipping_zones" => "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zones",
            "woocommerce_shipping_zone_methods" => "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id IN ('flat_rate', 'free_shipping', 'local_pickup')",
            "woocommerce_shipping_zone_locations" => "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_locations",
            "options" => $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", 'woocommerce_free%', 'woocommerce_local_pickup_%', 'woocommerce_flat_%')
        ];
        
        $data = [];
        foreach ($queries as $name => $query) {
            $this->query = $query;
            $results = $this->get_data();
            $data[$name] = $results;
        }
        
        $json_data = $this->format_json_data($data);
        $json_file_name = $this->get_json_filename();
        $this->download_json($json_data, $json_file_name);
    }
    
    
    public function get_json_filename() {
		return 'migratestore_zones_' . date( 'Ymd_His' ) . '.json';
	}
}
