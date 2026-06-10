---

description: "Task list for Phase 3 — Shipping Method Filter & Release QA"
---

# Tasks: Shipping Method Filter & Release QA (Phase 3)

**Input**: Design documents from `specs/003-shipping-method-filter/`
**Branch**: `migratestore-wp7-readiness` (all work commits here — do **not** create a new branch)
**Plugin root**: `D:\WordPress\migratestore\` (paths below are repo-relative)

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: No automated test suite exists in this repo. Per Constitution Principle V, verification is
`php -l` (PHP 7.4 + 8.3) + the manual 18-scenario QA matrix. No automated-test tasks are generated;
QA tasks appear in Phase 5.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: US1 = shipping-method export/import fix; US2 = release QA; US3 = readme/changelog

---

## ⚠️ READ FIRST — Implementation ground rules (for the implementing model)

These rules prevent the most likely mistakes. Follow them on **every** task.

1. **PHP 7.4 floor, PHP 8.3 tested.** Use only 7.4-safe syntax. The `: array` / `: bool` return
   types used below are valid in 7.4. Do **NOT** use `str_contains`/`str_starts_with`/`str_ends_with`
   without a guard, arrow-fn-only features beyond 7.4, enums, named args, readonly props, or
   `${var}` string interpolation.
2. **Keep existing field names.** The export/import JSON uses `option_name` / `option_value`
   (Phase 2 convention). Do not rename keys. The shipping section keys
   (`woocommerce_shipping_zones`, `woocommerce_shipping_zone_methods`,
   `woocommerce_shipping_zone_locations`, `options`) MUST stay exactly as-is — the importer's
   `switch` depends on them.
3. **Never build SQL from filter output or method IDs by concatenation.** Use `$wpdb->prepare`
   with placeholders, or filter in PHP (as specified). 
4. **Escape all output** in admin notices with `esc_html()` / `esc_html__()` and the `migratestore`
   text domain.
5. **A skipped shipping method must never abort the import** — collect it and continue.
6. **Idempotence:** if a value a task sets is already correct (e.g. version already `1.2.0` because
   Phase 1 ran), leave it correct — do not revert it.
7. **Do NOT touch the `//TODO` at `ShippingZonesImporter.php` line 22** — that is Phase 2's scope,
   not Phase 3's. If it is still present, leave it; just note it in your final report.
8. After each task or logical group, the change must keep the plugin loadable (no syntax errors).

---

## Phase 1: Setup & Pre-flight

**Purpose**: Confirm the working tree and prepare the QA environment. No code is written here.

- [ ] T001 Confirm the current branch is `migratestore-wp7-readiness` and the working tree is clean (`git status`). Confirm the four target files exist: `includes/Exporters/WooCommerce/ShippingZonesExporter.php`, `includes/Importers/WooCommerce/ShippingZonesImporter.php`, `includes/MigrateStore.php`, `includes/admin/admin-import-page.php`.
- [ ] T002 Prepare a QA environment for Phase 5 (does not block code tasks): WordPress 7.0 + latest WooCommerce, switchable PHP 7.4 and PHP 8.3, and a third-party shipping-method plugin installed (e.g. a Table Rate plugin) to exercise the third-party export/import scenarios. Record the environment details for the QA log.

**Checkpoint**: Branch confirmed, target files present, QA env identified.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish the one cross-file contract that US1 relies on.

**There is no shared schema, framework, or base-class change in this phase.** The only cross-file
dependency is the agreed integration point between the importer and the orchestrator:

- [ ] T003 Adopt the integration contract for skipped methods (no code yet — this is the agreement the next tasks implement): the importer will expose `public function get_skipped_methods(): array`, and the orchestrator will read it and set a `migratestore_import_warning` transient (60 s TTL) holding a sanitized, de-duplicated array of skipped method-id strings, rendered as a `notice notice-warning is-dismissible` on the import page. See `contracts/shipping-import.contract.md`.

**Checkpoint**: Integration contract understood — US1 implementation can begin.

---

## Phase 3: User Story 1 — Third-party shipping methods survive export & import (Priority: P1) 🎯 MVP

**Goal**: Remove the hardcoded shipping-method whitelist from export, make exclusion filterable,
carry each method's settings, and on import skip-with-warning any method not registered on the
target — instead of silently dropping methods.

