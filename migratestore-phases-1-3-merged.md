# Spec: Phase 1 — Plugin Headers & PHP Compatibility

**Plugin:** `migratestore`
**Phase:** 1 of 3
**Type:** Hardening / Compatibility
**Priority:** Must ship before WordPress 7.0

---

## Context

Migrate Store v1.1.9 currently declares `Tested up to: 6.9.4` and is missing several required headers for WordPress 7.0 compatibility. The plugin also contains PHP 8.x incompatibilities that will produce deprecation notices or fatal errors under PHP 8.2+ environments that WordPress 7.0 recommends.

This spec covers all header-level declarations and PHP code hygiene. No functional behavior changes.

---

## User Stories

### US-1 — Plugin header is WP 7.0 compliant

**As a** site administrator running WordPress 7.0,  
**I want** Migrate Store to declare correct compatibility headers,  
**so that** the plugin directory and WordPress admin show it as compatible and I am not warned about untested software.

**Acceptance criteria:**
- `readme.txt` has `Tested up to: 7.0`
- Main plugin file header has `Tested up to: 7.0`
- Main plugin file header has `Requires PHP: 7.4`
- Main plugin file header has `Requires Plugins: woocommerce`
- Plugin version is bumped to `1.2.0` in both locations
- Changelog entry added to `readme.txt`

---

### US-2 — Plugin runs without PHP 8.x deprecation notices

**As a** developer or host running PHP 8.2 or 8.3,  
**I want** Migrate Store to produce zero deprecation notices,  
**so that** my error logs stay clean and the plugin is forward-compatible.

**Acceptance criteria:**
- No `${var}` string interpolation anywhere in plugin source (use `{$var}` or concatenation)
- No dynamically created object properties without `#[\AllowDynamicProperties]`
- No `null` passed to non-nullable built-in parameters
- All named arguments (if any) are compatible with parent method signatures
- PHP lint (`php -l`) passes on all `.php` files under PHP 7.4 and PHP 8.3

---

### US-3 — No PHP 7.4-only functions used without guards

**As a** site running the minimum supported PHP 7.4,  
**I want** the plugin not to use PHP 8.0+ functions without fallbacks,  
**so that** it doesn't produce fatal errors on the declared minimum PHP version.

**Acceptance criteria:**
- `str_contains()`, `str_starts_with()`, `str_ends_with()` are either absent or guarded with `function_exists()` polyfills
- No other PHP 8.0+ functions used without guards
- If polyfills are added, they live in a dedicated `includes/polyfills.php` loaded early

---

## Out of Scope for This Phase

- Security hardening (Phase 2)
- Data integrity / constructor fixes (Phase 2)
- Shipping method filter (Phase 3)
- Any new features

---

## Files Likely Affected

```
migratestore.php                  (main plugin file — headers + version)
readme.txt                        (tested up to, changelog)
includes/                         (PHP compatibility audit)
  AbstractExporter.php
  AbstractImporter.php
  exporters/*.php
  importers/*.php
```

---

## Suggested AI Prompt for `/speckit.specify`

```
Audit all PHP files in the migratestore plugin for PHP 8.2+ deprecations:
${var} string interpolation, dynamic property creation, null-to-non-nullable
parameter passing. Also check for any PHP 8.0+ functions (str_contains,
str_starts_with, str_ends_with) used without a 7.4-compatible polyfill.
Fix all issues found. Then update the plugin headers in migratestore.php and
readme.txt: Tested up to 7.0, Requires PHP: 7.4, Requires Plugins:
woocommerce, version 1.2.0. Add a changelog entry.
```


---

# Spec: Phase 2 — Security Hardening & Data Integrity

**Plugin:** `migratestore`
**Phase:** 2 of 3
**Type:** Security / Bug Fix
**Priority:** Must ship before WordPress 7.0

---

## Context

The current export/import pipeline has several security gaps and data integrity bugs:

