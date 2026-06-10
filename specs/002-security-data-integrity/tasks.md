---
description: "Task list for Phase 2 — Security Hardening & Data Integrity"
---

# Tasks: Security Hardening & Data Integrity (Phase 2)

**Input**: Design documents from `specs/002-security-data-integrity/`
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`
**Branch**: `migratestore-wp7-readiness` (do NOT create a new branch — commit here)

**Tests**: No automated test suite exists in this repo. Verification is `php -l` (PHP 7.4 + 8.3)
plus the manual QA matrix in `quickstart.md`. No test-authoring tasks are included by design.

---

## IMPORTANT — read this entire section before starting (context for the implementing LLM)

You are **Kimi**, implementing a security/data-integrity hardening phase of a **WordPress +
WooCommerce plugin**. Work precisely; this code runs on 1,000+ live stores and ingests uploaded
archives that write store configuration.

### Golden rules

1. **Scope lock (FR-019).** Change ONLY what these tasks say. Do **NOT** touch: the shipping-method
   whitelist in `ShippingZonesExporter.php` line 31 (`method_id IN ('flat_rate', ...)` — that is
   **Phase 3**), plugin headers/version (Phase 1, already done), or anything in `lib/` (Composer
   vendor).
2. **Both, not either.** Every handler must enforce **capability AND nonce**. Never remove the
   existing `check_admin_referer()` calls.
3. **7.4-safe code only.** Target PHP **7.4 minimum**, must also lint clean on **8.3**. Allowed:
   `??` null-coalescing, typed properties, `wp_check_filetype_and_ext`, `ZipArchive`,
   `MB_IN_BYTES`, `size_format`, `apply_filters`. **Forbidden**: `str_contains`/`str_starts_with`/
   `str_ends_with` (8.0+), `${var}` interpolation, `match` (8.0+), enums, named args, `readonly`.
4. **Translate user-facing strings** with text domain `migratestore`, e.g.
   `esc_html__( '…', 'migratestore' )`. Unauthorized exits use `wp_die()`, never bare `exit`.
5. **Preserve behavior** for legitimate users. A `manage_woocommerce` admin must still complete every
   existing export/import flow unchanged.
6. After all edits, **leave the working tree ready to commit** to `migratestore-wp7-readiness` (or
   commit if explicitly instructed). Do not commit to or create any other branch.

### Verified repository facts (line numbers are current as of this plan; re-confirm before editing)

- **`includes/MigrateStore.php`** — the orchestrator. Registers handlers (lines 17–18) and contains:
  - `handle_export_action()` starts ~line 123; `check_admin_referer('migratestore_export_action_nonce')` at ~125.
  - `handle_import_action()` starts ~line 157; `WP_Filesystem()` at ~159; `check_admin_referer('migratestore_import_action_nonce')` at ~160; `$_FILES`/`UPLOAD_ERR_OK` check ~162; `wp_handle_upload()` ~167; `$unzip_folder` defined ~176; `mkdir` ~179; `unzip_file()` ~183; JSON `glob` ~188; `$filename` derived via `preg_replace('/_\d{8}_\d{6}$/', '', …)` ~197; `$importerStrategies` map ~199–208; **the buggy substring routing loop ~210–217**; "Invalid file name" bail ~219–221; `class_exists` bail ~223–225; `new $className()` ~227; `import()` + transients ~228–241; `$importer->cleanup($unzip_folder)` ~244.
  - **NEITHER handler calls `current_user_can()` today** — that is the US1 gap.
- **`includes/Exporters/AbstractExporter.php`** — `get_options_values()` writes `'option'`/`'value'`
  (lines 27–30). This is the single producer for option-style exporters.
- **`includes/Importers/AbstractImporter.php`** — `import()` checks `isset($item['option'], $item['value'])`
  (line 48); `import_option()` reads `$data['option']`/`$data['value']` (lines 56–57); allowed-name
  map reads `$item['option']` (lines 75–77); parent ctor `__construct(AbstractExporter $exporter)`
  (line 16). `recursiveRemoveDirectory()` exists (line 105) but is on the **importer**, not the
  handler.
- **`includes/Importers/WooCommerce/ShippingZonesImporter.php`** — `//TODO` at line 22;
  `import_option()` already reads `option_name`/`option_value` (lines 102–104); each importer has a
  zero-arg `__construct()` calling `parent::__construct( new XExporter() )`.
