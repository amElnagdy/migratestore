<?php

namespace MigrateWoo\Importers\WooCommerce;

use MigrateWoo\Exporters\WooCommerce\ShippingZonesExporter;
use MigrateWoo\Importers\AbstractImporter;

class ShippingOptionsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new ShippingZonesExporter() );
	}

}
