# Phase 0 Research: Security Hardening & Data Integrity

**Feature**: 002-security-data-integrity | **Date**: 2026-06-10

This phase had no open `NEEDS CLARIFICATION` markers — the spec resolved all ambiguities with
documented defaults. Phase 0 therefore consists of a static audit of the current code (no PHP binary
on PATH in the authoring environment) to confirm each defect exists, pin its exact location, and
decide the fix. Findings below are grounded in the source as of commit on
`migratestore-wp7-readiness`.

---

## Decision 1 — Authorization: add capability check beside the existing nonce check

**Finding**: `includes/MigrateStore.php` registers `admin_post_migratestore_export_action` →
`handle_export_action()` (line 123) and `admin_post_migratestore_import_action` →
`handle_import_action()` (line 157). Both call `check_admin_referer()` (lines 125, 160) for nonce
verification but **neither calls `current_user_can()`**. Any authenticated user who can obtain the
nonce (e.g., via the admin page if it is reachable, or CSRF-style replay) could export or import.

**Decision**: At the very top of each handler — before any file read, upload handling, or data
return — add:
```php
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You are not allowed to do this.', 'migratestore' ), 403 );
}
```
Keep `check_admin_referer()`. Order: capability first or nonce first is acceptable per the
constitution ("before any export/import action"); the spec requires **both**. Place capability check
first so an unauthorized user is rejected before nonce processing.

**Rationale**: Constitution Principle II.1 + II.2 require both, explicitly "not instead of" each
other. `manage_woocommerce` is the WooCommerce store-management capability and is guaranteed present
(WooCommerce is a required dependency). `wp_die()` with a translated message satisfies the
"never a bare `exit`" rule.

**Alternatives considered**: `manage_options` (rejected — broader than store management, and the
domain is WooCommerce settings); checking inside each exporter/importer (rejected — defense belongs
at the single entry point, and would duplicate the gate across 16 classes).

---

## Decision 2 — Upload validation: MIME, size cap, and pre-extraction traversal scan

**Finding** (`handle_import_action()`, lines 157–245):
- Upload uses `wp_handle_upload($_FILES['json_zip_file'], ['test_form' => false])` (line 167). No
  explicit MIME check for zip; relies on WP's default extension/type handling only.
- **No file-size cap** anywhere.
- Extraction uses WP core `unzip_file()` into `wp_upload_dir()['basedir'] . '/migratestore_tmp'`
  (line 183). `unzip_file()` has some internal safety but the constitution (II.5) requires explicit
  rejection of any entry containing `..` or an absolute path **before** extraction.
- **Cleanup leaks**: cleanup happens only via `$importer->cleanup($unzip_folder)` at line 244 (which
  removes the tmp dir, then `wp_safe_redirect` + `exit`). The **uploaded file** (`$uploaded_file['file']`,
  the moved upload, not in the tmp dir) is **never deleted**. Worse, every early `wp_die()` (lines
  163, 170, 180, 185, 190, 220, 224) returns **without any cleanup**, orphaning the uploaded file
  and possibly a partially populated tmp dir.

**Decision**:
1. **MIME validation** — after `wp_handle_upload`, call
   `wp_check_filetype_and_ext( $uploaded_file['file'], $uploaded_file_name )` and require the type to
   be one of `application/zip`, `application/x-zip-compressed`; otherwise reject.
2. **Size cap** — read `$_FILES['json_zip_file']['size']`; reject if it exceeds
   `apply_filters( 'migratestore_max_upload_size', 10 * MB_IN_BYTES )`. Default 10 MB, filterable.
3. **Traversal scan** — open the archive with `ZipArchive` and iterate entries
   (`statIndex`/`getNameIndex`); if any entry name contains `..` segments or is an absolute path
   (leading `/` or a Windows drive/UNC prefix), reject the **entire** import and extract nothing.
   Run this scan **before** `unzip_file()`.
4. **Guaranteed cleanup** — introduce a single private helper (e.g.
   `cleanup_import_artifacts( $uploaded_file_path, $unzip_folder )`) that deletes the moved upload
   **and** recursively removes the tmp dir, and call it on **every** exit path: replace bare
   `wp_die()` bails with "cleanup then `wp_die()`", and run it in both the success and the `catch`
   branches. Decouple cleanup from `AbstractImporter::cleanup()`'s redirect/exit so failure paths can
   also clean up without a forced redirect.

**Rationale**: Directly implements Constitution II.3–II.6 and spec FR-004..FR-008. Capping before
the traversal scan and extraction bounds the cost of the added inspection. Centralizing cleanup is
the only way to satisfy "removed on success AND failure" given the many early-exit `wp_die()` paths.

