<?php

namespace MigrateStore;

use MigrateStore\Importers\AbstractImporter;

class MigrateStore {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'migratestore_admin_menu' ) );
		add_action( 'admin_post_migratestore_export_action', array( $this, 'handle_export_action' ) );
		add_action( 'admin_post_migratestore_import_action', array( $this, 'handle_import_action' ) );
		add_action( 'init', array( $this, 'migratestore_load_textdomain' ) );
		add_filter( 'plugin_action_links_' . MIGRATESTORE_PLUGIN_BASENAME, array(
			$this,
			'add_plugin_page_settings_link'
		) );
		add_action( 'admin_enqueue_scripts', array( $this, 'migratestore_admin_enqueue_scripts' ) );
	}

	public function migratestore_load_textdomain() {
		load_plugin_textdomain( 'migratestore', false, MIGRATESTORE_PLUGIN_DIR_PATH . 'languages' );
	}

	public function migratestore_admin_menu() {
		add_menu_page( 'MigrateStore', 'MigrateStore', 'manage_options', 'migratestore', array(
			$this,
			'migratestore_admin_page'
		), 'dashicons-sort', 99 );
		add_submenu_page( 'migratestore', 'Home', 'Home', 'manage_options', 'migratestore', array(
			$this,
			'migratestore_admin_page'
		) );
		add_submenu_page( 'migratestore', 'Export', 'Export', 'manage_options', 'migratestore-export', array(
			$this,
			'migratestore_admin_export_page'
		) );
		add_submenu_page( 'migratestore', 'Import', 'Import', 'manage_options', 'migratestore-import', array(
			$this,
			'migratestore_admin_import_page'
		) );
	}

	public function add_plugin_page_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=migratestore' ) . '">' . __( 'Settings', 'migratestore' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	public function migratestore_admin_export_page() {
		require_once MIGRATESTORE_PLUGIN_DIR_PATH . 'includes/admin/admin-export-page.php';
	}

	public function migratestore_admin_import_page() {
		require_once MIGRATESTORE_PLUGIN_DIR_PATH . 'includes/admin/admin-import-page.php';
	}

	public function migratestore_admin_page() {
		require_once MIGRATESTORE_PLUGIN_DIR_PATH . 'includes/admin/admin-page.php';
	}

	public function migratestore_admin_enqueue_scripts() {
		wp_enqueue_style( 'migratestore-admin', MIGRATESTORE_PLUGIN_URL . 'assets/css/admin.css', array(), MIGRATESTORE_VERSION );
	}

	public function handle_export_action() {
		check_admin_referer( 'migratestore_export_action_nonce' );

		if ( ! isset( $_POST['migratestore_action'] ) ) {
			wp_die( 'Invalid action' );
		}

		$action = sanitize_text_field( $_POST['migratestore_action'] );

		$strategies = [
			'export_general_settings'         => 'MigrateStore\Exporters\WooCommerce\GeneralSettingsExporter',
			'export_shipping_zones'           => 'MigrateStore\Exporters\WooCommerce\ShippingZonesExporter',
			'export_shipping_options'         => 'MigrateStore\Exporters\WooCommerce\ShippingOptionsExporter',
			'export_tax_options'              => 'MigrateStore\Exporters\WooCommerce\TaxOptionsExporter',
			'export_accounts_privacy_options' => 'MigrateStore\Exporters\WooCommerce\AccountsPrivacyExporter',
			'export_def_emails_options'       => 'MigrateStore\Exporters\WooCommerce\EmailsOptionsExporter',
			'export_endpoints_options'        => 'MigrateStore\Exporters\WooCommerce\EndpointsExporter'
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
		WP_Filesystem();
		check_admin_referer( 'migratestore_import_action_nonce' );

		if ( ! isset( $_FILES['json_zip_file'] ) || $_FILES['json_zip_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_die( 'File upload failed' );
		}

		$upload_overrides = [ 'test_form' => false ];
		$uploaded_file    = wp_handle_upload( $_FILES['json_zip_file'], $upload_overrides );

		if ( ! $uploaded_file || isset( $uploaded_file['error'] ) ) {
			wp_die( $uploaded_file['error'] ?? 'File upload failed' );
		}

		$uploaded_file_name     = sanitize_file_name( $_FILES['json_zip_file']['name'] );
		$uploaded_file_basename = basename( $uploaded_file_name, '.zip' );

		$unzip_folder = wp_upload_dir()['basedir'] . '/migratestore_tmp';

		// Check if we have sufficient permission to create the folder
		if ( ! is_dir( $unzip_folder ) && ! @mkdir( $unzip_folder ) && ! is_dir( $unzip_folder ) ) {
			wp_die( 'Failed to create tmp directory: insufficient permission' );
		}

		$unzipped = unzip_file( $uploaded_file['file'], $unzip_folder );
		if ( is_wp_error( $unzipped ) ) {
			wp_die( 'Failed to unzip file: ' . $unzipped->get_error_message() );
		}

		$json_files = glob( $unzip_folder . '/' . $uploaded_file_basename . '*.json' );
		if ( empty( $json_files ) ) {
			wp_die( 'No matching JSON file found in uploaded ZIP.' );
		}

		$json_file      = $json_files[0];
		$filename_parts = explode( '_', basename( $json_file, '.json' ), 3 );
		$filename       = $filename_parts[0] . '_' . $filename_parts[1];

		$importerStrategies = [
			'migratestore_general_settings'         => 'MigrateStore\Importers\WooCommerce\GeneralSettingsImporter',
			'migratestore_zones'                    => 'MigrateStore\Importers\WooCommerce\ShippingZonesImporter',
			'migratestore_accounts_privacy_options' => 'MigrateStore\Importers\WooCommerce\AccountsPrivacyImporter',
			'migratestore_emails_settings'          => 'MigrateStore\Importers\WooCommerce\EmailsOptionsImporter',
			'migratestore_endpoints_options'        => 'MigrateStore\Importers\WooCommerce\EndpointsImporter',
			'migratestore_shipping_options'         => 'MigrateStore\Importers\WooCommerce\ShippingOptionsImporter',
			'migratestore_tax_options'              => 'MigrateStore\Importers\WooCommerce\TaxOptionsImporter'
		];

		$valid_file = false;
		foreach ( $importerStrategies as $key => $value ) {
			if ( strpos( $key, $filename ) !== false ) {
				$valid_file = true;
				$className  = $value;
				break;
			}
		}

		if ( ! $valid_file ) {
			wp_die( 'Invalid file name.' );
		}

		if ( ! class_exists( $className ) ) {
			wp_die( "Importer class '$className' not found." );
		}

		$importer = new $className();
		try {
			$importer->import( $json_file );
			set_transient( 'migratestore_import_success', true, 60 );
		} catch ( \Exception $e ) {
			set_transient( 'migratestore_import_error', $e->getMessage(), 60 );
		}

		//Using cleanup to delete the tmp folder
		$importer->cleanup( $unzip_folder );
	}


}
