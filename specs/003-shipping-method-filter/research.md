# Phase 0 Research: Shipping Method Filter & Release QA (Phase 3)

**Feature**: `specs/003-shipping-method-filter` | **Date**: 2026-06-11

This document records the code audit and the design decisions that resolve every unknown before
Phase 1 design. All findings are grounded in the current source on branch
`migratestore-wp7-readiness` (baseline v1.1.9 — Phases 1 and 2 are specced/planned but not yet
implemented in code).

---

## Audit findings (current state)

### Finding A — the whitelist lives in two places in the exporter SQL

`includes/Exporters/WooCommerce/ShippingZonesExporter.php::export()` builds four queries:

1. `woocommerce_shipping_zones` — all zones (no restriction). ✅ fine.
2. `woocommerce_shipping_zone_methods` — **restricted**:
   `... WHERE method_id IN ('flat_rate', 'free_shipping', 'local_pickup')` (line 31). **This is the
   primary whitelist.** Any zone method whose `method_id` is not one of these three is silently
   dropped from the export.
3. `woocommerce_shipping_zone_locations` — all locations (no restriction). ✅ fine.
4. `options` — **restricted**:
   `... WHERE option_name LIKE 'woocommerce_free%' OR LIKE 'woocommerce_local_pickup_%' OR LIKE
   'woocommerce_flat_%'` (line 33). This captures the per-instance *settings* options for the three
   built-in methods only. A third-party method's settings option
   (`woocommerce_{method_id}_{instance_id}_settings`) is **not** captured — a **secondary
   whitelist**. Removing only query 2 would export the method rows but lose their configuration.

### Finding B — the importer inserts methods blindly (no registration check)

`includes/Importers/WooCommerce/ShippingZonesImporter.php::import_shipping_zone_method()` (line 68)
does a raw `$wpdb->insert()` of whatever `method_id` is in the JSON. There is no check that the
method is registered on the target site. Today this "works" only because the export was restricted
to three always-present built-ins. Once the export carries arbitrary methods, an import on a site
lacking that method's plugin would write an orphaned `woocommerce_shipping_zone_methods` row that
WooCommerce cannot render — this is exactly the case US-1 requires us to detect and report.

### Finding C — admin notices are delivered via transients, rendered on the import page

`MigrateStore::handle_import_action()` sets `migratestore_import_success` /
`migratestore_import_error` transients (60 s TTL) after the import; `includes/admin/admin-import-page.php`
reads, deletes, and renders them as `notice notice-success/​error is-dismissible`. There is **no
warning channel yet**. Phase 3 must add one (`notice-warning is-dismissible`) following the exact
same transient pattern — no new notice framework.

### Finding D — release metadata is still at baseline

`migratestore.php`: `Version: 1.1.9`, `const MIGRATESTORE_VERSION = '1.1.9'`.
`readme.txt`: `Tested up to: 6.9`, `Stable tag: 1.1.9`, changelog top entry `= 1.1.9 =`.
The QA matrix scenario 18 ("header shows Tested up to 7.0 / Requires PHP 7.4") and US-3 (stable tag
`1.2.0`, complete `1.2.0` changelog) depend on the header/readme having been advanced. Phase 1
owns the header bump; Phase 3 owns the **final readme changelog consolidation** for `1.2.0`.

### Finding E — the `//TODO` is Phase 2's, not Phase 3's

`ShippingZonesImporter.php` line 22 still carries `//TODO: Add a Learn more link...`. The Phase 2
plan/spec already claims this fix (US-9). Phase 3 spec does **not** re-scope it. Decision: Phase 3
does not touch the TODO; if it is still present when Phase 3 runs, flag it as a Phase 2 carry-over,
do not silently absorb it.

---

## Decisions

### D1 — Remove the method whitelist; export all zone methods

- **Decision**: Drop the `WHERE method_id IN (...)` clause from the `woocommerce_shipping_zone_methods`
  query so every row is selected.
- **Rationale**: Directly satisfies FR-001/FR-002 and Constitution Principle III. The zone-methods
  table is small (one row per method instance per zone); selecting all of it is bounded and cheap.
- **Alternatives considered**: Keep an SQL `NOT IN` built from the exclusion list — rejected in
  favor of D2 (PHP-side exclusion) to avoid composing SQL from filter-supplied values.

### D2 — `migratestore_excluded_shipping_methods` filter applied in PHP after fetch

- **Decision**: After fetching all method rows, apply
  `apply_filters( 'migratestore_excluded_shipping_methods', array() )` to obtain an array of
  method IDs to exclude, then `array_filter` out rows whose `method_id` is in that list. Default
  return is an empty array (exclude nothing). Document the hook with a `@filter` docblock naming
  the parameter, default, and return type.
