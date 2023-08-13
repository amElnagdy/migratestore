<?php

namespace MigrateStore\Exporters\WooCommerce;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MigrateStore\Exporters\AbstractExporter;

class TaxOptionsExporter extends AbstractExporter {
    
    public function get_data() {
        $option_names = [
            'woocommerce_prices_include_tax',
            'woocommerce_tax_based_on',
            'woocommerce_shipping_tax_class',
            'woocommerce_tax_round_at_subtotal',
            'woocommerce_tax_classes',
            'woocommerce_tax_display_shop',
            'woocommerce_tax_display_cart',
            'woocommerce_price_display_suffix',
            'woocommerce_tax_total_display',
        ];
        
        return $this->get_options_values( $option_names );
    }
    
    
    public function get_json_filename() {
        return 'migratestore_tax_options_' . date( 'Ymd_His' ) . '.json';
    }
    
}
