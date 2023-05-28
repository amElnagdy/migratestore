<?php
$export_options = array(
	'export_general_settings'         => array(
		'label'       => __( 'Export General Settings', 'migratewoo' ),
		'description' => __( 'Export your WooCommerce general settings.', 'migratewoo' ),
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
);
?>

<div class="wrap">
	<h1>MigrateWoo</h1>
	<p><?php _e( 'Welcome to the MigrateWoo plugin. Use the options below to export and import your WooCommerce data.', 'migratewoo' ); ?></p>

	<h2><?php _e( 'Export Options', 'migratewoo' ); ?></h2>

	<?php foreach ( $export_options as $option => $info ) : ?>
		<p><?php echo esc_html( $info['description'] ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="migratewoo_action">
			<input type="hidden" name="migratewoo_action" value="<?php echo $option; ?>">
			<?php wp_nonce_field( 'migratewoo_action_nonce' ); ?>
			<input type="submit" value="<?php echo esc_attr( $info['label'] ); ?>">
		</form>
	<?php endforeach; ?>

	<h2><?php _e( 'Import Options', 'migratewoo' ); ?></h2>
</div>
