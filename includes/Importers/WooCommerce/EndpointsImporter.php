<?php

namespace MigrateStore\Importers\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MigrateStore\Exporters\WooCommerce\EndpointsExporter;
use MigrateStore\Importers\AbstractImporter;

class EndpointsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new EndpointsExporter() );
	}

}
