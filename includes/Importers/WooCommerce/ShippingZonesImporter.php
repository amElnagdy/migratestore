<?php

namespace MigrateWoo\Importers\WooCommerce;

use MigrateWoo\Exporters\WooCommerce\ShippingZonesExporter;
use MigrateWoo\Importers\AbstractImporter;


class ShippingZonesImporter extends AbstractImporter {
	private $wpdb;

	public function __construct() {
		parent::__construct( new ShippingZonesExporter() );
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	public function import( $csv_file_path ) {
		if ($this->validate() === false) {
			throw new \Exception('You have existing shipping zones. Please delete them before attempting to import new ones.');
		}

		$handle = fopen( $csv_file_path, 'r' );

		if ( $handle === false ) {
			throw new \RuntimeException( 'Could not open CSV file.' );
		}

		$csv_type  = null;
		$row_count = 0;
		while ( ( $data = fgetcsv( $handle, 1000, "," ) ) !== false ) {
			// Ignore empty lines
			if ( count( $data ) == 0 || $data[0] == '' ) {
				continue;
			}

			// Detect the type of the line
			if ( count( $data ) == 1 ) {
				$csv_type  = trim( $data[0] ); // remove any accidental whitespace
				$row_count = 0;  // reset row count for each section
				continue;
			}

			// skip the header line
			if ( $row_count == 0 ) {
				$row_count ++;
				continue;
			}

			switch ( $csv_type ) {
				case 'woocommerce_shipping_zones':
					$this->import_shipping_zone( $data );
					break;
				case 'woocommerce_shipping_zone_methods':
					$this->import_shipping_zone_method( $data );
					break;
				case 'woocommerce_shipping_zone_locations':
					$this->import_shipping_zone_location( $data );
					break;
				case 'options':
					$this->import_option( $data );
					break;
				default:
					throw new \RuntimeException( 'Invalid CSV type: ' . $csv_type );
			}

			$row_count ++;
		}

		fclose( $handle );
	}



	private function import_shipping_zone( $data ) {
		global $wpdb;

		$zone_id    = (int) $data[0];
		$zone_name  = sanitize_text_field( $data[1] );
		$zone_order = (int) $data[2];

		$wpdb->insert(
			"{$wpdb->prefix}woocommerce_shipping_zones",
			array(
				'zone_id'    => $zone_id,
				'zone_name'  => $zone_name,
				'zone_order' => $zone_order
			)
		);
	}

	private function import_shipping_zone_method( $data ) {
		global $wpdb;

		$zone_id      = (int) $data[0];
		$instance_id  = (int) $data[1];
		$method_id    = sanitize_text_field( $data[2] );
		$method_order = (int) $data[3];
		$is_enabled   = (int) $data[4];

		$wpdb->insert(
			"{$wpdb->prefix}woocommerce_shipping_zone_methods",
			array(
				'zone_id'      => $zone_id,
				'instance_id'  => $instance_id,
				'method_id'    => $method_id,
				'method_order' => $method_order,
				'is_enabled'   => $is_enabled
			)
		);
	}

	private function import_shipping_zone_location( $data ) {
		global $wpdb;

		$zone_id       = (int) $data[1];
		$location_code = sanitize_text_field( $data[2] );
		$location_type = sanitize_text_field( $data[3] );

		$wpdb->insert(
			"{$wpdb->prefix}woocommerce_shipping_zone_locations",
			array(
				'zone_id'       => $zone_id,
				'location_code' => $location_code,
				'location_type' => $location_type
			)
		);
	}

	protected function import_option( $data ) {
		$option_name  = sanitize_key( $data[0] );
		$option_value = sanitize_text_field( $data[1] );

		if ( is_serialized( $option_value ) ) {
			$option_value = maybe_unserialize( $option_value );
			if ( is_array( $option_value ) ) {
				array_walk_recursive( $option_value, function ( &$value ) {
					$value = sanitize_text_field( $value );
				} );
			}
		}

		update_option( $option_name, $option_value );
	}

	private function validate() {

		// Validate shipping zones
		$shipping_zones_query = "SELECT COUNT(*) FROM {$this->wpdb->prefix}woocommerce_shipping_zones";
		$shipping_zones_count = $this->wpdb->get_var( $shipping_zones_query );

		// Validate shipping zone methods
		$shipping_zone_methods_query = "SELECT COUNT(*) FROM {$this->wpdb->prefix}woocommerce_shipping_zone_methods";
		$shipping_zone_methods_count = $this->wpdb->get_var( $shipping_zone_methods_query );

		// Validate shipping zone locations
		$shipping_zone_locations_query = "SELECT COUNT(*) FROM {$this->wpdb->prefix}woocommerce_shipping_zone_locations";
		$shipping_zone_locations_count = $this->wpdb->get_var( $shipping_zone_locations_query );

		// Validate options
		$options_query = "SELECT COUNT(*) FROM {$this->wpdb->options} WHERE option_name LIKE 'woocommerce_free%' OR option_name LIKE 'woocommerce_local_pickup_%' OR option_name LIKE 'woocommerce_flat_%'";
		$options_count = $this->wpdb->get_var( $options_query );

		if ( $shipping_zones_count > 0 || $shipping_zone_methods_count > 0 || $shipping_zone_locations_count > 0 || $options_count > 0 ) {
			return false;
		}

		return true;
	}


}

