# Implementation Plan: Security Hardening & Data Integrity (Phase 2)

**Branch**: `migratestore-wp7-readiness` | **Date**: 2026-06-10 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/002-security-data-integrity/spec.md`

## Summary

Phase 2 closes the security gaps and data-integrity defects in the export/import pipeline without
adding features. The work concentrates in one orchestrator and the two abstract base classes:

- **Authorization (US-1):** add `current_user_can( 'manage_woocommerce' )` — ending in a translated
  `wp_die()` on failure — at the top of `handle_export_action()` and `handle_import_action()` in
  `includes/MigrateStore.php`, alongside the existing `check_admin_referer()` nonce checks (both,
  not either).
- **Safe upload handling (US-2):** in `handle_import_action()`, validate the upload by MIME via
  `wp_check_filetype_and_ext()`, enforce a filterable max size (default 10 MB,
  `migratestore_max_upload_size`), inspect every ZIP entry and reject the whole import on any `..`
  or absolute path, and guarantee removal of the uploaded file **and** the temp dir on **every**
  exit path (success, caught exception, and the early `wp_die()` bail-outs that currently leak).
- **Exact importer routing (US-4):** replace the reversed substring test
  `strpos($key, $filename) !== false` (line 212) with strict `array_key_exists()/===` lookup against
  the existing `$importerStrategies` map, failing with an actionable notice when nothing matches.
- **Field-naming integrity (US-3):** standardize on `option_name`/`option_value`. Exporters
  (`AbstractExporter::get_options_values()`) write the new pair; importers
  (`AbstractImporter::import()` + `import_option()` + the allowed-name validation) read the new pair
  with a documented fallback to the legacy `option`/`value` for v1.1.9 archives. Remove the
  duplicate `woocommerce_customer_completed_order_settings` line in `EmailsOptionsExporter.php`.
- **Constructor consistency (US-5):** verify the parent/child `__construct` signatures (audit shows
  they are already consistent — zero-arg children each calling
  `parent::__construct( new XExporter() )`); document and lock the contract, fix only if the lint
  gate surfaces a mismatch.
- **Code hygiene (US-6):** resolve the `//TODO` in `ShippingZonesImporter.php` (line 22) and confirm
  zero TODO/FIXME remain repo-wide.

Gated by `php -l` on PHP 7.4 + 8.3 and the manual QA matrix (legitimate flows + the four hardening
rejection cases), per Constitution Principle V.

## Technical Context

**Language/Version**: PHP — minimum **7.4** (declared/enforced), tested on **8.3**. Single codebase
must lint clean on both. New code uses only 7.4-safe syntax (no `str_contains` without guard, no
`${var}`).

