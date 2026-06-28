<?php

namespace MigrateStore\Importers\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MigrateStore\Exporters\WooCommerce\ShippingZonesExporter;
use MigrateStore\Importers\AbstractImporter;


class ShippingZonesImporter extends AbstractImporter {
	private $wpdb;
	private $skipped_methods    = array();
	private $registered_methods = null;

	public function __construct() {
		parent::__construct( new ShippingZonesExporter() );
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	// Intentional: this importer refuses to merge into existing zones to avoid zone_id
	// collisions and duplicate methods/locations. validate() below throws when zones
	// already exist, and the thrown message instructs the user to clear them first.
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
						throw new \RuntimeException( esc_html( 'Invalid JSON type: ' . $option ) );
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

		// Skip (and record) any method not registered on this site, so we
		// never write an unrenderable orphan row. Continue with the rest.
		if ( ! $this->is_method_registered( $method_id ) ) {
			if ( ! in_array( $method_id, $this->skipped_methods, true ) ) {
				$this->skipped_methods[] = $method_id;
			}
			return;
		}

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
		// Canonical keys with legacy 'option'/'value' fallback for v1.1.9 archives.
		$option_name  = sanitize_key( $data['option_name'] ?? $data['option'] ?? '' );
		$option_value = $data['option_value'] ?? $data['value'] ?? '';

		// Allowlist guard: this importer only ever writes per-instance shipping
		// method settings, whose option names follow the WooCommerce pattern
		// woocommerce_{method_id}_{instance_id}_settings. Reject anything else so
		// a crafted import file cannot overwrite arbitrary options. (The parent's
		// allowlist relies on the exporter querying the live DB, which does not
		// apply here — the whole point is to import options that do not exist on
		// the target site yet.)
		if ( ! preg_match( '/^woocommerce_.+_\d+_settings$/', $option_name ) ) {
			throw new \RuntimeException( esc_html( "Invalid option name: $option_name" ) );
		}

		// Unserialize the RAW value BEFORE sanitizing. PHP's serialization format
		// embeds the byte length of every string in length prefixes (e.g.
		// s:13:"My Settings 1"). sanitize_text_field() can change those byte
		// lengths without updating the prefixes, which silently corrupts the blob
		// so it no longer unserializes. maybe_unserialize() returns the value
		// unchanged if it is not serialized.
		$option_value = maybe_unserialize( $option_value );

		if ( is_array( $option_value ) ) {
			array_walk_recursive( $option_value, function ( &$value ) {
				if ( is_string( $value ) ) {
					$value = sanitize_text_field( $value );
				}
			} );
		} elseif ( is_string( $option_value ) ) {
			$option_value = sanitize_text_field( $option_value );
		}

		update_option( $option_name, $option_value );
	}

	private function validate(): bool {
        $shipping_zones_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}woocommerce_shipping_zones");
        $shipping_zone_methods_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}woocommerce_shipping_zone_methods");
        $shipping_zone_locations_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}woocommerce_shipping_zone_locations");

		if ( $shipping_zones_count > 0 || $shipping_zone_methods_count > 0 || $shipping_zone_locations_count > 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a shipping method id is registered with WooCommerce on this site.
	 * Uses exact key matching against the live method registry (cached once per import).
	 */
	private function is_method_registered( $method_id ) {
		if ( null === $this->registered_methods ) {
			$this->registered_methods = array();
			if ( function_exists( 'WC' ) && WC()->shipping() ) {
				// get_shipping_methods() returns an array keyed by method id.
				$this->registered_methods = WC()->shipping()->get_shipping_methods();
			}
		}

		return array_key_exists( $method_id, $this->registered_methods );
	}

	/**
	 * Shipping method ids that were skipped on import because they are not
	 * registered on this site. De-duplicated.
	 *
	 * @return string[]
	 */
	public function get_skipped_methods(): array {
		return array_values( array_unique( $this->skipped_methods ) );
	}

}

