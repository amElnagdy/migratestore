# Implementation Plan: Shipping Method Filter & Release QA (Phase 3)

**Branch**: `migratestore-wp7-readiness` | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/003-shipping-method-filter/spec.md`

## Summary

Phase 3 closes the last readiness gap before tagging v1.2.0: a silent data-loss bug in the shipping
zone exporter, plus the release QA and changelog gates. The work is narrow and localized:

- **Remove the method whitelist (US-1 / FR-001, FR-002):** delete the
  `WHERE method_id IN ('flat_rate','free_shipping','local_pickup')` clause in
  `ShippingZonesExporter::export()` (line 31) so every `woocommerce_shipping_zone_methods` row is
  exported.
- **Make export extensible (US-1 / FR-003, FR-004):** apply
  `apply_filters( 'migratestore_excluded_shipping_methods', array() )` (default empty), exclude
  matching method rows in PHP, and document the hook with a `@filter` docblock.
- **Carry method config (US-1 / FR-001, research D3):** replace the three hardcoded `LIKE` clauses
  in the `options` query (line 33) with a `$wpdb->prepare`d fetch of each exported instance's
  `woocommerce_{method_id}_{instance_id}_settings` option, so third-party method settings travel
  with their rows.
- **Detect unreproducible methods on import (US-1 / FR-006, FR-007):** in
  `ShippingZonesImporter::import_shipping_zone_method()`, check `method_id` against
  `WC()->shipping()->get_shipping_methods()` (exact key match); skip unregistered methods, collect
  their IDs, expose `get_skipped_methods()`, and continue importing the rest.
- **Warn the user (US-1 / FR-008):** in `handle_import_action()` set a new
  `migratestore_import_warning` transient when methods were skipped, and render it in
  `admin-import-page.php` as a dismissible `notice-warning` listing the IDs — reusing the existing
  transient→notice mechanism.
- **Release QA (US-2 / FR-010, FR-011):** run the 18-scenario matrix on WP 7.0 + latest WooCommerce;
  any failure blocks the release.
- **Readme/changelog (US-3 / FR-012–FR-014):** write the consolidated `= 1.2.0 =` changelog, set
  `Stable tag: 1.2.0`, confirm `Tested up to: 7.0`, and verify the Phase 1 header bump.

Backward compatibility is automatic: legacy (v1.1.9) files contain only the three always-registered
built-ins, so nothing is excluded on export or skipped on import (FR-005, FR-009). Gated by `php -l`
on 7.4 + 8.3 and the manual QA matrix, per Constitution Principle V.

## Technical Context

**Language/Version**: PHP — minimum **7.4** (declared/enforced), tested on **8.3**. All new code uses
7.4-safe syntax only (no `${var}`, no unguarded 8.0+ functions). `get_skipped_methods(): array` and
the existing `: bool` return types are 7.4-compatible.

**Primary Dependencies**: WordPress 7.0 (min 6.0) — `apply_filters`, `set_transient`/`get_transient`/
`delete_transient`, `esc_html`, `__()`, `$wpdb` (`get_results`, `prepare`, `insert`); WooCommerce —
`WC()->shipping()->get_shipping_methods()` (method registry, keyed by method id), capability
`manage_woocommerce`, declared via `Requires Plugins: woocommerce`. Composer PSR-4 maps
`MigrateStore\` → `includes/`. No new dependencies.

**Storage**: WordPress options table + WooCommerce shipping-zone tables
(`woocommerce_shipping_zones`, `_zone_methods`, `_zone_locations`). **No schema changes** — Phase 3
changes which rows/options are *selected* (export) and adds an import-time *validation* against the
live registry, plus one short-lived transient. No new tables/columns/indexes.

**Testing**: `php -l` on PHP 7.4 and 8.3 across all plugin `.php` files (lint gate) + the manual
18-scenario QA matrix (quickstart.md) on WP 7.0 + latest WooCommerce, including a third-party
shipping-method plugin for the third-party-export and unknown-method-import scenarios. No automated
test suite exists in the repo (Constitution Principle V defines done as lint + manual QA).

**Target Platform**: WordPress 7.0 site on PHP 7.4–8.3 with WooCommerce active; `admin_post_*` form
handlers (not AJAX, despite the plan's "AJAX" wording) in `includes/MigrateStore.php`; admin notices
rendered server-side on the import page via transients.

**Project Type**: Single-project WordPress/WooCommerce plugin (root `migratestore.php` bootstrap +
`includes/` PSR-4 source).

**Performance Goals**: N/A — correctness/data-faithfulness only. Added cost is one cached
`get_shipping_methods()` lookup per import and a bounded per-instance options fetch on export; both
scale with the (small) number of zone-method instances.

**Constraints**:
- Exclusion filter output MUST be kept out of SQL string construction (PHP-side `array_filter`;
  derived option names bound via `$wpdb->prepare`).
- Import registration check MUST use exact key matching (no substring) — Constitution Principle II.7.
- A skipped method MUST NOT abort the import; remaining data still imports (FR-006).
- The warning notice MUST coexist with the success notice (a partial import shows both).
- Backward compatibility: legacy v1.1.9 files import identically with no warning (FR-005/FR-009).
- New code must lint on 7.4 and 8.3.
- **Environment note**: no PHP binary on PATH in the authoring environment; the `php -l` gate and the
  QA matrix run in the developer/CI environment. research.md substitutes static reasoning for
  design-time confidence; binary lint + manual QA remain the merge gate.

**Scale/Scope**: ~4 files materially edited — `ShippingZonesExporter.php` (whitelist removal +
filter + options query), `ShippingZonesImporter.php` (registration check + accessor),
`MigrateStore.php` (warning transient), `admin-import-page.php` (warning notice render) — plus
`readme.txt` (changelog/stable tag) and a verify-only touch of `migratestore.php` (header from
Phase 1). No new classes, directories, or persistent structures (Constitution Principle VI).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

Gates derived from `.specify/memory/constitution.md` v1.0.0:

| Principle | Relevance to Phase 3 | Status |
|-----------|---------------------|--------|
| I. WP 7.0 & Modern PHP Compatibility | New code (filter, registry check, accessor, transient, notice) must lint on 7.4 + 8.3 with no deprecated patterns; `: array` return type is 7.4-safe. | ✅ Built into the lint gate; new code is 7.4-safe. |
| II. Security Is Non-Negotiable | No new handlers; capability+nonce already enforced (Phase 2). Import registration check uses **exact key matching** (clause 7). Exclusion-filter output is kept out of SQL. Notice output is `esc_html`-escaped. | ✅ Compliant; reinforces clause 7. |
| III. WP/WooCommerce Coding Standards | **This phase fulfills the explicit Principle III mandate**: "Shipping-method support MUST NOT be limited to a hardcoded whitelist; any shipping-methods usage MUST allow third-party methods." All new user-facing strings i18n-wrapped (`migratestore`). No new TODO/FIXME. | ✅ Directly satisfied. |
| IV. Data Integrity & Consistency | Export now faithfully reproduces all methods + their settings; import surfaces (not silently drops) unreproducible methods. Option field-name convention (Phase 2) is read, unchanged. | ✅ Compliant; advances data faithfulness. |
| V. Verified Before Merge | Phase 3 **is** the release QA gate: `php -l` (7.4 + 8.3) + the full 18-scenario matrix are explicit exit criteria (quickstart.md). | ✅ Built into plan. |
| VI. Scope Discipline | Whitelist removal + filter + warning + QA + changelog only. The filter is an extension point, not a new feature. No preview/diff, rollback, bulk export, WP-CLI, or merge mode. | ✅ Enforced by spec Out of Scope. |

**Result**: PASS — no violations, no justifications required. Complexity Tracking is empty.

## Project Structure

### Documentation (this feature)

```text
specs/003-shipping-method-filter/
├── plan.md              # This file (/speckit-plan output)
├── spec.md              # Feature specification (/speckit-specify output)
├── research.md          # Phase 0 output — audit findings & decisions (D1–D8)
├── data-model.md        # Phase 1 output — entity/data shapes (E1–E5)
├── quickstart.md        # Phase 1 output — verification runbook (lint + 18-scenario QA + readme)
├── contracts/
│   ├── shipping-export.contract.md   # Exporter behavior: no whitelist, filter, settings capture
│   └── shipping-import.contract.md   # Importer behavior: registration check, skipped-method notice
├── checklists/
│   └── requirements.md  # Spec quality checklist (already created)
└── tasks.md             # /speckit-tasks output (NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
includes/
├── MigrateStore.php                              # EDIT — after import(), set
│                                                 #   migratestore_import_warning transient when
│                                                 #   the importer reports skipped methods
├── Exporters/WooCommerce/
│   └── ShippingZonesExporter.php                 # EDIT — remove method_id IN(...) whitelist;
│                                                 #   apply migratestore_excluded_shipping_methods
│                                                 #   (@filter docblock); fetch per-instance
│                                                 #   _settings options for exported instances
├── Importers/WooCommerce/
│   └── ShippingZonesImporter.php                 # EDIT — registration check via
│                                                 #   WC()->shipping()->get_shipping_methods();
│                                                 #   collect skipped IDs; get_skipped_methods()
└── admin/
    └── admin-import-page.php                     # EDIT — render notice-warning is-dismissible
                                                  #   from migratestore_import_warning transient
