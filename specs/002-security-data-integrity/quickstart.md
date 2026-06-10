# Quickstart: Verifying Security Hardening & Data Integrity (Phase 2)

**Feature**: 002-security-data-integrity | **Branch**: `migratestore-wp7-readiness`

This is the verification runbook and merge gate for Phase 2. Per Constitution Principle V, the phase
is **not done** until both gates below pass. No PHP binary is on PATH in the authoring environment —
run the lint gate in your dev/CI environment (local PHP 7.4 + 8.3, or Docker images).

---

## Gate A — Lint (PHP 7.4 and 8.3)

Run from repo root in an environment with each PHP version:

```bash
# PHP 7.4
find . -path ./lib -prune -o -name '*.php' -print | xargs -n1 php7.4 -l
# PHP 8.3
find . -path ./lib -prune -o -name '*.php' -print | xargs -n1 php8.3 -l
```

**Pass**: zero errors and zero deprecation warnings on both versions for all plugin `.php` files.

**Windows PowerShell equivalent**:
```powershell
Get-ChildItem -Recurse -Filter *.php -Path includes,migratestore.php |
  ForEach-Object { & php -l $_.FullName }
```

---

## Gate B — Manual QA matrix

Environment: WordPress 7.0, WooCommerce latest (9.x), plugin active. Use an admin
(`manage_woocommerce`) account except where noted. Enable `WP_DEBUG` + `WP_DEBUG_LOG`.

### B1 — Legitimate flows (must all succeed, no regressions)

| # | Action | Expected |
|---|--------|----------|
| 1 | Export general settings | ZIP downloads; JSON readable; option entries use `option_name`/`option_value` |
| 2 | Import general settings | Settings applied; success notice shown |
| 3 | Export shipping zones (flat_rate + free_shipping + local_pickup) | All three method types present in JSON |
| 4 | Import shipping zones (empty target) | Zones/methods/locations created; success notice |
| 5 | Export shipping classes | ZIP downloads |
| 6 | Import shipping classes | Classes created; success notice |
| 7 | Export email settings | ZIP downloads; **no duplicate** `…completed_order_settings` entry |
| 8 | Import email settings | Settings applied; success notice |
| 9 | Import a **v1.1.9** export (legacy `option`/`value`) | Values applied via fallback; success notice |

### B2 — Security hardening (must all be rejected cleanly)

| # | Action | Expected |
|---|--------|----------|
| 10 | Import as **Subscriber** (valid nonce, no capability) | Blocked with 403 translated `wp_die`; nothing read/written |
| 11 | Export as **Subscriber** | Blocked; no data returned |
| 12 | Import request with bad/absent nonce (admin) | Blocked regardless of capability |
| 13 | Upload a `.txt` renamed `.zip` | Rejected (MIME) with clear notice; no extraction |
| 14 | Upload a ZIP > 10 MB | Rejected (size) with notice stating limit |
| 15 | Raise `migratestore_max_upload_size` via filter, re-upload the same large ZIP | Now accepted |
| 16 | Upload a ZIP containing `../../etc/passwd` (and `..\..\` / absolute variants) | Rejected; **nothing extracted**; notice shown |
| 17 | Upload a ZIP whose inner filename matches no routing key | `wp_die` actionable "unrecognized file" notice (no silent no-op) |
| 18 | Upload a ZIP whose inner name partially overlaps two keys | Routed to the **exact** importer only, never misrouted |

### B3 — Cleanup invariant (check after EACH of B1 + B2)

| Check | Expected |
|-------|----------|
| `wp-content/uploads/migratestore_tmp` | does not exist / is empty after every attempt |
| Moved upload in `wp-content/uploads/` | no orphaned uploaded `.zip` left behind after any attempt (success **or** rejection) |

### B4 — Stability

| # | Action | Expected |
|---|--------|----------|
| 19 | Import shipping zones against a site that **already has** zones | Clear "delete existing zones first" message; no fatal error |
| 20 | Instantiate every importer (`new <Importer>()`) | No constructor warning/error on 7.4 or 8.3 |

---

## Gate C — Code hygiene

```bash
grep -rnE 'TODO|FIXME' --include='*.php' includes/ migratestore.php
```
**Pass**: zero matches (the `ShippingZonesImporter` TODO is replaced with an explanatory comment).

---

## Definition of Done (Phase 2)

- [ ] Gate A: `php -l` clean on 7.4 + 8.3 (all plugin files)
- [ ] Gate B1: all 9 legitimate flows pass (incl. v1.1.9 fallback)
- [ ] Gate B2: all 9 hardening cases rejected/handled cleanly
- [ ] Gate B3: zero orphaned temp/upload artifacts after every attempt
- [ ] Gate B4: existing-zones import non-fatal; importers instantiate cleanly
- [ ] Gate C: zero TODO/FIXME
- [ ] Both nonce AND capability enforced on both handlers
- [ ] Option entries use `option_name`/`option_value` end-to-end; legacy import still works
- [ ] `EmailsOptionsExporter` duplicate removed
- [ ] Changes documented in inline comments (field-naming convention, fallback, traversal scan, cleanup, intentional zone-import design)
