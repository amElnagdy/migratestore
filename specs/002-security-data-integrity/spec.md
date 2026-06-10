# Feature Specification: Security Hardening & Data Integrity

**Feature Branch**: `002-security-data-integrity`

**Created**: 2026-06-10

**Status**: Draft

**Input**: User description: "Read the migratestore-plan.md and create the specification for Phase 2 — Security Hardening & Data Integrity ONLY"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Only authorized users can export or import (Priority: P1)

A store owner expects that only people trusted to manage the store can move store settings in or out of the site. Today, any logged-in user who can reach the export/import screen (or forge a valid request to it) can trigger a full settings export or overwrite store configuration with an import, because the handlers confirm the request is genuine but never confirm the requester is allowed to perform the action.

**Why this priority**: This is the highest-impact security gap. Without it, lower-privileged accounts (Subscriber, Editor, Author) or a compromised low-privilege session can exfiltrate store configuration or overwrite it, causing data loss or disclosure. It is the foundation all other import/export safety depends on.

**Independent Test**: Sign in as a user who lacks store-management permission, submit an otherwise valid export request and an otherwise valid import request, and confirm both are refused with a clear message and that no data is read, returned, or written.

**Acceptance Scenarios**:

1. **Given** a logged-in user without the store-management capability, **When** they submit a valid (correctly authenticated) export request, **Then** the request is refused with a translated error message and no settings data is read or returned.
2. **Given** a logged-in user without the store-management capability, **When** they submit a valid (correctly authenticated) import request with a real file, **Then** the request is refused before the file is processed and no store settings are changed.
3. **Given** a user with the store-management capability, **When** they submit a valid export or import request, **Then** the action proceeds normally.
4. **Given** any export or import request, **When** the request lacks a valid authenticity token, **Then** it is refused regardless of the user's capability (authenticity and authorization are both required, not either/or).

---

### User Story 2 - Uploaded archive files are validated and safely handled (Priority: P1)

A store owner uploads an exported archive to import settings. The plugin must reject files that are the wrong type, too large, or crafted to write outside the intended location, and must never leave leftover files on disk regardless of whether the import succeeds or fails.

**Why this priority**: Unvalidated archive handling is the second major attack surface. A malicious archive could place files outside the intended directory ("path traversal"), an oversized file could exhaust disk or memory, and leftover temporary files accumulate and may leak data. This must ship alongside authorization to make the import path safe.

**Independent Test**: Attempt imports with (a) a non-archive file renamed to look like an archive, (b) an archive larger than the configured limit, and (c) an archive containing an entry whose path escapes the target directory; confirm each is rejected with a clear notice and that no files remain on disk afterward.

**Acceptance Scenarios**:

1. **Given** a file whose real content is not a valid archive (e.g., a text file renamed with an archive extension), **When** it is uploaded for import, **Then** it is rejected based on its actual content type with a clear, actionable notice.
2. **Given** an archive larger than the configured maximum size (default 10 MB, adjustable by site operators), **When** it is uploaded, **Then** it is rejected with a notice stating the size limit.
3. **Given** an archive containing any entry with a parent-directory reference or an absolute path, **When** it is uploaded for import, **Then** the entire import is rejected, nothing is extracted, and a notice is shown.
4. **Given** any import attempt that succeeds, **When** the handler finishes, **Then** the uploaded file and any extracted temporary files are removed.
5. **Given** any import attempt that fails for any reason, **When** the handler finishes, **Then** the uploaded file and any partially extracted temporary files are removed, leaving no orphaned files in the uploads or temporary directories.

---

### User Story 3 - Imported settings are actually applied (consistent field naming) (Priority: P2)

A store owner migrates settings from one site to another and expects the imported values to take effect. Today, exporters and importers disagree on the names of the fields that hold each setting, so imports can silently complete without applying anything.

**Why this priority**: This is a data-integrity defect that causes silent failure — the user is told the import succeeded but their settings are not applied. It directly undermines the plugin's core promise, but it ranks below the security stories because it is a correctness issue rather than an exposure.

**Independent Test**: Export a set of settings from one site, import them on another, and confirm every exported option is present and applied after import; additionally import a file produced by the previous plugin version and confirm it still applies.

**Acceptance Scenarios**:

1. **Given** a settings export produced by the current version, **When** it is imported, **Then** every exported option value is applied to the target site (no silently skipped fields).
2. **Given** a settings file produced by an older version that used the legacy field names, **When** it is imported, **Then** the values are still applied using a backward-compatible fallback.
3. **Given** the email-settings export, **When** it is generated, **Then** it contains no duplicated option entries.

---

### User Story 4 - The correct importer is always selected (Priority: P2)

