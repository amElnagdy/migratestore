<?php

namespace MigrateStore\Importers\WooCommerce;

use MigrateStore\Exporters\WooCommerce\ShippingOptionsExporter;
use MigrateStore\Importers\AbstractImporter;

class ShippingOptionsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new ShippingOptionsExporter() );
	}

}
