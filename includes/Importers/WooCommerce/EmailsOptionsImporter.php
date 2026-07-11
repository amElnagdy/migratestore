<?php

namespace MigrateStore\Importers\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MigrateStore\Exporters\WooCommerce\EmailsOptionsExporter;
use MigrateStore\Importers\AbstractImporter;

class EmailsOptionsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new EmailsOptionsExporter() );
	}

	/**
	 * The email footer text may contain WooCommerce-permitted HTML, so it must be
	 * sanitized with wp_kses_post() rather than stripped by sanitize_text_field().
	 *
	 * @return string[]
	 */
	protected function get_rich_text_option_names(): array {
		return [ 'woocommerce_email_footer_text' ];
	}

}
