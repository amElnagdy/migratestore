<?php

namespace MigrateStore\Exporters;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AbstractExporter {
    
    abstract public function get_data();
    
    abstract public function get_json_filename();
    
    public function get_options_values( array $option_names ) {
        $settings = [];
        foreach ( $option_names as $option_name ) {
            $option_name  = trim( $option_name );
            $option_value = get_option( $option_name );
            
            
            // If value is an array, serialize it
            if ( is_array( $option_value ) ) {
                $option_value = maybe_serialize( $option_value );
            }
            
            $settings[] = [
                'option' => $option_name,
                'value'  => $option_value,
            ];
        }
        
        return $settings;
    }
    
    public function format_json_data( $data ) {
        return json_encode( $data, JSON_PRETTY_PRINT );
    }
    
    public function download_json( $json_data, $json_file_name ) {
        // Create new zip archive
        $zip = new \ZipArchive();
        
        // The zip file
        $zip_name = str_replace( '.json', '.zip', $json_file_name );
        
        // The full path to the zip file
        $zip_path = get_temp_dir() . $zip_name;
        
        if ( $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
            exit( "Cannot open <$zip_path>\n" );
        }
        
        // Add JSON data to the archive
        $zip->addFromString( $json_file_name, $json_data );
        
        // Close the zip -- done!
        $zip->close();
        
        // Stream the file to the client
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . basename( $zip_path ) . '"' );
        header( 'Content-Length: ' . filesize( $zip_path ) );
        
        readfile( $zip_path );
        
        exit;
    }
    
    
    public function export() {
        $json_data      = $this->format_json_data( $this->get_data() );
        $json_file_name = $this->get_json_filename();
        $this->download_json( $json_data, $json_file_name );
    }
}
