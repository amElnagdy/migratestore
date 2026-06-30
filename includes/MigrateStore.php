<?php

namespace MigrateStore;

if (! defined('ABSPATH')) {
	exit;
}

use MigrateStore\Importers\AbstractImporter;

class MigrateStore
{

	public function __construct()
	{
		add_action('admin_menu', array($this, 'migratestore_admin_menu'));
		add_action('admin_post_migratestore_export_action', array($this, 'handle_export_action'));
		add_action('admin_post_migratestore_import_action', array($this, 'handle_import_action'));
		add_action('init', array($this, 'migratestore_load_textdomain'));
		add_filter('plugin_action_links_' . MIGRATESTORE_PLUGIN_BASENAME, array(
			$this,
			'add_plugin_page_settings_link'
		));
		add_action('admin_enqueue_scripts', array($this, 'migratestore_admin_enqueue_scripts'));
	}

	public function migratestore_load_textdomain()
	{
		load_plugin_textdomain('migratestore', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
	}

	public function migratestore_admin_menu()
	{
		add_menu_page('Migrate Store', 'Migrate Store', 'manage_woocommerce', 'migratestore', array(
			$this,
			'migratestore_admin_page'
		), 'dashicons-sort', 99);
		add_submenu_page('migratestore', __('Home', 'migratestore'), __('Home', 'migratestore'), 'manage_woocommerce', 'migratestore', array(
			$this,
			'migratestore_admin_page'
		));
		add_submenu_page('migratestore', __('Export', 'migratestore'), __('Export', 'migratestore'), 'manage_woocommerce', 'migratestore-export', array(
			$this,
			'migratestore_admin_export_page'
		));
		add_submenu_page('migratestore', __('Import', 'migratestore'), __('Import', 'migratestore'), 'manage_woocommerce', 'migratestore-import', array(
			$this,
			'migratestore_admin_import_page'
		));
	}

	/**
	 * Add settings and donate links to plugin listing page
	 *
	 * @param array $links Array of plugin action links
	 * @return array Modified array of plugin action links
	 */
	public function add_plugin_page_settings_link($links)
	{
		$new_links = array(
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				esc_url(admin_url('admin.php?page=migratestore')),
				esc_html__('Settings', 'migratestore')
			),
			'donate' => sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url('https://ko-fi.com/nagdy'),
				esc_html__('Donate', 'migratestore')
			)
		);

