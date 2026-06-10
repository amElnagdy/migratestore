# Phase 1 Data Model: Security Hardening & Data Integrity

**Feature**: 002-security-data-integrity | **Date**: 2026-06-10

This phase introduces **no new persistent storage**. It tightens the validation, naming, routing,
and lifecycle of data that already flows through the export/import pipeline. The "entities" below
are the in-flight data contracts the implementation must honor, mapped from the spec's Key Entities
to their concrete representation in code.

---

## Entity: Option Entry

A single setting carried inside a settings JSON file and applied via `update_option()`.

| Field | Canonical key (v1.2.0+) | Legacy key (v1.1.9, read-only fallback) | Type | Rules |
|-------|-------------------------|------------------------------------------|------|-------|
| Option name | `option_name` | `option` | string | Sanitized with `sanitize_key()`; MUST be in the exporter's allowed-name set before `update_option()` |
| Option value | `option_value` | `value` | string / serialized array | `maybe_unserialize()` if serialized; arrays sanitized recursively with `sanitize_text_field`; scalars with `sanitize_text_field` |

**Write rule (exporters)**: `AbstractExporter::get_options_values()` MUST emit `option_name` /
`option_value`. Never write the legacy pair.

**Read rule (importers)**: resolve `option_name ?? option` and `option_value ?? value`. The legacy
fallback is read-only and exists solely for v1.1.9 archives; it MUST be documented in a code comment
(constitution IV).

**Validation rule**: an entry whose resolved name is not in `AbstractExporter::get_data()`'s allowed
names MUST cause the importer to throw (`RuntimeException`), as today — extended to read the
canonical key.

**State transition**: `exported (option_name/option_value)` → `imported (resolved + sanitized +
allow-listed)` → `applied (update_option)`. Old archives enter at `imported` via the fallback.

---

## Entity: Settings File

One JSON file within the uploaded ZIP, representing one settings category.

| Attribute | Representation | Rules |
|-----------|----------------|-------|
| Filename | `migratestore_<key>_<YYYYMMDD>_<HHMMSS>.json` | Timestamp suffix stripped via `preg_replace('/_\d{8}_\d{6}$/', '', …)` to yield `<routing key>` |
| Routing key | `migratestore_<key>` | MUST match a key in `$importerStrategies` **exactly** (`array_key_exists`) — never substring |
| Payload | JSON array of Option Entries, or category-specific nested structure (shipping zones) | Parsed by `AbstractImporter::get_json_data()` |

**Routing rule**: exactly one importer per file by exact key. No match → actionable `wp_die()`
notice, no silent no-op (FR-014). v1.1.9 filenames share the same key prefixes → still match
(FR-015).

**Known routing keys** (unchanged from current code):
`migratestore_general_settings`, `migratestore_zones`, `migratestore_accounts_privacy_options`,
`migratestore_emails_settings`, `migratestore_endpoints_options`, `migratestore_shipping_options`,
`migratestore_tax_options`, `migratestore_shipping_classes`.

---

## Entity: Settings Export Bundle (uploaded ZIP)

The archive a user uploads to import. Validated before any extraction.

| Attribute | Source | Validation gate | On failure |
|-----------|--------|-----------------|------------|
| Upload error | `$_FILES['json_zip_file']['error']` | MUST be `UPLOAD_ERR_OK` | cleanup + `wp_die()` notice |
| MIME type | `wp_check_filetype_and_ext( file, name )` | MUST be `application/zip` or `application/x-zip-compressed` | cleanup + `wp_die()` notice (FR-004) |
| Size | `$_FILES['json_zip_file']['size']` | MUST be ≤ `apply_filters('migratestore_max_upload_size', 10 * MB_IN_BYTES)` | cleanup + `wp_die()` notice stating limit (FR-005) |
| Entry paths | `ZipArchive` enumeration before extraction | NO entry may contain `..` segment or absolute path (leading `/`, drive letter, or UNC) | reject entire import, extract nothing, cleanup + notice (FR-006) |

**Default max size**: 10 MB (`10 * MB_IN_BYTES`), operator-adjustable via the
`migratestore_max_upload_size` filter.

---

## Entity: Import Artifacts (temp lifecycle)

Files created on disk during an import that MUST be removed on every exit path.

| Artifact | Path | Created by | Removed by |
|----------|------|------------|------------|
| Moved upload | `$uploaded_file['file']` (uploads dir) | `wp_handle_upload()` | centralized cleanup helper (NEW — currently leaked) |
| Temp extract dir | `wp_upload_dir()['basedir'] . '/migratestore_tmp'` | `mkdir` + `unzip_file()` | centralized cleanup helper / `recursiveRemoveDirectory()` |

**Lifecycle invariant (FR-007)**: after `handle_import_action()` returns by **any** path — success,
caught exception, or early `wp_die()` — **zero** of these artifacts remain. The current code removes
only the temp dir (via `$importer->cleanup()`) and only on the non-bail path; the moved upload and
all early-bail paths leak. The fix is a single helper invoked before every `wp_die()` and in both
success and `catch` branches.

---

## Entity: Requester (authorization)

The user invoking export or import. Not stored; evaluated per request.

| Attribute | Check | Rule |
|-----------|-------|------|
| Request authenticity | `check_admin_referer( '<action>_nonce' )` | MUST pass (already present) |
| Authorization | `current_user_can( 'manage_woocommerce' )` | MUST pass (NEW) — checked before any data read/write |

**Invariant (FR-003)**: BOTH must pass. Failing either → translated `wp_die()` (403 for capability),
no data read, returned, or written. Neither substitutes for the other (constitution II.1–II.2).

---

## Relationships

```
Requester ──(must pass capability + nonce)──> Handler (export | import)
Handler(import) ──validates──> Export Bundle (MIME, size, entry paths)
Export Bundle ──contains──> Settings File(s) ──route(exact key)──> Importer
Settings File ──contains──> Option Entry(ies) ──apply──> wp_options
Handler(import) ──owns──> Import Artifacts ──(cleanup on every exit)──> ∅
```

## Non-changes (explicit)

- `wp_options` schema, WooCommerce shipping-zone tables: **unchanged**.
- Exported JSON top-level shape per category: **unchanged** except option-entry field names.
- Importer/exporter class hierarchy and constructor arity: **unchanged** (verified consistent).
- No new options, transients (beyond existing success/error transients), or tables.
