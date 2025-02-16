<?php

namespace MigrateStore\Importers\WooCommerce;

if (! defined('ABSPATH')) {
    exit;
}

use MigrateStore\Exporters\WooCommerce\ShippingClassesExporter;
use MigrateStore\Importers\AbstractImporter;

class ShippingClassesImporter extends AbstractImporter
{

    public function __construct()
    {
        parent::__construct(new ShippingClassesExporter());
    }

    public function import($json_file_path)
    {
        $data = $this->get_json_data($json_file_path);

        if ( empty( $data ) || ! is_array( $data ) ) {
            throw new \Exception( 'Invalid shipping classes data' );
        }

        foreach ($data as $shipping_class) {
            $existing_class = get_term_by('slug', $shipping_class['slug'], 'product_shipping_class');

            if ($existing_class) {
                $this->update_shipping_class($existing_class, $shipping_class);
            } else {
                $this->create_shipping_class($shipping_class);
            }
        }

        return true;
    }

    private function create_shipping_class($shipping_class)
    {
        $result = wp_insert_term($shipping_class['name'], 'product_shipping_class', [
            'slug' => $shipping_class['slug'],
            'description' => $shipping_class['description'],
        ]);

        if (is_wp_error($result)) {
            return false;
        }
        return $result['term_id'];
    }
    private function update_shipping_class($existing_class, $shipping_class)
    {
        $term_id = $existing_class->term_id;
        $name = $shipping_class['name'];
        $slug = $shipping_class['slug'];
        $description = $shipping_class['description'];

        wp_update_term($term_id, 'product_shipping_class', [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
        ]);
    }
}
