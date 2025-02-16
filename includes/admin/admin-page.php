<?php if ( ! defined( 'ABSPATH' ) ) {
    exit;
} ?>
<div class="wrap migratestore">
	<div class="ms-content">
		<div class="ms-header">
			<h1><?php esc_html_e('Migrate Store', 'migratestore'); ?></h1>
			<p class="ms-subtitle"><?php esc_html_e('Transfer your WooCommerce settings effortlessly between sites', 'migratestore'); ?></p>
		</div>

		<div class="ms-welcome-card">
			<div class="ms-welcome-content">
				<p><?php esc_html_e('Looking for a reliable plugin to transfer your WooCommerce settings effortlessly? Look no further than Migrate Store! This tool is specifically designed to export and import all your WooCommerce settings with ease.', 'migratestore'); ?></p>
				<p><?php esc_html_e('Say goodbye to the hassle of manually setting up your new store settings. Whether you\'re migrating settings from one site to another or moving between test and live sites, Migrate Store simplifies the process.', 'migratestore'); ?></p>
			</div>
		</div>

		<div class="ms-features-section">
			<h2><?php esc_html_e('What Can Migrate Store Do For You?', 'migratestore'); ?></h2>
			<p><?php esc_html_e('Migrate Store handles your full-scale WooCommerce migration, covering every aspect of your store setup:', 'migratestore'); ?></p>

			<div class="ms-features-grid">
				<div class="ms-feature-card">
					<span class="dashicons dashicons-admin-settings"></span>
					<h3><?php esc_html_e('General Settings', 'migratestore'); ?></h3>
					<p><?php esc_html_e('WooCommerce → Settings → General', 'migratestore'); ?></p>
				</div>

				<div class="ms-feature-card">
					<span class="dashicons dashicons-calculator"></span>
					<h3><?php esc_html_e('Tax Options', 'migratestore'); ?></h3>
					<p><?php esc_html_e('WooCommerce → Settings → Tax options', 'migratestore'); ?></p>
				</div>

				<div class="ms-feature-card">
					<span class="dashicons dashicons-location"></span>
					<h3><?php esc_html_e('Shipping Zones', 'migratestore'); ?></h3>
					<p><?php esc_html_e('WooCommerce → Settings → Shipping zones', 'migratestore'); ?></p>
				</div>

				<div class="ms-feature-card">
					<span class="dashicons dashicons-car"></span>
					<h3><?php esc_html_e('Shipping Options', 'migratestore'); ?></h3>
					<p><?php esc_html_e('WooCommerce → Settings → Shipping options', 'migratestore'); ?></p>
				</div>

				<div class="ms-feature-card">
					<span class="dashicons dashicons-admin-users"></span>
					<h3><?php esc_html_e('Accounts & Privacy', 'migratestore'); ?></h3>
					<p><?php esc_html_e('WooCommerce → Settings → Accounts & Privacy', 'migratestore'); ?></p>
				</div>

				<div class="ms-feature-card">
					<span class="dashicons dashicons-networking"></span>
					<h3><?php esc_html_e('Shipping Classes', 'migratestore'); ?></h3>
					<p><?php esc_html_e('WooCommerce → Settings → Shipping → Classes', 'migratestore'); ?></p>
				</div>
			</div>
		</div>

		<div class="ms-getting-started">
			<h2><?php esc_html_e('How To Get Started?', 'migratestore'); ?></h2>
			<div class="ms-steps">
				<div class="ms-step">
					<span class="ms-step-number">1</span>
					<h3><?php esc_html_e('Export Settings', 'migratestore'); ?></h3>
					<p><?php esc_html_e('Select and export your desired WooCommerce settings to a ZIP file.', 'migratestore'); ?></p>
				</div>
				<div class="ms-step">
					<span class="ms-step-number">2</span>
					<h3><?php esc_html_e('Transfer File', 'migratestore'); ?></h3>
					<p><?php esc_html_e('Move the exported ZIP file to your destination site.', 'migratestore'); ?></p>
				</div>
				<div class="ms-step">
					<span class="ms-step-number">3</span>
					<h3><?php esc_html_e('Import Settings', 'migratestore'); ?></h3>
					<p><?php esc_html_e('Upload and import the ZIP file to apply all settings.', 'migratestore'); ?></p>
				</div>
			</div>

			<div class="ms-note">
				<p><strong><?php esc_html_e('Note:', 'migratestore'); ?></strong> <?php esc_html_e('Migrate Store is designed for WooCommerce settings only. It does not migrate products, orders, or other WordPress settings.', 'migratestore'); ?></p>
			</div>

			<div class="ms-cta-buttons">
				<a href="<?php echo esc_url(admin_url('admin.php?page=migratestore-export')); ?>" class="button button-primary button-hero">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e('Start Export', 'migratestore'); ?>
				</a>
				<a href="<?php echo esc_url(admin_url('admin.php?page=migratestore-import')); ?>" class="button button-primary button-hero">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e('Start Import', 'migratestore'); ?>
				</a>
			</div>
		</div>
	</div>
</div>
