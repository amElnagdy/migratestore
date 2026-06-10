# Implementation Plan: Plugin Headers & PHP Compatibility (Phase 1)

**Branch**: `migratestore-wp7-readiness` | **Date**: 2026-06-10 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/001-plugin-headers-php-compat/spec.md`

## Summary

Phase 1 makes Migrate Store present and behave as WordPress 7.0 / modern-PHP ready without any
functional change. Two concrete edits deliver the visible outcome (US-1): update the version to
`1.2.0` and add the WP 7.0 / PHP 7.4 / `Requires Plugins` headers in `migratestore.php` and
`readme.txt`, plus a `1.2.0` changelog entry. A repo-wide PHP-compatibility audit (US-2, US-3)
confirms there are no PHP 8.x deprecation patterns and no unguarded PHP 8.0+ functions; if any are
found during the lint gate they are fixed, and a guarded `includes/polyfills.php` is added only if
a PHP 8.0+ function is actually in use. The release is gated by `php -l` on PHP 7.4 and 8.3 plus
the manual QA smoke of export/import flows.

## Technical Context

**Language/Version**: PHP — minimum **7.4** (declared/enforced), tested on **8.3**. Single
codebase must lint clean on both.

**Primary Dependencies**: WordPress 7.0 (min 6.0), WooCommerce (declared via
`Requires Plugins: woocommerce`, tested to 9.x), `ext-zip` (`ZipArchive`), `WP_Filesystem`.
Composer PSR-4 autoload maps `MigrateStore\` → `includes/`, vendor dir is `lib/`.

**Storage**: WordPress options table (read/written by exporters/importers). **Not touched** in
Phase 1 — no schema or query changes.

**Testing**: `php -l` across all plugin `.php` files on PHP 7.4 and 8.3 (lint gate) + manual QA
smoke of export/import flows on PHP 8.3 with deprecation logging, and on PHP 7.4 for the
undefined-function check. No automated test suite exists in the repo.

**Target Platform**: WordPress 7.0 site running PHP 7.4–8.3 with WooCommerce active.

**Project Type**: Single-project WordPress/WooCommerce plugin (root `migratestore.php` bootstrap +
`includes/` PSR-4 source + `lib/` Composer vendor).

**Performance Goals**: N/A — no runtime behavior change; headers and code-hygiene only.

**Constraints**:
- No functional behavior change to export/import (FR-017).
- Same source must lint on 7.4 and 8.3 without version-forking that breaks either.
- Polyfill file is created **only if** a PHP 8.0+ function is actually used (conditional).
- **Environment note**: no PHP binary is on PATH in the authoring environment; the `php -l` gate
  runs in the developer/CI environment (local PHP 7.4 + 8.3, or Docker images). The static audit
  below substitutes for design-time confidence; the binary lint remains the merge gate.

**Scale/Scope**: 33 plugin `.php` files (24 under `includes/`, 3 admin includes, root bootstrap,
plus `lib/` vendor). Two files edited for headers; the rest are audit-only unless the lint gate
surfaces a fix.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Gates derived from `.specify/memory/constitution.md` v1.0.0:

| Principle | Relevance to Phase 1 | Status |
|-----------|---------------------|--------|
| I. WP 7.0 & Modern PHP Compatibility | This phase **is** Principle I: `php -l` clean on 7.4 + 8.3, no deprecated 8.x patterns, polyfill guards for 8.0+ functions. | ✅ Directly satisfied; the plan's entire purpose. |
| II. Security Is Non-Negotiable | Out of scope (Phase 2). Phase 1 must not regress existing checks. | ✅ No handler logic touched. |
| III. WP/WooCommerce Coding Standards | `Requires Plugins: woocommerce` header added; no whitelist/i18n changes. No new TODO/FIXME. | ✅ Compliant. |
| IV. Data Integrity & Consistency | Out of scope (Phase 2 — field naming, constructor). Not touched here. | ✅ Untouched. |
| V. Verified Before Merge | Lint gate (7.4 + 8.3) + manual QA smoke are explicit exit criteria. | ✅ Built into plan. |
| VI. Scope Discipline | Phase 1 only (US-1/2/3). No features; no Phase 2/3 work. | ✅ Enforced by FR-017 + Out of Scope. |

**Result**: PASS — no violations, no justifications required. Complexity Tracking is empty.

## Project Structure

### Documentation (this feature)

```text
specs/001-plugin-headers-php-compat/
├── plan.md              # This file (/speckit-plan output)
├── spec.md              # Feature specification (/speckit-specify output)
├── research.md          # Phase 0 output — audit findings & decisions
├── data-model.md        # Phase 1 output — metadata "entities" (headers, readme, polyfill)
├── quickstart.md        # Phase 1 output — verification runbook (lint + QA)
├── contracts/
│   └── plugin-metadata.contract.md   # Required header/readme field contract
├── checklists/
│   └── requirements.md  # Spec quality checklist (already created)
└── tasks.md             # /speckit-tasks output (NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
migratestore.php                       # Bootstrap + plugin header — EDIT (version + headers)
readme.txt                             # WP.org metadata + changelog — EDIT (tested up to, tag, changelog)
includes/
├── MigrateStore.php                   # AUDIT only
├── Plugins_Checker.php                # AUDIT only
├── admin/                             # AUDIT only (3 files)
├── Exporters/                         # AUDIT only (AbstractExporter + 8 WooCommerce exporters)
└── Importers/                         # AUDIT only (AbstractImporter + 8 WooCommerce importers)
includes/polyfills.php                 # CREATE only if a PHP 8.0+ function is found in use
lib/                                   # Composer vendor — lint must pass; not hand-edited
```

**Structure Decision**: Single-project WordPress plugin layout, unchanged. The only mutated files
this phase are the two metadata files (`migratestore.php`, `readme.txt`); `includes/polyfills.php`
is created conditionally. All other source is audited (read + lint), not modified, unless the lint
gate surfaces a concrete deprecation that must be fixed in place.

## Complexity Tracking

> No Constitution Check violations. No entries required.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| (none) | — | — |
