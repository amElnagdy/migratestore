<?php

namespace MigrateWoo\Importers;

use MigrateWoo\Exporters\EndpointsExporter;

class EndpointsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new EndpointsExporter() );
	}

}
