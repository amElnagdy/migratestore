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

		// Same refuse-rather-than-clobber stance for block Local Pickup: only bail
		// when THIS archive actually carries a pickup option AND the destination
		// already has configured pickup locations. A zones-only import (no pickup
		// data in the file) is never blocked, even on a store that already uses
		// Local Pickup.
		if ( $this->archive_has_pickup_option( $data ) && $this->has_configured_pickup_locations() ) {
			throw new \Exception( 'This file includes Local Pickup locations, but your store already has some. Please remove your existing Local Pickup locations before importing.' );
		}

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
		// method settings (woocommerce_{method_id}_{instance_id}_settings) PLUS the
		// two global block Local Pickup options. Reject anything else so a crafted
		// import file cannot overwrite arbitrary options. (The parent's allowlist
		// relies on the exporter querying the live DB, which does not apply here —
		// the whole point is to import options that do not exist on the target site
		// yet.) Exact-name matching for the pickup options keeps the guard strict.
		// Names confirmed on WC 9.0.0 (see specs/013-local-pickup-investigation):
		// the locations option is 'pickup_location_pickup_locations'.
		$allowed_pickup_options = $this->pickup_option_names();
		if ( ! in_array( $option_name, $allowed_pickup_options, true )
			&& ! preg_match( '/^woocommerce_.+_\d+_settings$/', $option_name ) ) {
			throw new \RuntimeException( esc_html( "Invalid option name: $option_name" ) );
		}

		// Unserialize the RAW value BEFORE sanitizing. PHP's serialization format
		// embeds the byte length of every string in length prefixes (e.g.
		// s:13:"My Settings 1"). sanitize_text_field() can change those byte
		// lengths without updating the prefixes, which silently corrupts the blob
		// so it no longer unserializes. The allowlist above already restricted
		// $option_name, so only permitted options reach this line; passing
		// allowed_classes => false blocks object-injection gadgets in attacker
		// supplied archive data while the pickup arrays/scalars still decode.
		// is_serialized() leaves a non-serialized value untouched.
		if ( is_serialized( $option_value ) ) {
			$option_value = unserialize( $option_value, array( 'allowed_classes' => false ) );
		}

		// Local Pickup 'details'/address fields legitimately hold multi-line
		// strings, so sanitize the two pickup options with sanitize_textarea_field()
		// which preserves newlines. Per-instance method settings stay on
		// sanitize_text_field().
		$sanitize_string = in_array( $option_name, $allowed_pickup_options, true )
			? 'sanitize_textarea_field'
			: 'sanitize_text_field';

		if ( is_array( $option_value ) ) {
			array_walk_recursive( $option_value, function ( &$value ) use ( $sanitize_string ) {
				if ( is_string( $value ) ) {
					$value = $sanitize_string( $value );
				}
			} );
		} elseif ( is_string( $option_value ) ) {
			$option_value = $sanitize_string( $option_value );
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
	 * Whether the destination already has configured block Local Pickup locations.
	 *
	 * "Configured" means a non-empty 'pickup_location_pickup_locations' array
	 * (at least one saved location). WooCommerce's default empty settings option
	 * does not count, so a normal fresh-store import into an empty destination is
	 * unaffected. Mirrors the zones philosophy: refuse rather than clobber.
	 */
	private function has_configured_pickup_locations(): bool {
		$pickup_locations = get_option( 'pickup_location_pickup_locations', array() );

		return is_array( $pickup_locations ) && ! empty( $pickup_locations );
	}

	/**
	 * The two global block Local Pickup option names that ride along with the
	 * shipping-zones export/import. Single source of truth reused by both the
	 * import_option() allowlist and archive_has_pickup_option().
	 *
	 * @return string[]
	 */
	private function pickup_option_names(): array {
		return array(
			'woocommerce_pickup_location_settings',
			'pickup_location_pickup_locations',
		);
	}

	/**
	 * Whether the import archive itself carries a block Local Pickup option.
	 *
	 * Pickup options ride along under the 'options' group as {option_name,
	 * option_value} rows (legacy v1.1.9 archives use 'option'/'value'). Matching
	 * mirrors import_option()'s sanitize_key() normalization. Returns false when
	 * the archive has no 'options' group at all, so a zones-only import is never
	 * treated as carrying pickup data.
	 *
	 * @param array $data Decoded archive data as iterated by import().
	 */
	private function archive_has_pickup_option( $data ): bool {
		if ( empty( $data['options'] ) || ! is_array( $data['options'] ) ) {
			return false;
		}

		$pickup_names = $this->pickup_option_names();

		foreach ( $data['options'] as $row ) {
			$option_name = sanitize_key( $row['option_name'] ?? $row['option'] ?? '' );
			if ( in_array( $option_name, $pickup_names, true ) ) {
				return true;
			}
		}

		return false;
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