**Alternatives considered**: Relying on `unzip_file()`'s implicit safety (rejected — not an explicit,
auditable `..`/absolute rejection; constitution demands explicit). Validating type by extension only
(rejected — spoofable; constitution II.3 requires MIME). Using `try/finally` for cleanup (acceptable
and preferred where control flow allows, but `wp_die()` terminates the request, so each bail must
clean up immediately before calling it).

---

## Decision 3 — Importer routing: exact key match (fix reversed substring bug)

**Finding** (lines 199–217): after deriving `$filename` from the extracted JSON name (stripping the
`_YYYYMMDD_HHMMSS` suffix, line 197), routing loops the `$importerStrategies` map and tests
`if ( strpos( $key, $filename ) !== false )` (line 212). This is **doubly wrong**: (a) it is
substring matching, not exact; (b) the arguments are **reversed** — it asks whether `$filename` is a
substring of the strategy `$key`, so a truncated/crafted filename can match the wrong importer (or
multiple, taking the first). The `$importerStrategies` keys are the canonical export file keys
(e.g. `migratestore_general_settings`, `migratestore_zones`).

**Decision**: Replace the loop with an exact lookup:
```php
if ( ! array_key_exists( $filename, $importerStrategies ) ) {
    // cleanup, then:
    wp_die( esc_html__( 'Unrecognized import file.', 'migratestore' ) );
}
$className = $importerStrategies[ $filename ];
```
The `preg_replace` that strips the timestamp suffix already normalizes legacy filenames, so v1.1.9
exports (same key prefixes) continue to match exactly (FR-015).

**Rationale**: Constitution II.7 mandates exact key matching, never substring. Exact lookup also
gives the actionable-failure path FR-014 requires.

**Alternatives considered**: `in_array`/`===` over keys (equivalent; `array_key_exists` is the
direct expression). Keeping substring with corrected argument order (rejected — still substring,
still violates II.7).

---

## Decision 4 — Option field naming: standardize on `option_name`/`option_value` with legacy fallback

**Finding**: The codebase is **internally split**:
- `AbstractExporter::get_options_values()` writes `['option' => …, 'value' => …]`
  (`AbstractExporter.php` lines 28–29) — used by all option-style exporters.
- `AbstractImporter::import()` reads `$item['option']`/`$item['value']` (line 48) and
  `import_option()` reads `$data['option']`/`$data['value']` (lines 56–57), validating against
  allowed names mapped from `$item['option']` (lines 75–77).
- **But** `ShippingZonesImporter::import_option()` (override, lines 102–104) reads
  `$data['option_name']`/`$data['option_value']` — the explicit pair. So the shipping-zones path
  already expects the new convention while the base path expects the legacy one. This is the silent
  inconsistency the spec describes.

**Decision**: Adopt `option_name`/`option_value` as the single canonical convention (spec FR-009,
constitution IV).
- **Exporters**: change `AbstractExporter::get_options_values()` to emit
  `['option_name' => …, 'option_value' => …]`. All 8 option-style exporters inherit this; no
  per-exporter edits needed.
- **Importers**: change `AbstractImporter::import()` and `import_option()` (and the allowed-name
  validation) to read `option_name`/`option_value`, with a **documented backward-compat fallback**:
  ```php
  $name  = $item['option_name']  ?? $item['option']  ?? null;   // legacy fallback
  $value = $item['option_value'] ?? $item['value']   ?? null;
  ```
  Update the `isset()` guard in `import()` accordingly (accept either pair). Add a code comment in
  both `AbstractExporter.php` and `AbstractImporter.php` naming the convention and the fallback,
  citing v1.1.9 (constitution IV requires the deviation be documented).
- `ShippingZonesImporter::import_option()` already uses the new pair — leave it, and add the same
  legacy fallback for consistency so old zone exports still import.

**Rationale**: The explicit pair is the spec's chosen convention and is already what the
shipping-zones path uses; moving the base to match unifies the contract. The `??` fallback preserves
v1.1.9 imports (FR-011) and is null-safe on 7.4. Defining it once in the bases means the 16 concrete
classes inherit correctness.

**Alternatives considered**: Standardize on legacy `option`/`value` (rejected — spec explicitly chose
`option_name`/`option_value`, the "more explicit pair", and the shipping path already uses it).
No fallback (rejected — breaks v1.1.9 archives, violates FR-011).

---

## Decision 5 — `EmailsOptionsExporter` duplicate entry