When a settings bundle is imported, the plugin must route each file to exactly the right importer. Today it guesses based on whether a filename contains a fragment, so a file can be matched to the wrong importer or matched by accident.

**Why this priority**: Mis-routing an import can apply the wrong settings or corrupt data, and the substring approach is brittle and potentially exploitable. It is important for correctness and safety but depends on the same import path already being authorized and validated (P1 stories).

**Independent Test**: Import each known settings file type and confirm each is handled by its intended importer; import a file whose name partially overlaps two types and confirm it is not misrouted; import an unrecognized file and confirm a clear failure notice.

**Acceptance Scenarios**:

1. **Given** a recognized settings file, **When** it is imported, **Then** it is matched to exactly one importer by exact key, never by partial-name overlap.
2. **Given** a file whose name partially matches more than one known type, **When** it is imported, **Then** it is not routed to the wrong importer.
3. **Given** a file that matches no known importer, **When** it is imported, **Then** the import fails with an actionable notice rather than silently doing nothing.
4. **Given** settings files exported by the previous plugin version, **When** they are imported, **Then** they continue to match their correct importers without breaking.

---

### User Story 5 - Importers instantiate without errors (Priority: P3)

A developer or the runtime instantiates the importers. The shared importer base and its specific importers must agree on how they are constructed so that no warnings or errors occur when they are created.

**Why this priority**: A construction-signature mismatch can surface as warnings or, in stricter environments, errors during import. It is a stability/robustness fix with lower user-facing impact than the security and data-integrity stories.

**Independent Test**: Instantiate every importer in a strict environment and confirm none produces a construction-related warning or error, and that each correctly initializes through its shared base.

**Acceptance Scenarios**:

1. **Given** any importer, **When** it is instantiated, **Then** no construction-signature warning or error is produced.
2. **Given** any importer, **When** it is instantiated, **Then** it correctly initializes via the shared importer base with the expected inputs.

---

### User Story 6 - No leftover debug artifacts in the shipped code (Priority: P3)

A reviewer or auditor reading the plugin source expects it to look production-ready, with no unfinished-work markers left behind.

**Why this priority**: This is a code-hygiene and release-readiness item with no direct user impact, so it is the lowest priority, but it is a required gate for shipping.

**Independent Test**: Search the entire plugin source for unfinished-work markers and confirm none remain, and that the one previously-known marker has been resolved with either implemented behavior or a clear explanation.

**Acceptance Scenarios**:

1. **Given** the shipping-zones importer, **When** its source is reviewed, **Then** the previously-present unfinished-work marker is gone — either the intended behavior is implemented or it is replaced with a clear explanation of the intentional design decision.
2. **Given** the complete plugin source, **When** it is searched for unfinished-work markers, **Then** none remain.

---

### Edge Cases

- What happens when an import request passes authenticity and authorization checks but the uploaded file slot is empty or missing? The import should fail gracefully with a clear notice and clean up any temporary state.
- What happens when the archive is valid and authorized but extraction fails partway through (e.g., disk full or a corrupt entry)? All temporary files must still be removed and a clear failure notice shown.
- What happens when a site operator lowers the maximum upload size below the size of a legitimate older export? The upload is rejected with a notice stating the limit; the operator can raise the limit to proceed.
- What happens when an import file contains both the new field names and the legacy field names? The new names take precedence; the legacy names are used only when the new ones are absent.
- What happens when an archive contains a mix of safe entries and one unsafe (traversal) entry? The entire import is rejected; no entries are extracted.
- What happens when an export contains a shipping or settings type the target site does not recognize? (Routing must fail clearly for unknown types; broader handling of unknown shipping methods is addressed in Phase 3 and is out of scope here.)

## Requirements *(mandatory)*

### Functional Requirements

**Authorization**

- **FR-001**: Every export handler MUST confirm the requester holds the store-management capability before any settings data is read or returned, and MUST refuse the request with a translated error message if the requester does not.
- **FR-002**: Every import handler MUST confirm the requester holds the store-management capability before the uploaded file is processed, and MUST refuse the request with a translated error message if the requester does not.
- **FR-003**: The system MUST require BOTH request-authenticity verification AND the store-management capability for every export and import action; satisfying only one MUST NOT permit the action.

**Upload validation & safe handling**

- **FR-004**: The system MUST validate that an uploaded import file is a genuine archive based on its actual content type, not solely on its file extension, and MUST reject files that are not.
- **FR-005**: The system MUST reject import uploads that exceed a maximum size, with a default of 10 MB, and the maximum MUST be adjustable by site operators.
- **FR-006**: The system MUST inspect every entry path inside an uploaded archive and MUST reject the entire import if any entry contains a parent-directory reference or an absolute path; no entries may be extracted when such an entry is present.
- **FR-007**: The system MUST remove the uploaded file and any extracted temporary files at the end of every import attempt, on both success and failure paths, leaving no orphaned files in the uploads or temporary directories.
- **FR-008**: Whenever an upload is rejected (wrong type, too large, unsafe entry, or otherwise invalid), the system MUST present a clear, actionable notice explaining why it was rejected.

