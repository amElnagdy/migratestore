<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly
$export_options = array(
    'export_general_settings'         => array(
        'label'       => __( 'General Settings', 'migratestore' ),
        'description' => __( 'Export WooCommerce general settings.
		Found at WooCommerce → Settings → General page.', 'migratestore' ),
    ),
    'export_tax_options'              => array(
        'label'       => __( 'Tax Options', 'migratestore' ),
        'description' => __( 'Export  WooCommerce tax options. Found at WooCommerce → Settings → Tax.', 'migratestore' ),
    ),
    'export_shipping_zones'           => array(
        'label'       => __( 'Shipping Zones', 'migratestore' ),
        'description' => __( 'Export WooCommerce shipping zones. This will export WooCommerce → Settings → Shipping → Zones.
		Including the Zones, the locations and the default shipping methods (Flat Rate, Free Shipping, and Local Pickup).', 'migratestore' ),
    ),
    'export_shipping_options'         => array(
        'label'       => __( 'Shipping Options', 'migratestore' ),
        'description' => __( 'Export  WooCommerce shipping options. Found at WooCommerce → Settings → Shipping → Shipping options.', 'migratestore' ),
    ),
    'export_accounts_privacy_options' => array(
        'label'       => __( 'Accounts & Privacy Options', 'migratestore' ),
        'description' => __( 'Export WooCommerce accounts and privacy options. Found at WooCommerce → Settings → Accounts & Privacy.', 'migratestore' ),
    ),
    'export_def_emails_options'       => array(
        'label'       => __( 'Emails Options', 'migratestore' ),
        'description' => __( 'Export WooCommerce default emails options. This includes the email sender options, email template, and the email options for all default WooCommerce emails such as New order, Cancelled order, etc. Found at WooCommerce → Settings → Emails.', 'migratestore' ),
    ),
    'export_endpoints_options'        => array(
        'label'       => __( 'Endpoints Options', 'migratestore' ),
        'description' => __( 'Export  WooCommerce endpoints options. Found at WooCommerce → Settings → Advanced.', 'migratestore' ),
    ),
);
?>
<div class="wrap migratestore">
	<div class="ms-content">
		<h1><?php _e( 'Export Options', 'migratestore' ); ?></h1>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="migratestore_export_action">
            <?php wp_nonce_field( 'migratestore_export_action_nonce' ); ?>
            
            <?php foreach ( $export_options as $option => $info ) : ?>
				<div class="ms-option">
					<input type="radio" id="<?php echo esc_attr( $option ); ?>" name="migratestore_action"
					       value="<?php echo esc_attr( $option ); ?>">
					<strong><label
								for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $info['label'] ); ?></label></strong>
					<p class="ms-description"><?php echo esc_html( $info['description'] ); ?></p>
					<hr>
				</div>
            <?php endforeach; ?>

			<input class="button-primary" type="submit"
			       value="<?php echo esc_attr__( 'Export Selected', 'migratestore' ); ?>">
		</form>
	</div>
</div>