- **Rationale**: Satisfies FR-003/FR-004. Filtering in PHP keeps untrusted/third-party filter
  output out of the SQL string (defense in depth, consistent with Principle II's posture), and
  keeps the default behavior "export everything."
- **Alternatives considered**: SQL-side `NOT IN` placeholder list — more code, marginal benefit,
  and couples filter output to query construction. Rejected.

### D3 — Capture per-instance settings for every exported method instance

- **Decision**: Replace the three hardcoded `LIKE` clauses in the `options` query with a query that
  also captures each exported instance's settings option. After the method rows are known, collect
  the expected option names `woocommerce_{method_id}_{instance_id}_settings` for the (post-exclusion)
  instances and fetch exactly those, plus retain the existing global option captures needed by the
  three built-ins for backward-compatible files.
- **Rationale**: Without this, third-party methods export as rows with no configuration and import
  half-broken — partially defeating FR-001. Building the option-name set from the already-fetched
  instances (not from user input) keeps it injection-safe via `$wpdb->prepare`.
- **Alternatives considered**: Leave the options query unchanged (rows only) — rejected: produces
  configless methods on import. Broaden to `woocommerce_%_settings` blanket — rejected: over-captures
  unrelated options and bloats the export.

### D4 — Import-time registration check via WooCommerce's method registry

- **Decision**: In `import_shipping_zone_method()`, look up the registered methods once via
  `WC()->shipping()->get_shipping_methods()` (returns an array **keyed by method id**). Use
  `array_key_exists( $method_id, $registered )` (exact-key match — consistent with Principle II.7).
  If absent, **skip** the insert, record the `method_id` in a `$skipped_methods` collection on the
  importer, and continue. Expose the collection via a public `get_skipped_methods(): array`
  (de-duplicated).
- **Rationale**: Satisfies FR-006. Exact-key lookup avoids the substring-matching anti-pattern.
  Caching the registry once per import avoids repeated lookups in the method loop.
- **Alternatives considered**: Fail the whole import on the first unknown method — rejected: violates
  FR-006 (remaining zones must still import) and the "all methods unrecognized" edge case.

### D5 — Skipped-methods surfaced as a dismissible warning notice via a new transient

- **Decision**: After `$importer->import()` returns, if the importer is a `ShippingZonesImporter`
  and `get_skipped_methods()` is non-empty, set a `migratestore_import_warning` transient (60 s)
  holding the de-duplicated, sanitized list of skipped method IDs. In `admin-import-page.php`, read
  → delete → render it as `<div class="notice notice-warning is-dismissible">` listing the IDs, with
  every ID passed through `esc_html()`.
- **Rationale**: Satisfies FR-007/FR-008. Reuses the existing transient→notice mechanism (Finding C)
  exactly, so no new infrastructure and it coexists with the success notice (a partially-successful
  import shows both success and warning).
- **Alternatives considered**: Embed the warning inside the success transient payload — rejected:
  muddies the success/warning separation and the rendering logic; a distinct transient is clearer
  and independently testable.

### D6 — Backward compatibility is automatic

- **Decision**: No special-casing for legacy (v1.1.9) export files.
- **Rationale**: Legacy files contain only `flat_rate`/`free_shipping`/`local_pickup`, which are
  always registered when WooCommerce is active, so D4's check skips nothing and the import is
  byte-for-byte identical to today. Satisfies FR-005/FR-009 with zero added code paths.

### D7 — Release readme/changelog consolidation owns the `1.2.0` entry

- **Decision**: Phase 3's documentation work writes the single `= 1.2.0 =` changelog entry covering
  all Phase 1–3 fixes, sets `Stable tag: 1.2.0`, and confirms `Tested up to: 7.0` in `readme.txt`.
  The plugin-header `Version`/`MIGRATESTORE_VERSION` bump is Phase 1's responsibility; Phase 3
  verifies (QA scenario 18) rather than re-owns it.
- **Rationale**: Satisfies FR-012/FR-013/FR-014 and Constitution "Versioning & Release Discipline".
  Consolidating the changelog at the last phase guarantees it reflects what actually shipped.

### D8 — Verification is lint + the 18-scenario QA matrix (no automated suite)

- **Decision**: The merge gate is `php -l` on PHP 7.4 and 8.3 across all plugin `.php` files plus the
  manual 18-scenario QA matrix from the spec, executed on WP 7.0 + latest WooCommerce.
- **Rationale**: The repo has no automated test suite; Constitution Principle V defines "done" as
  lint + manual QA. The authoring environment has no PHP on PATH, so binary lint and QA run in the
  developer/CI environment; this research substitutes static reasoning for design-time confidence.
- **Alternatives considered**: Introduce PHPUnit/WP test scaffolding — rejected under Principle VI
  (scope discipline: no new infrastructure in a hardening release).

---

## Resolved unknowns

| Unknown (from Technical Context) | Resolution |
|----------------------------------|------------|
| How is the whitelist enforced today? | SQL `WHERE method_id IN (...)` (rows) + `LIKE` clauses (settings options). D1/D3. |
| How to make export extensible? | `migratestore_excluded_shipping_methods` filter, PHP-side, default empty. D2. |
| How to detect unreproducible methods on import? | `WC()->shipping()->get_shipping_methods()` keyed lookup, exact match. D4. |
| How to surface skipped methods to the user? | New `migratestore_import_warning` transient → `notice-warning is-dismissible`. D5. |
| Backward compatibility risk? | None — legacy methods are always registered; no special path. D6. |
| Who owns the version bump vs changelog? | Header bump = Phase 1; `1.2.0` changelog consolidation + stable tag = Phase 3. D7. |
| What is the verification gate? | `php -l` (7.4 + 8.3) + 18-scenario manual QA matrix. D8. |

All NEEDS CLARIFICATION items are resolved. Ready for Phase 1 design.