**Data integrity — field naming**

- **FR-009**: The system MUST use a single, documented field-naming convention for option entries shared by all exporters and importers, using the explicit `option_name` / `option_value` pair.
- **FR-010**: All exporters MUST write option entries using the chosen convention, and all importers MUST read option entries using the chosen convention.
- **FR-011**: Importers MUST apply a backward-compatible fallback that reads the legacy field names only when the current field names are absent, so files from the previous plugin version still import correctly.
- **FR-012**: The email-settings export MUST NOT contain duplicate option entries.

**Importer selection**

- **FR-013**: The system MUST select the importer for each settings file by exact key match against a defined set of expected file keys, and MUST NOT use partial-name or substring matching.
- **FR-014**: When no importer matches a file, the import MUST fail with an actionable notice rather than completing with no effect.
- **FR-015**: Settings files exported by the previous plugin version MUST continue to be routed to their correct importers without breaking.

**Importer construction**

- **FR-016**: The shared importer base and all specific importers MUST agree on construction inputs so that instantiating any importer produces no construction-related warning or error, and each importer MUST initialize through the shared base with the expected inputs.

**Code hygiene**

- **FR-017**: The previously-present unfinished-work marker in the shipping-zones importer MUST be resolved by either implementing the intended behavior or replacing it with a clear explanation of the intentional design decision.
- **FR-018**: The shipped plugin source MUST contain no unfinished-work markers (e.g., TODO/FIXME) anywhere.

**Scope guardrails**

- **FR-019**: This feature MUST NOT change any user-facing export/import functionality beyond the security, validation, and correctness fixes described here; no new features are introduced.

### Key Entities *(include if feature involves data)*

- **Settings Export Bundle**: The archive a user downloads, containing one or more settings files. Key attributes: archive content type, total size, and the set of entries (each with a path that must be safe).
- **Settings File**: A single file within the bundle representing one category of store settings (e.g., general, email, shipping zones, tax). Key attribute: an exact key that identifies which importer handles it.
- **Option Entry**: A single setting carried inside a settings file. Key attributes: an option name and an option value, expressed using the agreed field-naming convention (with legacy names recognized on import only as a fallback).
- **Requester**: The user initiating an export or import. Key attributes: authentication state, request authenticity, and whether they hold the store-management capability.
- **Maximum Upload Size**: An operator-adjustable limit (default 10 MB) governing the largest accepted import file.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of export and import actions performed by a user lacking the store-management capability are refused with no data read, returned, or written.
- **SC-002**: 100% of import attempts using a non-archive file, an oversized archive, or an archive with an unsafe entry are rejected with a clear notice, and 0 such files are extracted.
- **SC-003**: After every import attempt — successful or failed — 0 orphaned upload or temporary files remain on disk.
- **SC-004**: 100% of option values present in an export are applied on import for files produced by the current version, and files produced by the previous version still apply 100% of their values via the backward-compatible fallback.
- **SC-005**: Every recognized settings file is routed to exactly one correct importer, and 0 files are misrouted when their names partially overlap another type.
- **SC-006**: Instantiating every importer produces 0 construction-related warnings or errors.
- **SC-007**: A full-source search for unfinished-work markers returns 0 results.
- **SC-008**: A previously-authorized store manager can still complete every existing export and import flow with no change in their experience (no regressions to legitimate use).

## Assumptions

- "Store-management capability" refers to the WooCommerce store-management permission (`manage_woocommerce`); WooCommerce is a required dependency of the plugin, so this capability is available.
- The default maximum upload size of 10 MB is appropriate for typical settings exports; site operators who need larger imports can raise the limit through the provided adjustment point.
- The chosen field-naming convention is `option_name` / `option_value`; the legacy pair (`option` / `value`) is recognized on import only as a fallback and is never written by exporters.
- "Unfinished-work markers" means developer notes such as TODO and FIXME comments left in source.
- Backward compatibility targets files produced by plugin version 1.1.9 and earlier; no other historical formats are in scope.
- Handling of unknown or third-party shipping methods on export/import (beyond failing cleanly for unrecognized files) is Phase 3 work and is out of scope here.
- No user-facing UI redesign is involved; notices use the platform's standard admin-notice mechanism.
- This phase introduces no new persistent data structures; it changes how existing export/import data is validated, named, routed, and cleaned up.
