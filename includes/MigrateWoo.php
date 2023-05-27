<?php

namespace MigrateWoo;
use MigrateWoo\Exporters\GeneralSettingsExporter;
use MigrateWoo\Exporters\ShippingExporter;
use MigrateWoo\Exporters\TaxOptionsExporter;

class MigrateWoo {

	public function __construct() {
		add_action('admin_menu', array($this, 'migratewoo_admin_menu'));
		add_action('admin_post_migratewoo_action', array($this, 'handle_export_import_actions'));
		add_action( 'init',array($this,'migratewoo_load_textdomain' ));
	}

	public function migratewoo_load_textdomain() {
		load_plugin_textdomain( 'migratewoo', false, MIGRATEWOO_PLUGIN_DIR_PATH . 'languages' );
	}

	public function migratewoo_admin_menu() {
		add_menu_page('MigrateWoo', 'MigrateWoo', 'manage_options', 'migratewoo', array($this, 'migratewoo_admin_page'), 'dashicons-sort', 99);
	}

	public function migratewoo_admin_page() {
		require_once MIGRATEWOO_PLUGIN_DIR_PATH . 'includes/templates/admin-page.php';
	}

	public function handle_export_import_actions() {
		if (isset($_POST['migratewoo_action'])) {
			check_admin_referer('migratewoo_action_nonce');

			$action = sanitize_text_field($_POST['migratewoo_action']);

			switch ($action) {
				case 'export_general_settings':
					$this->export_woocommerce_general_settings();
					break;
				case 'export_shipping_settings':
					$this->export_shipping_settings();
					break;
				case 'export_tax_options':
					$this->export_tax_options();
					break;
				case 'import_woocommerce_settings':
					$this->import_woocommerce_settings();
					break;
				case 'import_shipping_settings':
					$this->import_shipping_settings();
					break;
				case 'import_tax_settings':
					$this->import_tax_settings();
					break;
				default:
					wp_die('Invalid action');
			}
		}
	}

	public function export_woocommerce_general_settings() {
		$exporter = new GeneralSettingsExporter();
		$exporter->export();
	}

	public function export_shipping_settings() {
		$exporter = new ShippingExporter();
		$exporter->export();
		exit;
	}

	public function export_tax_options() {
		$exporter = new TaxOptionsExporter();
		$exporter->export();
		exit;
	}

	public function import_woocommerce_settings() {
		// Implement your import logic here
	}

	public function import_shipping_settings() {
		// Implement your import logic here
	}

	public function import_tax_settings() {
		// Implement your import logic here
	}
}
