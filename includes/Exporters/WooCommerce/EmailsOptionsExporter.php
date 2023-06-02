<?php

namespace MigrateWoo\Exporters\WooCommerce;

use MigrateWoo\Exporters\AbstractExporter;

class EmailsOptionsExporter extends AbstractExporter {


	public function get_def_emails_options() {
		$option_names = [
			'woocommerce_email_from_name	',
			'woocommerce_email_from_address	',
			'woocommerce_email_header_image	',
			'woocommerce_email_footer_text	',
			'woocommerce_email_base_color	',
			'woocommerce_email_background_color	',
			'woocommerce_email_body_background_color',
			'woocommerce_email_body_text_color	',
			'woocommerce_new_order_settings',
			'woocommerce_cancelled_order_settings',
			'woocommerce_failed_order_settings',
			'woocommerce_customer_on_hold_order_settings',
			'woocommerce_customer_processing_order_settings',
			'woocommerce_customer_completed_order_settings',
			'woocommerce_customer_completed_order_settings',
			'woocommerce_customer_invoice_settings',
			'woocommerce_customer_note_settings	',
			'woocommerce_customer_reset_password_settings',
			'woocommerce_customer_new_account_settings'
		];

		return $this->get_options_values( $option_names );

	}

	public function get_data() {
		return $this->get_def_emails_options();
	}

	public function get_csv_filename() {
		return 'migratewoo_emails_options_' . date( 'Ymd_His' ) . '.csv';
	}

}
