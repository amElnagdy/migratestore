<?php

namespace MigrateWoo\Importers\WooCommerce;

use MigrateWoo\Exporters\WooCommerce\GeneralSettingsExporter;
use MigrateWoo\Importers\AbstractImporter;

class GeneralSettingsImporter extends AbstractImporter{

	public function __construct() {
		parent::__construct( new GeneralSettingsExporter() );
	}
}
