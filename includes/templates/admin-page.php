<?php

// Check if the transient is set for errors
if ( $error = get_transient( 'migratewoo_import_error' ) ) {
	// Clear the transient, so it doesn't show again on the next page load
	delete_transient( 'migratewoo_import_error' );

	// Display the error message
	echo '<div class="notice notice-error is-dismissible">';
	echo '<p>' . $error . '</p>';
	echo '</div>';
}

// Check if the transient is set
if ( get_transient( 'migratewoo_import_success' ) ) {
	// Clear the transient, so it doesn't show again on the next page load
	delete_transient( 'migratewoo_import_success' );

	// Display the success message
	echo '<div class="notice notice-success is-dismissible">';
	echo '<p>' . __( 'MigrateWoo has imported your file successfully', 'migratewoo' ) . '</p>';
	echo '</div>';
}


$export_options = array(
	'export_general_settings'         => array(
		'label'       => __( 'Export General Settings', 'migratewoo' ),
		'description' => __( 'Export WooCommerce general settings. 
		Found at WooCommerce → Settings → General page.', 'migratewoo' ),
	),
	'export_shipping_zones'           => array(
		'label'       => __( 'Export Shipping Zones', 'migratewoo' ),
		'description' => __( 'Export your WooCommerce shipping zones.', 'migratewoo' ),
	),
	'export_shipping_options'         => array(
		'label'       => __( 'Export Shipping Options', 'migratewoo' ),
		'description' => __( 'Export your WooCommerce shipping options.', 'migratewoo' ),
	),
	'export_tax_options'              => array(
		'label'       => __( 'Export Tax Options', 'migratewoo' ),
		'description' => __( 'Export your WooCommerce tax options.', 'migratewoo' ),
	),
	'export_accounts_privacy_options' => array(
		'label'       => __( 'Export Accounts & Privacy Options', 'migratewoo' ),
		'description' => __( 'Export your WooCommerce accounts and privacy options.', 'migratewoo' ),
	),
	'export_def_emails_options'       => array(
		'label'       => __( 'Export Default Emails Options', 'migratewoo' ),
		'description' => __( 'Export your WooCommerce default emails options.', 'migratewoo' ),
	),
	'export_endpoints_options'        => array(
		'label'       => __( 'Export Endpoints Options', 'migratewoo' ),
		'description' => __( 'Export your WooCommerce endpoints options.', 'migratewoo' ),
	),
);
?>

<div>
	<h1>MigrateWoo</h1>
	<p class="description"><?php _e( 'Welcome to the MigrateWoo plugin. Use the options below to export and import your WooCommerce data.', 'migratewoo' ); ?></p>

	<h2><?php _e( 'Export Options', 'migratewoo' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="migratewoo_export_action">
		<?php wp_nonce_field( 'migratewoo_export_action_nonce' ); ?>

		<?php foreach ( $export_options as $option => $info ) : ?>
			<div>
				<input type="radio" id="<?php echo $option; ?>" name="migratewoo_action" value="<?php echo $option; ?>">
				<strong><label for="<?php echo $option; ?>">
						<?php echo esc_html( $info['label'] ); ?>
					</label></strong>
				<p class="description"><?php echo esc_html( $info['description'] ); ?></p>
			</div>
		<?php endforeach; ?>

		<input class="button-primary" type="submit"
		       value="<?php echo esc_attr__( 'Export Selected', 'migratewoo' ); ?>">
	</form>

	<h2><?php _e( 'Import Options', 'migratewoo' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="migratewoo_import_action">
		<?php wp_nonce_field( 'migratewoo_import_action_nonce' ); ?>
		<input type="file" name="csv_file" accept=".csv">
		<input class="button-primary" type="submit" value="<?php echo esc_attr__( 'Import CSV', 'migratewoo' ); ?>">
	</form>
</div>
