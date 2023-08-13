<?php

namespace MigrateStore\Importers\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MigrateStore\Exporters\WooCommerce\TaxOptionsExporter;
use MigrateStore\Importers\AbstractImporter;

class TaxOptionsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new TaxOptionsExporter() );
	}

}
