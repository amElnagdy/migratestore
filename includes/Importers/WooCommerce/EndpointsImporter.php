<?php

namespace MigrateWoo\Importers\WooCommerce;

use MigrateWoo\Exporters\WooCommerce\EndpointsExporter;
use MigrateWoo\Importers\AbstractImporter;

class EndpointsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new EndpointsExporter() );
	}

}