- **`includes/Exporters/WooCommerce/EmailsOptionsExporter.php`** — option name
  `woocommerce_customer_completed_order_settings` is listed **twice** (lines 27 and 28).
- **`ShippingZonesExporter.php`** already selects `option_name, option_value` (line 33) → its
  options path is already on the canonical convention; **do not change it**.

### Field-naming decision (canonical = `option_name` / `option_value`)

Exporters WRITE `option_name`/`option_value`. Importers READ `option_name`/`option_value` and fall
back to legacy `option`/`value` **only when the new keys are absent** (for v1.1.9 archives). This
fallback must be documented in a code comment in both base classes (Constitution IV).

---

## Phase 1: Setup (no edits yet)

**Purpose**: Confirm branch and verification tooling.

- [ ] T001 Confirm you are on git branch `migratestore-wp7-readiness` by running `git branch --show-current` from repo root `D:\WordPress\migratestore`. If not, run `git checkout migratestore-wp7-readiness`. Do NOT create a new branch.
- [ ] T002 [P] Confirm a PHP 7.4 binary and a PHP 8.3 binary are reachable for the lint gate (`php --version`); if absent, use Docker images `php:7.4-cli` and `php:8.3-cli` per `specs/002-security-data-integrity/quickstart.md` Gate A. Record which method you will use. (The lint gate in Phase 8 cannot be skipped.)

**Checkpoint**: Branch correct, lint tooling identified.

---

## Phase 2: Foundational (Blocking prerequisite for US1, US2, US4)

**Purpose**: Add the centralized import-artifact cleanup helper that the security tasks depend on,
and pin the exact current line anchors in `MigrateStore.php`. US1/US2/US4 all edit
`MigrateStore.php`; do this phase first so those edits have a stable foundation.

**⚠️ This phase blocks Phase 3 (US1), Phase 4 (US2), and Phase 6 (US4).**

- [ ] T003 Re-read `includes/MigrateStore.php` in full and confirm the line anchors listed in the "Verified repository facts" section above still match (the file is otherwise unchanged since this plan). If any anchor drifted, note the new line numbers — the later tasks reference behavior, not just line numbers, so locate by the quoted code, not the number.
- [ ] T004 Add a private cleanup helper method to the `MigrateStore` class in `includes/MigrateStore.php` (place it directly AFTER the `handle_import_action()` method, before `get_import_type_data()`). This removes BOTH the moved upload file and the temp extraction directory, and is safe to call when either argument is null/missing. Insert exactly:
  ```php
  /**
   * Remove import artifacts (the moved upload file + the temp extraction dir).
   * Called on EVERY exit path of handle_import_action() — success, exception,
   * and each early wp_die() bail — so no orphaned files remain (FR-007).
   *
   * @param string|null $uploaded_file_path Absolute path to the moved upload, or null.
   * @param string|null $temp_dir           Absolute path to the temp extract dir, or null.
   */
  private function cleanup_import_artifacts( $uploaded_file_path, $temp_dir ) {
      if ( ! empty( $uploaded_file_path ) && file_exists( $uploaded_file_path ) ) {
          @unlink( $uploaded_file_path );
      }
      if ( ! empty( $temp_dir ) && is_dir( $temp_dir ) ) {
          $items = glob( rtrim( $temp_dir, '/\\' ) . '/*', GLOB_MARK );
          if ( is_array( $items ) ) {
              foreach ( $items as $item ) {
                  if ( is_dir( $item ) ) {
                      $this->cleanup_import_artifacts( null, $item ); // recurse into nested dirs
                  } else {
                      @unlink( $item );
                  }
              }
          }
          @rmdir( $temp_dir );
      }
  }
  ```
  Do not call it yet — Phase 4 wires it into the handler.

