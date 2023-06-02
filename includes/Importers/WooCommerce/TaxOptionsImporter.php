<?php

namespace MigrateWoo\Importers\WooCommerce;

use MigrateWoo\Exporters\WooCommerce\TaxOptionsExporter;
use MigrateWoo\Importers\AbstractImporter;

class TaxOptionsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new TaxOptionsExporter() );
	}

}
