# Feature Specification: Plugin Headers & PHP Compatibility (Phase 1)

**Feature Branch**: `migratestore-wp7-readiness`

**Created**: 2026-06-10

**Status**: Draft

**Input**: User description: "Read the migratestore-plan.md file then create the specification for phase 1 Plugin Headers & PHP Compatibility ONLY"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Plugin header is WordPress 7.0 compliant (Priority: P1)

A site administrator running WordPress 7.0 installs or updates Migrate Store. The plugin
declares correct compatibility metadata so that the WordPress.org directory and the WordPress
admin show it as compatible, and the administrator is not warned that the plugin is untested
against their WordPress version.

**Why this priority**: Header compliance is the single most visible signal of WP 7.0 readiness.
Without it, every user on WP 7.0 sees an "untested" warning regardless of how clean the code is,
and the WordPress.org listing flags the plugin. This is the minimum shippable increment.

**Independent Test**: Install the built plugin on a WordPress 7.0 site, open
Plugins → Installed Plugins, and confirm the entry shows the new version and no
"untested with your version" warning; open `readme.txt` and the main plugin file and confirm the
declared headers. Deliverable value: the plugin presents as WP 7.0 compatible.

**Acceptance Scenarios**:

1. **Given** the plugin source, **When** the main plugin file header is inspected, **Then** it
   contains `Tested up to: 7.0`, `Requires PHP: 7.4`, `Requires Plugins: woocommerce`, and the
   version is `1.2.0`.
2. **Given** the plugin source, **When** `readme.txt` is inspected, **Then** `Tested up to: 7.0`
   is declared, the version/stable tag reflects `1.2.0`, and a `1.2.0` changelog entry is present.
3. **Given** a WordPress 7.0 site, **When** the administrator views the installed plugin, **Then**
   no "untested with your version of WordPress" warning is shown for this plugin.

---

### User Story 2 - Plugin runs without PHP 8.x deprecation notices (Priority: P2)

A developer or host running PHP 8.2 or 8.3 activates and uses Migrate Store. The plugin produces
zero PHP deprecation notices in the error log, keeping logs clean and the plugin forward
compatible with the PHP versions WordPress 7.0 recommends.

**Why this priority**: Deprecation notices on PHP 8.2/8.3 erode trust and clutter logs, and some
deprecated patterns become fatal in later PHP. This must be fixed for forward compatibility, but
it depends on the same release that ships the headers (US-1), so it follows P1.

**Independent Test**: Run the plugin's full export/import flows on a PHP 8.3 environment with
deprecation logging enabled and confirm the log records zero deprecation notices originating from
the plugin; additionally run `php -l` across all plugin PHP files on PHP 7.4 and PHP 8.3.

**Acceptance Scenarios**:

1. **Given** the plugin source, **When** it is searched for `${var}`-style string interpolation,
   **Then** no occurrences remain (only `{$var}` or concatenation are used).
2. **Given** the plugin source, **When** classes assign object properties, **Then** no property is
   created dynamically unless its class is annotated with `#[\AllowDynamicProperties]`.
3. **Given** the plugin source, **When** built-in functions are called, **Then** no `null` is
   passed to a non-nullable built-in parameter.
4. **Given** all plugin `.php` files, **When** `php -l` is run on PHP 7.4 and on PHP 8.3, **Then**
   every file reports no syntax errors.
5. **Given** a PHP 8.3 runtime with deprecation logging enabled, **When** the plugin's export and
   import flows are exercised, **Then** no deprecation notice attributable to the plugin is logged.

---

### User Story 3 - No PHP 7.4-only-incompatible functions used without guards (Priority: P3)

A site running the declared minimum PHP 7.4 uses Migrate Store. The plugin does not call PHP 8.0+
string functions without a 7.4-compatible fallback, so it never produces a fatal "undefined
function" error on the supported minimum.

**Why this priority**: This protects the declared floor (PHP 7.4). It is lower priority than US-2
only because PHP 8.0+ function usage may be absent already; if present, it is a hard fatal and
must be guarded. Verifying and guarding closes the compatibility matrix.

**Independent Test**: On a clean PHP 7.4 environment, activate the plugin and exercise every
export/import flow; confirm no "call to undefined function" fatal occurs. Statically confirm any
`str_contains` / `str_starts_with` / `str_ends_with` usage is either absent or polyfilled.

**Acceptance Scenarios**:

1. **Given** the plugin source, **When** it is searched for `str_contains()`,
   `str_starts_with()`, and `str_ends_with()`, **Then** any occurrence is guarded by a
   `function_exists()` polyfill (or no occurrences exist).
2. **Given** the plugin source, **When** it is audited for other PHP 8.0+ built-in functions,
   **Then** none are used without a 7.4-compatible guard or polyfill.
3. **Given** a polyfill is required, **When** the plugin loads, **Then** the polyfills live in a
   single dedicated file (`includes/polyfills.php`) that is loaded early, before any code that
   depends on those functions.
4. **Given** a PHP 7.4 runtime, **When** the plugin's export and import flows are exercised,
   **Then** no "call to undefined function" fatal error occurs.

---

### Edge Cases

- **WooCommerce inactive**: With `Requires Plugins: woocommerce` declared, WordPress 7.0 governs
  activation gating. The header change MUST NOT itself introduce a fatal when WooCommerce is
  inactive on WordPress versions that do not enforce the header.
