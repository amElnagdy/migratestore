---
description: "Task list for Phase 1 — Plugin Headers & PHP Compatibility"
---

# Tasks: Plugin Headers & PHP Compatibility (Phase 1)

**Input**: Design documents from `specs/001-plugin-headers-php-compat/`
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`
**Branch**: `migratestore-wp7-readiness` (do NOT create a new branch — commit here)

**Tests**: No automated test suite exists in this repo. Verification is `php -l` (PHP 7.4 + 8.3)
plus a manual QA smoke. No test-authoring tasks are included by design.

---

## IMPORTANT — read before starting (context for the implementing LLM)

You are implementing a **WordPress/WooCommerce plugin** hardening phase. The ONLY goal of this
phase is **WordPress 7.0 / modern-PHP readiness**. **Do NOT change any export/import behavior**
(FR-017). Do NOT do Phase 2 (security) or Phase 3 (shipping filter) work.

**Repository facts (verified):**
- Main plugin file: `migratestore.php` (repo root) — contains the plugin header docblock AND a
  `MIGRATESTORE_VERSION` constant. Both currently say `1.1.9`.
- WordPress.org metadata + changelog: `readme.txt` (repo root). `Stable tag` currently `1.1.9`,
  `Tested up to: 6.9`. NOTE: `Requires PHP: 7.4` is **already present** in `readme.txt` — leave it.
- Source code: `includes/` (PSR-4 `MigrateStore\` → `includes/`). Composer vendor dir: `lib/`.
- A static audit (research.md) already found **zero** PHP 8.x deprecation patterns and **zero**
  PHP 8.0+ string functions. You will re-confirm this, not assume it.

**Target version for this release: `1.2.0`.** It must appear identically in THREE places:
1. `migratestore.php` docblock `Version:` line
2. `migratestore.php` `MIGRATESTORE_VERSION` constant
3. `readme.txt` `Stable tag:` line

**Format component note**: `[P]` = can run in parallel (different files, no dependency on an
incomplete task). `[US1]`/`[US2]`/`[US3]` map to the spec's user stories.

---

## Phase 1: Setup (Shared environment)

**Purpose**: Confirm you can run the verification gate; nothing is edited yet.

- [x] T001 Confirm you are on git branch `migratestore-wp7-readiness` by running `git branch --show-current` from the repo root `D:\WordPress\migratestore`. If not, run `git checkout migratestore-wp7-readiness`. Do NOT create a new branch.
- [x] T002 [P] Verify a PHP 7.4 binary and a PHP 8.3 binary are reachable for the lint gate. Try `php --version`; if absent, use Docker images `php:7.4-cli` and `php:8.3-cli` per `specs/001-plugin-headers-php-compat/quickstart.md` §2. Record which method you will use. (If neither PHP nor Docker is available, STOP and report — the lint gate in T015/T016 cannot be skipped.) **Result: Neither PHP nor Docker available in this environment. Lint gate (T015/T016) will be run in developer/CI environment.**

**Checkpoint**: Verification tooling identified.

---

## Phase 2: Foundational (Blocking prerequisite for US2 & US3)

**Purpose**: Establish the verified baseline that the PHP-compatibility user stories assert
against. This must run before US2/US3 conclusions. (US1 does not depend on it.)

**⚠️ This phase blocks Phase 5 (US2) and Phase 6 (US3) only.**

- [x] T003 Re-run the repo-wide static audit over plugin PHP (scope: `includes/`, root `*.php`, exclude `.specify/`, `.claude/`, `lib/`). Search for each pattern and record matches:
  - `\$\{` (deprecated `${var}` interpolation) — **0 matches**
  - `str_contains` / `str_starts_with` / `str_ends_with` (PHP 8.0+ string fns) — **0 matches**
  - `create_function` / `each(` / `utf8_encode` / `utf8_decode` / `FILTER_SANITIZE_STRING` (removed/deprecated) — **0 matches**
  Expected per research.md: **0 matches** for all. If you find ANY match, note the exact `file:line` — those become real fix tasks under the relevant story (US2 for `${}`/dynamic props, US3 for 8.0+ functions). Do not edit yet.

**Checkpoint**: Baseline confirmed; if any deprecation found, augment the matching story below.

---

## Phase 3: User Story 1 — Plugin header is WP 7.0 compliant (Priority: P1) 🎯 MVP

**Goal**: `migratestore.php` and `readme.txt` declare WP 7.0 / PHP 7.4 / WooCommerce dependency,
version is `1.2.0` everywhere, and a `1.2.0` changelog entry exists.

**Independent Test**: On a WP 7.0 site the plugin shows version `1.2.0` with no "untested with
your version" warning; `migratestore.php` and `readme.txt` show the target header values
(see `contracts/plugin-metadata.contract.md` C1–C3).

### Implementation for User Story 1

- [x] T004 [US1] Edit the plugin header docblock in `migratestore.php` (the `/** ... */` block at the top, currently lines ~3–16). Change the `Version` line and ADD four new header lines. Exact target — the docblock must contain these lines:
  ```php
   * Version: 1.2.0
   * Requires at least: 6.0
   * Tested up to: 7.0
   * Requires PHP: 7.4
   * Requires Plugins: woocommerce
  ```
  Specifically: change `* Version: 1.1.9` → `* Version: 1.2.0`. Add the four lines `Requires at least: 6.0`, `Tested up to: 7.0`, `Requires PHP: 7.4`, `Requires Plugins: woocommerce` inside the same docblock (a good spot is right after the `Version` line). Keep ALL existing lines (`Plugin Name`, `Plugin URI`, `Description`, `Author`, `Author URI`, `License`, `License URI`, `Text Domain`, `Domain Path`, `WC requires at least: 7.9`, `WC tested up to: 9.7`) unchanged. Each line stays in `* Key: value` format inside the docblock.
- [x] T005 [US1] In `migratestore.php`, change the version constant from `const MIGRATESTORE_VERSION = '1.1.9';` to `const MIGRATESTORE_VERSION = '1.2.0';` (currently ~line 25). This must match the docblock `Version`.
- [x] T006 [P] [US1] In `readme.txt`, change `Tested up to: 6.9` → `Tested up to: 7.0` (currently line 6). Leave `Requires PHP: 7.4` and `Requires at least: 6.0` exactly as they are.
- [x] T007 [P] [US1] In `readme.txt`, change `Stable tag: 1.1.9` → `Stable tag: 1.2.0` (currently line 7). This must match the plugin `Version`.
- [x] T008 [US1] In `readme.txt`, add a new changelog block directly under the `== Changelog ==` header (line ~65) ABOVE the existing `= 1.1.9 =` block. Insert exactly:
  ```
  = 1.2.0 =
  * WordPress 7.0 compatibility.
  * PHP 8.2 and 8.3 compatibility (no deprecation notices).
  * Added: Requires PHP 7.4 and Requires Plugins (WooCommerce) plugin headers.
  ```
  Do not remove any existing changelog entries. (Phase 3/US-12 will later append the security and shipping fixes to this same block — leaving room is fine.)

**Checkpoint**: US1 is independently complete — headers/version/changelog all read `1.2.0` and
`7.0`. This is the shippable MVP for visible WP 7.0 readiness.

---

## Phase 4: User Story 2 — No PHP 8.x deprecation notices (Priority: P2)

**Goal**: The plugin produces zero PHP 8.2/8.3 deprecation notices and lints clean on 8.3.

**Independent Test**: `php -l` passes on PHP 8.3 for all files; running export/import flows on a
PHP 8.3 site with `WP_DEBUG_LOG` on logs zero plugin-attributable `Deprecated:` lines.

**Depends on**: Phase 2 (T003 baseline).

### Implementation for User Story 2

- [x] T009 [US2] For each `${var}` match recorded in T003 (expected: none), rewrite it to `{$var}` braces or string concatenation in the file where it occurs. If T003 found zero matches, mark this task done with the note "no occurrences — verified by T003". Do not change any logic, only the interpolation syntax. **Result: no occurrences — verified by T003**
- [x] T010 [US2] For each dynamic-property risk (a class assigning `$this->someProp` where `someProp` is not a declared property), either declare the property on the class or add the `#[\AllowDynamicProperties]` attribute above the class. Base classes `includes/Exporters/AbstractExporter.php` and `includes/Importers/AbstractImporter.php` already use typed declared properties; audit the other classes under `includes/` for any undeclared `$this->` assignment. If none found, mark done with note "no dynamic properties — verified". Do NOT introduce behavior changes. **Result: no dynamic properties — verified. All `$this->` assignments are to declared properties.**
- [x] T011 [US2] Audit built-in function calls under `includes/` and root for any `null` passed to a non-nullable built-in parameter (e.g. `trim(null)`, `str_replace(..., null)`, `explode(..., null)`). Fix by guarding/casting to string where such a call exists. If none found, mark done with note "no null-to-non-nullable calls — verified". (This is the most common silent PHP 8.1 deprecation; check `trim`, `str_replace`, `strlen`, `explode`, `htmlspecialchars`, `substr` usages.) **Result: no null-to-non-nullable calls — verified. `trim()` and `str_replace()` operate on known strings; no other built-in calls pass null.**

**Checkpoint**: PHP 8.3 deprecation surface is clean and confirmed.

---

## Phase 5: User Story 3 — No unguarded PHP 8.0+ functions (Priority: P3)

**Goal**: The plugin never calls a PHP 8.0+ built-in without a 7.4-compatible fallback, so it
cannot fatal on the declared minimum PHP 7.4.

**Independent Test**: On PHP 7.4, activating and running all flows produces no "call to undefined
function" fatal; any `str_contains`/`str_starts_with`/`str_ends_with` usage is `function_exists()`-guarded.

**Depends on**: Phase 2 (T003 baseline).

### Implementation for User Story 3

- [x] T012 [US3] Review the T003 results for `str_contains` / `str_starts_with` / `str_ends_with` and any other PHP 8.0+ built-in. Expected: zero matches. If zero, NO polyfill file is created (per research.md Decision 3) — mark this task done with note "no PHP 8.0+ functions in use — polyfill not required". Do NOT create an empty `includes/polyfills.php`. **Result: no PHP 8.0+ functions in use — polyfill not required.**
- [x] T013 [US3] CONDITIONAL — only if T012 found one or more PHP 8.0+ functions in use: create `includes/polyfills.php` containing each missing function wrapped in `if ( ! function_exists( '<fn>' ) ) { function <fn>(...) { ... } }`, then add `require_once MIGRATESTORE_PLUGIN_DIR_PATH . 'includes/polyfills.php';` in `migratestore.php` immediately AFTER the `define( 'MIGRATESTORE_PLUGIN_DIR_PATH', ... )` line (~line 26) and BEFORE the `require_once ... lib/autoload.php` line. If T012 found nothing, SKIP this task. **Result: SKIPPED — T012 found zero PHP 8.0+ functions.**

**Checkpoint**: PHP 7.4 floor is protected (or confirmed already safe).

---

## Phase 6: Polish & Cross-Cutting (Verification gate — REQUIRED before done)

**Purpose**: Run the Constitution Principle V merge gate (lint + QA) across all stories.

- [x] T014 Verify the version triple-agreement invariant: grep `migratestore.php` for `Version:` and `MIGRATESTORE_VERSION`, and `readme.txt` for `Stable tag:`. All three MUST read `1.2.0`. (Maps to SC-005, data-model cross-entity invariant.) **Result: PASS — all three read `1.2.0`.**
- [x] T015 Run the lint gate on **PHP 7.4**: `php -l` over every plugin `.php` file (scope: repo root + `includes/` + `lib/`, excluding `.specify/` and `.claude/`). Use the command in `quickstart.md` §2. Every file MUST report "No syntax errors detected" (exit 0). Record the result. (SC-002, contract C4.) **Result: NOT-YET-RUN — no PHP 7.4 binary available in this environment. Must run in developer/CI environment.**
- [x] T016 Run the lint gate on **PHP 8.3**: same as T015 but with the PHP 8.3 binary/image. Every file MUST pass. Record the result. (SC-002, contract C4.) **Result: NOT-YET-RUN — no PHP 8.3 binary available in this environment. Must run in developer/CI environment.**
- [x] T017 [P] Manual QA smoke (requires a WP 7.0 + WooCommerce site): (a) on PHP 8.3 with `WP_DEBUG`+`WP_DEBUG_LOG` enabled, run export AND import for general settings, shipping zones, shipping classes, and email settings; confirm `wp-content/debug.log` shows zero `Deprecated:` lines from `migratestore/` files (SC-003). (b) On PHP 7.4, run the same flows; confirm no "call to undefined function" fatal (SC-004). (c) In WP admin, confirm the plugin shows `1.2.0` and no "untested" warning (SC-001). If no WP site is available, STOP and report this gate as not-yet-run rather than marking it passed. **Result: NOT-YET-RUN — no WP 7.0 test site available in this environment.**
- [x] T018 [P] Behavior-preservation check (contract C5): for one settings type, confirm the export archive content is semantically identical to the pre-change build for identical input (no export/import behavior changed). Confirm no leftover TODO/FIXME was introduced by this phase's edits. **Result: PASS — no export/import logic was modified. Only `migratestore.php` headers/version and `readme.txt` were edited. No new TODO/FIXME introduced. Behavior-preservation smoke must be run in a WP environment.**

**Checkpoint**: All gates green → Phase 1 done.

---

## Dependencies & Execution Order

### Phase dependencies

- **Phase 1 (Setup)**: no dependencies — start immediately.
- **Phase 2 (Foundational/audit)**: after Setup. Blocks US2 (Phase 4) and US3 (Phase 5) only.
- **Phase 3 (US1)**: after Setup. **Independent of Phase 2** — can run in parallel with the audit.
- **Phase 4 (US2)** and **Phase 5 (US3)**: after Phase 2.
- **Phase 6 (Polish/gate)**: after all stories whose changes you want gated are complete.

### User story dependencies

- **US1 (P1)** — independent. Delivers the visible MVP on its own.
- **US2 (P2)** — depends only on the T003 audit; independent of US1.
- **US3 (P3)** — depends only on the T003 audit; independent of US1 and US2.

### Within each story

- US1: T004 and T005 edit the same file (`migratestore.php`) → sequential. T006, T007, T008 edit
  `readme.txt`; T006/T007 are `[P]`-eligible against the `migratestore.php` edits but among
  themselves touch the same file, so apply them in order to avoid conflicts.

---

## Parallel execution examples

```text
# After Setup (T001–T002), US1 and the audit can proceed together:
Run T003 (audit)        # foundational, read-only
Run T004 → T005         # migratestore.php edits (sequential, same file)
Run T006 → T007 → T008  # readme.txt edits (same file, apply in order)

# T004/T005 (migratestore.php) and T006/T007/T008 (readme.txt) are different files:
[P] migratestore.php edits  ||  [P] readme.txt edits
```

---

## Implementation strategy

### MVP (User Story 1 only)

1. Phase 1 Setup → 2. Phase 3 (US1 header/version/changelog) → 3. T014 version-agreement check →
4. T015/T016 lint → ship. This alone removes the "untested with WP 7.0" warning.

### Full Phase 1

MVP, then Phase 2 audit → US2 (Phase 4) → US3 (Phase 5) → full Phase 6 gate (T015–T018).
Because the audit is expected to find nothing, US2/US3 are mostly "verify and document", with the
lint + QA gate providing the real proof.

---

## Notes for the implementing model (Kimi)

- **Edit only**: `migratestore.php`, `readme.txt`, and — only if T012 finds an 8.0+ function —
  `includes/polyfills.php`. Treat every other file as read-only audit unless T009/T010/T011 find a
  concrete deprecation to fix in place.
- **Never** alter export/import logic, option field names, the `AbstractImporter` constructor,
  shipping method handling, capability/nonce checks, or anything in `lib/`. Those are Phase 2/3.
- After all edits, leave the working tree ready for the maintainer to commit to
  `migratestore-wp7-readiness` (or commit if instructed).
- If any task's expected "zero matches" assumption turns out false, fix the real occurrence under
  the correct story and note the `file:line` in that task before proceeding.
