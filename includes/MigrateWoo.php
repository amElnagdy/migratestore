<?php

namespace MigrateWoo;

use MigrateWoo\Exporters\AccountsPrivacyExporter;
use MigrateWoo\Exporters\EmailsOptionsExporter;
use MigrateWoo\Exporters\EndpointsExporter;
use MigrateWoo\Exporters\GeneralSettingsExporter;
use MigrateWoo\Exporters\ShippingOptionsExporter;
use MigrateWoo\Exporters\ShippingZonesExporter;
use MigrateWoo\Exporters\TaxOptionsExporter;
use MigrateWoo\Importers\AccountsPrivacyImporter;

class MigrateWoo {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'migratewoo_admin_menu' ) );
		add_action( 'admin_post_migratewoo_export_action', array( $this, 'handle_export_action' ) );
		add_action( 'admin_post_migratewoo_import_action', array( $this, 'handle_import_action' ) );
		add_action( 'init', array( $this, 'migratewoo_load_textdomain' ) );
	}

	public function migratewoo_load_textdomain() {
		load_plugin_textdomain( 'migratewoo', false, MIGRATEWOO_PLUGIN_DIR_PATH . 'languages' );
	}

	public function migratewoo_admin_menu() {
		add_menu_page( 'MigrateWoo', 'MigrateWoo', 'manage_options', 'migratewoo', array(
			$this,
			'migratewoo_admin_page'
		), 'dashicons-sort', 99 );
	}

	public function migratewoo_admin_page() {
		require_once MIGRATEWOO_PLUGIN_DIR_PATH . 'includes/templates/admin-page.php';
	}

	public function handle_export_action() {
		check_admin_referer( 'migratewoo_export_action_nonce' );

		if ( ! isset( $_POST['migratewoo_action'] ) ) {
			wp_die( 'Action not set' );
		}

		$action = sanitize_text_field( $_POST['migratewoo_action'] );

		$strategies = [
			'export_general_settings'         => 'MigrateWoo\Exporters\GeneralSettingsExporter',
			'export_shipping_zones'           => 'MigrateWoo\Exporters\ShippingZonesExporter',
			'export_shipping_options'         => 'MigrateWoo\Exporters\ShippingOptionsExporter',
			'export_tax_options'              => 'MigrateWoo\Exporters\TaxOptionsExporter',
			'export_accounts_privacy_options' => 'MigrateWoo\Exporters\AccountsPrivacyExporter',
			'export_def_emails_options'       => 'MigrateWoo\Exporters\EmailsOptionsExporter',
			'export_endpoints_options'        => 'MigrateWoo\Exporters\EndpointsExporter'
		];

		if ( ! isset( $strategies[ $action ] ) ) {
			wp_die( 'Invalid action' );
		}

		$class = $strategies[ $action ];
		if ( class_exists( $class ) ) {
			$strategy = new $class;
			$strategy->export();
		} else {
			wp_die( 'Exporter class not found' );
		}
	}


	public function handle_import_action() {
		check_admin_referer( 'migratewoo_import_action_nonce' );

		if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_die( 'File upload failed' );
		}

		$upload_overrides = [ 'test_form' => false ];
		$uploaded_file    = wp_handle_upload( $_FILES['csv_file'], $upload_overrides );

		if ( ! $uploaded_file || isset( $uploaded_file['error'] ) ) {
			wp_die( $uploaded_file['error'] ?? 'File upload failed' );
		}

		$filename = basename( $uploaded_file['file'] );

		// Mapping of filename to importer classes
		$importerStrategies = [
			'migratewoo_general_settings'         => 'MigrateWoo\Importers\GeneralSettingsImporter',
			'migratewoo_shipping_zones'           => 'MigrateWoo\Importers\ShippingZonesImporter',
			'migratewoo_accounts_privacy_options' => 'MigrateWoo\Importers\AccountsPrivacyImporter',
			'migratewoo_def_emails_options'       => 'MigrateWoo\Importers\EmailsOptionsImporter',
			'migratewoo_endpoints_options'        => 'MigrateWoo\Importers\EndpointsImporter'
		];

		foreach ( $importerStrategies as $key => $className ) {
			if ( strpos( $filename, $key ) !== false ) {
				if ( ! class_exists( $className ) ) {
					wp_die( "Importer class '$className' not found." );
				}

				$importer = new $className();
				$importer->import( $uploaded_file['file'] );

				return;
			}
		}

		wp_die( 'Invalid file format' );
	}

}
