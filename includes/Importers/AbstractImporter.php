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
            throw new \RuntimeException('Could not parse JSON: ' . json_last_error_msg());
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

    protected function import_option($data)
    {
        // Canonical keys (v1.2.0+) with legacy 'option'/'value' fallback for v1.1.9 archives.
        $option_name  = sanitize_key( $data['option_name'] ?? $data['option'] );
        $option_value = $data['option_value'] ?? $data['value'];

        // If the option value is a serialized string, unserialize it
        if (is_serialized($option_value)) {
            $option_value = maybe_unserialize($option_value);
        }

        // If option value is an array, sanitize each value
        if (is_array($option_value)) {
            array_walk_recursive($option_value, function (&$value) {
                $value = sanitize_text_field($value);
            });
        } else {
            // If option value is a string, sanitize it
            $option_value = sanitize_text_field($option_value);
        }

        $allowed_option_data  = $this->exporter->get_data();
        $allowed_option_names = array_map(function ($item) {
            return $item['option_name'] ?? $item['option'];
        }, $allowed_option_data);

        if (! in_array($option_name, $allowed_option_names)) {
            throw new \RuntimeException("Invalid option name: $option_name");
        }
        // At this point, the option name and value should be safe to import
        update_option($option_name, $option_value);
    }

    public function cleanup($temp_dir)
    {
        // Cleaning up the migratestore_tmp folder after the import
        if (! $this->recursiveRemoveDirectory($temp_dir)) {
            wp_die('Unable to delete directory: ' . $temp_dir);
        }
        wp_safe_redirect(wp_get_referer());
        exit;
    }

    /**
     * Recursively remove a directory.
     * This will help with multi imports and also when the users try importing wrong zips that contain folders (it happens :D).
     *
     * @param string $directory The directory to remove
     *
     * @return bool True on success, false on failure
     */

    function recursiveRemoveDirectory($directory)
    {
        // Ensure the directory path has a trailing slash
        $directory = rtrim($directory, '/') . '/';
        // Get all items in the directory
        $items = glob($directory . '*', GLOB_MARK);
        foreach ($items as $item) {
            if (is_dir($item)) {
                // If the item in the directory is a directory itself, then
                // recursively call this method to remove that directory.
                if (! $this->recursiveRemoveDirectory($item)) {
                    return false;
                }
            } else {
                // If the item is a file, remove it
                if (! unlink($item)) {
                    return false;
                }
            }
        }
        // Remove the directory itself
        if (! rmdir($directory)) {
            return false;
        }

        return true;
    }
}
