# Migrate Store — Project Constitution

**Project:** `migratestore`
**Scope:** WordPress 7.0 readiness + hardening (no new features)
**Version Target:** 1.2.0

---

## 1. Project Identity

Migrate Store is a WooCommerce plugin (1,000+ active installs, 24 all-5-star reviews) that exports and imports WooCommerce settings between sites. The goal of this release is to make it trustworthy, secure, and fully compatible with WordPress 7.0 and modern PHP — not to add features.

**Out of scope for this release:** import preview/diff, rollback, bulk export, WP-CLI commands, import logs, merge/replace modes for shipping zones.

---

## 2. Environment & Compatibility Requirements

- **WordPress minimum:** 6.0 (keep existing)
- **WordPress tested up to:** 7.0
- **PHP minimum:** 7.4 (declare in plugin header; enforce in code)
- **PHP recommended/tested:** 8.3
- **WooCommerce minimum:** declare `Requires Plugins: woocommerce` in plugin header
- **WooCommerce tested up to:** latest stable (9.x)
- **MySQL minimum:** 8.0 (WP 7.0 requirement; no direct DB code changes needed unless queries are incompatible)

---

## 3. Code Quality Standards

### PHP
- All code must pass PHP lint (`php -l`) with zero errors on PHP 7.4 and PHP 8.3.
- No use of deprecated PHP 8.x features:
  - No `${var}` string interpolation (deprecated PHP 8.2) — use `{$var}` or concatenation.
  - No dynamic property creation without `#[\AllowDynamicProperties]` (PHP 8.2).
  - No passing `null` to non-nullable parameters (PHP 8.1).
- Use `str_contains()`, `str_starts_with()`, `str_ends_with()` only with a PHP 7.4 polyfill or `function_exists()` guard — the minimum is 7.4, which doesn't have these.
- All functions must have proper parameter types and return types where PHP 7.4 supports them.
- No TODO/FIXME comments left in production code.

### WordPress Coding Standards
- Capability checks (`current_user_can()`) must be present on every AJAX and admin action handler — not just nonce checks.
- Nonces must also still be verified (both nonce AND capability, not either/or).
- Use `wp_die()` with a translated message on unauthorized access, not `exit`.
- All user-facing strings must be wrapped in i18n functions with the correct text domain (`migratestore`).
- No hardcoded text domain mismatches.

### WooCommerce Standards
- Shipping method support must not be limited to a hardcoded whitelist.
- Any `woocommerce_shipping_methods` hook usage must allow third-party methods.

---

## 4. Security Requirements

These are non-negotiable and must be present in every export/import handler:

1. **Capability check** — `current_user_can( 'manage_woocommerce' )` before any export or import action.
2. **Nonce verification** — `check_ajax_referer()` or `wp_verify_nonce()` on every handler.
3. **File type validation** — uploaded files must be validated as `.zip` by MIME type, not just extension.
4. **File size validation** — enforce a reasonable maximum upload size.
5. **ZIP path traversal prevention** — extracted file paths must be sanitized and confined to the temp directory; reject any entry with `..` or absolute paths.
6. **Temp file cleanup** — all temporary files and directories must be removed after import (success or failure), using `WP_Filesystem` where appropriate.
7. **Import key matching** — importer selection must use exact key matching, not substring/partial matching.

---

## 5. Data Integrity Requirements

- The option field naming must be consistent across all exporters and importers:
  - A single convention must be chosen (`option_name`/`option_value` **or** `option`/`value`) and applied uniformly across all classes.
  - Any deviation that exists for backward compatibility with already-exported files must be explicitly documented in a code comment.
- The `AbstractImporter` constructor signature must match across parent and all child classes — no type mismatches.
- Duplicate entries in `EmailsOptionsExporter.php` must be removed.

---

## 6. Testing Requirements

Manual QA must cover all of the following before each phase is considered done:

| Scenario | Expected Result |
|----------|----------------|
| Export general settings | JSON file downloads correctly |
| Import general settings | Settings applied, admin notice confirms |
| Export shipping zones | JSON file downloads correctly |
| Import shipping zones | Zones created, admin notice confirms |
| Export shipping classes | JSON file downloads correctly |
| Import shipping classes | Classes created, admin notice confirms |
| Export email settings | JSON file downloads correctly |
| Import email settings | Settings applied, admin notice confirms |
| Upload a non-ZIP file | Rejected with clear admin notice |
| Upload an oversized ZIP | Rejected with clear admin notice |
| Upload a ZIP with path traversal entries | Rejected, no files extracted |
| Import as a Subscriber (no `manage_woocommerce`) | Request blocked, 403 equivalent |
| Site with existing shipping zones | Import runs without fatal error |

PHP lint must pass on PHP 7.4 (`php -l`) and PHP 8.3 before merge.

---

## 7. Versioning & Release

- Bump plugin version to `1.2.0` in the main plugin file and `readme.txt`.
- Update `Tested up to` to `7.0` in both `readme.txt` and the plugin header.
- Add/confirm `Requires PHP: 7.4` in the plugin header.
- Add `Requires Plugins: woocommerce` in the plugin header.
- Changelog entry must be added to `readme.txt` describing each fix.

---

## 8. What This Constitution Does Not Cover

The following are explicitly deferred to a future release and must not be implemented as part of this readiness effort:

- Import preview / diff UI
- Rollback / undo functionality
- Bulk export (single ZIP for all setting types)
- WP-CLI commands
- Import audit logs
- Merge/replace mode for shipping zones
- Any UI redesign
