<?php

namespace MigrateStore;

/**
 * Class Plugins_Checker
 * 
 * Handles dependency checking for the Migrate Store plugin.
 * 
 * Current dependencies:
 * - WooCommerce
 * - ZipArchive
 *
 * @package MigrateStore
 */
class Plugins_Checker {

	/**
	 * Stores any dependency errors encountered.
	 *
	 * @var array
	 */
	private $dependency_errors = array();

	/**
	 * Check all plugin dependencies.
	 *
	 * @return bool True if all dependencies are met, false otherwise.
	 */
	public function check_dependencies() {
		// Run all dependency checks
		$this->is_woocommerce_activated();
		$this->is_ziparchive_available();
		
		// If we have any errors, register the admin notice.
		if ( ! empty( $this->dependency_errors ) ) {
			add_action( 'admin_notices', array( $this, 'display_dependency_errors' ) );
			return false;
		}
		
		return true;
	}

	/**
	 * Check if WooCommerce is activated.
	 *
	 * @return bool True if WooCommerce is activated, false otherwise.
	 */
	public function is_woocommerce_activated() {
		// Load plugin.php if not already loaded.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Check for WooCommerce in single site or network activated plugins.
		if (
			! in_array(
				'woocommerce/woocommerce.php',
				apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
				true
			)
			&& ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' )
		) {
			$this->dependency_errors[] = __( 'Migrate Store requires WooCommerce to be installed and activated.', 'migratestore' );
			return false;
		}

		return true;
	}

	/**
	 * Check if ZipArchive is available on the server.
	 *
	 * @return bool True if ZipArchive is available, false otherwise.
	 */
	public function is_ziparchive_available() {
		if ( ! class_exists( '\ZipArchive' ) ) {
			$this->dependency_errors[] = __( 'Migrate Store requires the PHP ZipArchive extension to be enabled on your server. Please contact your hosting provider to enable this extension.', 'migratestore' );
			return false;
		}

		return true;
	}

	/**
	 * Display all dependency errors in a single admin notice.
	 */
	public function display_dependency_errors() {
		if ( empty( $this->dependency_errors ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Migrate Store Plugin Error:', 'migratestore' ); ?></strong></p>
			<ul style="list-style-type: disc; padding-left: 20px;">
				<?php foreach ( $this->dependency_errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'The plugin has been deactivated until these requirements are met.', 'migratestore' ); ?></p>
		</div>
		<?php
	}
}
