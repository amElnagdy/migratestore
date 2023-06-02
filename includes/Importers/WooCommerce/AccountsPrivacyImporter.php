<?php

namespace MigrateWoo\Importers\WooCommerce;

use MigrateWoo\Exporters\WooCommerce\AccountsPrivacyExporter;
use MigrateWoo\Importers\AbstractImporter;

class AccountsPrivacyImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct(new AccountsPrivacyExporter());
	}

}