**Independent Test**: Export a zone containing a third-party method → it appears in the JSON (QA #4);
import that JSON on a site lacking the method's plugin → the method is skipped, the rest imports, and
a dismissible warning notice lists the skipped method ID (QA #6). A legacy/built-ins-only file imports
unchanged with no warning (QA #5).

### Implementation for User Story 1

- [ ] T004 [P] [US1] **Remove the method whitelist + add the exclusion filter + carry per-instance settings** in `includes/Exporters/WooCommerce/ShippingZonesExporter.php`.
  Replace the **entire** `export()` method (currently lines 26–46, the one containing the `$queries` array with `WHERE method_id IN ('flat_rate', 'free_shipping', 'local_pickup')`) with the following:

  ```php
  public function export() {
      global $wpdb;

      // Shipping zones and locations are exported in full (no restriction).
      $zones = $wpdb->get_results(
          "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zones",
          ARRAY_A
      );
      $locations = $wpdb->get_results(
          "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_locations",
          ARRAY_A
      );

      // Export ALL shipping zone methods — no hardcoded whitelist (Phase 3).
      $methods = $wpdb->get_results(
          "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods",
          ARRAY_A
      );

      /**
       * Filters the shipping method IDs to exclude from export.
       *
       * @filter migratestore_excluded_shipping_methods
       * @param string[] $excluded Array of shipping method IDs to omit from export.
       *                           Default empty array (export every method).
       * @return string[]
       */
      $excluded = apply_filters( 'migratestore_excluded_shipping_methods', array() );
      if ( ! is_array( $excluded ) ) {
          $excluded = array();
      }

      if ( ! empty( $excluded ) && ! empty( $methods ) ) {
          $methods = array_values(
              array_filter(
                  $methods,
                  function ( $method ) use ( $excluded ) {
                      return ! in_array( $method['method_id'], $excluded, true );
                  }
              )
          );
      }

      // Collect the per-instance settings option for every exported method
      // instance, so third-party method configuration travels with its row.
      // Option name pattern: woocommerce_{method_id}_{instance_id}_settings
      $option_names = array();
      foreach ( (array) $methods as $method ) {
          $option_names[] = 'woocommerce_' . $method['method_id'] . '_' . (int) $method['instance_id'] . '_settings';
      }
      $option_names = array_values( array_unique( $option_names ) );

      $options = array();
      if ( ! empty( $option_names ) ) {
          $placeholders = implode( ', ', array_fill( 0, count( $option_names ), '%s' ) );
          $options = $wpdb->get_results(
              $wpdb->prepare(
                  "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
                  $option_names
              ),
              ARRAY_A
          );
      }

      $data = array(
          'woocommerce_shipping_zones'          => $zones,
          'woocommerce_shipping_zone_methods'   => $methods,
          'woocommerce_shipping_zone_locations' => $locations,
          'options'                             => $options,
      );

      $json_data      = $this->format_json_data( $data );
      $json_file_name = $this->get_json_filename();
      $this->download_json( $json_data, $json_file_name );
  }
  ```

  Notes: (a) the four `$data` keys MUST stay exactly as shown — the importer's `switch` depends on
  them. (b) `$wpdb->prepare()` accepts the `$option_names` array as its second argument for multiple
  `%s` placeholders (valid in WP 7.0). (c) The unused private `$query`/`$wpdb` properties and the
  `get_data()`/`format_csv_data()` helpers may remain untouched — do not delete them in this task.

- [ ] T005 [P] [US1] **Add the import-time registration check + skipped-method collection** in `includes/Importers/WooCommerce/ShippingZonesImporter.php`.

  1. Add two private properties next to the existing `private $wpdb;` (around line 14):
     ```php
     private $wpdb;
     private $skipped_methods    = array();
     private $registered_methods = null;
     ```

  2. Replace the body of `import_shipping_zone_method()` (currently lines 68–85) so it checks the
     registry before inserting. The method becomes:
     ```php
     private function import_shipping_zone_method( $data ) {
         $zone_id      = (int) $data['zone_id'];
         $instance_id  = (int) $data['instance_id'];
         $method_id    = sanitize_text_field( $data['method_id'] );
         $method_order = (int) $data['method_order'];
         $is_enabled   = (int) $data['is_enabled'];

         // Skip (and record) any method not registered on this site, so we
         // never write an unrenderable orphan row. Continue with the rest.
         if ( ! $this->is_method_registered( $method_id ) ) {
             if ( ! in_array( $method_id, $this->skipped_methods, true ) ) {
                 $this->skipped_methods[] = $method_id;
             }
             return;
         }

         $this->wpdb->insert(
             "{$this->wpdb->prefix}woocommerce_shipping_zone_methods",
             array(
                 'zone_id'      => $zone_id,
                 'instance_id'  => $instance_id,
                 'method_id'    => $method_id,
                 'method_order' => $method_order,
                 'is_enabled'   => $is_enabled
             )
         );
     }
     ```

  3. Add these two methods to the class (e.g. just after `import_shipping_zone_method()`):
     ```php
     /**
      * Whether a shipping method id is registered with WooCommerce on this site.
      * Uses exact key matching against the live method registry (cached once per import).
      */
     private function is_method_registered( $method_id ) {
         if ( null === $this->registered_methods ) {
             $this->registered_methods = array();
             if ( function_exists( 'WC' ) && WC()->shipping() ) {
                 // get_shipping_methods() returns an array keyed by method id.
                 $this->registered_methods = WC()->shipping()->get_shipping_methods();
             }
         }

         return array_key_exists( $method_id, $this->registered_methods );
     }

     /**
      * Shipping method ids that were skipped on import because they are not
      * registered on this site. De-duplicated.
      *
      * @return string[]
      */
     public function get_skipped_methods(): array {
         return array_values( array_unique( $this->skipped_methods ) );
     }
     ```

  Notes: exact-key `array_key_exists()` matching satisfies Constitution Principle II.7 (no substring
  matching). Do **not** modify the `//TODO` on line 22 (Phase 2 scope, see ground rule 7).

- [ ] T006 [US1] **Set the warning transient after a successful import** in `includes/MigrateStore.php`, inside `handle_import_action()`.
  In the `try` block, immediately **after** the existing `set_transient('migratestore_import_success', [...], 60);` call (around lines 234–237) and **before** the closing `}` of the `try`, add:
  ```php
  // Surface any shipping methods that were skipped because they are not
  // registered on this site (Phase 3 — shipping method filter).
  if ( method_exists( $importer, 'get_skipped_methods' ) ) {
      $skipped_methods = $importer->get_skipped_methods();
      if ( ! empty( $skipped_methods ) ) {
          $skipped_methods = array_map( 'sanitize_text_field', $skipped_methods );
          set_transient( 'migratestore_import_warning', $skipped_methods, 60 );
      }
  }
  ```
  Notes: `method_exists()` keeps this safe for every other importer (only `ShippingZonesImporter`
  defines `get_skipped_methods()`). This is set in addition to the success transient — a partial
  import shows both notices. Depends on T005.

- [ ] T007 [P] [US1] **Render the warning notice** in `includes/admin/admin-import-page.php`.
  Inside the `<?php ... ?>` block that already handles the error and success transients (the block
  spanning roughly lines 6–53), add the following — place it **after** the error-transient block and
  **before** the success-transient block (so a partial import shows the warning above the success):
  ```php
  // Check if the transient is set for skipped shipping methods (Phase 3)
  if ( $skipped_methods = get_transient( 'migratestore_import_warning' ) ) {
      delete_transient( 'migratestore_import_warning' );

      if ( is_array( $skipped_methods ) && ! empty( $skipped_methods ) ) {
          echo '<div class="notice notice-warning is-dismissible">';
          echo '<p>'
              . esc_html__( 'The following shipping methods were skipped because they are not available on this site:', 'migratestore' )
              . ' ' . esc_html( implode( ', ', $skipped_methods ) )
              . '</p>';
          echo '</div>';
      }
  }
  ```
  Notes: warning is dismissible (`is-dismissible`) and warning-styled (`notice-warning`) per FR-008.
  Every id is escaped. Depends on the transient key agreed in T003/T006.

**Checkpoint (US1)**: Run `php -l` on the four edited files (PHP 7.4 and 8.3) — all must report
"No syntax errors". Then verify QA scenarios 3, 4, 5, 6 (see Phase 5) pass. US1 is independently
shippable at this point.

---

## Phase 4: User Story 3 — readme.txt & changelog complete for v1.2.0 (Priority: P2)

**Goal**: Make the release metadata and changelog accurate and self-consistent for v1.2.0.

**Independent Test**: `readme.txt` shows `Stable tag: 1.2.0`, `Tested up to: 7.0`, a complete
`= 1.2.0 =` changelog covering all Phase 1–3 fixes, and no placeholder/draft text.

> **Dependency note**: The plugin **header** version bump (`migratestore.php`) and the
> `Requires PHP` / `Tested up to` header lines are owned by Phase 1. As of this writing the header is
> still `1.1.9` and lacks those lines. To keep the v1.2.0 release self-consistent (FR-014: "stable
> tag matches plugin version"), T011 brings the version to `1.2.0` **idempotently** — if Phase 1
> already did it, leave it. Do not add the `Requires PHP` / `Tested up to` header lines here unless
> they are absent at release time (flag that as a Phase 1 gap in your report).

- [ ] T008 [US3] **Update release metadata** in `readme.txt` (header, lines 6–7):
  - Change `Tested up to: 6.9` → `Tested up to: 7.0`
  - Change `Stable tag: 1.1.9` → `Stable tag: 1.2.0`

- [ ] T009 [US3] **Add the 1.2.0 changelog entry** in `readme.txt`, immediately under `== Changelog ==` (line 65) and **above** `= 1.1.9 =` (line 67). Insert:
  ```
  = 1.2.0 =
  * Compatibility: Tested with WordPress 7.0.
  * Compatibility: PHP 8.2 / 8.3 compatible; minimum PHP 7.4.
  * Security: Added capability checks (manage_woocommerce) to all export and import handlers.
  * Security: Validated ZIP uploads (MIME type, size limit) and guaranteed temp-file cleanup on success and failure.
  * Fix: Unified option field names (option_name/option_value) across all exporters and importers.
  * Fix: Corrected the AbstractImporter constructor signature.
  * Fix: Removed a duplicate entry in the email settings exporter.
  * Fix: Shipping method export is no longer limited to the three built-in types; all registered shipping methods are now exported. Unrecognized methods are reported on import.
  ```
  Notes: these items summarize Phases 1–3 (per FR-012 / US-3 Scenario 1). Keep the existing older
  entries untouched.

- [ ] T010 [US3] **Scan `readme.txt` for placeholder/draft text** (e.g. `TODO`, `TBD`, `XXX`, `Lorem`, `[ ]`, `PLACEHOLDER`) and remove/resolve any found. The finalized readme must contain none (FR-013).

- [ ] T011 [P] [US3] **Ensure plugin version is 1.2.0 (idempotent)** in `migratestore.php`:
  - Line 7 header: `* Version: 1.1.9` → `* Version: 1.2.0` (skip if already `1.2.0`)
  - Line 25: `const MIGRATESTORE_VERSION = '1.1.9';` → `const MIGRATESTORE_VERSION = '1.2.0';` (skip if already `1.2.0`)
  This guarantees FR-014 (stable tag matches plugin version) even if Phase 1 has not been applied yet.
  Different file from T008–T010, so parallelizable.

**Checkpoint (US3)**: `readme.txt` stable tag/tested-up-to/changelog correct and placeholder-free;
plugin version consistent at `1.2.0`.

---

## Phase 5: User Story 2 — Release is fully QA-verified (Priority: P1) ✅ FINAL GATE

**Goal**: Prove, on WordPress 7.0 + latest WooCommerce, that every flow and guarantee works before
tagging v1.2.0. This phase is executed **last** because it validates US1 + US3 and the prior phases.

**Independent Test**: Execute all 18 scenarios from `quickstart.md`; record pass/fail; any failure
blocks the release (FR-010 / FR-011).

> **Dependency note**: Scenarios 11–14 and 18 verify Phase 1/2 guarantees (upload validation, temp
> cleanup, capability checks, header fields). If Phases 1–2 are not yet implemented on this branch,
> those scenarios will fail — that is expected; record them as "blocked on Phase 1/2" rather than as
> Phase 3 defects. Scenarios 1–10 and 15–17 must pass on the Phase 3 code itself.

- [ ] T012 [US2] **Lint gate — PHP 7.4**: run `php -l` on every plugin `.php` file under PHP 7.4 (QA #16). Expect zero errors. Record output.
- [ ] T013 [US2] **Lint gate — PHP 8.3**: run `php -l` on every plugin `.php` file under PHP 8.3 (QA #17). Expect zero errors/warnings. Record output.
- [ ] T014 [US2] **Run the shipping-focused QA scenarios** (the core of this phase) on WP 7.0 + latest WooCommerce and record results:
  - #3 Export zones with the three built-ins → all three in the JSON.
  - #4 Export zones with a third-party method → all methods in JSON incl. the third-party row **and** its `_settings` option; none dropped.
  - #5 Import zones (built-ins) on a clean target → zones created, success notice.
  - #6 Import zones referencing an unregistered method (target lacks the third-party plugin) → dismissible `notice-warning` lists the skipped id(s); remaining zones/methods imported.
  - #15 Import zones on a site that already has zones → no fatal error (existing-zone guard message).
- [ ] T015 [US2] **Run the remaining QA scenarios** and record results: #1, #2, #7, #8, #9, #10 (general/classes/email export+import), and #11–#14, #18 (Phase 1/2 guarantees — mark "blocked on Phase 1/2" if those phases are not yet implemented).
- [ ] T016 [US2] **Compile the QA result log** into `specs/003-shipping-method-filter/quickstart.md` (append a "Results — <date>" section) or a sibling note, marking each of the 18 scenarios pass/fail/blocked. The release is releasable only when Gate 1 (lint) passes on both PHP versions, all non-blocked scenarios pass, and Gate 3 (readme) is satisfied.

**Checkpoint (US2)**: All gates green (or remaining failures attributed solely to not-yet-implemented
Phase 1/2 work) → v1.2.0 is releasable.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T017 [P] Re-grep the whole plugin for `TODO`/`FIXME` introduced by Phase 3 work (must be none of your own; the pre-existing line-22 TODO is Phase 2's — report it, don't fix it here).
- [ ] T018 [P] Confirm no new user-facing string was added without `__()`/`esc_html__()` and the `migratestore` text domain (check T007's notice string).
- [ ] T019 Final `git status` review; commit the Phase 3 implementation to `migratestore-wp7-readiness` with a descriptive message. Do not create a branch; do not merge.

---

## Dependencies & Execution Order

### Phase order

- **Phase 1 (Setup)** → no dependencies.
- **Phase 2 (Foundational)** → after Phase 1. Defines the importer↔orchestrator contract (T003). No code.
- **Phase 3 (US1, P1)** → after Phase 2. The core code change. **This is the MVP.**
- **Phase 4 (US3, P2)** → after Phase 2; independent of US1 code (docs/metadata). Can run in parallel with Phase 3 if staffed.
- **Phase 5 (US2, P1)** → **last**: validates US1 + US3 + prior phases. Run after Phases 3 and 4.
- **Phase 6 (Polish)** → after Phases 3–5.

### Task-level dependencies

- T004 (exporter) and T005 (importer) are independent → **[P]**.
- T006 (orchestrator transient) depends on **T005** (`get_skipped_methods()` must exist).
- T007 (admin notice) depends only on the agreed transient key (T003/T006) and is a different file → **[P]** with T006.
- T008–T010 edit the same file (`readme.txt`) → sequential, not parallel with each other.
- T011 (`migratestore.php`) is a different file → **[P]** with T008–T010.
- T012/T013 (lint) before T014/T015 (manual QA). T016 after QA. 

### Parallel opportunities

- T004 ∥ T005 (then T006 after T005; T007 ∥ T006).
- Phase 4 (T011 ∥ T008→T009→T010).
- T012 ∥ T013 (two PHP versions, independent runs).
- T017 ∥ T018 in Phase 6.

---

## Parallel Example: User Story 1

```text
# Start together (different files, no shared state):
Task T004: Rewrite export() in includes/Exporters/WooCommerce/ShippingZonesExporter.php
Task T005: Add registration check + get_skipped_methods() in includes/Importers/WooCommerce/ShippingZonesImporter.php

# Then:
Task T006: Set migratestore_import_warning transient in includes/MigrateStore.php   (needs T005)
Task T007: Render notice-warning in includes/admin/admin-import-page.php             (parallel with T006)
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 1 (Setup) → Phase 2 (contract) → Phase 3 (T004–T007).
2. `php -l` the four files on 7.4 + 8.3.
3. Verify QA #3–#6 and #15 → **STOP and VALIDATE**. US1 (the data-loss fix) is now shippable.

### Incremental delivery

1. US1 (Phase 3) → test independently → the core fix is done.
2. US3 (Phase 4) → readme/changelog/version → release metadata ready.
3. US2 (Phase 5) → full 18-scenario QA + lint → release gate. Tag v1.2.0 when green.

---

## Notes

- [P] = different files, no incomplete dependency.
- [Story] labels map tasks to spec.md user stories (US1/US2/US3) for traceability.
- Commit after each logical group; keep the plugin loadable at every step.
- Do not create a branch; all commits go to `migratestore-wp7-readiness`.
- Exact code blocks above are authoritative — match them (file paths, key names, text domain).
- The pre-existing `//TODO` at `ShippingZonesImporter.php:22` is **out of Phase 3 scope** — leave it.
