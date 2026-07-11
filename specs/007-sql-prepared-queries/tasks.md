---
description: "Task list for SQL Query Compliance (WordPress.DB)"
---

# Tasks: SQL Query Compliance (WordPress.DB)

**Input**: Design documents from `specs/007-sql-prepared-queries/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/get-data-return.md, quickstart.md

**Tests**: No automated test suite exists in this plugin; the feature spec did not
request TDD. Verification is via `php -l`, `wp plugin check migratestore`, and
manual export/import QA (Constitution Principle V). No test-authoring tasks are
generated.

**Branch**: `plugin-check-compliance` (current branch — do NOT create a new branch).

## Context for the implementing LLM (read first)

This is a **one-line code fix**. The entire change is the body of one method.

- **File**: `includes/Exporters/WooCommerce/ShippingZonesExporter.php`
- **Method**: `get_data()` (around lines 18–20)
- **The 2 `WordPress.DB` errors** are on the statement
  `return $this->wpdb->get_results( $this->query, ARRAY_A );` (line ~19).
- **Why the fix is `return null;` and NOT `$wpdb->prepare()`**: `$this->query` is
  declared (`private $query;`) but **never assigned**, so the call is
  `get_results( null, … )`, which WordPress returns as `null` without running SQL.
  The method is dead code — `ShippingZonesExporter` overrides `export()` (never
  calls `get_data()`), and `ShippingZonesImporter` overrides `import_option()`
  (never uses the parent's `get_data()`-based allow-list). There are no
  interpolated values to bind, so `prepare()` is inapplicable. Returning `null`
  clears both errors while preserving the exact (`null`) return contract. Full
  reasoning: `specs/007-sql-prepared-queries/research.md`.
- **Return contract that must NOT change**: `get_data()` returns `null` before and
  after. See `specs/007-sql-prepared-queries/contracts/get-data-return.md`.
- **Do NOT touch**: the `export()` queries (lines 30/34/40), the
  `ShippingZonesImporter::validate()` COUNT(*) queries (lines 153–155), or the
  vendor `lib/` directory. These are prefix-literal and already sniff-clean.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files/no dependency)
- **[Story]**: US1, US2 (maps to spec.md user stories)

---

## Phase 1: Setup (Baseline)

**Purpose**: Establish the pre-change baseline so progress is measurable.

- [X] T001 Confirm the working branch is `plugin-check-compliance` by running `git rev-parse --abbrev-ref HEAD`; do NOT create or switch branches.
- [X] T002 Record the baseline by running `wp plugin check migratestore` (in a WordPress environment) and noting the count and locations of `WordPress.DB` errors; expect 2 errors, both pointing at `includes/Exporters/WooCommerce/ShippingZonesExporter.php` around line 19. If the CLI is unavailable in this environment, note that verification is deferred to the maintainer per research.md.

**Checkpoint**: Baseline known (target after fix = 0 `WordPress.DB` errors).

---

## Phase 2: Foundational (Confirm the target before editing)

**Purpose**: Verify the code matches the plan's assumptions so the edit is safe. BLOCKS the edit.

- [X] T003 Open `includes/Exporters/WooCommerce/ShippingZonesExporter.php` and confirm: (a) line ~11 declares `private $query;`, (b) there is NO assignment to `$this->query` anywhere in the file, and (c) line ~19 reads `return $this->wpdb->get_results( $this->query, ARRAY_A );`. If `$this->query` IS assigned somewhere, STOP and re-read `specs/007-sql-prepared-queries/research.md` — the dead-code premise would not hold.
- [X] T004 Confirm `get_data()` is not consumed for this class: grep the codebase for `get_data(` and verify the only caller path is `includes/Importers/AbstractImporter.php` `import_option()`, AND that `includes/Importers/WooCommerce/ShippingZonesImporter.php` overrides `import_option()` (around line 115). This proves the `null` return is never consumed for shipping zones.

**Checkpoint**: Dead-code + null-return premise confirmed; safe to edit.

---

## Phase 3: User Story 1 - Clear Plugin Check database-query errors (Priority: P1) 🎯 MVP

**Goal**: `wp plugin check migratestore` reports 0 `WordPress.DB` errors for `get_data()`.

**Independent Test**: Run `wp plugin check migratestore` and confirm 0 `WordPress.DB` errors in own code (`lib/` excluded); line 19 is no longer flagged.

### Implementation for User Story 1

- [X] T005 [US1] In `includes/Exporters/WooCommerce/ShippingZonesExporter.php`, replace the body of `get_data()` so it no longer executes the unprepared query. Change:
  ```php
  public function get_data() {
      return $this->wpdb->get_results( $this->query, ARRAY_A );
  }
  ```
  to:
  ```php
  public function get_data() {
      // The shipping-zones data is exported by export() directly; this method is
      // not used as an allow-list source (ShippingZonesImporter overrides
      // import_option()). It has always returned null (the $query property is
      // never set), so we keep that exact contract without running an unprepared
      // raw query that trips WordPress.DB. See specs/007-sql-prepared-queries.
      return null;
  }
  ```
  Do not change any other method. Do not add a `phpcs:ignore`.
- [ ] T006 [US1] Run `php -l includes/Exporters/WooCommerce/ShippingZonesExporter.php` and confirm "No syntax errors detected" (run under PHP 7.4 and PHP 8.3 if both are available).
- [ ] T007 [US1] Run `wp plugin check migratestore` and confirm 0 `WordPress.DB` errors in own code, that line 19 is no longer flagged, and that NO new `WordPress.DB` error appeared on the `export()` (lines 30/34/40) or `validate()` (lines 153–155) prefix-literal queries. (If the CLI is unavailable here, mark for maintainer verification per research.md.)

**Checkpoint**: 2 `WordPress.DB` errors → 0; no new sniff errors. MVP complete.

---

## Phase 4: User Story 2 - Legacy export still returns the same data (Priority: P1)

**Goal**: Confirm the edit changed no observable behavior — `get_data()` still returns `null`, and the shipping-zones export/import round-trip is unchanged.

**Independent Test**: `get_data()` returns `null` for any DB state; a shipping-zones export downloads and imports identically to a pre-change build.

### Verification for User Story 2

- [X] T008 [US2] Confirm the return contract: verify `ShippingZonesExporter::get_data()` returns `null` (matching `specs/007-sql-prepared-queries/contracts/get-data-return.md`) — it must NOT return an array, `false`, or a populated result set.
- [ ] T009 [P] [US2] Manual export QA: from the admin export page, export shipping zones. Confirm a ZIP downloads and, opened, contains the same JSON keys as before (`woocommerce_shipping_zones`, `woocommerce_shipping_zone_methods`, `woocommerce_shipping_zone_locations`, `options`).
- [ ] T010 [P] [US2] Manual import QA: on a site with no existing shipping zones, import the ZIP from T009. Confirm zones, methods, locations, and per-instance method settings apply with the usual confirming admin notice — identical to pre-change behavior. (Per `ShippingZonesImporter::validate()`, the import requires zero pre-existing zones.)

**Checkpoint**: No user-visible difference in export or import; return contract preserved.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Optional cleanup and final whole-plugin verification.

- [~] T011 [P] OPTIONAL (cosmetic, no compliance impact): in `includes/Exporters/WooCommerce/ShippingZonesExporter.php`, remove the now-unused `private $wpdb;` and `private $query;` properties and the constructor that sets `$this->wpdb`. **Skipped** to keep a minimal diff.
- [ ] T012 Run the full `wp plugin check migratestore` once more and confirm total own-code errors are unchanged except `WordPress.DB` dropping to 0 (no regressions in other sniff categories).
- [X] T013 OPTIONAL: add a `readme.txt` changelog line under the in-progress version noting the `WordPress.DB` (unprepared query) compliance fix, consistent with prior Plugin Check phase entries. Only do this if the repo's changelog convention expects a line per phase.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: After Setup. BLOCKS the edit (verifies the premise).
- **User Story 1 (Phase 3)**: After Foundational. Contains the only code edit (T005).
- **User Story 2 (Phase 4)**: After T005 (the edit) is applied. Pure verification.
- **Polish (Phase 5)**: After US1 and US2.

### User Story Dependencies

- **US1 (P1)**: The fix itself. Independently verifiable via `wp plugin check`.
- **US2 (P1)**: Depends on US1's edit (T005) being in place, then verifies behavior
  was preserved. Not a separate code change — it shares the single edit.

### Within Each Story

- T005 (edit) → T006 (lint) → T007 (sniff) in order.
- T008 before T009/T010; T009 and T010 are independent ([P]).

### Parallel Opportunities

- T009 and T010 (manual export QA / import QA) can be split across testers ([P]).
- T011 and T013 (optional polish) are independent of each other ([P]).
- The core path (T005→T006→T007) is strictly sequential — a single 1-line change.

---

## Implementation Strategy

### MVP (delivers the entire feature value)

1. Phase 1 (Setup) → Phase 2 (Foundational, confirm premise) → Phase 3 (US1: the edit + lint + sniff).
2. **STOP and VALIDATE**: `wp plugin check migratestore` shows 0 `WordPress.DB` errors. This alone satisfies the primary success criterion (SC-001/SC-005).

### Then

3. Phase 4 (US2): confirm export/import behavior is unchanged (SC-002/SC-004).
4. Phase 5 (Polish): optional cleanup + final full Plugin Check run.

---

## Notes

- The whole feature is **one line** (`return null;`). Phases 1–2 protect the premise; Phases 4–5 prove no regression.
- Do NOT wrap the query in `$wpdb->prepare()` — there is nothing to bind, and the query is null/dead (see research.md decision and Complexity Tracking in plan.md).
- Do NOT add a `phpcs:ignore` — removing the dead query clears the sniff outright.
- Do NOT modify `export()`/`validate()` queries or `lib/`.
- Commit on `plugin-check-compliance` only.
