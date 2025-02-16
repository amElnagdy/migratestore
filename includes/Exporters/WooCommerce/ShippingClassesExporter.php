<?php

namespace MigrateStore\Exporters\WooCommerce;

use MigrateStore\Exporters\AbstractExporter;

class ShippingClassesExporter extends AbstractExporter {
    
    public function get_data() {
        $shipping_classes = get_terms([
            'taxonomy' => 'product_shipping_class',
            'hide_empty' => false,
        ]);

        $formatted_classes = [];
        foreach ($shipping_classes as $class) {
            $formatted_classes[] = [
                'name' => $class->name,
                'slug' => $class->slug,
                'description' => $class->description,
            ];
        }

        return $formatted_classes;
    }
    public function get_json_filename() {
        return 'migratestore_shipping_classes_' . date( 'Ymd_His' ) . '.json';
    }
}
