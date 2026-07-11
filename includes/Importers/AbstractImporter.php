<?php

namespace MigrateStore\Importers;

if (! defined('ABSPATH')) {
    exit;
}

use MigrateStore\Exporters\AbstractExporter;

abstract class AbstractImporter
{

    protected AbstractExporter $exporter;

    /**
     * Children pass their paired exporter: each concrete importer declares a
     * zero-arg constructor and calls parent::__construct( new XExporter() ).
     * Instantiated via `new $className()` in MigrateStore::handle_import_action().
     */
    public function __construct(AbstractExporter $exporter)
    {
        $this->exporter = $exporter;
    }

    public function get_json_data($json_file_path)
    {
        WP_Filesystem();
        global $wp_filesystem;

        if (!$wp_filesystem) {
            throw new \RuntimeException('Could not initialize WP_Filesystem.');
        }

        $contents = $wp_filesystem->get_contents($json_file_path);
        if ($contents === false) {
            throw new \RuntimeException('Could not open JSON file.');
        }

        $data = json_decode($contents, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException( esc_html( 'Could not parse JSON: ' . json_last_error_msg() ) );
        }

        return $data;
    }

    public function import($json_file_path)
    {
        $data = $this->get_json_data($json_file_path);

        foreach ($data as $item) {
            // Accept canonical (option_name/option_value) or legacy (option/value) entries.
            if ( ( isset( $item['option_name'], $item['option_value'] ) ) || ( isset( $item['option'], $item['value'] ) ) ) {
                $this->import_option($item);
            }
        }
    }

    /**
     * Option names whose values may contain WooCommerce-permitted HTML and must be
     * sanitized with wp_kses_post() instead of sanitize_text_field() on import.
     *
     * Base returns an empty array so every existing importer keeps its current
     * behavior. Subclasses override to opt specific fields in.
     *
     * @return string[]
     */
    protected function get_rich_text_option_names(): array
    {
        return [];
    }

    protected function import_option($data)
    {
        // Canonical key (v1.2.0+) with legacy 'option' fallback for v1.1.9 archives.
        $option_name = sanitize_key( $data['option_name'] ?? $data['option'] );

        // Enforce the allowlist BEFORE reading or deserializing the value: a rejected
        // option must never reach unserialize(), or an attacker-crafted serialized
        // object could fire gadget magic methods (PHP object injection) on a name we
        // would reject anyway.
        $allowed_option_data  = $this->exporter->get_data();
        $allowed_option_names = array_map(function ($item) {
            return $item['option_name'] ?? $item['option'];
        }, $allowed_option_data);

        if (! in_array($option_name, $allowed_option_names, true)) {
            throw new \RuntimeException( esc_html( "Invalid option name: $option_name" ) );
        }

        // Canonical 'option_value' with legacy 'value' fallback for v1.1.9 archives.
        $option_value = $data['option_value'] ?? $data['value'];

        // If the option value is a serialized string, unserialize it without
        // instantiating objects. allowed_classes => false turns any serialized
        // object into a harmless __PHP_Incomplete_Class, while arrays and scalars
        // from legacy archives still decode correctly.
        if ( is_serialized( $option_value ) ) {
            $option_value = unserialize( $option_value, array( 'allowed_classes' => false ) );
        }

        // Rich-text options (e.g. WooCommerce email footer) keep the HTML that
        // WooCommerce itself permits; everything else is stripped to plain text.
        if ( in_array( $option_name, $this->get_rich_text_option_names(), true ) ) {
            $option_value = wp_kses_post( (string) $option_value );
        } elseif ( is_array( $option_value ) ) {
            // If option value is an array, sanitize each value
            array_walk_recursive( $option_value, function ( &$value ) {
                $value = sanitize_text_field( $value );
            } );
        } else {
            // If option value is a string, sanitize it
            $option_value = sanitize_text_field( $option_value );
        }

        // At this point, the option name and value should be safe to import
        update_option($option_name, $option_value);
    }

    public function cleanup($temp_dir)
    {
        // Cleaning up the migratestore_tmp folder after the import. Deletion goes
        // through WP_Filesystem so it works on hosts where PHP does not own the
        // files (FTP/SSH), instead of raw unlink()/rmdir() which silently fail there.
        global $wp_filesystem;
        if (! function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        if ($wp_filesystem && ! $wp_filesystem->delete($temp_dir, true)) { // true = recursive
            wp_die('Unable to delete directory: ' . esc_html($temp_dir));
        }
        wp_safe_redirect(wp_get_referer());
        exit;
    }
}
