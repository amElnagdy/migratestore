<?php

namespace MigrateStore\Importers\WooCommerce;

use MigrateStore\Exporters\WooCommerce\EmailsOptionsExporter;
use MigrateStore\Importers\AbstractImporter;

class EmailsOptionsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new EmailsOptionsExporter() );
	}

}
