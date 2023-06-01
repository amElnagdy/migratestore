<?php

namespace MigrateWoo\Exporters;

class AccountsPrivacyExporter extends AbstractExporter{


	public function get_accounts_privacy_options() {

		$option_names = [

			'woocommerce_enable_guest_checkout',
			'woocommerce_enable_checkout_login_reminder',
			'woocommerce_enable_signup_and_login_from_checkout',
			'woocommerce_enable_myaccount_registration',
			'woocommerce_registration_generate_username',
			'woocommerce_registration_generate_password',
			'woocommerce_erasure_request_removes_order_data',
			'woocommerce_erasure_request_removes_download_data',
			'woocommerce_allow_bulk_remove_personal_data',
			'woocommerce_registration_privacy_policy_text',
			'woocommerce_checkout_privacy_policy_text',
			'woocommerce_delete_inactive_accounts',
			'woocommerce_trash_pending_orders',
			'woocommerce_trash_failed_orders',
			'woocommerce_trash_cancelled_orders',
			'woocommerce_anonymize_completed_orders',
		];

		return $this->get_options_values( $option_names );
	}

	public function get_data() {
		return $this->get_accounts_privacy_options();
	}

	public function get_csv_filename() {
		return 'migratewoo_accounts_privacy_options_' . date( 'Ymd_His' ) . '.csv';
	}

}
