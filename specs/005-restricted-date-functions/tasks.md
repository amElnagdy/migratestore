---
description: "Task list for Restricted Date Functions Compliance (WordPress.DateTime)"
---

# Tasks: Restricted Date Functions Compliance (WordPress.DateTime)

**Input**: Design documents from `specs/005-restricted-date-functions/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/export-filename.md, quickstart.md

**Branch**: `plugin-check-compliance`

**Tests**: None. This is a mechanical lint/compliance fix; verification is via
`php -l`, `wp plugin check`, and manual export QA (Constitution Principle V),
not automated unit tests. No test tasks are generated.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 = clear Plugin Check errors; US2 = filenames keep format
- Exact file paths are included in every task.

## The exact edit (applies to all 8 implementation tasks)

In each listed file, change **only** the date function name on the
filename-builder line — `date(` becomes `gmdate(`. Do NOT change the format
string `'Ymd_His'`, the surrounding prefix, the `.json` suffix, spacing, or any
other line.

```php
// BEFORE
return 'migratestore_<type>_' . date( 'Ymd_His' ) . '.json';
// AFTER
return 'migratestore_<type>_' . gmdate( 'Ymd_His' ) . '.json';
```

---

## Phase 1: Setup (Baseline)

**Purpose**: Establish the starting point before editing.

- [X] T001 Confirm the working branch is `plugin-check-compliance` (run `git rev-parse --abbrev-ref HEAD`); do not create a new branch.
- [X] T002 Inventory the target call sites: run `grep -rn "[^_a-z]date(" includes/Exporters/` and confirm exactly 8 matches, one per file listed in Phase 3. Record the baseline `WordPress.DateTime` error count if a WordPress env is available (`wp plugin check migratestore`).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: None required. There is no shared infrastructure, schema, or base
class to build first — each edit is a self-contained one-line change in a leaf
class.

*(No tasks in this phase. Proceed directly to Phase 3.)*

**Checkpoint**: No foundational work blocks the user stories.

---

## Phase 3: User Story 1 - Clear Plugin Check date-function errors (Priority: P1) 🎯 MVP

**Goal**: Replace every `date( 'Ymd_His' )` call in the WooCommerce exporters
with `gmdate( 'Ymd_His' )` so `wp plugin check migratestore` reports 0
`WordPress.DateTime` errors in own code.

**Independent Test**: Run `wp plugin check migratestore` and confirm the
`WordPress.DateTime` category reports 0 errors across `includes/Exporters/`
(`lib/` excluded per Phase 0).

### Implementation for User Story 1

All 8 tasks edit different files and are fully parallelizable.

- [X] T003 [P] [US1] In `includes/Exporters/WooCommerce/TaxOptionsExporter.php` (the `migratestore_tax_options_` filename line, ~line 30) change `date( 'Ymd_His' )` to `gmdate( 'Ymd_His' )`.
- [X] T004 [P] [US1] In `includes/Exporters/WooCommerce/ShippingZonesExporter.php` (inside `get_json_filename()`, `migratestore_zones_`, ~line 109) change `date( 'Ymd_His' )` to `gmdate( 'Ymd_His' )`.
- [X] T005 [P] [US1] In `includes/Exporters/WooCommerce/ShippingOptionsExporter.php` (the `migratestore_shipping_options_` filename line, ~line 26) change `date( 'Ymd_His' )` to `gmdate( 'Ymd_His' )`.
- [X] T006 [P] [US1] In `includes/Exporters/WooCommerce/EmailsOptionsExporter.php` (the `migratestore_emails_settings_` filename line, ~line 39) change `date( 'Ymd_His' )` to `gmdate( 'Ymd_His' )`.
- [X] T007 [P] [US1] In `includes/Exporters/WooCommerce/ShippingClassesExporter.php` (the `migratestore_shipping_classes_` filename line, ~line 27) change `date( 'Ymd_His' )` to `gmdate( 'Ymd_His' )`.
- [X] T008 [P] [US1] In `includes/Exporters/WooCommerce/AccountsPrivacyExporter.php` (the `migratestore_accounts_privacy_options_` filename line, ~line 39) change `date( 'Ymd_His' )` to `gmdate( 'Ymd_His' )`.
- [X] T009 [P] [US1] In `includes/Exporters/WooCommerce/EndpointsExporter.php` (the `migratestore_endpoints_options_` filename line, ~line 36) change `date( 'Ymd_His' )` to `gmdate( 'Ymd_His' )`.
- [X] T010 [P] [US1] In `includes/Exporters/WooCommerce/GeneralSettingsExporter.php` (the `migratestore_general_settings_` filename line, ~line 39) change `date( 'Ymd_His' )` to `gmdate( 'Ymd_His' )`.

### Verification for User Story 1

- [X] T011 [US1] Confirm no bare `date()` remains in own exporter code: `grep -rn "[^_a-z]date(" includes/Exporters/` returns nothing (only `gmdate(` should remain). Depends on T003–T010.
- [ ] T012 [US1] Run `php -l` on each of the 8 edited files; all must report "No syntax errors detected" (verify on PHP 7.4 and PHP 8.3 per Constitution Principle I). Depends on T003–T010.
- [ ] T013 [US1] In a WordPress environment, run `wp plugin check migratestore` and confirm **0** `WordPress.DateTime` errors in own code (`lib/` excluded). Depends on T003–T010.

**Checkpoint**: User Story 1 is complete — Plugin Check reports 0
`WordPress.DateTime` errors and syntax is clean.

---

## Phase 4: User Story 2 - Exported filenames keep format and uniqueness (Priority: P2)

**Goal**: Confirm the 8 edits did not change the export filename shape — files
still match `migratestore_<type>_<8 digits>_<6 digits>.json` and remain unique
across exports taken at different seconds.

**Independent Test**: Generate an export from an affected exporter and confirm
the downloaded filename matches `^migratestore_[a-z_]+_[0-9]{8}_[0-9]{6}\.json$`.

### Verification for User Story 2

- [X] T014 [US2] Static check: confirm every edited line preserves the literal format string `'Ymd_His'`, its prefix, and the `.json` suffix exactly (diff review of T003–T010 against `contracts/export-filename.md`). Depends on T003–T010.
- [ ] T015 [US2] Manual QA: trigger an export for at least 3 of the 8 types (e.g. shipping zones, general settings, emails) and confirm each downloaded filename matches `^migratestore_[a-z_]+_[0-9]{8}_[0-9]{6}\.json$`. Depends on T013.
- [ ] T016 [US2] Uniqueness/ordering check: generate two exports of the same type ≥1 second apart and confirm the timestamp segments differ and sort chronologically. Depends on T013.

**Checkpoint**: Filename format and uniqueness verified unchanged — no
regression to the user-facing export artifact.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Final validation and changelog.

- [ ] T017 Run the full `quickstart.md` verification checklist end-to-end and confirm every "Done when" item passes.
- [X] T018 [P] Add a `readme.txt` changelog entry under the `1.2.0` release noting: "Replaced `date()` with `gmdate()` in WooCommerce exporters to satisfy `WordPress.DateTime` Plugin Check (timezone-independent export filenames)." (Per Constitution Versioning & Release Discipline.)
- [ ] T019 Final gate: re-run `wp plugin check migratestore` and confirm the `WordPress.DateTime` category remains at 0 errors after all changes.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Empty — nothing blocks the user stories.
- **User Story 1 (Phase 3)**: Implementation tasks T003–T010 depend only on Setup. Verification T011–T013 depend on all 8 edits.
- **User Story 2 (Phase 4)**: Depends on US1 edits (T003–T010) and, for manual QA, on T013.
- **Polish (Phase 5)**: Depends on US1 and US2 verification passing.

### User Story Dependencies

- **US1 (P1)**: The MVP. Delivers the compliance fix on its own.
- **US2 (P2)**: Pure verification layer over the same edits — confirms no regression. Not independently implementable (it has no edits of its own) but independently *testable*.

### Within Each User Story

- US1: all 8 edits (T003–T010) are independent → verification (T011–T013).
- US2: static diff check (T014) can run right after edits; manual QA (T015–T016) after T013.

### Parallel Opportunities

- **T003–T010** all touch different files with no shared state → run all 8 in parallel.
- T011 and T012 can run in parallel after the edits; T013 requires a WP environment.

---

## Parallel Example: User Story 1

```bash
# Apply all 8 exporter edits together (different files, no dependencies):
Task: "Edit TaxOptionsExporter.php — date('Ymd_His') → gmdate('Ymd_His')"
Task: "Edit ShippingZonesExporter.php — date('Ymd_His') → gmdate('Ymd_His')"
Task: "Edit ShippingOptionsExporter.php — date('Ymd_His') → gmdate('Ymd_His')"
Task: "Edit EmailsOptionsExporter.php — date('Ymd_His') → gmdate('Ymd_His')"
Task: "Edit ShippingClassesExporter.php — date('Ymd_His') → gmdate('Ymd_His')"
Task: "Edit AccountsPrivacyExporter.php — date('Ymd_His') → gmdate('Ymd_His')"
Task: "Edit EndpointsExporter.php — date('Ymd_His') → gmdate('Ymd_His')"
Task: "Edit GeneralSettingsExporter.php — date('Ymd_His') → gmdate('Ymd_His')"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 Setup (confirm branch + inventory the 8 calls).
2. Phase 3: apply all 8 edits (T003–T010), then verify (T011–T013).
3. **STOP and VALIDATE**: `wp plugin check migratestore` → 0 `WordPress.DateTime` errors. This alone is a shippable compliance increment.

### Incremental Delivery

1. US1 → Plugin Check passes (MVP).
2. US2 → confirm filenames unchanged (regression guard).
3. Polish → changelog + final gate.

---

## Notes

- [P] tasks = different files, no dependencies — the 8 edits are the canonical parallel set.
- Each edit is a single-token change (`date` → `gmdate`); do not refactor or touch the format string (Constitution Principle VI — scope discipline).
- Do NOT modify the vendor `lib/` directory or any non-`WordPress.DateTime` finding.
- Commit after the 8 edits as one logical group (e.g. `fix(datetime): use gmdate() for export filenames to satisfy WordPress.DateTime`).
- `wp plugin check` verification is performed by the maintainer in a WordPress environment; the dev sandbox lacks the CLI.
