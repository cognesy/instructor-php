# Schema2 Phase 0 Baseline Report

## Summary
Phase 0 baseline artifacts were created using legacy `packages/schema` as source of truth.

## Artifacts
- Golden fixtures: `packages/schema2/tests/Baseline/fixtures/*.json`
- Fixture generator/checker: `packages/schema2/tools/baseline/generate.php`
- Parity mapping: `packages/schema2/research/parity-matrix.md`

## Verification
Run:

```bash
php packages/schema2/tools/baseline/generate.php --check
```

Expected result:
- Exit code `0`
- Output: `Baseline fixtures match.`

## Why this is sufficient for Phase 0
- Captures behavior for highest-risk regression zones (refs, recursion, conversion, union policy).
- Anchors cross-package callsite expectations before schema2 implementation starts.
- Creates objective pass/fail gate for future changes.