**Finding**: `EmailsOptionsExporter.php` lists `woocommerce_customer_completed_order_settings`
**twice** (lines 27 and 28). Exporting produces a duplicated option entry.

**Decision**: Remove the duplicate line (keep one). No other behavior change.

**Rationale**: FR-012 / constitution IV ("Duplicate entries … MUST be removed"). Harmless but untidy
and produces redundant JSON.

**Alternatives considered**: De-dup at runtime in `get_options_values()` (rejected — masks the
source error; the array literal is the right place to fix).

---

## Decision 6 — `AbstractImporter` constructor consistency

**Finding**: Audit of all 8 importers shows a **consistent** pattern: each child declares a zero-arg
`public function __construct()` that calls `parent::__construct( new XExporter() )`, and the parent
`AbstractImporter::__construct( AbstractExporter $exporter )` accepts exactly that. The handler
instantiates with `new $className()` (line 227), which matches the zero-arg child constructors.
`ShippingZonesImporter` additionally sets `$this->wpdb` after `parent::__construct`. **No signature
mismatch exists in the current code.**

**Decision**: Treat US-5 / FR-016 as **verify-and-document**, not a code change: confirm under the
`php -l` + instantiation smoke (quickstart) that no constructor warning/error occurs on 7.4 or 8.3,
and record the parent/child constructor contract in a comment on `AbstractImporter::__construct`.
Only edit if the lint/QA gate surfaces an actual mismatch.

**Rationale**: The plan must reflect the code as it is. The spec's US-8 was written from the
migration plan's risk list; the audit shows the risk is not currently realized. Honesty over
inventing a fix; the acceptance criteria ("signatures match, no warnings") are already met and will
be verified, not assumed.

**Alternatives considered**: Forcing a refactor to inject the exporter from the handler (rejected —
no problem to solve, adds surface area, violates Scope Discipline VI).

---

## Decision 7 — Resolve the `ShippingZonesImporter` TODO

**Finding**: `ShippingZonesImporter.php` line 22:
`//TODO: Add a Learn more link that explains why users should delete existing zones.` The importer
already throws a clear exception when existing zones are present (lines 24–26, via `validate()`).

**Decision**: Resolve per FR-017 by **replacing the TODO with a proper explanatory comment** (an
intentional-design note: the importer intentionally refuses to merge into existing zones to avoid ID
collisions/duplication, and the user is instructed to clear zones first via the thrown message). A
"Learn more" UI link is new user-facing surface area and is out of scope (VI); the comment documents
the deliberate decision instead. Then confirm zero TODO/FIXME remain repo-wide (FR-018).

**Rationale**: FR-017 explicitly allows "implement the behavior **or** replace with a clear
explanation of the intentional design decision." Adding a UI link would be feature creep; the
explanatory comment is the in-scope resolution.

**Alternatives considered**: Implement the "Learn more" link (rejected — new UI surface, Scope
Discipline VI). Delete the comment silently (rejected — loses the rationale; FR-017 wants the
decision documented).

---

## Cross-cutting: PHP 7.4 / 8.3 safety of new code (Principle I)

All new constructs are 7.4-safe and 8.3-clean: `??` null-coalescing (7.0+), `wp_check_filetype_and_ext`,
`ZipArchive::statIndex`, `MB_IN_BYTES`, `apply_filters`, `current_user_can`, `wp_die`. No `${var}`
interpolation, no `str_contains`/`str_starts_with` (the traversal scan uses `strpos`/`preg_match`,
both 7.4-native). No dynamic property creation introduced. The binary `php -l` gate on 7.4 + 8.3
remains the merge requirement.

## Summary of file impact

| File | Change | Drivers |
|------|--------|---------|
| `includes/MigrateStore.php` | Capability checks (both handlers); MIME + size + traversal validation; exact routing; centralized cleanup on all paths | US-1, US-2, US-4 / FR-001..008, 013..015 |
| `includes/Exporters/AbstractExporter.php` | `get_options_values()` → `option_name`/`option_value` + doc comment | US-3 / FR-009, FR-010 |
| `includes/Importers/AbstractImporter.php` | read `option_name`/`option_value` + legacy fallback + doc comment; constructor contract comment | US-3, US-5 / FR-009..011, FR-016 |
| `includes/Exporters/WooCommerce/EmailsOptionsExporter.php` | remove duplicate option entry | US-3 / FR-012 |
| `includes/Importers/WooCommerce/ShippingZonesImporter.php` | resolve TODO → explanatory comment; add legacy fallback in `import_option` | US-6, US-3 / FR-017, FR-011 |
| 7 exporters + 7 importers | AUDIT (inherit base contract) | FR-010, FR-016 |
