# Feature Specification: Shipping Method Filter & Release QA (Phase 3)

**Feature Branch**: `migratestore-wp7-readiness` (existing branch — no new branch created)

**Created**: 2026-06-11

**Status**: Draft

**Input**: User description: "Read the migratestore-plan.md file then create a specification for the Phase 3 — Shipping Method Filter & Release QA ONLY. Do not create a new branch, the changes will be committed to the current branch"

## Overview

Phase 3 is the final readiness phase before tagging Migrate Store v1.2.0 for WordPress 7.0. It removes a silent data-loss bug in the shipping zone exporter, makes shipping-method export extensible, surfaces clear feedback when methods cannot be reproduced on import, and gates the release behind a full manual QA pass and a complete, accurate changelog.

Today the shipping zone exporter only exports three hardcoded built-in method types (`flat_rate`, `free_shipping`, `local_pickup`). Any third-party or custom shipping method a store uses is dropped during export with no warning, so the store owner's configuration is silently and incompletely migrated. This phase eliminates that whitelist, replaces it with an explicit, filterable export of all registered methods, and ensures import failures for unrecognized methods are visible rather than silent.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Third-party shipping methods survive export and import (Priority: P1)

A store owner uses a third-party or custom shipping method plugin (for example a table-rate or carrier-calculated method) in one or more WooCommerce shipping zones. When they export their shipping zones from the source site and import them on the target site, they expect every method they configured to be reproduced — not silently discarded. When a method genuinely cannot be reproduced on the target (because that method's plugin is not installed there), they expect to be told exactly which methods were skipped, rather than discovering the gap later by accident.

**Why this priority**: This is the core defect Phase 3 exists to fix. It is a silent data-loss bug that directly corrupts the primary value proposition of the plugin (faithful store migration). It is independently shippable and delivers value on its own.

**Independent Test**: Configure a shipping zone with a third-party method on a source site, export shipping zones, confirm the method appears in the exported data, import on a target site that has the method's plugin installed, and confirm the method is recreated. Then import on a target site lacking that plugin and confirm the method is skipped with a clear warning naming it.

**Acceptance Scenarios**:

1. **Given** a shipping zone containing `flat_rate`, `free_shipping`, and `local_pickup` methods, **When** the owner exports shipping zones, **Then** all three methods are present in the exported data exactly as before this change.
2. **Given** a shipping zone containing a registered third-party shipping method, **When** the owner exports shipping zones, **Then** the third-party method is included in the exported data and nothing is silently dropped.
3. **Given** a developer who needs to exclude a specific method from export, **When** they hook the documented exclusion filter and return that method's ID, **Then** that method is omitted from the export while all other methods remain included.
4. **Given** no exclusion filter is registered, **When** the owner exports shipping zones, **Then** the set of excluded methods is empty and every registered method is exported.
5. **Given** exported shipping-zone data that references a method ID not registered on the target site, **When** the owner imports it, **Then** the unrecognized method is skipped, the remaining zones and methods are imported, and a dismissible warning notice lists every skipped method by its ID.
6. **Given** a shipping-zone export file produced by v1.1.9 or earlier (containing only the three built-in method types), **When** the owner imports it on the new version, **Then** it imports successfully with no breaking changes.

---

### User Story 2 - Release is fully QA-verified before tagging (Priority: P1)

The plugin author must not tag v1.2.0 until every export/import flow and every security/compatibility guarantee introduced across Phases 1–3 has been manually verified on WordPress 7.0 with the latest WooCommerce. The QA pass is the gate that prevents shipping a regression to users who upgrade.

**Why this priority**: Release QA is a hard gate on shipping. A missed regression after upgrade erodes trust and generates support load. It is independently executable as a checklist run, regardless of the order other stories are completed.

**Independent Test**: Execute the full release QA scenario matrix on a clean WordPress 7.0 + latest WooCommerce environment and record a pass/fail result for each of the 18 scenarios; the release is blocked unless all pass.

**Acceptance Scenarios**:

1. **Given** a WordPress 7.0 + latest WooCommerce test environment, **When** the QA matrix below is executed end to end, **Then** all 18 scenarios produce their expected outcome.
2. **Given** any single QA scenario fails, **When** the author reviews results, **Then** the release is blocked until the underlying defect is fixed and the scenario re-run passes.

**Release QA scenario matrix** (all must pass):

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Export general settings | Data downloaded, readable |
| 2 | Import general settings | Settings applied, success notice shown |
| 3 | Export shipping zones (flat_rate + free_shipping + local_pickup) | All three method types present in export |
| 4 | Export shipping zones with a third-party method (e.g. Table Rate) | All methods present, none silently dropped |
| 5 | Import shipping zones | Zones created, success notice |
| 6 | Import shipping zones with unknown method IDs | Warning notice lists skipped methods; remaining zones imported |
| 7 | Export shipping classes | Data downloaded |
| 8 | Import shipping classes | Classes created, success notice |
| 9 | Export email settings | Data downloaded |
| 10 | Import email settings | Settings applied, success notice |
| 11 | Upload a `.txt` file as an import | Rejected with clear admin notice |
| 12 | Upload an oversized archive | Rejected with clear admin notice |
| 13 | Upload an archive containing a path-traversal entry (e.g. `../../etc/passwd`) | Rejected, no extraction, notice shown |
| 14 | Import while signed in as a Subscriber | Blocked, no data written |
| 15 | Import on a site that already has shipping zones | Runs without fatal error |
| 16 | PHP lint on PHP 7.4 | Zero errors |
| 17 | PHP lint on PHP 8.3 | Zero errors/warnings |
| 18 | Plugin header in WordPress admin | Shows "Tested up to 7.0" and "Requires PHP: 7.4" |

---

### User Story 3 - readme.txt and changelog are complete and accurate for v1.2.0 (Priority: P2)

A WordPress.org reviewer or an end user reading the changelog needs the readme to accurately and completely describe what changed in v1.2.0 across all three phases, with no leftover draft or placeholder text, and version metadata that matches the shipped version.

**Why this priority**: Required for a clean WordPress.org release and for user trust, but it is documentation that depends on the functional work being complete. It is independently verifiable by inspecting the readme.

**Independent Test**: Inspect `readme.txt` and confirm the `1.2.0` changelog entry covers every required item, contains no placeholder text, and that the stable-tag and tested-up-to metadata match the shipped version.

**Acceptance Scenarios**:

1. **Given** the v1.2.0 release is ready, **When** a reviewer reads the `readme.txt` changelog, **Then** the `1.2.0` entry covers: WordPress 7.0 compatibility; PHP 8.2/8.3 compatibility; capability checks on all export/import handlers; archive upload validation and temp-file cleanup; option field-name consistency; the AbstractImporter constructor fix; the duplicate email-settings exporter entry fix; and shipping-method export no longer being limited to three built-in types.
2. **Given** the readme is finalized, **When** a reviewer scans it, **Then** no placeholder or draft text remains.
3. **Given** the plugin version is 1.2.0, **When** a reviewer checks `readme.txt`, **Then** the stable tag reads `1.2.0` and the tested-up-to value reads `7.0`.

---

### Edge Cases

- **Method registered on source but not on target**: The method is skipped on import and named in the warning notice; the rest of the import still completes.
- **Method excluded by filter then imported elsewhere**: A method excluded from export simply never appears in the file; import behaves exactly as if the store never had it.
- **Export with zero shipping methods in a zone**: The zone is exported with an empty method set and imports without error.
- **All methods in an import file are unrecognized on the target**: Every method is skipped, the warning notice lists all of them, and zones are still created (without methods) rather than the import failing outright.
- **Legacy export file (v1.1.9 and earlier)**: Imports without breaking changes; the three built-in types behave identically to before.
- **Duplicate method IDs within a single zone in the source data**: Each is processed; no crash. (Reproduces source state faithfully.)
- **Warning notice with a very long list of skipped methods**: The notice remains readable and dismissible.

## Requirements *(mandatory)*

### Functional Requirements

**Shipping method export**

- **FR-001**: The shipping zone export MUST include every shipping method registered with WooCommerce on the source site, not a fixed subset of built-in types.
- **FR-002**: The system MUST remove the hardcoded shipping-method whitelist (`flat_rate`, `free_shipping`, `local_pickup`) as the gating mechanism for what gets exported.
- **FR-003**: The system MUST provide a documented extension point allowing developers to exclude specific shipping method IDs from export; its default behavior MUST exclude nothing (empty exclusion set).
- **FR-004**: The exclusion extension point MUST be documented in the source so developers can discover and use it.
- **FR-005**: The three previously-supported built-in method types MUST continue to export and import identically, with no change to the format of existing exported files.

**Shipping method import**

- **FR-006**: On import, when a shipping method referenced in the data is not registered on the target site, the system MUST skip that method and continue importing the remaining zones and methods.
- **FR-007**: After an import that skipped one or more methods, the system MUST display an admin notice that lists every skipped method by its ID.
- **FR-008**: The skipped-methods notice MUST be a dismissible warning-style admin notice.
- **FR-009**: The system MUST import shipping-zone export files produced by v1.1.9 and earlier without breaking changes.

**Release QA**

- **FR-010**: All 18 scenarios in the Release QA scenario matrix MUST be executed on WordPress 7.0 with the latest WooCommerce and MUST all pass before the release is tagged.
- **FR-011**: A failure in any QA scenario MUST block the release until the defect is fixed and the scenario re-run passes.

**Release documentation**

- **FR-012**: The `readme.txt` changelog MUST contain a `1.2.0` entry covering all fixes delivered across Phases 1, 2, and 3 (the items enumerated in User Story 3, Scenario 1).
- **FR-013**: The `readme.txt` MUST contain no placeholder or draft text in the finalized release.
- **FR-014**: The `readme.txt` stable tag MUST equal the shipped plugin version (`1.2.0`) and the tested-up-to value MUST read `7.0`.

### Key Entities *(include if feature involves data)*

- **Shipping Method**: A WooCommerce shipping option attached to a zone, identified by a method ID. May be a built-in type or one registered by a third-party/custom plugin. Relevant attributes: method ID, the zone it belongs to, and its configured settings.
- **Shipping Zone Export**: The exported representation of one or more shipping zones and their associated shipping methods, used to reproduce the configuration on a target site.
- **Skipped-Method Report**: The collection of method IDs encountered during import that were not registered on the target site, surfaced to the user as a warning notice.
- **Changelog Entry**: The `1.2.0` record in `readme.txt` describing all changes across Phases 1–3, plus the stable-tag and tested-up-to metadata.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of a store's registered shipping methods are present in the exported shipping-zone data (zero silently dropped), versus only the three built-in types before this change.
- **SC-002**: When importing data that references methods unavailable on the target, 100% of skipped methods are named in a warning notice — the user is never left unaware that methods were not reproduced.
- **SC-003**: All 18 release QA scenarios pass on WordPress 7.0 with the latest WooCommerce before the release is tagged.
- **SC-004**: Shipping-zone export files created by v1.1.9 and earlier import successfully with a 0% breakage rate.
- **SC-005**: The finalized `readme.txt` has a complete `1.2.0` changelog (all required items present), zero placeholder/draft text, and stable-tag/tested-up-to metadata matching the shipped version.

## Assumptions

- The store's registered shipping methods are discoverable through WooCommerce's standard shipping-methods registry at export time; "all registered methods" is interpreted as that set.
- The default exclusion set is empty, meaning the out-of-the-box behavior exports every registered method.
- "Oversized archive" in QA scenario 12 refers to an upload exceeding the maximum upload size established in Phase 2 (default 10 MB, filterable).
- The QA environment is a clean WordPress 7.0 install with the latest WooCommerce; a representative third-party shipping method plugin (e.g. a table-rate plugin) is available to exercise scenario 4.
- The target plugin version for this release is `1.2.0`, consistent with Phases 1 and 2.
- This phase introduces no new end-user features (no preview/diff, rollback, bulk export, or WP-CLI); it is strictly a data-loss fix plus release QA and documentation.

## Out of Scope

- Any new end-user features (preview/diff, rollback, bulk export, WP-CLI).
- Changes to export/import flows other than shipping methods (those were addressed in Phases 1 and 2).
- Migration of shipping method *plugin code* itself — only the configuration/data is exported and imported; the third-party plugin must already be installed on the target for its methods to be recreated.

## Dependencies

- Builds on Phase 1 (plugin headers & PHP 7.4–8.3 compatibility) and Phase 2 (capability checks, archive upload validation, temp-file cleanup, exact importer-key matching, option field-name consistency, AbstractImporter constructor fix, duplicate-entry removal). Several QA matrix scenarios verify Phase 1 and Phase 2 guarantees and therefore depend on that work being complete and merged on the current branch.