**Checkpoint**: Cleanup helper exists; anchors confirmed.

---

## Phase 3: User Story 1 — Only authorized users can export or import (Priority: P1) 🎯 MVP

**Goal**: Both handlers reject non-`manage_woocommerce` users via translated `wp_die()` BEFORE any
data is read/written, keeping the existing nonce checks.

**Independent Test**: As a Subscriber (valid nonce, no capability), an export and an import request
are both refused with no data read/returned/written; an admin proceeds normally; a bad/absent nonce
is still rejected. (Contract: `contracts/handler-security.contract.md` C-1.)

### Implementation for User Story 1

- [ ] T005 [US1] In `includes/MigrateStore.php`, add a capability check as the FIRST statement inside `handle_export_action()` (before the existing `check_admin_referer('migratestore_export_action_nonce');`). Insert:
  ```php
  if ( ! current_user_can( 'manage_woocommerce' ) ) {
      wp_die( esc_html__( 'You do not have permission to export store settings.', 'migratestore' ), 403 );
  }
  ```
  Keep the `check_admin_referer(...)` line exactly as-is, immediately after.
- [ ] T006 [US1] In `includes/MigrateStore.php`, add a capability check as the FIRST statement inside `handle_import_action()` (before `WP_Filesystem();` and before `check_admin_referer('migratestore_import_action_nonce');`). Insert:
  ```php
  if ( ! current_user_can( 'manage_woocommerce' ) ) {
      wp_die( esc_html__( 'You do not have permission to import store settings.', 'migratestore' ), 403 );
  }
  ```
  Keep `WP_Filesystem();` and `check_admin_referer(...)` exactly as-is, in their current order, after this check.

**Checkpoint**: US1 complete — both handlers enforce capability + nonce. This is the highest-value
security MVP and is independently shippable.

---

## Phase 4: User Story 2 — Uploaded archive files are validated and safely handled (Priority: P1)

**Goal**: `handle_import_action()` validates MIME type and size, rejects path-traversal archives
before extracting anything, and removes the upload + temp dir on EVERY exit path.

**Independent Test**: A non-zip renamed `.zip` (MIME reject), an oversized zip (size reject), and a
zip with a `..`/absolute entry (traversal reject) are each refused with a clear notice and nothing
extracted; after every import attempt (pass or fail) zero artifacts remain on disk. (Contract C-2..C-6.)

**Depends on**: Phase 2 (T004 cleanup helper). Edits the SAME function as US1 (T006) — apply after
US1.

### Implementation for User Story 2

> All edits below are inside `handle_import_action()` in `includes/MigrateStore.php`. Apply them in
> this order; they build the final control flow:
> capability → WP_Filesystem → nonce → upload-present → **size** → wp_handle_upload → upload-error →
> compute name → **MIME** → define temp dir → **traversal scan** → mkdir → unzip(+cleanup on fail) →
> json-glob(+cleanup) → derive filename → route(+cleanup) → import → **cleanup on success/exception**.

- [ ] T007 [US2] Add the file-SIZE check immediately AFTER the existing `$_FILES`/`UPLOAD_ERR_OK` block (the `if (! isset($_FILES['json_zip_file']) || ... !== UPLOAD_ERR_OK) { wp_die('File upload failed'); }`) and BEFORE `wp_handle_upload(...)`. At this point nothing has been moved to disk, so no cleanup is needed. Insert:
  ```php
  $max_size = (int) apply_filters( 'migratestore_max_upload_size', 10 * MB_IN_BYTES );
  if ( (int) $_FILES['json_zip_file']['size'] > $max_size ) {
      wp_die( sprintf(
          /* translators: %s: maximum allowed upload size, e.g. "10 MB". */
          esc_html__( 'The uploaded file exceeds the maximum allowed size of %s.', 'migratestore' ),
          esc_html( size_format( $max_size ) )
      ) );
  }
  ```
