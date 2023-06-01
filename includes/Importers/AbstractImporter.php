<?php

namespace MigrateWoo\Importers;

use MigrateWoo\Exporters\AbstractExporter;

abstract class AbstractImporter {

	protected AbstractExporter $exporter;

	public function __construct(AbstractExporter $exporter) {
		$this->exporter = $exporter;
	}

	public function import($csv_file_path) {
		$handle = fopen($csv_file_path, 'r');

		if ($handle === false) {
			throw new \RuntimeException('Could not open CSV file.');
		}

		$header = fgetcsv($handle, 1000, ",");

		while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			if(count($data) == 2) {
				$this->import_option($data);
			}
		}

		fclose($handle);
	}

	protected function import_option($data) {
		$option_name = sanitize_key($data[0]);
		$option_value = maybe_unserialize($data[1]);

		$allowed_option_data = $this->exporter->get_data();

		$allowed_option_names = array_map(function($item) {
			return $item['Option'];
		}, $allowed_option_data);

		if (!in_array($option_name, $allowed_option_names)) {
			throw new \RuntimeException("Invalid option name: $option_name");
		}

		// If option value is a string, sanitize it
		if (is_string($option_value)) {
			$option_value = sanitize_text_field($option_value);
		}

		// If option value is an array, sanitize its elements
		if (is_array($option_value)) {
			array_walk_recursive($option_value, function (&$value) {
				$value = sanitize_text_field($value);
			});
		}

		// At this point, the option name and value should be safe to import
		update_option($option_name, $option_value);

		set_transient( 'migratewoo_import_success', true, 5 );

		wp_redirect( admin_url( 'admin.php?page=migratewoo' ) );
		exit;
	}
}
