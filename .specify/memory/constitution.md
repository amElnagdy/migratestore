<!--
SYNC IMPACT REPORT
==================
Version change: (template / unversioned) → 1.0.0
Bump rationale: Initial ratification. Constitution populated from project-root
  constitution.md (Migrate Store WP 7.0 readiness charter). First concrete,
  versioned adoption replacing the unfilled template → MAJOR baseline 1.0.0.

Modified principles (template slot → ratified principle):
  [PRINCIPLE_1] → I. WordPress 7.0 & Modern PHP Compatibility
  [PRINCIPLE_2] → II. Security Is Non-Negotiable
  [PRINCIPLE_3] → III. WordPress & WooCommerce Coding Standards
  [PRINCIPLE_4] → IV. Data Integrity & Consistency
  [PRINCIPLE_5] → V. Verified Before Merge (Manual QA + Lint)
  (added)       → VI. Scope Discipline — Readiness, Not Features

Added sections:
  - Environment & Compatibility Requirements (was [SECTION_2])
  - Versioning & Release Discipline (was [SECTION_3])
  - Governance (filled)

Removed sections: none (all template slots filled or repurposed)

Templates requiring updates:
  ✅ .specify/templates/plan-template.md — Constitution Check gate is
     constitution-derived; no hardcoded rules to change.
  ✅ .specify/templates/spec-template.md — generic; no constitution coupling.
  ✅ .specify/templates/tasks-template.md — generic; no constitution coupling.
  ✅ .claude/skills/speckit-*/ command guidance — no outdated agent-specific
     references requiring change.

Follow-up TODOs: none. Ratification date set to today (first adoption).
-->

# Migrate Store Constitution

**Project:** `migratestore` — a WooCommerce plugin (1,000+ active installs) that exports and
imports WooCommerce settings between sites.

**Charter scope:** WordPress 7.0 readiness + hardening for the `1.2.0` release. The goal is to
make the plugin trustworthy, secure, and fully compatible with WordPress 7.0 and modern PHP — not
to add features.

## Core Principles

### I. WordPress 7.0 & Modern PHP Compatibility

The plugin MUST run cleanly on the supported matrix and MUST NOT rely on behavior removed or
deprecated in modern PHP.

- All code MUST pass `php -l` with zero errors on **PHP 7.4** (declared minimum) and **PHP 8.3**.
- No deprecated PHP 8.x patterns: no `${var}` string interpolation (use `{$var}` or
  concatenation); no dynamic property creation without `#[\AllowDynamicProperties]`; no passing
  `null` to non-nullable parameters.
- `str_contains()` / `str_starts_with()` / `str_ends_with()` MUST be guarded with a PHP 7.4
  polyfill or `function_exists()` check, since the declared minimum (7.4) lacks them.
- Functions MUST declare parameter and return types wherever PHP 7.4 supports them.

**Rationale:** WP 7.0 raises the platform floor; a plugin on 1,000+ live sites must not fatal on
either the declared minimum or a current PHP. Compatibility is verifiable by lint, not opinion.

### II. Security Is Non-Negotiable

Every export and import handler MUST enforce all of the following. These are gates, not
guidelines — a handler missing any one of them is non-compliant.

1. **Capability check** — `current_user_can( 'manage_woocommerce' )` before any export/import action.
2. **Nonce verification** — `check_ajax_referer()` or `wp_verify_nonce()` on every handler, in
   addition to (not instead of) the capability check.
3. **File type validation** — uploads validated as `.zip` by MIME type, not extension alone.
4. **File size validation** — a reasonable maximum upload size is enforced.
5. **ZIP path-traversal prevention** — extracted paths sanitized and confined to the temp
   directory; any entry containing `..` or an absolute path is rejected.
6. **Temp file cleanup** — all temporary files/directories removed after import on success AND
   failure, using `WP_Filesystem` where appropriate.
7. **Exact import-key matching** — importer selection uses exact key matching, never substring or
   partial matching.

Unauthorized access MUST end via `wp_die()` with a translated message, never a bare `exit`.

**Rationale:** The plugin ingests uploaded archives and writes store configuration. A single
missing capability check, traversal gap, or leaked temp file is a real exploit on a real store.

### III. WordPress & WooCommerce Coding Standards

- All user-facing strings MUST be wrapped in i18n functions with the correct text domain
  (`migratestore`); no text-domain mismatches.
