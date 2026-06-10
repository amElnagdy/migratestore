# Phase 1 Data Model: Plugin Headers & PHP Compatibility

**Feature**: 001-plugin-headers-php-compat | **Date**: 2026-06-10

This feature has no application data entities (no DB schema, no new options). The "entities" here
are the **metadata artifacts** the feature mutates and the invariants they must satisfy. They are
modeled so the tasks/implementation phases have an unambiguous target state.

---

## Entity: Main Plugin Header (`migratestore.php` docblock + version constant)

The metadata block WordPress parses for the admin Plugins screen and dependency gating.

| Field | Current value | Target value | Rule |
|-------|---------------|--------------|------|
| `Version` (docblock) | `1.1.9` | `1.2.0` | MUST equal constant + readme `Stable tag` (FR-004, FR-008) |
| `MIGRATESTORE_VERSION` (constant) | `1.1.9` | `1.2.0` | MUST equal docblock `Version` (FR-008) |
| `Requires at least` | *(absent)* | `6.0` | SHOULD be present (matches readme; min unchanged) |
| `Tested up to` (WP) | *(absent)* | `7.0` | MUST be present and `= 7.0` (FR-001) |
| `Requires PHP` | *(absent)* | `7.4` | MUST be present and `= 7.4` (FR-002) |
| `Requires Plugins` | *(absent)* | `woocommerce` | MUST be present, canonical slug (FR-003) |
| `WC requires at least` | `7.9` | `7.9` | unchanged |
| `WC tested up to` | `9.7` | `9.7` (or current 9.x) | unchanged unless retested |

**Validation**: After edit, the docblock must remain a valid WordPress plugin header (one
`Key: value` per line, within the opening `/** ... */` block before any code).

---

## Entity: `readme.txt` (WordPress.org metadata + changelog)

| Field | Current value | Target value | Rule |
|-------|---------------|--------------|------|
| `Requires PHP` | `7.4` | `7.4` | already correct; leave unchanged |
| `Requires at least` | `6.0` | `6.0` | unchanged |
| `Tested up to` | `6.9` | `7.0` | MUST be `7.0` (FR-005) |
| `Stable tag` | `1.1.9` | `1.2.0` | MUST equal plugin `Version` (FR-006, FR-008) |
| Changelog top entry | `= 1.1.9 =` | `= 1.2.0 =` added above it | MUST exist, describe Phase 1 changes (FR-007) |

**Changelog `= 1.2.0 =` content (Phase 1 scope)** — at minimum:
- WordPress 7.0 compatibility.
- PHP 8.2 / 8.3 compatibility (no deprecation notices).
- Declared `Requires PHP: 7.4` and `Requires Plugins: woocommerce`.

> Note: Phase 3 (US-12) finalizes the consolidated `1.2.0` changelog with the security and
> shipping fixes. Phase 1 contributes accurate entries for its own changes; later phases append.

---

## Entity: Polyfill File (`includes/polyfills.php`) — *conditional*

| Property | Value |
|----------|-------|
| Exists? | **No** in Phase 1 (audit found no PHP 8.0+ function usage) |
| Created when | Only if a PHP 8.0+ built-in (e.g. `str_contains`) is found in use |
| Structure (if created) | Each definition wrapped in `if ( ! function_exists( '<fn>' ) ) { ... }` |
| Load point (if created) | `require_once` early in `migratestore.php`, before dependent code |

**State transition**: `absent` → (only on discovery of an unguarded 8.0+ call) → `created &
loaded & guarded`. No transition expected in Phase 1 per the audit.

---

## Cross-entity invariant

**Version triple-agreement**: `migratestore.php` docblock `Version` == `MIGRATESTORE_VERSION`
constant == `readme.txt` `Stable tag` == `1.2.0`. This is the single most regression-prone
invariant and is asserted by SC-005 and the quickstart verification.
