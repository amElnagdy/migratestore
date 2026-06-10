# Contract: Export/Import Handler Security Gate

**Feature**: 002-security-data-integrity
**Applies to**: `MigrateStore::handle_export_action()`, `MigrateStore::handle_import_action()` in
`includes/MigrateStore.php`
**Source of truth**: Constitution Principle II (v1.0.0); spec FR-001..FR-008.

This contract defines the mandatory gate every export/import handler MUST satisfy. A handler missing
any clause is non-compliant (hard merge block per Principle II).

---

## C-1 Authorization + authenticity (both handlers)

- MUST call `current_user_can( 'manage_woocommerce' )` and, on failure, end via
  `wp_die( <translated message>, 403 )` — **before** any data is read, any file is processed, or any
  response is returned.
- MUST retain `check_admin_referer( '<action>_nonce' )` (nonce verification).
- BOTH checks are required; passing only one MUST NOT permit the action.
- Unauthorized termination MUST use `wp_die()` with a `migratestore`-text-domain translated string,
  never a bare `exit`.

**Verification**: a Subscriber (valid nonce, lacks capability) is blocked with no data read/written;
a `manage_woocommerce` user proceeds; a request with a bad/absent nonce is blocked regardless of
capability.

---

## C-2 Upload type validation (import handler)

- MUST validate the uploaded file's real type via `wp_check_filetype_and_ext()` and accept only
  `application/zip` or `application/x-zip-compressed`.
- MUST NOT rely on the file extension alone.
- On rejection: cleanup (C-5) then `wp_die()` with a clear, actionable translated notice.

**Verification**: a `.txt` (or any non-zip) renamed `.zip` is rejected; no extraction occurs.

---

## C-3 Upload size validation (import handler)

- MUST reject uploads whose size exceeds
  `apply_filters( 'migratestore_max_upload_size', 10 * MB_IN_BYTES )`.
- Default limit: 10 MB. The filter MUST allow operators to adjust it.
- On rejection: cleanup (C-5) then `wp_die()` with a notice stating the size limit.

**Verification**: an oversized ZIP is rejected with the limit stated; raising the filter value
allows a larger file.

---

## C-4 ZIP path-traversal prevention (import handler)

- Before extraction, MUST enumerate every archive entry and reject the **entire** import if any
  entry name contains a `..` path segment or is absolute (leading `/`, a Windows drive prefix like
  `C:\`, or a UNC `\\` prefix).
- On rejection: nothing is extracted; cleanup (C-5) then `wp_die()` with a notice.

**Verification**: a ZIP containing `../../etc/passwd` (or `..\..\` / `/abs/path`) is rejected with no
files written outside `migratestore_tmp`.

---

## C-5 Temp-file cleanup on every exit path (import handler)

- The moved upload (`wp_handle_upload()` result) **and** the temp extract directory
  (`…/migratestore_tmp`) MUST be removed before the handler returns on **every** path: success,
  caught exception, and each early `wp_die()` bail.
- Cleanup MUST NOT depend on a redirect/exit side effect; it MUST be invokable on failure paths too.
- Post-condition: **zero** orphaned files in the uploads or temp directory after any import attempt.

**Verification**: after each of {success, every rejection case, mid-extraction failure}, the uploads
dir and `migratestore_tmp` contain none of this import's artifacts.

---

## C-6 Actionable rejection notices

- Every rejection (C-2..C-5, plus empty/missing upload and unrecognized file) MUST surface a clear,
  translated message explaining why, via `wp_die()` or the existing
  `migratestore_import_error` transient → admin notice path.

**Verification**: each rejection path shows a human-readable reason, not a silent failure or raw
error.

---

## Ordering (recommended)

1. Capability (C-1) → 2. Nonce (C-1) → 3. Upload present & `UPLOAD_ERR_OK` → 4. `wp_handle_upload` →
5. MIME (C-2) → 6. Size (C-3) → 7. Traversal scan (C-4) → 8. Extract → 9. Route (exact key) →
10. Import → 11. Cleanup (C-5) on success/catch. Every bail between steps 3–10 runs C-5 first.
