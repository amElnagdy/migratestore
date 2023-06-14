<?php

namespace MigrateStore\Exporters\WooCommerce;

use MigrateStore\Exporters\AbstractExporter;

class EndpointsExporter extends AbstractExporter{


	public function get_data() {

		$option_names = [
			'woocommerce_checkout_pay_endpoint',
			'woocommerce_checkout_order_received_endpoint',
			'woocommerce_myaccount_add_payment_method_endpoint',
			'woocommerce_myaccount_delete_payment_method_endpoint',
			'woocommerce_myaccount_set_default_payment_method_endpoint',
			'woocommerce_myaccount_orders_endpoint',
			'woocommerce_myaccount_view_order_endpoint',
			'woocommerce_myaccount_downloads_endpoint',
			'woocommerce_myaccount_edit_account_endpoint',
			'woocommerce_myaccount_address_endpoint',
			'woocommerce_myaccount_payment_methods_endpoint',
			'woocommerce_myaccount_lost_password_endpoint',
			'woocommerce_myaccount_customer-logout_endpoint',
		];

		return $this->get_options_values( $option_names );

	}

	public function get_json_filename() {
		return 'migratestore_endpoints_options_' . date( 'Ymd_His' ) . '.json';
	}

}
