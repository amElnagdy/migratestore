<?php

namespace MigrateStore\Importers\WooCommerce;

use MigrateStore\Exporters\WooCommerce\TaxOptionsExporter;
use MigrateStore\Importers\AbstractImporter;

class TaxOptionsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new TaxOptionsExporter() );
	}

}
