<?php if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly ?>
<div class="wrap migratestore">
	<div class="ms-content">
        <?php
        // Check if the transient is set for errors
        if ( $error = get_transient( 'migratestore_import_error' ) ) {
            // Clear the transient, so it doesn't show again on the next page load
            delete_transient( 'migratestore_import_error' );
            
            // Display the error message
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . esc_html( $error ) . '</p>'; // Escaping the error message
            echo '</div>';
        }
        
        // Check if the transient is set for success
        if ($success_data = get_transient('migratestore_import_success')) {
            delete_transient('migratestore_import_success');
            echo '<div class="notice notice-success is-dismissible">';
            echo '<div class="success-content">';
            
            // Basic success message with checkmark
            echo '<span class="dashicons dashicons-yes-alt"></span>';
            echo '<div class="success-message">';
            echo '<p>' . esc_html__('Migrate Store has imported your file successfully.', 'migratestore') . '</p>';
            
            echo '<div class="action-links">';

			if (!empty($success_data['type_data'])) {
                echo '<a href="' . esc_url($success_data['type_data']['url']) . '" class="verify-link">';
                echo '<span class="dashicons dashicons-visibility"></span>';
                echo esc_html($success_data['type_data']['message']);
                echo '</a>';
            }
			
			echo '<a href="https://ko-fi.com/nagdy" target="_blank" class="review-link">';
			echo '<span class="dashicons dashicons-star-filled"></span>';
			echo esc_html__('Donate', 'migratestore');
			echo '</a>';
			
            echo '<a href="https://wordpress.org/support/plugin/migratestore/reviews/#new-post" target="_blank" class="review-link">';
            echo '<span class="dashicons dashicons-star-filled"></span>';
            echo esc_html__('Leave a Review', 'migratestore');
            echo '</a>';
			
            echo '</div>'; // .action-links
            
            echo '</div>'; // .success-message
            echo '</div>'; // .success-content
            echo '</div>';
        }
        ?>
		<h1><?php echo esc_html__( 'Import Options', 'migratestore' ); ?></h1> <!-- Escaping the title -->
		<div class="ms-import-container">
			<div class="ms-import-card">
				<div class="ms-import-icon">
					<span class="dashicons dashicons-upload"></span>
				</div>
				
				<div class="ms-import-content">
					<h2><?php echo esc_html__('Import Settings', 'migratestore'); ?></h2>
					<p class="ms-description">
						<?php echo esc_html__('Import your WooCommerce settings by uploading the .zip file exported by the Migrate Store plugin.', 'migratestore'); ?>
					</p>

					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" 
						  enctype="multipart/form-data" 
						  class="ms-import-form">
						
						<input type="hidden" name="action" value="migratestore_import_action">
						<?php wp_nonce_field('migratestore_import_action_nonce'); ?>
						
						<div class="ms-file-upload">
							<label for="json_zip_file" class="ms-file-label">
								<span class="dashicons dashicons-upload"></span>
								<?php echo esc_html__('Choose ZIP file or drag it here', 'migratestore'); ?>
							</label>
							<input type="file" 
								   name="json_zip_file" 
								   id="json_zip_file" 
								   accept=".zip"
								   class="ms-file-input">
							<p class="ms-file-name"></p>
						</div>

						<div class="ms-submit-container">
							<button type="submit" class="button button-primary button-hero">
								<span class="dashicons dashicons-database-import"></span>
								<?php echo esc_html__('Import Settings', 'migratestore'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
