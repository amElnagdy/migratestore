<?php

namespace MigrateWoo\Importers;

use MigrateWoo\Exporters\EmailsOptionsExporter;

class EmailsOptionsImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct( new EmailsOptionsExporter() );
	}

}
