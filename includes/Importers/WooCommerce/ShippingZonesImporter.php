<?php

namespace MigrateStore\Importers\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MigrateStore\Exporters\WooCommerce\ShippingZonesExporter;
use MigrateStore\Importers\AbstractImporter;


class ShippingZonesImporter extends AbstractImporter {
	private $wpdb;

	public function __construct() {
		parent::__construct( new ShippingZonesExporter() );
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	//TODO: Add a Learn more link that explains why users should delete existing zones.
	public function import( $json_file_path ) {
		if ( $this->validate() === false ) {
			throw new \Exception( 'You have existing shipping zones. Please delete them before attempting to import new ones.' );
		}

		$data = $this->get_json_data( $json_file_path );

		foreach ( $data as $option => $values ) {
			foreach ( $values as $value ) {
				switch ( $option ) {
					case 'woocommerce_shipping_zones':
						$this->import_shipping_zone( $value );
						break;
					case 'woocommerce_shipping_zone_methods':
						$this->import_shipping_zone_method( $value );
						break;
					case 'woocommerce_shipping_zone_locations':
						$this->import_shipping_zone_location( $value );
						break;
					case 'options':
						$this->import_option( $value );
						break;
					default:
						throw new \RuntimeException( 'Invalid JSON type: ' . $option );
				}
			}
		}
	}


	private function import_shipping_zone( $data ) {
		$zone_id    = (int) $data['zone_id'];
		$zone_name  = sanitize_text_field( $data['zone_name'] );
		$zone_order = (int) $data['zone_order'];

		$this->wpdb->insert(
			"{$this->wpdb->prefix}woocommerce_shipping_zones",
			array(
				'zone_id'    => $zone_id,
				'zone_name'  => $zone_name,
				'zone_order' => $zone_order
			)
		);
	}

	private function import_shipping_zone_method( $data ) {
		$zone_id      = (int) $data['zone_id'];
		$instance_id  = (int) $data['instance_id'];
		$method_id    = sanitize_text_field( $data['method_id'] );
		$method_order = (int) $data['method_order'];
		$is_enabled   = (int) $data['is_enabled'];

		$this->wpdb->insert(
			"{$this->wpdb->prefix}woocommerce_shipping_zone_methods",
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
		$zone_id       = (int) $data['zone_id'];
		$location_code = sanitize_text_field( $data['location_code'] );
		$location_type = sanitize_text_field( $data['location_type'] );

		$this->wpdb->insert(
			"{$this->wpdb->prefix}woocommerce_shipping_zone_locations",
			array(
				'zone_id'       => $zone_id,
				'location_code' => $location_code,
				'location_type' => $location_type
			)
		);
	}

	protected function import_option( $data ) {
		$option_name  = sanitize_key( $data['option_name'] );
		$option_value = sanitize_text_field( $data['option_value'] );

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
        $shipping_zones_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}woocommerce_shipping_zones");
        $shipping_zone_methods_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}woocommerce_shipping_zone_methods");
        $shipping_zone_locations_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}woocommerce_shipping_zone_locations");
        
        $options_query = $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", 'woocommerce_free%', 'woocommerce_local_pickup_%', 'woocommerce_flat_%');
        $options_count = $this->wpdb->get_var( $options_query );

		if ( $shipping_zones_count > 0 || $shipping_zone_methods_count > 0 || $shipping_zone_locations_count > 0 || $options_count > 0 ) {
			return false;
		}

		return true;
	}

}