- Capability checks and nonce verification are required together on every AJAX and admin action
  handler (see Principle II) — neither substitutes for the other.
- Shipping-method support MUST NOT be limited to a hardcoded whitelist; any
  `woocommerce_shipping_methods` usage MUST allow third-party methods.
- No `TODO` / `FIXME` comments left in production code.

**Rationale:** Following WordPress.org and WooCommerce conventions keeps the plugin
review-passable, translatable, and interoperable with the third-party shipping ecosystem.

### IV. Data Integrity & Consistency

- Option-field naming MUST follow a single convention (`option_name`/`option_value` **or**
  `option`/`value`) applied uniformly across all exporters and importers. Any deviation kept for
  backward compatibility with already-exported files MUST be documented in a code comment.
- The `AbstractImporter` constructor signature MUST match across the parent and every child class —
  no type mismatches.
- Duplicate entries (e.g., in `EmailsOptionsExporter.php`) MUST be removed.

**Rationale:** Export/import correctness is the plugin's entire value proposition; inconsistent
field names or mismatched signatures silently corrupt migrations.

### V. Verified Before Merge (Manual QA + Lint)

No phase is "done" and no change merges until both gates pass:

- **Lint gate:** `php -l` passes on PHP 7.4 and PHP 8.3.
- **Manual QA gate:** the full QA matrix passes — export/import of general settings, shipping
  zones, shipping classes, and email settings (each downloads/applies with a confirming admin
  notice); plus the hardening cases: non-ZIP upload rejected, oversized ZIP rejected,
  path-traversal ZIP rejected with no extraction, import as a non-`manage_woocommerce` user
  blocked (403-equivalent), and import against a site with existing shipping zones runs without a
  fatal error.

**Rationale:** This release is about trust. Behavior is proven by running the scenarios, not by
inspecting the diff.

### VI. Scope Discipline — Readiness, Not Features

The following are explicitly deferred and MUST NOT be implemented in this effort: import
preview/diff UI, rollback/undo, bulk export (single ZIP for all setting types), WP-CLI commands,
import audit logs, merge/replace mode for shipping zones, and any UI redesign.

**Rationale:** Scope creep is the primary risk to a hardening release; new surface area means new
untested attack surface and delays the compatibility fix users need.

## Environment & Compatibility Requirements

| Dimension | Requirement |
|-----------|-------------|
| WordPress minimum | 6.0 (unchanged) |
| WordPress tested up to | 7.0 |
| PHP minimum | 7.4 (declared in header, enforced in code) |
| PHP recommended/tested | 8.3 |
| WooCommerce | declare `Requires Plugins: woocommerce`; tested up to latest stable (9.x) |
| MySQL minimum | 8.0 (WP 7.0 requirement; change DB code only if queries are incompatible) |

## Versioning & Release Discipline

- Bump the plugin version to `1.2.0` in the main plugin file and `readme.txt`.
- Update `Tested up to` to `7.0` in both `readme.txt` and the plugin header.
- Add/confirm `Requires PHP: 7.4` and `Requires Plugins: woocommerce` in the plugin header.
- Add a `readme.txt` changelog entry describing each fix.

Plugin releases follow semantic versioning (MAJOR.MINOR.PATCH). This constitution is versioned
independently of the plugin (see footer).

## Governance

This constitution supersedes other process preferences for the `migratestore-wp7-readiness` effort.
All work — across every Spec Kit phase (specify, plan, tasks, implement) — is committed to the
`migratestore-wp7-readiness` branch.

- **Compliance:** Every plan's Constitution Check gate and every change MUST verify compliance with
  Principles I–VI. The security gates (Principle II) and the verification gates (Principle V) are
  hard blocks on merge.
- **Amendments:** Changes to this constitution require an explicit edit to this file with an updated
  version and Sync Impact Report, plus propagation to any dependent templates.
- **Versioning policy:** MAJOR = backward-incompatible principle removal/redefinition; MINOR = a new
  principle or materially expanded guidance; PATCH = clarifications and wording fixes.
- **Complexity justification:** Any deviation from a principle MUST be recorded in the plan's
  Complexity Tracking table with the reason and the rejected simpler alternative.

**Version**: 1.0.0 | **Ratified**: 2026-06-10 | **Last Amended**: 2026-06-10
