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

}