- Export and import AJAX handlers verify nonces but do not check user capabilities.
- ZIP file upload handling does not validate file type by MIME, does not cap file size, does not sanitize extracted paths, and may leave temp files behind on failure.
- Importer selection uses substring matching on filenames, making it brittle and potentially exploitable.
- `AbstractImporter` constructor signature mismatches child classes.
- Option field names are inconsistent between exporters (`option_name`/`option_value`) and importers (`option`/`value`), causing silent import failures.
- `EmailsOptionsExporter.php` contains a duplicate entry.
- A TODO comment was left in `ShippingZonesImporter.php`.

---

## User Stories

### US-4 — Only authorized users can export or import

**As a** site owner,  
**I want** only users with the `manage_woocommerce` capability to be able to trigger exports or imports,  
**so that** a Subscriber or Editor cannot exfiltrate or overwrite my store settings.

**Acceptance criteria:**
- Every AJAX export handler calls `current_user_can( 'manage_woocommerce' )` and calls `wp_die()` with a translated error message if it fails — before any data is read or returned.
- Every AJAX import handler does the same check before processing the uploaded file.
- Nonce verification remains in place (both nonce AND capability, not either/or).
- A test import request made with a nonce-valid but capability-lacking user (Subscriber) returns an error and performs no action.

---

### US-5 — Uploaded ZIP files are validated and safely handled

**As a** site owner,  
**I want** the plugin to reject invalid, oversized, or malicious ZIP uploads gracefully,  
**so that** a bad file cannot cause data loss, leave junk on disk, or extract files outside the intended directory.

**Acceptance criteria:**
- File type is validated by MIME type (`application/zip`, `application/x-zip-compressed`) using `wp_check_filetype_and_ext()`, not just by extension.
- File size is validated; uploads exceeding a configurable maximum (default: 10 MB, filterable via `migratestore_max_upload_size`) are rejected with an admin notice.
- All ZIP entry paths are sanitized before extraction; any entry containing `..` or an absolute path causes the entire import to be rejected.
- On both success and failure, the temporary upload file and any extracted temp directory are deleted before the handler returns.
- Rejected uploads produce a clear, actionable admin notice explaining why the file was rejected.
- No orphaned files remain in the uploads or temp directory after any import attempt.

---

### US-6 — Importer selection uses exact key matching

**As a** developer maintaining the plugin,  
**I want** importer selection to match file keys exactly,  
**so that** a filename that partially matches two importers doesn't trigger the wrong one.

**Acceptance criteria:**
- Importer selection logic uses strict equality (`===`) against a defined map of expected export file keys.
- No use of `strpos()`, `str_contains()`, or similar substring methods to identify which importer to use.
- If no importer is matched, the import fails with an actionable admin notice rather than silently doing nothing.
- Existing exported files (from v1.1.9 and earlier) continue to be importable without breaking changes.

---

### US-7 — Option field names are consistent across all exporters and importers

**As a** store owner migrating settings between sites,  
**I want** exports and imports to use matching field names,  
**so that** my imported settings are actually applied and not silently ignored.

**Acceptance criteria:**
- A single field naming convention is chosen and documented in a code comment in `AbstractImporter.php` and `AbstractExporter.php`.
- The chosen convention: `option_name` / `option_value` (the more explicit pair).
- All importers read `option_name` and `option_value` from the JSON.
- All exporters write `option_name` and `option_value` to the JSON.
- If supporting the old `option`/`value` format for backward compatibility with v1.1.9 exports, the importer falls back to those keys only if the new keys are absent, and this is documented in a comment.
- `EmailsOptionsExporter.php` duplicate entry is removed.

---

### US-8 — AbstractImporter constructor is consistent with child classes

**As a** developer,  
**I want** the `AbstractImporter` parent constructor and all child constructors to have matching signatures,  
**so that** PHP does not throw warnings or errors when instantiating importers.

**Acceptance criteria:**
- Parent `AbstractImporter::__construct()` and all child `__construct()` signatures match (same parameter names, types, and order).
- No PHP warnings related to constructor signature mismatch under PHP 7.4 or PHP 8.3.
- All child classes correctly call `parent::__construct()` with the appropriate arguments.

