# Schema2 Baseline Fixtures

This directory contains golden fixtures captured from the legacy `packages/schema` implementation.

## Purpose
- Lock current behavior before schema2 reimplementation.
- Detect regressions during migration by comparing current output to fixtures.
- Provide explicit coverage for runtime-critical flows used by `instructor`, `dynamic`, and `addons`.

## Scenarios
- `schema_simple_inline.json`
- `schema_self_ref_inline.json`
- `toolcall_transitive_defs.json`
- `toolcall_defs_collision.json`
- `jsonschema_object_without_class.json`
- `typedetails_union_policy.json`

## Commands
Generate fixtures from legacy implementation:

```bash
php packages/schema2/tools/baseline/generate.php --write
```

Validate fixtures:

```bash
php packages/schema2/tools/baseline/generate.php --check
```

`--check` exits with non-zero when fixture drift is detected.
