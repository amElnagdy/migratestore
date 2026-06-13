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

        // Shipping zones and locations are exported in full (no restriction).
        $zones = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zones",
            ARRAY_A
        );
        $locations = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_locations",
            ARRAY_A
        );

        // Export ALL shipping zone methods — no hardcoded whitelist (Phase 3).
        $methods = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods",
            ARRAY_A
        );

        /**
         * Filters the shipping method IDs to exclude from export.
         *
         * @filter migratestore_excluded_shipping_methods
         * @param string[] $excluded Array of shipping method IDs to omit from export.
         *                           Default empty array (export every method).
         * @return string[]
         */
        $excluded = apply_filters( 'migratestore_excluded_shipping_methods', array() );
        if ( ! is_array( $excluded ) ) {
            $excluded = array();
        }

        if ( ! empty( $excluded ) && ! empty( $methods ) ) {
            $methods = array_values(
                array_filter(
                    $methods,
                    function ( $method ) use ( $excluded ) {
                        return ! in_array( $method['method_id'], $excluded, true );
                    }
                )
            );
        }

        // Collect the per-instance settings option for every exported method
        // instance, so third-party method configuration travels with its row.
        // Option name pattern: woocommerce_{method_id}_{instance_id}_settings
        $option_names = array();
        foreach ( (array) $methods as $method ) {
            $option_names[] = 'woocommerce_' . $method['method_id'] . '_' . (int) $method['instance_id'] . '_settings';
        }
        $option_names = array_values( array_unique( $option_names ) );

        $options = array();
        if ( ! empty( $option_names ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $option_names ), '%s' ) );
            $options = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
                    $option_names
                ),
                ARRAY_A
            );
        }

        $data = array(
            'woocommerce_shipping_zones'          => $zones,
            'woocommerce_shipping_zone_methods'     => $methods,
            'woocommerce_shipping_zone_locations'   => $locations,
            'options'                               => $options,
        );

        $json_data      = $this->format_json_data( $data );
        $json_file_name = $this->get_json_filename();
        $this->download_json( $json_data, $json_file_name );
    }
    
    
    public function get_json_filename() {
		return 'migratestore_zones_' . date( 'Ymd_His' ) . '.json';
	}
}
