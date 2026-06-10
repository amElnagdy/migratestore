# Contract: Shipping Zone Export (Phase 3)

**File**: `includes/Exporters/WooCommerce/ShippingZonesExporter.php`
**Consumers**: the export JSON file (later read by `ShippingZonesImporter`).

## Behavioral contract

### C-EXP-1 — No method-type whitelist
The `woocommerce_shipping_zone_methods` selection MUST NOT filter by a hardcoded set of method IDs.
All method instance rows are candidates for export.

- **Removed**: `WHERE method_id IN ('flat_rate', 'free_shipping', 'local_pickup')`.

### C-EXP-2 — Exclusion filter
Before serialization, the exporter MUST apply:

```php
/**
 * Filters the shipping method IDs to exclude from export.
 *
 * @filter migratestore_excluded_shipping_methods
 * @param string[] $excluded Array of shipping method IDs to omit. Default empty (export all).
 * @return string[]
 */
$excluded = apply_filters( 'migratestore_excluded_shipping_methods', array() );
```

- Default (no hook): `$excluded === []` → every method exported.
- Non-array return MUST be coerced to `[]`.
- Method rows whose `method_id` ∈ `$excluded` MUST be omitted, and their per-instance settings
  option (C-EXP-3) MUST NOT be collected.

### C-EXP-3 — Per-instance settings travel with their method
For every **exported** (post-exclusion) method instance, the exporter MUST include its settings
option `woocommerce_{method_id}_{instance_id}_settings` in the `options` section of the export, so
third-party method configuration is reproducible. Option names are derived from fetched rows and
bound via `$wpdb->prepare` (never concatenated from filter input).

### C-EXP-4 — Built-in compatibility preserved
For `flat_rate`, `free_shipping`, and `local_pickup`, the exported structure (section keys,
field names) MUST be identical to v1.1.9 output so that older importers and existing files are
unaffected.

## Verification
- Export a zone with only the three built-ins → JSON identical in shape to v1.1.9 (QA #3).
- Export a zone containing a third-party method → its row **and** its `_settings` option present;
  nothing dropped (QA #4).
- Add a hook returning `['table_rate']` → that method absent, all others present.
- `php -l` clean on 7.4 + 8.3.
