<div class="wrap">
	<h1>MigrateWoo</h1>
	<p>Welcome to the MigrateWoo plugin. Use the options below to export and import your WooCommerce data.</p>

	<h2>Export Options</h2>
	<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
		<input type="hidden" name="action" value="migratewoo_action">
		<input type="hidden" name="migratewoo_action" value="export_shipping_settings">
		<?php wp_nonce_field('migratewoo_action_nonce'); ?>
		<input type="submit" value="Export Shipping Settings">
	</form>

	<!-- More forms for other actions -->

	<h2>Import Options</h2>
	<ul>
		<li><a href="#">Import WooCommerce Settings</a></li>
		<li><a href="#">Import Shipping Settings</a></li>
		<li><a href="#">Import Tax Settings</a></li>
		<!-- Add more import options as needed -->
	</ul>
</div>