- [ ] T008 [US2] Ensure `$uploaded_file_name` is available before the MIME check. The code already computes `$uploaded_file_name = sanitize_file_name($_FILES['json_zip_file']['name']);` (~line 173, after `wp_handle_upload`). Keep that line where it is — the MIME check in T009 is inserted AFTER it. (No edit if it already precedes T009's insertion point; otherwise move the `$uploaded_file_name` assignment up so it sits immediately after the `wp_handle_upload` error check.)
- [ ] T009 [US2] Add the MIME-TYPE check immediately AFTER `$uploaded_file_name` is set (i.e. after the `wp_handle_upload` success/error handling and the `$uploaded_file_name`/`$uploaded_file_basename` lines), and BEFORE `$unzip_folder` is defined. The upload has been moved, so reject WITH cleanup. Insert:
  ```php
  $filetype      = wp_check_filetype_and_ext( $uploaded_file['file'], $uploaded_file_name );
  $allowed_mimes = array( 'application/zip', 'application/x-zip-compressed' );
  if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) {
      $this->cleanup_import_artifacts( $uploaded_file['file'], null );
      wp_die( esc_html__( 'Invalid file type. Please upload a .zip file exported by Migrate Store.', 'migratestore' ) );
  }
  ```
- [ ] T010 [US2] Add the ZIP PATH-TRAVERSAL scan AFTER `$unzip_folder` is defined (~line 176) and BEFORE the `unzip_file(...)` call (~line 183). It must reject the WHOLE import if any entry is absolute or contains a `..` segment, extracting nothing. (You may place it before or after the `mkdir` block; passing `$unzip_folder` to cleanup is safe whether or not the dir exists.) Insert:
  ```php
  $zip_check = new \ZipArchive();
  if ( $zip_check->open( $uploaded_file['file'] ) !== true ) {
      $this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
      wp_die( esc_html__( 'The uploaded file could not be read as a valid ZIP archive.', 'migratestore' ) );
  }
  for ( $i = 0; $i < $zip_check->numFiles; $i++ ) {
      $entry_name = $zip_check->getNameIndex( $i );
      if ( $entry_name === false ) {
          continue;
      }
      $normalized  = str_replace( '\\', '/', (string) $entry_name );
      $segments    = explode( '/', $normalized );
      $is_absolute = ( substr( $normalized, 0, 1 ) === '/' ) || (bool) preg_match( '#^[A-Za-z]:/#', $normalized );
      $has_dotdot  = in_array( '..', $segments, true );
      if ( $is_absolute || $has_dotdot ) {
          $zip_check->close();
          $this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
          wp_die( esc_html__( 'The uploaded archive contains unsafe file paths and was rejected.', 'migratestore' ) );
      }
  }
  $zip_check->close();
  ```
- [ ] T011 [US2] Wire cleanup into the remaining EARLY bails in `handle_import_action()` that occur AFTER the upload was moved but BEFORE the importer is constructed. For EACH of these existing `wp_die(...)` calls, add `$this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );` on the line immediately before the `wp_die(...)`:
  - the `mkdir` failure bail (`wp_die('Failed to create tmp directory: insufficient permission');`)
  - the unzip failure bail (`wp_die('Failed to unzip file: ' . $unzipped->get_error_message());`)
  - the no-JSON-found bail (`wp_die('No matching JSON file found in uploaded ZIP.');`)
  - the `class_exists` failure bail (`wp_die("Importer class '$className' not found.");`)
  (The "Invalid file name" / unrecognized-file bail is handled in T015 under US4. Do NOT add cleanup to the two bails that run BEFORE the upload is moved — the `UPLOAD_ERR_OK` bail and the `wp_handle_upload` error bail — there are no artifacts there yet.)
- [ ] T012 [US2] Guarantee cleanup of the moved UPLOAD on the SUCCESS and CAUGHT-EXCEPTION paths. The code currently ends with `$importer->cleanup($unzip_folder);` (~line 244), which removes the temp dir then redirects+exits but NEVER deletes the moved upload file. Immediately BEFORE that `$importer->cleanup($unzip_folder);` line, insert:
  ```php
  // Remove the moved upload; $importer->cleanup() removes the temp dir then redirects.
  if ( ! empty( $uploaded_file['file'] ) && file_exists( $uploaded_file['file'] ) ) {
      @unlink( $uploaded_file['file'] );
  }
  ```
  Both the success branch and the `catch` branch fall through to this line, so this covers both. Do not otherwise modify `$importer->cleanup()`.

**Checkpoint**: US2 complete — uploads are MIME/size/traversal-validated and zero artifacts leak on
any path. Verify with quickstart Gate B2 (#13,14,16) and B3.

---

## Phase 5: User Story 3 — Imported settings are actually applied (consistent field naming) (Priority: P2)

**Goal**: Exporters write `option_name`/`option_value`; importers read them with a documented legacy
fallback; the email-settings duplicate is removed.

**Independent Test**: Round-trip a v1.2.0 export→import (every value applied); import a v1.1.9
export (every value applied via fallback); the email export contains no duplicate entry.
(Contract: `contracts/option-payload.contract.md`.)

**Depends on**: nothing in US1/US2 (different files), so this phase is `[P]` with Phases 3–4.

### Implementation for User Story 3

- [ ] T013 [P] [US3] In `includes/Exporters/AbstractExporter.php`, change `get_options_values()` so each entry uses the canonical keys. Replace the array literal (currently `'option' => $option_name, 'value' => $option_value,`, ~lines 28–29) with:
  ```php
  $settings[] = [
      // Canonical option-entry field names (v1.2.0+). Importers read these and fall
      // back to the legacy 'option'/'value' keys only for v1.1.9 and earlier archives.
      'option_name'  => $option_name,
      'option_value' => $option_value,
  ];
  ```
  Change nothing else in the method.
- [ ] T014 [US3] In `includes/Importers/AbstractImporter.php`, update the base importer to read canonical keys with a legacy fallback, in THREE places:
  1. In `import()`, replace the guard `if (isset($item['option'], $item['value'])) {` with one that accepts either pair:
     ```php
     // Accept canonical (option_name/option_value) or legacy (option/value) entries.
     if ( ( isset( $item['option_name'], $item['option_value'] ) ) || ( isset( $item['option'], $item['value'] ) ) ) {
         $this->import_option( $item );
     }
     ```
  2. In `import_option()`, replace the two assignments (`$option_name = sanitize_key($data['option']);` and `$option_value = $data['value'];`) with:
     ```php
     // Canonical keys (v1.2.0+) with legacy 'option'/'value' fallback for v1.1.9 archives.
     $option_name  = sanitize_key( $data['option_name'] ?? $data['option'] );
     $option_value = $data['option_value'] ?? $data['value'];
     ```
  3. In `import_option()`, the allowed-name map reads `$item['option']` (the `array_map` over `$allowed_option_data`, ~lines 75–77). Change the closure body `return $item['option'];` to:
     ```php
     return $item['option_name'] ?? $item['option'];
     ```
     (The exporter now returns `option_name`; the `?? $item['option']` keeps it null-safe.) Leave the rest of `import_option()` (serialization, sanitization, allow-list check, `update_option`) unchanged.
- [ ] T015 [P] [US3] In `includes/Exporters/WooCommerce/EmailsOptionsExporter.php`, remove the DUPLICATE option name. The array lists `'woocommerce_customer_completed_order_settings'` twice (lines 27 and 28). Delete one of the two identical lines so it appears exactly once. Change nothing else.
- [ ] T016 [P] [US3] In `includes/Importers/WooCommerce/ShippingZonesImporter.php`, add the same legacy fallback to its overriding `import_option()` (lines 102–104) so old zone exports still import. Replace:
  ```php
  $option_name  = sanitize_key( $data['option_name'] );
  $option_value = sanitize_text_field( $data['option_value'] );
  ```
  with:
  ```php
  // Canonical keys with legacy 'option'/'value' fallback for v1.1.9 archives.
  $option_name  = sanitize_key( $data['option_name'] ?? $data['option'] );
  $option_value = sanitize_text_field( $data['option_value'] ?? $data['value'] );
  ```
- [ ] T017 [P] [US3] AUDIT (read-only): confirm `includes/Exporters/WooCommerce/ShippingZonesExporter.php` line ~33 already selects `option_name, option_value` for its `options` query (it does) — so it is already canonical and needs NO change. Confirm the other 7 exporters all rely on the inherited `get_options_values()` (they do not override it) so they pick up T013 automatically. Record "verified — exporters consistent". Do NOT edit the shipping-method whitelist on line 31 (Phase 3).

**Checkpoint**: US3 complete — option entries are canonical end-to-end with v1.1.9 fallback; email
duplicate gone. Verify with quickstart Gate B1 (#1,7,9).

---

## Phase 6: User Story 4 — The correct importer is always selected (Priority: P2)

**Goal**: Importer routing uses exact-key matching against `$importerStrategies`, failing with an
actionable notice on no match.

**Independent Test**: Each recognized file routes to exactly one correct importer; a partially
overlapping name is not misrouted; an unrecognized file fails with a clear notice (no silent no-op);
v1.1.9 filenames still match. (Contract C: research.md Decision 3.)

**Depends on**: Phase 2 (T004 cleanup helper). Edits the SAME function as US1/US2 — apply after them.

### Implementation for User Story 4

- [ ] T018 [US4] In `includes/MigrateStore.php` `handle_import_action()`, replace the buggy substring routing block. Delete the entire loop + bail (currently):
  ```php
  $valid_file = false;
  foreach ($importerStrategies as $key => $value) {
      if (strpos($key, $filename) !== false) {
          $valid_file = true;
          $className  = $value;
          break;
      }
  }

  if (! $valid_file) {
      wp_die('Invalid file name.');
  }
  ```
  and replace it with exact-key matching that cleans up on no-match:
  ```php
  if ( ! array_key_exists( $filename, $importerStrategies ) ) {
      $this->cleanup_import_artifacts( $uploaded_file['file'], $unzip_folder );
      wp_die( esc_html__( 'Unrecognized import file. This file was not produced by Migrate Store.', 'migratestore' ) );
  }
  $className = $importerStrategies[ $filename ];
  ```
  Do NOT change the `$importerStrategies` map keys (lines 199–208) or the `$filename` derivation (the `preg_replace` that strips the `_YYYYMMDD_HHMMSS` suffix) — those keep v1.1.9 files matching (FR-015). The `class_exists($className)` check that follows already has cleanup added in T011.

**Checkpoint**: US4 complete — routing is exact, with an actionable failure path. Verify with
quickstart Gate B2 (#17,18) and B1 (#9 for v1.1.9).

---

## Phase 7: User Stories 5 & 6 — Constructor consistency + debug-artifact removal (Priority: P3)

**Goal (US5)**: Importer construction is verified consistent and documented. **Goal (US6)**: the
`ShippingZonesImporter` TODO is resolved and zero TODO/FIXME remain.

**Independent Test (US5)**: Instantiating every importer produces no constructor warning/error on
7.4 or 8.3. **Independent Test (US6)**: a repo-wide search for TODO/FIXME returns zero.

**Depends on**: US6's edit is in the same file as T016 (US3) — apply T016 first.

### Implementation for User Story 5

- [ ] T019 [P] [US5] AUDIT + document (no signature change). Confirm all 8 importers under `includes/Importers/WooCommerce/` declare a zero-arg `public function __construct()` that calls `parent::__construct( new XExporter() )`, matching the parent `AbstractImporter::__construct( AbstractExporter $exporter )`. (Per research.md Decision 6 they already do — this is verify-only.) Then add a documenting comment directly above `AbstractImporter::__construct()` in `includes/Importers/AbstractImporter.php`:
  ```php
  /**
   * Children pass their paired exporter: each concrete importer declares a
   * zero-arg constructor and calls parent::__construct( new XExporter() ).
   * Instantiated via `new $className()` in MigrateStore::handle_import_action().
   */
  ```
  If — and only if — you find an actual signature mismatch, fix the child to match this contract and note the file. Otherwise record "verified — constructors consistent, no change".

### Implementation for User Story 6

- [ ] T020 [US6] In `includes/Importers/WooCommerce/ShippingZonesImporter.php`, replace the TODO comment on line 22 (`//TODO: Add a Learn more link that explains why users should delete existing zones.`) with an explanatory comment documenting the intentional design decision (per FR-017; a "Learn more" UI link is Phase-3-style new surface and is out of scope). Use:
  ```php
  // Intentional: this importer refuses to merge into existing zones to avoid zone_id
  // collisions and duplicate methods/locations. validate() below throws when zones
  // already exist, and the thrown message instructs the user to clear them first.
  ```
- [ ] T021 [US6] Verify zero leftover debug markers: run `grep -rnE 'TODO|FIXME' --include='*.php' includes/ migratestore.php` (or the PowerShell equivalent `Select-String`). Expected result: **zero matches**. If any remain (outside `lib/`), resolve them the same way (implement or replace with an explanatory comment) and re-run. (FR-018.)

**Checkpoint**: US5 verified/documented; US6 TODO resolved and repo clean.

---

## Phase 8: Polish & Verification Gate (REQUIRED before done — Constitution Principle V)

**Purpose**: Run the lint + manual QA merge gate across all stories. See `quickstart.md`.

- [ ] T022 Run the lint gate on **PHP 7.4** over all plugin `.php` files (repo root `migratestore.php` + `includes/`, excluding `.specify/`, `.claude/`). Every file MUST report "No syntax errors detected". Record the result. (quickstart Gate A.)
- [ ] T023 Run the lint gate on **PHP 8.3** (same scope). Every file MUST pass with zero errors/deprecation warnings. Record the result. (quickstart Gate A.)
- [ ] T024 [P] Manual QA — legitimate flows (quickstart Gate B1, requires a WP 7.0 + WooCommerce site as a `manage_woocommerce` admin): export+import general settings, shipping zones, shipping classes, email settings (each succeeds with its notice); confirm the email export has NO duplicate `…completed_order_settings` entry; import a captured **v1.1.9** export and confirm values apply via the legacy fallback. If no WP site is available, STOP and report this gate as not-yet-run rather than marking it passed.
- [ ] T025 [P] Manual QA — security rejections (quickstart Gate B2): Subscriber export AND import blocked (403, nothing written); bad/absent nonce blocked; `.txt`-renamed-`.zip` rejected (MIME); >10 MB zip rejected (size); raising `migratestore_max_upload_size` lets the large file through; zip with `../../etc/passwd` (and `..\..\` / absolute variants) rejected with nothing extracted; unrecognized inner filename → actionable notice; partially-overlapping name not misrouted.
- [ ] T026 [P] Manual QA — cleanup invariant (quickstart Gate B3) + stability (B4): after EVERY attempt above (success and each rejection), confirm `wp-content/uploads/migratestore_tmp` is gone/empty AND no orphaned uploaded `.zip` remains in `wp-content/uploads/`; confirm importing zones against a site that already has zones gives the clear "delete existing zones" message with no fatal; confirm every importer instantiates with no constructor warning on 7.4/8.3.
- [ ] T027 Run the code-hygiene gate (quickstart Gate C): `grep -rnE 'TODO|FIXME' --include='*.php' includes/ migratestore.php` returns zero matches. Confirm the Definition of Done checklist in `quickstart.md` is fully satisfied.

**Checkpoint**: All gates green → Phase 2 done.

---

## Dependencies & Execution Order

### Phase dependencies

- **Phase 1 (Setup)**: no dependencies — start immediately.
- **Phase 2 (Foundational)**: after Setup. **Blocks Phases 3, 4, 6** (all edit `MigrateStore.php`).
- **Phase 3 (US1)**, **Phase 4 (US2)**, **Phase 6 (US4)**: all edit `handle_import_action()` in the
  SAME file → run **sequentially in this order** (US1 capability first, then US2 validation/cleanup,
  then US4 routing). US1 also edits `handle_export_action()`.
- **Phase 5 (US3)**: edits exporters/importers (different files from `MigrateStore.php`) → can run in
  **parallel** with Phases 3/4/6.
- **Phase 7 (US5/US6)**: US5 audit is independent `[P]`; US6 (T020) shares
  `ShippingZonesImporter.php` with US3's T016 → do T016 before T020.
- **Phase 8 (Gate)**: after all desired stories are complete.

### User story dependencies

- **US1 (P1)** — independent; the security MVP on its own.
- **US2 (P1)** — needs T004 (cleanup helper); shares the import handler with US1 → after US1.
- **US3 (P2)** — fully independent of US1/US2/US4 (different files).
- **US4 (P2)** — needs T004; shares the import handler → after US1/US2.
- **US5 (P3)** — independent (verify + comment).
- **US6 (P3)** — shares one file with US3 (T016) → after T016.

### Same-file serialization (critical for Kimi — avoid merge conflicts)

- `includes/MigrateStore.php`: **T004 → T005 → T006 → T007 → T008 → T009 → T010 → T011 → T012 → T018**
  (one file, one continuous edit sequence; never parallelize these).
- `includes/Importers/AbstractImporter.php`: **T014 → T019** (T019 adds a comment).
- `includes/Importers/WooCommerce/ShippingZonesImporter.php`: **T016 → T020**.

---

## Parallel execution examples

```text
# After Foundational (T004), two independent tracks can proceed at once:
Track A (MigrateStore.php, sequential):  T005 → T006 → T007 → T008 → T009 → T010 → T011 → T012 → T018
Track B (data integrity, parallel-friendly): T013 [P], T015 [P], T016 [P], T017 [P]  (then T014 on AbstractImporter, then T019 comment)

# US3 file-parallel set (different files, safe to do together):
[P] T013 AbstractExporter.php
[P] T015 EmailsOptionsExporter.php
[P] T016 ShippingZonesImporter.php
[P] T017 ShippingZonesExporter.php (read-only audit)
```

---

## Implementation strategy

### MVP (User Story 1 only)

1. Phase 1 Setup → 2. Phase 2 T004 (cleanup helper) is optional for US1 but cheap to add → 3. Phase 3
(US1 capability checks) → 4. Lint (T022/T023) → ship. This alone closes the highest-severity gap
(unauthorized export/import).

### Recommended full sequence

Setup → Foundational (T004) → **US1** → **US2** → **US4** (the three import-handler stories, in
order) → **US3** (in parallel any time, different files) → **US5/US6** → **Phase 8 gate**. Because
US5's constructor "mismatch" does not actually exist in the current code, US5 is verify-and-document
only.

---

## Notes for the implementing model (Kimi)

- **Edit only these files**: `includes/MigrateStore.php`, `includes/Exporters/AbstractExporter.php`,
  `includes/Importers/AbstractImporter.php`,
  `includes/Exporters/WooCommerce/EmailsOptionsExporter.php`,
  `includes/Importers/WooCommerce/ShippingZonesImporter.php`. Everything else is read-only audit.
- **Never** touch `lib/`, plugin headers/version (Phase 1), or the shipping-method whitelist in
  `ShippingZonesExporter.php` line 31 (Phase 3).
- **Locate edits by the quoted code, not the line number** — line numbers shift as you insert code.
  After each insertion, the anchors for later tasks move down; that is expected.
- Use only PHP 7.4-safe syntax (see Golden Rule 3). If unsure whether a function exists on 7.4,
  prefer a WordPress core function or a `function_exists()` guard.
- All new user-facing strings use `esc_html__( '…', 'migratestore' )` (or `sprintf` + `esc_html__`).
- After all edits, leave the working tree ready for commit to `migratestore-wp7-readiness`; do not
  create or switch branches.
- If any "verify — expected consistent/zero" assumption turns out false (e.g. T019 finds a real
  constructor mismatch, or T021 finds a stray TODO), fix the real occurrence under the matching
  story and note the `file:line` in that task before proceeding.
```
