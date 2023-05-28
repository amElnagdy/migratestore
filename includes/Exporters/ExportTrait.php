<?php

namespace MigrateWoo\Exporters;

trait ExportTrait {

	public function get_options_values(array $option_names){
		$settings = [];
		foreach ( $option_names as $option_name ) {
			$settings[] = [
				'Option' => $option_name,
				'Value'  => get_option( $option_name ),
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

}
