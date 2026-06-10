# Contract: Shipping Zone Import & Skipped-Method Notice (Phase 3)

**Files**: `includes/Importers/WooCommerce/ShippingZonesImporter.php`,
`includes/MigrateStore.php`, `includes/admin/admin-import-page.php`.

## Behavioral contract

### C-IMP-1 — Registration check before inserting a method
`import_shipping_zone_method()` MUST insert a row only when its `method_id` is registered on the
target site:

```php
$registered = WC()->shipping()->get_shipping_methods(); // keyed by method id
if ( ! array_key_exists( $method_id, $registered ) ) {
    $this->skipped_methods[] = $method_id; // collect, do not insert
    return;
}
```

- Lookup MUST use exact key matching (no substring/`strpos`), per Constitution Principle II.7.
- The registry SHOULD be fetched once per import (cached), not per row.

### C-IMP-2 — Partial import continues
A skipped method MUST NOT abort the import. Remaining zones, locations, options, and registered
methods MUST still import. If **every** method is unregistered, zones/locations still import and all
method IDs are reported (edge case).

### C-IMP-3 — Skipped-method accessor
The importer MUST expose:

```php
public function get_skipped_methods(): array // de-duplicated list of skipped method IDs
```

### C-IMP-4 — Warning transient
After `import()` returns in `handle_import_action()`, when the importer reports skipped methods, the
orchestrator MUST set:

```php
set_transient( 'migratestore_import_warning', $skipped_ids, 60 ); // sanitized, de-duplicated
```

This is set **in addition to** the existing `migratestore_import_success` transient — a partially
successful import shows both a success and a warning notice.

### C-IMP-5 — Warning notice rendering
`admin-import-page.php` MUST read → delete → render the warning transient as:

```php
<div class="notice notice-warning is-dismissible">
  <p>Skipped shipping methods not available on this site: {ids}</p>
</div>
```

- Dismissible (`is-dismissible`) and warning-styled (`notice-warning`) — FR-008.
- Every method ID escaped with `esc_html()`. Message wrapped in i18n (`migratestore` text domain).

### C-IMP-6 — Legacy files unaffected
An import file containing only `flat_rate`/`free_shipping`/`local_pickup` MUST skip nothing (those
are always registered when WooCommerce is active) and import identically to v1.1.9.

## Verification
- Import file referencing an unregistered method → method skipped, rest imported, dismissible
  `notice-warning` lists the ID (QA #6).
- Import legacy file → no warning, success notice only (QA #5).
- Import with all methods unregistered → zones created, warning lists all IDs, no fatal (edge case).
- `php -l` clean on 7.4 + 8.3.