		return array_merge($new_links, $links);
	}

	public function migratestore_admin_export_page()
	{
		require_once MIGRATESTORE_PLUGIN_DIR_PATH . 'includes/admin/admin-export-page.php';
	}

	public function migratestore_admin_import_page()
	{
		require_once MIGRATESTORE_PLUGIN_DIR_PATH . 'includes/admin/admin-import-page.php';
	}

	public function migratestore_admin_page()
	{
		require_once MIGRATESTORE_PLUGIN_DIR_PATH . 'includes/admin/admin-page.php';
	}

	public function migratestore_admin_enqueue_scripts($hook)
	{
		// Only enqueue on our plugin pages
		if (!isset($_GET['page']) || !in_array($_GET['page'], array(
			'migratestore',
			'migratestore-export',
			'migratestore-import'
		))) {
			return;
		}

		// Enqueue CSS on all plugin pages
		wp_enqueue_style(
			'migratestore-admin', 
			MIGRATESTORE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MIGRATESTORE_VERSION
		);

		// Enqueue JS only on import page
		if (isset($_GET['page']) && $_GET['page'] === 'migratestore-import') {
			wp_register_script(
				'migratestore-admin',
				MIGRATESTORE_PLUGIN_URL . 'assets/js/admin.js',
				array(),
				MIGRATESTORE_VERSION,
				true // Load in footer
			);
			wp_enqueue_script('migratestore-admin');
		}
	}

	public function handle_export_action()
	{
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export store settings.', 'migratestore' ), 403 );
		}
		check_admin_referer('migratestore_export_action_nonce');

		if (! isset($_POST['migratestore_action'])) {
			wp_die('Invalid action');
		}

		$action = sanitize_text_field($_POST['migratestore_action']);

		$strategies = [
			'export_general_settings'         => 'MigrateStore\Exporters\WooCommerce\GeneralSettingsExporter',
			'export_shipping_zones'           => 'MigrateStore\Exporters\WooCommerce\ShippingZonesExporter',
			'export_shipping_options'         => 'MigrateStore\Exporters\WooCommerce\ShippingOptionsExporter',
			'export_tax_options'              => 'MigrateStore\Exporters\WooCommerce\TaxOptionsExporter',
			'export_accounts_privacy_options' => 'MigrateStore\Exporters\WooCommerce\AccountsPrivacyExporter',
			'export_def_emails_options'       => 'MigrateStore\Exporters\WooCommerce\EmailsOptionsExporter',
			'export_endpoints_options'        => 'MigrateStore\Exporters\WooCommerce\EndpointsExporter',
			'export_shipping_classes'        => 'MigrateStore\Exporters\WooCommerce\ShippingClassesExporter'
		];

		if (! isset($strategies[$action])) {
			wp_die('Invalid action');
		}

		$class = $strategies[$action];
		if (class_exists($class)) {
			$strategy = new $class;
			$strategy->export();
		} else {
			wp_die('Exporter class not found');
		}
	}

	public function handle_import_action()
	{
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to import store settings.', 'migratestore' ), 403 );
		}
		WP_Filesystem();
		check_admin_referer('migratestore_import_action_nonce');

		if ( ! isset( $_FILES['json_zip_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['json_zip_file']['error'] ) {
			wp_die('File upload failed');
		}

		// Read the upload size from the superglobal once, existence-checked and
		// cast to int (the cast also satisfies unslash/sanitize for numeric input).
		$uploaded_file_size = isset( $_FILES['json_zip_file']['size'] ) ? (int) $_FILES['json_zip_file']['size'] : 0;

		$max_size = (int) apply_filters( 'migratestore_max_upload_size', 10 * MB_IN_BYTES );
		if ( $uploaded_file_size > $max_size ) {
			wp_die( sprintf(
				/* translators: %s: maximum allowed upload size, e.g. "10 MB". */
				esc_html__( 'The uploaded file exceeds the maximum allowed size of %s.', 'migratestore' ),
				esc_html( size_format( $max_size ) )
			) );
		}

		$upload_overrides = ['test_form' => false];
		$uploaded_file    = wp_handle_upload($_FILES['json_zip_file'], $upload_overrides);

		if (! $uploaded_file || isset($uploaded_file['error'])) {
			wp_die( esc_html( $uploaded_file['error'] ?? 'File upload failed' ) );
		}

		$uploaded_file_name     = isset( $_FILES['json_zip_file']['name'] )
			? sanitize_file_name( wp_unslash( $_FILES['json_zip_file']['name'] ) )
			: '';
		$uploaded_file_basename = basename($uploaded_file_name, '.zip');

		$filetype      = wp_check_filetype_and_ext( $uploaded_file['file'], $uploaded_file_name );
		$allowed_mimes = array( 'application/zip', 'application/x-zip-compressed' );
		if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
			$this->cleanup_import_artifacts( $uploaded_file['file'], null );
			wp_die( esc_html__( 'Invalid file type. Please upload a .zip file exported by Migrate Store.', 'migratestore' ) );
		}

		$unzip_folder = wp_upload_dir()['basedir'] . '/migratestore_tmp';

		// Check if we have sufficient permission to create the folder.
		// wp_mkdir_p() returns true if the directory already exists or is created.
		if ( ! wp_mkdir_p( $unzip_folder ) ) {
			$this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
			wp_die('Failed to create tmp directory: insufficient permission');
		}

		$zip_check = new \ZipArchive();
		if ( $zip_check->open( $uploaded_file['file'] ) !== true ) {
			$this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
			wp_die( esc_html__( 'The uploaded file could not be read as a valid ZIP archive.', 'migratestore' ) );
		}
		for ( $i = 0; $i < $zip_check->numFiles; $i++ ) {
			$entry_name = $zip_check->getNameIndex( $i );
			if ( $entry_name === false ) {
				continue;
			}
			$normalized  = str_replace( '\\', '/', (string) $entry_name );
			$segments    = explode( '/', $normalized );
			$is_absolute = ( substr( $normalized, 0, 1 ) === '/' ) || (bool) preg_match( '#^[A-Za-z]:/#' , $normalized );
			$has_dotdot  = in_array( '..', $segments, true );
			if ( $is_absolute || $has_dotdot ) {
				$zip_check->close();
				$this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
				wp_die( esc_html__( 'The uploaded archive contains unsafe file paths and was rejected.', 'migratestore' ) );
			}
		}
		$zip_check->close();

		$unzipped = unzip_file($uploaded_file['file'], $unzip_folder);
		if (is_wp_error($unzipped)) {
			$this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
			wp_die( esc_html( 'Failed to unzip file: ' . $unzipped->get_error_message() ) );
		}

		$json_files = glob($unzip_folder . '/migratestore_*.json');
		if (empty($json_files)) {
			$this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
			wp_die('No matching JSON file found in uploaded ZIP.');
		}

		// The extracted file name must match importer strategy key
		// so we need to remove the date and time from the file name
		$json_file      = $json_files[0];
		$full_filename = basename($json_file, '.json');
		$filename = preg_replace('/_\d{8}_\d{6}$/', '', $full_filename);

		$importerStrategies = [
			'migratestore_general_settings'         => 'MigrateStore\Importers\WooCommerce\GeneralSettingsImporter',
			'migratestore_zones'                    => 'MigrateStore\Importers\WooCommerce\ShippingZonesImporter',
			'migratestore_accounts_privacy_options' => 'MigrateStore\Importers\WooCommerce\AccountsPrivacyImporter',
			'migratestore_emails_settings'          => 'MigrateStore\Importers\WooCommerce\EmailsOptionsImporter',
			'migratestore_endpoints_options'        => 'MigrateStore\Importers\WooCommerce\EndpointsImporter',
			'migratestore_shipping_options'         => 'MigrateStore\Importers\WooCommerce\ShippingOptionsImporter',
			'migratestore_tax_options'              => 'MigrateStore\Importers\WooCommerce\TaxOptionsImporter',
			'migratestore_shipping_classes'         => 'MigrateStore\Importers\WooCommerce\ShippingClassesImporter'
		];

		if ( ! array_key_exists( $filename, $importerStrategies ) ) {
			$this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
			wp_die( esc_html__( 'Unrecognized import file. This file was not produced by Migrate Store.', 'migratestore' ) );
		}
		$className = $importerStrategies[ $filename ];

		if (! class_exists($className)) {
			$this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
			wp_die( esc_html( "Importer class '$className' not found." ) );
		}

		$importer = new $className();
		try {
			$importer->import($json_file);
			
			// Get import type data before setting the transient
			$import_type_data = $this->get_import_type_data(basename($json_file, '.json'));
			
			set_transient('migratestore_import_success', [
				'success' => true,
				'type_data' => $import_type_data
			], 60);

			// Surface any shipping methods that were skipped because they are not
			// registered on this site (Phase 3 — shipping method filter).
			if ( method_exists( $importer, 'get_skipped_methods' ) ) {
				$skipped_methods = $importer->get_skipped_methods();
				if ( ! empty( $skipped_methods ) ) {
					$skipped_methods = array_map( 'sanitize_text_field', $skipped_methods );
					set_transient( 'migratestore_import_warning', $skipped_methods, 60 );
				}
			}
			
		} catch (\Exception $e) {
			set_transient('migratestore_import_error', $e->getMessage(), 60);
		}

		// Remove the moved upload; $importer->cleanup() removes the temp dir then redirects.
		if ( ! empty( $uploaded_file['file'] ) && file_exists( $uploaded_file['file'] ) ) {
			wp_delete_file( $uploaded_file['file'] );
		}

		// Using cleanup to delete the tmp folder
		$importer->cleanup($unzip_folder);
	}

	/**
	 * Remove import artifacts (the moved upload file + the temp extraction dir).
	 * Called on EVERY exit path of handle_import_action() — success, exception,
	 * and each early wp_die() bail — so no orphaned files remain (FR-007).
	 *
	 * @param string|null $uploaded_file_path Absolute path to the moved upload, or null.
	 * @param string|null $temp_dir           Absolute path to the temp extract dir, or null.
	 */
	private function cleanup_import_artifacts( $uploaded_file_path, $temp_dir ) {
		if ( ! empty( $uploaded_file_path ) && file_exists( $uploaded_file_path ) ) {
			wp_delete_file( $uploaded_file_path );
		}
		if ( ! empty( $temp_dir ) && is_dir( $temp_dir ) ) {
			// Recursively remove the temp extraction tree through WP_Filesystem so
			// deletion works on hosts where PHP does not own the files (FTP/SSH).
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			if ( $wp_filesystem ) {
				$wp_filesystem->delete( $temp_dir, true ); // true = recursive
			}
		}
	}

	/**
	 * Get the import type data from the filename
	 * We're using this method to display a URL to the user based on the import type
	 * @param string $filename
	 */
	private function get_import_type_data($filename)
	{
		$import_links = [
			'migratestore_general_settings'         => [
				'url' => admin_url('admin.php?page=wc-settings'),
				'message' => __('View WooCommerce General Settings', 'migratestore')
			],
			'migratestore_zones'                    => [
				'url' => admin_url('admin.php?page=wc-settings&tab=shipping&section'),
				'message' => __('View WooCommerce Shipping Zones', 'migratestore')
			],
			'migratestore_accounts_privacy_options' => [
				'url' => admin_url('admin.php?page=wc-settings&tab=account'),
				'message' => __('View WooCommerce Accounts & Privacy Settings', 'migratestore')
			],
			'migratestore_emails_settings'          => [
				'url' => admin_url('admin.php?page=wc-settings&tab=email'),
				'message' => __('View WooCommerce Emails Settings', 'migratestore')
			],
			'migratestore_shipping_options'         => [
				'url' => admin_url('admin.php?page=wc-settings&tab=shipping&section=options'),
				'message' => __('View WooCommerce Shipping Settings', 'migratestore')
			],
			'migratestore_endpoints_options'        => [
				'url' => admin_url('admin.php?page=wc-settings&tab=advanced&section'),
				'message' => __('View WooCommerce Endpoints Settings', 'migratestore')
			],
			'migratestore_tax_options'              => [
				'url' => admin_url('admin.php?page=wc-settings&tab=tax'),
				'message' => __('View WooCommerce Tax Settings', 'migratestore')
			],
			'migratestore_shipping_classes'         => [
				'url' => admin_url('admin.php?page=wc-settings&tab=shipping&section=classes'),
				'message' => __('View WooCommerce Shipping Classes', 'migratestore')
			]
		];

		$import_type = preg_replace('/_\d{8}_\d{6}$/', '', $filename);
		return isset($import_links[$import_type]) ? $import_links[$import_type] : null;
	}
}
