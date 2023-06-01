<?php

namespace MigrateWoo\Importers;

use MigrateWoo\Exporters\AccountsPrivacyExporter;

class AccountsPrivacyImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct(new AccountsPrivacyExporter());
	}

}
