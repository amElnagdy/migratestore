# Quickstart & Verification Runbook: Shipping Method Filter & Release QA (Phase 3)

**Feature**: `specs/003-shipping-method-filter` | **Date**: 2026-06-11

This is the merge gate for Phase 3 (and, via the QA matrix, the release gate for v1.2.0). Per
Constitution Principle V, the change is **not done** until both gates below pass.

> **Environment note**: the authoring environment has no PHP on PATH. Run the lint gate and the QA
> matrix in a developer/CI environment: WordPress 7.0 + latest WooCommerce, on PHP 7.4 and PHP 8.3
> (Docker or local). A representative third-party shipping method plugin (e.g. a table-rate plugin)
> is required for scenarios 4 and 6.

---

## Gate 1 — Lint (PHP 7.4 and 8.3)

Run on every plugin `.php` file; expect zero errors on both versions.

```bash
# from the plugin root, on each PHP version
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

Pass = "No syntax errors detected" for every file on **both** 7.4 and 8.3 (QA #16, #17).

---

## Gate 2 — Manual QA matrix (WP 7.0 + latest WooCommerce)

Record pass/fail for all 18 scenarios. **Any** failure blocks the release (FR-010/FR-011).

| # | Scenario | Steps | Expected |
|---|----------|-------|----------|
| 1 | Export general settings | Export page → General settings | File downloaded, readable |
| 2 | Import general settings | Import the file from #1 | Settings applied, success notice |
| 3 | Export shipping zones (built-ins) | Zone with flat_rate + free_shipping + local_pickup → export | All three method types in JSON |
| 4 | Export shipping zones (third-party) | Zone with a Table Rate (or similar) method → export | **All** methods in JSON incl. third-party; none dropped; its `_settings` option present |
| 5 | Import shipping zones | Import #3 file on a clean target | Zones created, success notice |
| 6 | Import zones with unknown method IDs | Import #4 file on a target **without** the third-party plugin | Dismissible `notice-warning` lists skipped method IDs; remaining zones/methods imported |
| 7 | Export shipping classes | Export classes | File downloaded |
| 8 | Import shipping classes | Import #7 file | Classes created, success notice |
| 9 | Export email settings | Export emails | File downloaded |
| 10 | Import email settings | Import #9 file | Settings applied, success notice |
| 11 | Upload `.txt` as import | Upload a text file | Rejected, clear admin notice (Phase 2) |
| 12 | Upload oversized archive | Upload > max size (default 10 MB) | Rejected, clear admin notice (Phase 2) |
| 13 | Path-traversal archive | Upload ZIP with `../../etc/passwd` entry | Rejected, no extraction, notice (Phase 2) |
| 14 | Import as Subscriber | Attempt import as Subscriber role | Blocked, no data written (Phase 2) |
| 15 | Import onto existing zones | Import zones on a site that already has zones | No fatal error (existing-zone guard) |
| 16 | Lint PHP 7.4 | Gate 1 on 7.4 | Zero errors |
| 17 | Lint PHP 8.3 | Gate 1 on 8.3 | Zero errors/warnings |
| 18 | Header in WP admin | Plugins screen → Migrate Store | "Tested up to 7.0", "Requires PHP: 7.4" |

> Scenarios 11–14 and 18 verify Phase 1/2 guarantees and therefore require Phases 1 and 2 to be
> implemented on the branch before the release QA pass is meaningful (see plan Dependencies).

---

## Gate 3 — Release documentation (US-3 / FR-012–014)

Confirm in `readme.txt`:

- [ ] `Stable tag: 1.2.0`
- [ ] `Tested up to: 7.0`
- [ ] A `= 1.2.0 =` changelog entry covering: WP 7.0 compat; PHP 8.2/8.3 compat; capability checks on
      all export/import handlers; archive upload validation + temp-file cleanup; option field-name
      consistency; AbstractImporter constructor fix; duplicate email-settings exporter entry fix;
      shipping-method export no longer limited to three built-in types.
- [ ] No placeholder/draft text anywhere in `readme.txt`.
- [ ] `migratestore.php` header `Version: 1.2.0` and `MIGRATESTORE_VERSION` match (header bump from
      Phase 1; verify here).

---

## Definition of done

Phase 3 is done when **Gate 1 passes on both PHP versions, all 18 QA scenarios pass, and Gate 3 is
fully checked** — at which point v1.2.0 is releasable.