**Primary Dependencies**: WordPress 7.0 (min 6.0) admin/upload/filesystem APIs —
`current_user_can`, `check_admin_referer`, `wp_handle_upload`, `wp_check_filetype_and_ext`,
`unzip_file`, `WP_Filesystem`, `wp_die`, `apply_filters`, `get_transient`/`set_transient`;
WooCommerce (capability `manage_woocommerce`; declared via `Requires Plugins: woocommerce`);
`ext-zip` (`ZipArchive`) for export and for pre-extraction entry inspection. Composer PSR-4 maps
`MigrateStore\` → `includes/`.

**Storage**: WordPress options table + WooCommerce shipping-zone tables (read by exporters, written
by importers via `update_option()` and `$wpdb->insert()`). No schema changes; this phase changes
how option **payloads are named, validated, routed, and cleaned up**, not the storage shape.

**Testing**: `php -l` across all plugin `.php` files on 7.4 and 8.3 (lint gate) + manual QA matrix
(see quickstart.md): legitimate export/import of each settings type, plus the hardening cases —
non-ZIP rejected, oversized ZIP rejected, traversal ZIP rejected with no extraction, Subscriber
blocked, and re-import against a site with existing shipping zones. No automated test suite exists
in the repo.

**Target Platform**: WordPress 7.0 site on PHP 7.4–8.3 with WooCommerce active; admin-post.php
form handlers (not AJAX — the handlers are `admin_post_*` actions despite the spec's "AJAX" wording).

**Project Type**: Single-project WordPress/WooCommerce plugin (root `migratestore.php` bootstrap +
`includes/` PSR-4 source).

**Performance Goals**: N/A — correctness/security only. The added work (MIME check, size check, ZIP
entry scan) is bounded by upload size, which is now capped at 10 MB.

**Constraints**:
- Both nonce AND capability required on every handler; satisfying one alone must not permit action.
- Temp cleanup must run on **all** exit paths, including the early `wp_die()` bails that currently
  leak the uploaded file and tmp dir.
- Backward compatibility: v1.1.9 archives (legacy `option`/`value` keys) must still import; new
  exports write `option_name`/`option_value`.
- New code must lint on 7.4 and 8.3 — no version-forking that breaks either.
- **Environment note**: no PHP binary on PATH in the authoring environment; the `php -l` gate runs
  in the developer/CI environment (local PHP 7.4 + 8.3, or Docker). The static analysis in
  research.md substitutes for design-time confidence; binary lint + manual QA remain the merge gate.

**Scale/Scope**: One orchestrator (`includes/MigrateStore.php`) holds both handlers and the routing
loop; two base classes (`AbstractExporter`, `AbstractImporter`) hold the field-naming contract;
8 exporters + 8 importers inherit it; 2 point fixes (`EmailsOptionsExporter` duplicate,
`ShippingZonesImporter` TODO + its `option_name`/`option_value` `import_option` override already
aligned). ~5 files materially edited, ~16 inherit behavior, all audited.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Gates derived from `.specify/memory/constitution.md` v1.0.0:

| Principle | Relevance to Phase 2 | Status |
|-----------|---------------------|--------|
| I. WP 7.0 & Modern PHP Compatibility | New code must lint on 7.4 + 8.3, no deprecated patterns, no unguarded 8.0+ functions. | ✅ Built into the lint gate; new code is 7.4-safe. |
| II. Security Is Non-Negotiable | This phase **is** Principle II: capability + nonce on both handlers, MIME + size validation, ZIP traversal rejection, temp cleanup on success AND failure, exact import-key matching, `wp_die()` with translated message on unauthorized. | ✅ Directly satisfied; the plan's core purpose. Every Principle II clause (1–7) maps to an FR. |
| III. WP/WooCommerce Coding Standards | Translated `wp_die()` strings with `migratestore` text domain; capability + nonce together; no new TODO/FIXME (one removed). Shipping-method whitelist is Phase 3, untouched here. | ✅ Compliant. |
| IV. Data Integrity & Consistency | This phase **is** Principle IV: single `option_name`/`option_value` convention across all exporters/importers with documented legacy fallback; constructor signatures verified consistent; `EmailsOptionsExporter` duplicate removed. | ✅ Directly satisfied. |
| V. Verified Before Merge | Lint gate (7.4 + 8.3) + the full manual QA matrix incl. the four hardening rejection cases are explicit exit criteria (quickstart.md). | ✅ Built into plan. |
| VI. Scope Discipline | Phase 2 only (US-1..6). No shipping-method whitelist removal (Phase 3), no new features. | ✅ Enforced by FR-019 + Out of Scope. |

**Result**: PASS — no violations, no justifications required. Complexity Tracking is empty.

## Project Structure

### Documentation (this feature)

```text
specs/002-security-data-integrity/
├── plan.md              # This file (/speckit-plan output)
├── spec.md              # Feature specification (/speckit-specify output)
├── research.md          # Phase 0 output — audit findings & decisions
├── data-model.md        # Phase 1 output — payload/entity contracts (option entry, upload, routing)
├── quickstart.md        # Phase 1 output — verification runbook (lint + QA matrix)
├── contracts/
│   ├── handler-security.contract.md   # Capability/nonce/upload/cleanup gate contract
│   └── option-payload.contract.md     # option_name/option_value JSON field contract + fallback
├── checklists/
│   └── requirements.md  # Spec quality checklist (already created)
└── tasks.md             # /speckit-tasks output (NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
includes/
├── MigrateStore.php                              # EDIT — capability checks on both handlers;
│                                                 #   MIME + size + traversal validation; exact
│                                                 #   importer routing (replace strpos); cleanup
│                                                 #   on all exit paths
├── Exporters/
│   ├── AbstractExporter.php                       # EDIT — get_options_values() writes
│   │                                              #   option_name/option_value (+ doc comment)
│   └── WooCommerce/
│       ├── EmailsOptionsExporter.php              # EDIT — remove duplicate option entry
│       └── {7 other exporters}                    # AUDIT — inherit get_options_values(); confirm
├── Importers/
│   ├── AbstractImporter.php                       # EDIT — import()/import_option()/allowed-name
│   │                                              #   validation read option_name/option_value with
│   │                                              #   documented legacy option/value fallback;
│   │                                              #   confirm constructor contract
│   └── WooCommerce/
│       ├── ShippingZonesImporter.php              # EDIT — resolve TODO (line 22); import_option
│       │                                          #   already uses option_name/option_value
│       └── {7 other importers}                    # AUDIT — inherit base; confirm zero-arg
│                                                  #   constructor + parent::__construct contract
languages/                                          # AUDIT — new wp_die() strings are translatable
```

**Structure Decision**: Single-project WordPress plugin layout, unchanged. The security and routing
changes are localized to `includes/MigrateStore.php`; the data-integrity (field-naming) contract is
defined once in the two abstract bases and inherited by all concrete exporters/importers, with two
file-local point fixes (`EmailsOptionsExporter` duplicate, `ShippingZonesImporter` TODO). No new
directories, classes, or persistent structures are introduced (FR-019).

## Complexity Tracking

> No Constitution Check violations. No entries required.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| (none) | — | — |
