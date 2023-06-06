<?php

namespace MigrateWoo\Importers;

use MigrateWoo\Exporters\AbstractExporter;

abstract class AbstractImporter {

	protected AbstractExporter $exporter;

	public function __construct( AbstractExporter $exporter ) {
		$this->exporter = $exporter;
	}

	public function import( $json_file_path ) {
		WP_Filesystem();
		global $wp_filesystem;

		// Get the contents of the JSON file
		$contents = $wp_filesystem->get_contents($json_file_path);
		if ($contents === false) {
			throw new \RuntimeException( 'Could not open JSON file.' );
		}

		$data = json_decode( $contents, true );
		if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException( 'Could not parse JSON: ' . json_last_error_msg() );
		}

		foreach ($data as $item) {
			if (isset($item['option'], $item['value'])) {
				$this->import_option( $item );
			}
		}
	}


	protected function import_option( $data ) {
		$option_name  = sanitize_key( $data['option'] );
		$option_value = $data['value'];

		// If option value is an array, sanitize each value
		if ( is_array( $option_value ) ) {
			array_walk_recursive( $option_value, function ( &$value ) {
				$value = sanitize_text_field( $value );
			});
		} else {
			// If option value is a string, sanitize it
			$option_value = sanitize_text_field( $option_value );
		}

		$allowed_option_data  = $this->exporter->get_data();
		$allowed_option_names = array_map( function ( $item ) {
			return $item['option'];
		}, $allowed_option_data );

		if ( ! in_array( $option_name, $allowed_option_names ) ) {
			throw new \RuntimeException( "Invalid option name: $option_name" );
		}

		// At this point, the option name and value should be safe to import
		update_option( $option_name, $option_value );

	}

	public function complete_import() {
		wp_redirect( admin_url( 'admin.php?page=migratewoo-import' ) );
		exit;
	}

}
