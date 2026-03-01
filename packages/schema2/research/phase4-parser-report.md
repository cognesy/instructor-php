# Phase 4 Report - JSON Schema Parser Rebuild

## Implemented
- Rebuilt parsing pipeline to be descriptor-based:
  - `JsonSchemaToDescriptor`
  - `DescriptorToSchemaFactory`
  - `JsonSchemaToSchema` now composes both.
- Updated `JsonSchemaToSchema` behavior for object schemas without `x-php-class`:
  - previous behavior: convert nested untyped objects to `ArraySchema` (lossy)
  - new behavior: convert nested untyped objects to `ArrayShapeSchema` with preserved properties and required list

## Why this matters
- Keeps structural fidelity for untyped object payloads.
- Improves roundtrip stability (`json schema -> schema -> json schema`) for supported object patterns.

## Validation
Added tests in:
- `packages/schema2/tests/Unit/JsonSchemaToSchemaRoundtripTest.php`

Covered:
- untyped nested object remains shape (properties preserved)
- supported object schema roundtrip keeps key fields stable

Executed:
```bash
vendor/bin/pest packages/schema2/tests/Unit/JsonSchemaToSchemaRoundtripTest.php --compact
```

Result:
- `2 passed (12 assertions)`