---

### US-9 — No leftover debug artifacts in production code

**As a** code reviewer or auditor,  
**I want** the plugin source to contain no TODO/FIXME comments,  
**so that** the code signals production readiness.

**Acceptance criteria:**
- `ShippingZonesImporter.php` TODO comment is resolved (either the missing behavior is implemented or the comment is replaced with a proper explanation of the intentional design decision).
- No other TODO or FIXME comments remain anywhere in the plugin source.

---

## Out of Scope for This Phase

- Shipping method whitelist removal (Phase 3)
- Any new features

---

## Files Likely Affected

```
includes/
  AbstractImporter.php            (constructor + field naming convention)
  AbstractExporter.php            (field naming convention)
  importers/
    GeneralImporter.php           (capability check, exact key matching, field names)
    ShippingZonesImporter.php     (capability check, exact key matching, field names, TODO)
    ShippingClassesImporter.php   (capability check, exact key matching)
    EmailsImporter.php            (capability check, exact key matching, field names)
    TaxImporter.php               (capability check, exact key matching)
    ShippingOptionsImporter.php   (capability check, exact key matching)
    AccountsPrivacyImporter.php   (capability check, exact key matching)
    AdvancedImporter.php          (capability check, exact key matching)
  exporters/
    EmailsOptionsExporter.php     (duplicate entry removal, field names)
    ShippingZonesExporter.php     (field names)
    [all other exporters]         (field names audit)
admin/
  [AJAX handler registration]     (capability checks, ZIP validation, temp cleanup)
```

---

## Suggested AI Prompt for `/speckit.specify`

```
Harden the Migrate Store import/export security pipeline. Apply to every
AJAX export and import handler:
1. Add current_user_can('manage_woocommerce') check before nonce check.
2. Add ZIP MIME type validation using wp_check_filetype_and_ext().
3. Add max file size check (default 10MB, filterable).
4. Sanitize all ZIP entry paths; reject any with '..' or absolute paths.
5. Ensure temp files are cleaned up in both success and failure paths.
6. Replace substring importer-matching with an exact key map.
7. Unify option field names to option_name/option_value across all
   exporters and importers, with backward-compat fallback for old exports.
8. Fix AbstractImporter constructor signature mismatch.
9. Remove the duplicate entry in EmailsOptionsExporter.php.
10. Resolve the TODO in ShippingZonesImporter.php.
Document every behavior change in inline comments.
```


---

# Spec: Phase 3 — Shipping Method Filter & Release QA

**Plugin:** `migratestore`
**Phase:** 3 of 3
**Type:** Bug Fix / Release Readiness
**Priority:** Must ship before WordPress 7.0

---

## Context

The current shipping zone exporter silently drops any shipping method that isn't one of three hardcoded types (`flat_rate`, `free_shipping`, `local_pickup`). Stores using third-party or custom shipping methods lose those methods silently on export with no warning — a data loss bug.

This phase removes the hardcoded whitelist, introduces a filter hook for extensibility, adds an admin notice when methods are skipped, and performs full release QA across all export/import flows.

---

## User Stories

### US-10 — Third-party shipping methods are exported and imported

**As a** store owner using a custom or third-party shipping method plugin,  
**I want** Migrate Store to export my shipping methods rather than silently dropping them,  
**so that** my shipping zone configuration is faithfully reproduced on the target site.

**Acceptance criteria:**
- The hardcoded shipping method whitelist (`flat_rate`, `free_shipping`, `local_pickup`) is removed from `ShippingZonesExporter.php`.
- All registered shipping methods (as returned by `WC()->shipping()->get_shipping_methods()`) are included in the export by default.
- A filter hook `migratestore_excluded_shipping_methods` allows developers to exclude specific method IDs from export. Default value: empty array (nothing excluded).
- The filter is documented with a `@filter` docblock in the exporter.
- On import, if a shipping method ID in the JSON is not registered on the target site, the method is skipped and an admin notice lists the skipped methods by ID.
- The admin notice is dismissible and uses the `notice-warning` class.
- The three previously-supported method types (`flat_rate`, `free_shipping`, `local_pickup`) continue to work without any changes to existing exported files.

