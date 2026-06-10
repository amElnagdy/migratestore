# Contract: Option Payload Field Naming

**Feature**: 002-security-data-integrity
**Applies to**: `AbstractExporter::get_options_values()`, `AbstractImporter::import()` /
`import_option()`, and every concrete exporter/importer that inherits them; plus
`ShippingZonesImporter::import_option()`.
**Source of truth**: Constitution Principle IV (v1.0.0); spec FR-009..FR-012, FR-015.

This contract defines the single option-entry field-naming convention shared by all exporters and
importers, and the read-only backward-compatibility fallback.

---

## C-1 Canonical convention (v1.2.0+)

- The canonical option-entry shape is:
  ```json
  { "option_name": "<string>", "option_value": "<string|serialized>" }
  ```
- All exporters MUST **write** `option_name` / `option_value`. The legacy pair (`option` / `value`)
  MUST NOT be written by any exporter.
- `AbstractExporter::get_options_values()` is the single producer; concrete exporters inherit it
  unchanged.

---

## C-2 Import read with documented legacy fallback

- Importers MUST **read** `option_name` / `option_value`.
- For backward compatibility with v1.1.9 archives only, importers MUST fall back to the legacy keys
  **when and only when** the canonical keys are absent:
  ```php
  $name  = $item['option_name']  ?? $item['option'];   // legacy fallback (v1.1.9)
  $value = $item['option_value'] ?? $item['value'];     // legacy fallback (v1.1.9)
  ```
- The presence detection in `AbstractImporter::import()` MUST accept an entry that has **either**
  pair.
- The fallback MUST be documented with a code comment in both `AbstractExporter.php` and
  `AbstractImporter.php` naming the convention and citing v1.1.9 (Constitution IV requires any
  backward-compat deviation to be documented in a comment).

---

## C-3 Validation unchanged in intent

- The resolved option name MUST still be sanitized (`sanitize_key`) and checked against the
  exporter's allowed-name set (`AbstractExporter::get_data()`) before `update_option()`.
- The allowed-name comparison MUST read the canonical key from the exporter output (now
  `option_name`).

---

## C-4 No duplicate entries

- Exporter option-name lists MUST NOT contain duplicates.
- Specifically, `EmailsOptionsExporter::get_data()` MUST list
  `woocommerce_customer_completed_order_settings` exactly once (currently listed twice).

---

## Compatibility matrix

| Archive produced by | Keys present | Importer behavior | Result |
|---------------------|--------------|-------------------|--------|
| v1.2.0+ (this release) | `option_name`/`option_value` | reads canonical | ✅ applied |
| v1.1.9 and earlier | `option`/`value` | canonical absent → legacy fallback | ✅ applied (FR-011, FR-015) |
| Malformed (neither pair) | none | entry skipped / not matched | not applied (no crash) |

**Verification**: round-trip a v1.2.0 export→import (all values applied); import a captured v1.1.9
export (all values applied via fallback); confirm `EmailsOptionsExporter` output has no duplicate
entry.
