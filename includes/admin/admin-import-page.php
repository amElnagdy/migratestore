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
        
        // Check if the transient is set
        if ( get_transient( 'migratestore_import_success' ) ) {
            // Clear the transient, so it doesn't show again on the next page load
            delete_transient( 'migratestore_import_success' );
            
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__( 'MigrateStore has imported your file successfully.', 'migratestore' ) . '</p>'; // Escaping the success message
            echo '</div>';
        }
        ?>
		<h1><?php echo esc_html__( 'Import Options', 'migratestore' ); ?></h1> <!-- Escaping the title -->
		<p><?php echo esc_html__( 'Import your WooCommerce settings by uploading the .zip file exported by the Migrate Store plugin.', 'migratestore' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		      enctype="multipart/form-data">
			<input type="hidden" name="action" value="migratestore_import_action">
            <?php wp_nonce_field( 'migratestore_import_action_nonce' ); ?>
			<input type="file" name="json_zip_file" accept=".zip">
			<input class="button-primary" type="submit"
			       value="<?php echo esc_attr__( 'Import Zip', 'migratestore' ); ?>"> <!-- Escaping the button text -->
		</form>
	</div>
</div>
