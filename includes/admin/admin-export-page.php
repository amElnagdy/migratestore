<?php
$export_options = array(
	'export_general_settings'         => array(
		'label'       => __( 'General Settings', 'migratewoo' ),
		'description' => __( 'Export WooCommerce general settings. 
		Found at WooCommerce → Settings → General page.', 'migratewoo' ),
	),
	'export_shipping_zones'           => array(
		'label'       => __( 'Shipping Zones', 'migratewoo' ),
		'description' => __( 'Export WooCommerce shipping zones. This will export WooCommerce → Settings → Shipping → Zones.
		Including the Zones, the locations and the default shipping methods (Flat Rate, Free Shipping, and Local Pickup).', 'migratewoo' ),
	),
	'export_shipping_options'         => array(
		'label'       => __( 'Shipping Options', 'migratewoo' ),
		'description' => __( 'Export  WooCommerce shipping options. Found at WooCommerce → Settings → Shipping → Shipping options.', 'migratewoo' ),
	),
	'export_tax_options'              => array(
		'label'       => __( 'Tax Options', 'migratewoo' ),
		'description' => __( 'Export  WooCommerce tax options. Found at WooCommerce → Settings → Tax.', 'migratewoo' ),
	),
	'export_accounts_privacy_options' => array(
		'label'       => __( 'Accounts & Privacy Options', 'migratewoo' ),
		'description' => __( 'Export WooCommerce accounts and privacy options. Found at WooCommerce → Settings → Accounts & Privacy.', 'migratewoo' ),
	),
	'export_def_emails_options'       => array(
		'label'       => __( 'Emails Options', 'migratewoo' ),
		'description' => __( 'Export WooCommerce default emails options. This includes the email sender options, email template, and the email options for all default WooCommerce emails such as New order, Cancelled order, etc. Found at WooCommerce → Settings → Emails.', 'migratewoo' ),
	),
	'export_endpoints_options'        => array(
		'label'       => __( 'Endpoints Options', 'migratewoo' ),
		'description' => __( 'Export  WooCommerce endpoints options. Found at WooCommerce → Settings → Advanced.', 'migratewoo' ),
	),
);
?>
<div class="wrap">
<h1><?php _e( 'Export Options', 'migratewoo' ); ?></h1>

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
</div>
