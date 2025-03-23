<?php
if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
$export_options = array(
    'export_general_settings'         => array(
        'label'       => __('General Settings', 'migratestore'),
        'description' => __('Export WooCommerce general settings.', 'migratestore'),
        'tooltip'     => __('Found at WooCommerce → Settings → General page.', 'migratestore'),
    ),
    'export_tax_options'              => array(
        'label'       => __('Tax Options', 'migratestore'),
        'description' => __('Export  WooCommerce tax options.', 'migratestore'),
        'tooltip'     => __('Found at WooCommerce → Settings → Tax.', 'migratestore'),
    ),
    'export_shipping_zones'           => array(
        'label'       => __('Shipping Zones', 'migratestore'),
        'description' => __('Export WooCommerce shipping zones.', 'migratestore'),
        'tooltip'     => __('Including the Zones, the locations and the default shipping methods (Flat Rate, Free Shipping, and Local Pickup).', 'migratestore'),
    ),
    'export_shipping_options'         => array(
        'label'       => __('Shipping Options', 'migratestore'),
        'description' => __('Export  WooCommerce shipping options.', 'migratestore'),
        'tooltip'     => __('Found at WooCommerce → Settings → Shipping → Shipping options.', 'migratestore'),
    ),
    'export_accounts_privacy_options' => array(
        'label'       => __('Accounts & Privacy Options', 'migratestore'),
        'description' => __('Export WooCommerce accounts and privacy options.', 'migratestore'),
        'tooltip'     => __('Found at WooCommerce → Settings → Accounts & Privacy.', 'migratestore'),
    ),
    'export_def_emails_options'       => array(
        'label'       => __('Emails Options', 'migratestore'),
        'description' => __('Export WooCommerce default emails options.', 'migratestore'),
        'tooltip'     => __('This includes the email sender options, email template, and the email options for all default WooCommerce emails such as New order, Cancelled order, etc. Found at WooCommerce → Settings → Emails.', 'migratestore'),
    ),
    'export_endpoints_options'        => array(
        'label'       => __('Endpoints Options', 'migratestore'),
        'description' => __('Export  WooCommerce endpoints options.', 'migratestore'),
        'tooltip'     => __('Found at WooCommerce → Settings → Advanced.', 'migratestore'),
    ),
    'export_shipping_classes'        => array(
        'label'       => __('Shipping Classes', 'migratestore'),
        'description' => __('Export WooCommerce shipping classes.', 'migratestore'),
        'tooltip'     => __('Found at WooCommerce → Settings → Shipping → Classes.', 'migratestore'),
    ),
);
?>
<div class="wrap migratestore">
    <div class="ms-content">
        <h1><?php _e('Export Options', 'migratestore'); ?></h1>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="migratestore_export_action">
            <?php wp_nonce_field('migratestore_export_action_nonce'); ?>

            <div class="ms-options-grid" data-instructions="<?php esc_attr_e('Click on a card to select an option', 'migratestore'); ?>">
                <?php foreach ($export_options as $option => $info) : ?>
                    <div class="ms-option-card">
                        <input type="radio"
                            id="<?php echo esc_attr($option); ?>"
                            name="migratestore_action"
                            value="<?php echo esc_attr($option); ?>"
                            class="ms-radio">
                        <label for="<?php echo esc_attr($option); ?>" class="ms-option-label">
                            <div class="ms-option-header">
                                <strong><?php echo esc_html($info['label']); ?></strong>
                                <span class="ms-tooltip-icon dashicons dashicons-info-outline"
                                    data-tooltip="<?php echo esc_attr($info['tooltip']); ?>">
                                </span>
                            </div>
                            <div class="ms-option-description">
                                <?php echo esc_html($info['description']); ?>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ms-submit-container">
                <button type="submit" class="button button-primary button-hero">
                    <span class="dashicons dashicons-download"></span>
                    <?php echo esc_html__('Export Selected', 'migratestore'); ?>
                </button>
            </div>
        </form>
    </div>
</div>