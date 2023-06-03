<?php

namespace MigrateWoo\Exporters;

abstract class AbstractExporter {

	abstract public function get_data();

	abstract public function get_csv_filename();

	public function get_options_values( array $option_names ) {
		$settings = [];
		foreach ( $option_names as $option_name ) {
			$option_name = trim($option_name);
			$option_value = get_option( $option_name );


			// If value is an array, serialize it
			if ( is_array( $option_value ) ) {
				$option_value = maybe_serialize( $option_value );
			}

			$settings[] = [
				'Option' => $option_name,
				'Value'  => $option_value,
			];
		}

		return $settings;
	}

	public function format_csv_data( $data ) {
		$output = fopen( 'php://temp', 'w' );
		fputcsv( $output, array_keys( reset( $data ) ) );
		foreach ( $data as $row ) {
			fputcsv( $output, $row );
		}
		rewind( $output );

		return stream_get_contents( $output );
	}

	public function download_csv( $csv_data, $csv_file_name ) {
		header( "Content-Type: text/csv" );
		header( "Content-Disposition: attachment; filename={$csv_file_name}" );
		header( "Pragma: no-cache" );
		header( "Expires: 0" );
		echo $csv_data;
		exit;
	}

	public function export() {
		$csv_data      = $this->format_csv_data( $this->get_data() );
		$csv_file_name = $this->get_csv_filename();
		$this->download_csv( $csv_data, $csv_file_name );
	}
}