---

### US-11 — Release is fully QA-verified

**As a** plugin author,  
**I want** every export/import scenario to be manually tested on WordPress 7.0 with WooCommerce latest before tagging the release,  
**so that** users don't encounter regressions after upgrading.

**Acceptance criteria — all of the following must pass:**

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Export general settings | JSON downloaded, readable |
| 2 | Import general settings | Settings applied, success notice shown |
| 3 | Export shipping zones (with flat_rate + free_shipping + local_pickup) | All three method types in JSON |
| 4 | Export shipping zones (with a third-party method, e.g. Table Rate) | All methods in JSON, none silently dropped |
| 5 | Import shipping zones | Zones created, success notice |
| 6 | Import shipping zones with unknown method IDs | Warning notice listing skipped methods; rest of zones imported |
| 7 | Export shipping classes | JSON downloaded |
| 8 | Import shipping classes | Classes created, success notice |
| 9 | Export email settings | JSON downloaded |
| 10 | Import email settings | Settings applied, success notice |
| 11 | Upload a `.txt` file as import | Rejected with clear admin notice |
| 12 | Upload an oversized ZIP | Rejected with clear admin notice |
| 13 | Upload a ZIP with `../../etc/passwd` entry | Rejected, no extraction, notice shown |
| 14 | Import as Subscriber role | Blocked, no data written |
| 15 | Import on a site with existing shipping zones | Runs without fatal error |
| 16 | PHP lint on PHP 7.4 | Zero errors |
| 17 | PHP lint on PHP 8.3 | Zero errors/warnings |
| 18 | Plugin header in WP admin | Shows "Tested up to 7.0", "Requires PHP: 7.4" |

---

### US-12 — `readme.txt` and changelog are complete for v1.2.0

**As a** WordPress.org reviewer or user reading the changelog,  
**I want** the readme to accurately describe what changed in v1.2.0,  
**so that** I understand what was fixed before updating.

**Acceptance criteria:**
- `readme.txt` changelog entry for `1.2.0` covers:
  - WordPress 7.0 compatibility
  - PHP 8.2/8.3 compatibility
  - Security: capability checks on all export/import handlers
  - Security: ZIP upload validation and temp file cleanup
  - Fix: option field name consistency (`option_name`/`option_value`)
  - Fix: `AbstractImporter` constructor mismatch
  - Fix: duplicate entry in email settings exporter
  - Fix: shipping method export no longer limited to three built-in types
- No placeholder or draft text remains in `readme.txt`.
- `Stable tag` in `readme.txt` matches plugin version `1.2.0`.

---

## Out of Scope for This Phase

- Any new features (preview/diff, rollback, bulk export, WP-CLI)

---

## Files Likely Affected

```
includes/exporters/
  ShippingZonesExporter.php       (remove whitelist, add filter hook)
includes/importers/
  ShippingZonesImporter.php       (handle unknown method IDs, admin notice)
readme.txt                        (changelog, stable tag, tested up to)
```

---

## Suggested AI Prompt for `/speckit.specify`

```
In ShippingZonesExporter.php, remove the hardcoded whitelist that limits
export to flat_rate, free_shipping, and local_pickup. Replace it with a
call to WC()->shipping()->get_shipping_methods() to get all registered
methods. Add a filter hook migratestore_excluded_shipping_methods (returns
array of method IDs to skip, default empty array) with a @filter docblock.

In ShippingZonesImporter.php, when a method ID from the import JSON is not
found among registered methods, skip it, collect the skipped IDs, and after
import display a dismissible WP admin notice (notice-warning) listing the
skipped method IDs.

Finally, update readme.txt: set Stable tag to 1.2.0, verify Tested up to
is 7.0, and write a complete changelog entry for 1.2.0 covering all fixes
made in Phases 1, 2, and 3.
```