- **Version-string locations drift**: If the plugin version appears in more than the two known
  locations (main file header and `readme.txt`), every occurrence that represents the plugin
  release version MUST be updated to `1.2.0` so the admin and directory agree.
- **Polyfill double-definition**: If another active plugin already defines the same polyfilled
  function, the plugin's `function_exists()` guard MUST prevent a redeclaration fatal.
- **Mixed PHP minor versions**: The same source MUST lint clean on both 7.4 and 8.3 without
  version-specific branches that themselves break one of the two.

## Requirements *(mandatory)*

### Functional Requirements

**Header & version declarations (US-1)**

- **FR-001**: The main plugin file header MUST declare `Tested up to: 7.0`.
- **FR-002**: The main plugin file header MUST declare `Requires PHP: 7.4`.
- **FR-003**: The main plugin file header MUST declare `Requires Plugins: woocommerce`.
- **FR-004**: The plugin version MUST be `1.2.0` in the main plugin file header.
- **FR-005**: `readme.txt` MUST declare `Tested up to: 7.0`.
- **FR-006**: `readme.txt` MUST reflect version `1.2.0` (including the `Stable tag`).
- **FR-007**: `readme.txt` MUST include a changelog entry for `1.2.0` describing the Phase 1
  changes (WP 7.0 compatibility, PHP 8.2/8.3 compatibility, header additions).
- **FR-008**: Every location in the source that states the plugin release version MUST agree on
  `1.2.0` (no stale `1.1.9` references representing the release version).

**PHP 8.x deprecation hygiene (US-2)**

- **FR-009**: The source MUST contain no `${var}` string interpolation; only `{$var}` or
  concatenation are permitted.
- **FR-010**: No class MUST create object properties dynamically unless that class is annotated
  with `#[\AllowDynamicProperties]`.
- **FR-011**: No call MUST pass `null` to a non-nullable built-in function parameter.
- **FR-012**: Any overriding method MUST have a signature compatible with the parent it overrides
  (including named-argument compatibility).
- **FR-013**: Every plugin `.php` file MUST pass `php -l` with zero errors on PHP 7.4 and PHP 8.3.

**PHP 7.4 floor protection (US-3)**

- **FR-014**: Any use of `str_contains()`, `str_starts_with()`, or `str_ends_with()` MUST be
  guarded by a `function_exists()` polyfill, or MUST not be present.
- **FR-015**: No other PHP 8.0+ built-in function MUST be used without a 7.4-compatible guard or
  polyfill.
- **FR-016**: If any polyfill is required, all polyfills MUST reside in a single dedicated file
  `includes/polyfills.php`, loaded before any dependent code, and each polyfill MUST be wrapped in
  a `function_exists()` guard.

**Scope guard**

- **FR-017**: This phase MUST NOT change functional behavior of export/import flows; only headers,
  version strings, and PHP-compatibility-equivalent code edits are permitted.

### Key Entities

- **Main plugin file header**: The plugin-bootstrap PHP file's metadata block — carries
  `Version`, `Tested up to`, `Requires PHP`, `Requires Plugins`.
- **`readme.txt`**: The WordPress.org metadata + changelog file — carries `Stable tag`,
  `Tested up to`, and the versioned changelog.
- **Polyfill file (`includes/polyfills.php`)**: A single, early-loaded file holding
  `function_exists()`-guarded definitions for PHP 8.0+ string helpers, created only if needed.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a WordPress 7.0 site, the installed plugin shows version `1.2.0` and displays
  zero "untested with your version" warnings.
- **SC-002**: Running `php -l` across 100% of the plugin's `.php` files returns zero errors on
  both PHP 7.4 and PHP 8.3.
- **SC-003**: Exercising all export and import flows on PHP 8.3 with deprecation logging enabled
  produces zero plugin-attributable deprecation notices.
- **SC-004**: Exercising all export and import flows on PHP 7.4 produces zero "call to undefined
  function" fatal errors.
- **SC-005**: 100% of plugin-release-version references read `1.2.0`; `readme.txt` and the main
  plugin header agree, and a complete `1.2.0` changelog entry is present.
- **SC-006**: No `${var}` interpolation, no unguarded dynamic property creation, and no unguarded
  PHP 8.0+ function calls remain in the source (verifiable by static search).

## Assumptions

- The "main plugin file" is the plugin's primary bootstrap PHP file at the package root
  (e.g., `migratestore.php`); the planning document names this file directly.
- The chosen `1.2.0` version and the WP 7.0 / PHP 7.4 targets are fixed by the project
  constitution and the plan, and are not open for re-decision in this phase.
- "Zero deprecation notices attributable to the plugin" excludes notices originating from
  WordPress core or third-party plugins outside Migrate Store's source.
- WooCommerce is the assumed companion plugin; `Requires Plugins: woocommerce` uses the canonical
  WordPress.org slug `woocommerce`.
- If a polyfill is unnecessary (no PHP 8.0+ functions are used), `includes/polyfills.php` is not
  created — the requirement is conditional on need.

## Out of Scope *(this phase)*

- Security hardening (capability checks, ZIP validation, temp cleanup) — Phase 2.
- Data integrity / constructor signature / field-naming fixes — Phase 2.
- Shipping method whitelist removal and the filter hook — Phase 3.
- Full release QA matrix and final changelog assembly across all phases — Phase 3.
- Any new features (preview/diff, rollback, bulk export, WP-CLI, import logs).
