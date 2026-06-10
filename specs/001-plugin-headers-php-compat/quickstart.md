# Quickstart / Verification Runbook: Phase 1

**Feature**: 001-plugin-headers-php-compat | **Date**: 2026-06-10

How to verify Phase 1 is done. Run after the header edits (and after any polyfill, if one was
needed). These steps map directly to the spec's Success Criteria (SC-001…SC-006) and the contract
(C1…C5).

## Prerequisites

- PHP **7.4** and PHP **8.3** binaries available (local installs, or Docker images
  `php:7.4-cli` / `php:8.3-cli`). The authoring environment has no PHP on PATH — run these in your
  dev/CI environment.
- A WordPress 7.0 test site with WooCommerce active for the admin/QA checks.

## 1. Header & version verification (US-1 → SC-001, SC-005, C1–C3)

Inspect the two metadata files and confirm the target values:

```bash
# migratestore.php must show: Version 1.2.0, Tested up to 7.0, Requires PHP 7.4, Requires Plugins woocommerce
grep -E "Version:|Tested up to:|Requires PHP:|Requires Plugins:" migratestore.php
grep "MIGRATESTORE_VERSION" migratestore.php          # must be '1.2.0'

# readme.txt must show: Tested up to 7.0, Stable tag 1.2.0, and a = 1.2.0 = changelog block
grep -E "Tested up to:|Stable tag:" readme.txt
grep "= 1.2.0 =" readme.txt
```

**Pass when**: all values match the contract; the three version strings (docblock, constant,
stable tag) all read `1.2.0`.

**Admin check (SC-001)**: On the WP 7.0 site, Plugins → Installed Plugins shows version `1.2.0`
and no "untested with your version of WordPress" warning for Migrate Store.

## 2. Lint gate (US-2/US-3 → SC-002, C4)

Run `php -l` on every plugin PHP file under **both** PHP versions. From repo root:

```bash
# PHP 7.4
find . -name "*.php" -not -path "./.specify/*" -not -path "./.claude/*" \
  -print0 | xargs -0 -n1 php7.4 -l
# PHP 8.3
find . -name "*.php" -not -path "./.specify/*" -not -path "./.claude/*" \
  -print0 | xargs -0 -n1 php8.3 -l
```

Docker fallback (run from repo root):

```bash
docker run --rm -v "$PWD":/app -w /app php:7.4-cli \
  bash -c 'find . -name "*.php" -not -path "./.specify/*" -not -path "./.claude/*" -print0 | xargs -0 -n1 php -l'
docker run --rm -v "$PWD":/app -w /app php:8.3-cli \
  bash -c 'find . -name "*.php" -not -path "./.specify/*" -not -path "./.claude/*" -print0 | xargs -0 -n1 php -l'
```

**Pass when**: every file reports `No syntax errors detected` on both versions (exit 0).

## 3. Runtime deprecation smoke on PHP 8.3 (US-2 → SC-003, C4)

On the WP 7.0 + PHP 8.3 site, set in `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Then exercise each flow and watch `wp-content/debug.log`:

- Export general / shipping zones / shipping classes / email settings (each downloads a ZIP).
- Import each of the above back.

**Pass when**: no `Deprecated:` line attributable to a `migratestore/` file appears in the log.

## 4. PHP 7.4 floor check (US-3 → SC-004)

On a PHP 7.4 environment, activate the plugin and run the same export/import flows.

**Pass when**: no `Fatal error: ... call to undefined function` occurs (confirms no unguarded
PHP 8.0+ functions). If a polyfill was added, confirm `includes/polyfills.php` loads early and
each function is `function_exists()`-guarded.

## 5. Behavior preservation (C5)

For one settings type, export on the pre-change build and the post-change build using identical
fixture data; diff the archive contents.

**Pass when**: archive contents are semantically identical (Phase 1 changes no export/import
behavior).

---

## Done criteria (all must hold)

- [ ] §1 header/version values match contract; admin shows 1.2.0, no untested warning.
- [ ] §2 `php -l` clean on 7.4 **and** 8.3 for all files.
- [ ] §3 zero plugin-attributable deprecation notices on 8.3.
- [ ] §4 no undefined-function fatal on 7.4.
- [ ] §5 export/import behavior unchanged.
