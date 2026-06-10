# Phase 1 Data Model: Shipping Method Filter & Release QA (Phase 3)

**Feature**: `specs/003-shipping-method-filter` | **Date**: 2026-06-11

Phase 3 introduces **no database schema changes**. It changes which rows/options are *selected* on
export, adds an import-time *validation* against the live method registry, and adds one transient.
The entities below describe the data shapes that cross the export → file → import boundary.

---

## E1 — Shipping Zone Method (row)

Source/target table: `{prefix}woocommerce_shipping_zone_methods`. One row per method instance
attached to a zone.

| Field | Type | Notes |
|-------|------|-------|
| `zone_id` | int | FK to `woocommerce_shipping_zones.zone_id`. |
| `instance_id` | int | Unique per method instance; part of the settings option name. |
| `method_id` | string | e.g. `flat_rate`, `free_shipping`, `local_pickup`, or any third-party id. **Previously whitelisted; now unrestricted on export.** |
| `method_order` | int | Display order within the zone. |
| `is_enabled` | int (0/1) | Whether the method is active. |

**Export rule change (FR-001/FR-002/FR-003)**: select **all** rows; then remove rows whose
`method_id` ∈ `apply_filters( 'migratestore_excluded_shipping_methods', [] )`.

**Import validation rule (FR-006)**: a row is inserted **only if** `method_id` is a key in
`WC()->shipping()->get_shipping_methods()`. Otherwise the row is skipped and `method_id` is added to
the Skipped-Method Report (E4). Existing field sanitization (`(int)` casts, `sanitize_text_field`
on `method_id`) is unchanged.

---

## E2 — Shipping Method Settings (option)

Source/target: `{prefix}options`. Per-instance configuration for a method.

| Attribute | Value |
|-----------|-------|
| `option_name` | `woocommerce_{method_id}_{instance_id}_settings` |
| `option_value` | serialized array of that instance's settings |

**Export rule change (FR-001, research D3)**: instead of three hardcoded `LIKE` prefixes, the export
collects the settings option name for each **exported** (post-exclusion) method instance and fetches
exactly those, so third-party method configuration travels with its row. Option-name values are
derived from already-fetched rows (not user input) and bound via `$wpdb->prepare`.

**Import**: handled by the existing `import_option()` path (unchanged), which already sanitizes and
`update_option()`s. (Field-name convention `option_name`/`option_value` is Phase 2's contract;
Phase 3 does not alter it.)

---

## E3 — Excluded-Methods Filter Input

A developer-supplied list consumed at export time.

| Attribute | Value |
|-----------|-------|
| Hook | `migratestore_excluded_shipping_methods` (filter) |
| Default value | `array()` (empty — exclude nothing) |
| Expected return | array of method-id strings to omit from export |
| Effect | Rows in E1 whose `method_id` is in the list are not exported; their E2 settings are likewise not collected. |

Validation: non-array returns are coerced to an empty array (defensive); only string method IDs are
honored. Documented with a `@filter` docblock on the exporter.

---

## E4 — Skipped-Method Report (transient + in-memory)

Produced during import; consumed by the admin notice.

| Attribute | Value |
|-----------|-------|
| In-memory | `ShippingZonesImporter::$skipped_methods` (array of method-id strings, de-duplicated) |
| Accessor | `public function get_skipped_methods(): array` |
| Transient key | `migratestore_import_warning` |
| Transient value | de-duplicated, sanitized array of skipped method IDs |
| TTL | 60 seconds (matches existing success/error transients) |
| Render | `notice notice-warning is-dismissible` listing each ID via `esc_html()` |

State transitions:
1. Import loop encounters a method row → registry lookup (E1 rule).
2. Not registered → append `method_id` to `$skipped_methods`.
3. After `import()` returns → if `get_skipped_methods()` non-empty, set `migratestore_import_warning`.
4. Import page load → read + delete transient → render warning notice.

---

## E5 — Release Changelog & Metadata

Documentation artifacts in `readme.txt` (and verified header in `migratestore.php`).

| Attribute | Required value (FR-012/013/014) |
|-----------|----------------------------------|
| `Stable tag` | `1.2.0` |
| `Tested up to` | `7.0` |
| Changelog `= 1.2.0 =` | Covers: WP 7.0 compat; PHP 8.2/8.3 compat; capability checks on all export/import handlers; archive upload validation + temp-file cleanup; option field-name consistency; AbstractImporter constructor fix; duplicate email-settings exporter entry fix; shipping-method export no longer limited to three built-in types. |
| Placeholder/draft text | none |
| Header (`migratestore.php`) | `Version: 1.2.0`, `Tested up to: 7.0`, `Requires PHP: 7.4` (header bump owned by Phase 1; verified here). |

---

## Relationships

```
Shipping Zone (existing) 1───* Shipping Zone Method (E1) 1───1 Method Settings (E2)
                                      │
                                      │ export: minus Excluded-Methods Filter (E3)
                                      │ import: validated against WC method registry
                                      ▼
                              Skipped-Method Report (E4) ──> notice-warning
```

No new tables, columns, or indexes. The only persistent additions are one short-lived transient
(E4) and documentation/metadata (E5).