readme.txt                                          # EDIT — = 1.2.0 = changelog; Stable tag 1.2.0;
                                                    #   Tested up to 7.0; no placeholder text
migratestore.php                                    # VERIFY — Version/MIGRATESTORE_VERSION 1.2.0,
                                                    #   Requires PHP 7.4 (header bump owned by Phase 1)
```

**Structure Decision**: Single-project WordPress plugin layout, unchanged. The data-loss fix is
localized to the shipping zone exporter/importer pair; the user feedback reuses the existing
transient→notice channel (one new transient key, one new render block); release artifacts are
documentation-only. No new directories, classes, or persistent structures are introduced
(Constitution Principle VI).

## Phase Dependencies

Phase 3 builds on Phase 1 (header/PHP compatibility) and Phase 2 (capability checks, upload
validation, temp cleanup, exact importer routing, field-name consistency). The current branch is
still at the v1.1.9 baseline in code, so QA matrix scenarios 11–14 and 18 (which verify Phase 1/2
guarantees) only become meaningful once Phases 1 and 2 are implemented on the branch. The Phase 3
functional changes (US-1) and the readme consolidation (US-3) are independently implementable now;
the full release QA pass (US-2) is the final gate after all three phases land.

## Complexity Tracking

> No Constitution Check violations. No entries required.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| (none) | — | — |
