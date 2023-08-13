<?php

namespace MigrateStore\Importers\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MigrateStore\Exporters\WooCommerce\GeneralSettingsExporter;
use MigrateStore\Importers\AbstractImporter;

class GeneralSettingsImporter extends AbstractImporter{

	public function __construct() {
		parent::__construct( new GeneralSettingsExporter() );
	}
}
