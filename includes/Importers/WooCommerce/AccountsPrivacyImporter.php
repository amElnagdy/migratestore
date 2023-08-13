<?php

namespace MigrateStore\Importers\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MigrateStore\Exporters\WooCommerce\AccountsPrivacyExporter;
use MigrateStore\Importers\AbstractImporter;

class AccountsPrivacyImporter extends AbstractImporter {

	public function __construct() {
		parent::__construct(new AccountsPrivacyExporter());
	}

}
