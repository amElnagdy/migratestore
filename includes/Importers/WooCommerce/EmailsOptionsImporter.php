<?php

namespace MigrateWoo\Importers\WooCommerce;

use MigrateWoo\Exporters\WooCommerce\EmailsOptionsExporter;
use MigrateWoo\Importers\AbstractImporter;

class EmailsOptionsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new EmailsOptionsExporter() );
	}

}
