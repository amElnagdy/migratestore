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
		// The shipping-zones data is exported by export() directly; this method is
		// not used as an allow-list source (ShippingZonesImporter overrides
		// import_option()). It has always returned null (the $query property is
		// never set), so we keep that exact contract without running an unprepared
		// raw query that trips WordPress.DB. See specs/007-sql-prepared-queries.
		return null;
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

        // Read each per-instance settings option through the cached options API
        // instead of a direct IN () query. The option name list is fully known
        // here, so there is no SQL value to bind — this avoids the unprepared-SQL
        // pattern entirely and is the idiomatic WordPress approach.
        $options = array();
        foreach ( $option_names as $option_name ) {
            $value = get_option( $option_name, null );
            if ( null !== $value ) {
                $options[] = array(
                    'option_name'  => $option_name,
                    // Re-serialize so the exported value matches the raw DB format
                    // the importer expects (it runs maybe_unserialize() on import).
                    'option_value' => maybe_serialize( $value ),
                );
            }
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
		return 'migratestore_zones_' . gmdate( 'Ymd_His' ) . '.json';
	}
}
