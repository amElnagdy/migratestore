# Contract: Plugin Metadata (WordPress 7.0 readiness)

**Feature**: 001-plugin-headers-php-compat | **Type**: WordPress plugin metadata contract

A WordPress plugin's "external interface" for compatibility is the set of header fields WordPress
core and WordPress.org parse. This contract defines the exact fields and values Phase 1 must
satisfy. Each row is independently verifiable (by reading the file or by the admin UI).

## C1 — `migratestore.php` plugin header docblock

The opening docblock MUST contain these lines (order not significant; format `Key: value`):

```
 * Version: 1.2.0
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
```

Existing lines retained unchanged: `Plugin Name`, `Plugin URI`, `Description`, `Author`,
`Author URI`, `License`, `License URI`, `Text Domain`, `Domain Path`, `WC requires at least`,
`WC tested up to`.

**Contract test**: Reading `migratestore.php` shows all five lines above with exactly these
values. The `MIGRATESTORE_VERSION` constant equals `1.2.0`.

## C2 — `readme.txt` header block

```
Requires PHP: 7.4
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.2.0
```

**Contract test**: Reading `readme.txt` shows these four lines with exactly these values.

## C3 — `readme.txt` changelog

A `== Changelog ==` section MUST contain a `= 1.2.0 =` block (above `= 1.1.9 =`) describing the
Phase 1 changes (WP 7.0 + PHP 8.2/8.3 compatibility, new headers). No placeholder/draft text.

**Contract test**: The string `= 1.2.0 =` appears in the changelog with ≥1 descriptive bullet.

## C4 — PHP compatibility (negative contract)

Across all plugin `.php` files:

- `php -l` returns exit 0 on **PHP 7.4** and **PHP 8.3** for every file.
- Exercising export/import flows on PHP 8.3 with deprecation logging emits **zero**
  plugin-attributable deprecation notices.
- Exercising export/import flows on PHP 7.4 emits **zero** "call to undefined function" fatals.

**Contract test**: see `quickstart.md` runbook (lint loop + manual smoke).

## C5 — Behavior preservation (negative contract)

No export or import behavior changes in Phase 1. The JSON/ZIP output of every exporter and the
effect of every importer are byte-for-byte/semantically identical to v1.1.9 for the same inputs.

**Contract test**: Export a settings type before and after the change on identical fixture data;
the produced archive contents are equivalent (ignoring only the embedded version string if any).
