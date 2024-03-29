<?php

namespace MigrateStore\Exporters\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MigrateStore\Exporters\AbstractExporter;

class GeneralSettingsExporter extends AbstractExporter {
    public function get_data() {
        $option_names = [
            'woocommerce_store_address',
            'woocommerce_store_address_2',
            'woocommerce_store_city',
            'woocommerce_default_country',
            'woocommerce_store_postcode',
            'woocommerce_allowed_countries',
            'woocommerce_all_except_countries',
            'woocommerce_specific_allowed_countries',
            'woocommerce_ship_to_countries',
            'woocommerce_specific_ship_to_countries',
            'woocommerce_default_customer_address',
            'woocommerce_calc_taxes',
            'woocommerce_enable_coupons',
            'woocommerce_calc_discounts_sequentially',
            'woocommerce_currency',
            'woocommerce_currency_pos',
            'woocommerce_price_thousand_sep',
            'woocommerce_price_decimal_sep',
            'woocommerce_price_num_decimals',
        ];
        
        return $this->get_options_values( $option_names );
    }
    
    public function get_json_filename() {
        return 'migratestore_general_settings_' . date( 'Ymd_His' ) . '.json';
    }
    
}
