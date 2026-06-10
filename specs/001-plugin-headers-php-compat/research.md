# Phase 0 Research: Plugin Headers & PHP Compatibility

**Feature**: 001-plugin-headers-php-compat | **Date**: 2026-06-10

This phase had **no open `NEEDS CLARIFICATION` markers** in the spec. Research here records the
static codebase audit that grounds the plan and the best-practice decisions for the header and
polyfill work.

---

## Audit findings (current state of the source)

A repo-wide static search was run over all plugin PHP (`includes/` + root + admin), excluding the
`lib/` Composer vendor and tooling dirs (`.specify/`, `.claude/`).

| Check | Pattern searched | Result |
|-------|------------------|--------|
| `${var}` string interpolation (deprecated PHP 8.2) | `\$\{` | **0 matches** |
| PHP 8.0+ string functions | `str_contains`/`str_starts_with`/`str_ends_with` | **0 matches** |
| Removed/deprecated functions | `create_function`, `each(`, `utf8_encode/decode`, `FILTER_SANITIZE_STRING` | **0 matches** |
| Dynamic-property annotation | `#[\AllowDynamicProperties]` | 0 (none needed — see below) |

Spot review of base classes (`AbstractExporter`, `AbstractImporter`) confirms typed properties
(`protected AbstractExporter $exporter;`) and constructor-initialized state — no ad-hoc dynamic
property assignment that would trigger the PHP 8.2 dynamic-property deprecation.

**Current header state** (`migratestore.php`): `Version: 1.1.9`, `WC requires at least: 7.9`,
`WC tested up to: 9.7`. Missing: `Requires PHP`, `Requires Plugins`, `Tested up to` (WP). The
version constant `MIGRATESTORE_VERSION = '1.1.9'` is a third version-string location.

**Current `readme.txt` state**: `Requires PHP: 7.4` ✅ already present, `Requires at least: 6.0`,
`Tested up to: 6.9`, `Stable tag: 1.1.9`, changelog top entry `= 1.1.9 =`.

---

## Decision 1 — Header edits in `migratestore.php`

**Decision**: In the header docblock add `Requires at least: 6.0`, `Tested up to: 7.0`,
`Requires PHP: 7.4`, `Requires Plugins: woocommerce`; change `Version: 1.1.9` → `1.2.0`; and
update the `MIGRATESTORE_VERSION` constant to `1.2.0`.

**Rationale**: WordPress reads the docblock header (not the constant) for admin compatibility
display and for `Requires Plugins` dependency gating (WP 6.5+). All three version-string
locations (docblock `Version`, constant, `readme.txt`) must agree per FR-008.

**Alternatives considered**: Leaving `WC requires at least` as the only WooCommerce signal —
rejected: WP 7.0 / 6.5+ uses the canonical `Requires Plugins: woocommerce` slug for dependency
management, which the plugin currently lacks.

## Decision 2 — `readme.txt` edits

**Decision**: Set `Tested up to: 7.0`, `Stable tag: 1.2.0`; add a `= 1.2.0 =` changelog block at
the top of the changelog covering WP 7.0 compatibility, PHP 8.2/8.3 compatibility, and the new
headers. Leave `Requires PHP: 7.4` (already correct) and `Requires at least: 6.0` unchanged.

**Rationale**: `Stable tag` drives which version WordPress.org serves; it must match the plugin
version. The changelog is the user-facing record required by Constitution §7 / FR-007.

**Alternatives considered**: Deferring the full changelog to Phase 3 — rejected for Phase 1's own
entry, but note: Phase 3 (US-12) assembles the **complete** consolidated `1.2.0` changelog across
all phases. Phase 1 writes an accurate entry for its own changes that Phase 3 will finalize.

## Decision 3 — Polyfills (`includes/polyfills.php`)

**Decision**: **Do not create** `includes/polyfills.php`. The audit found zero uses of PHP 8.0+
string functions or other 8.0+ built-ins. The file is created only if the binary lint gate (or a
later phase) introduces such a use.

**Rationale**: FR-016 is explicitly conditional ("if any polyfill is required"). Adding an empty
or unused polyfill file would be dead code and violates scope discipline (Principle VI).

**Alternatives considered**: Pre-emptively adding a polyfill file for future-proofing — rejected:
YAGNI; no current call sites; would require an early `require_once` in the bootstrap for no
benefit.

## Decision 4 — Verification strategy (the merge gate)

**Decision**: The lint gate is `php -l` over every plugin `.php` file on **both** PHP 7.4 and PHP
8.3, run in the developer/CI environment (the authoring environment has no PHP binary). It is
paired with a manual QA smoke: run each export and import flow on PHP 8.3 with
`WP_DEBUG`/deprecation logging on (expect zero plugin-attributable deprecation notices), and on
PHP 7.4 confirm no "call to undefined function" fatal.

**Rationale**: Constitution Principle V makes lint + manual QA the hard exit criteria. Because the
phase asserts a *negative* (no deprecations), only running on a real 8.3 runtime proves it; static
search gives design-time confidence but is not the gate.

**Alternatives considered**: Relying on static search alone — rejected: cannot observe runtime
deprecation notices (e.g., implicit nullable parameters, internal-function arg deprecations).
Adding PHPCS/PHPCompatibility tooling — out of scope for Phase 1 (no tooling exists in repo);
worth proposing later but not required to ship.

---

## Open risks

- **No PHP toolchain in the authoring environment** — the lint gate must be executed by the
  developer/CI. Mitigation: `quickstart.md` documents the exact commands and a Docker fallback.
- **`lib/` vendor code** must also pass `php -l` for a clean release; if a vendored file uses a
  deprecated pattern, that is a dependency-update decision, flagged but not hand-patched.
- **Version drift** — three locations must stay in sync; the tasks phase will pin each explicitly.

**All NEEDS CLARIFICATION resolved**: none existed. Ready for Phase 1 design.
