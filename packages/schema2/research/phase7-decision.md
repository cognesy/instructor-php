# Phase 7 Decision Memo

## Decision
Go forward with `packages/schema2` as the active implementation path.

## Evidence Summary
Completed phases:
- Phase 0: baseline fixtures + parity matrix + check script
- Phase 1: schema2 skeleton and essential contract availability
- Phase 2: TypeInfo-native adapter path, Symfony 6 path removed
- Phase 3: descriptor model + descriptor renderer with parity tests
- Phase 4: descriptor-based JSON parser and lossy untyped-object fix
- Phase 5: compatibility hardening slice (domain exceptions in core paths, docs alignment)
- Phase 6: focused cross-package verification

## Verification Metrics
- schema2 unit suites: `11 passed (26 assertions)`
- dynamic StructureFactory suite: `14 passed (103 assertions)`
- addons FunctionCall suite: `11 passed (28 assertions)`
- instructor schema-regression focus: `3 passed (17 assertions)`
- baseline drift check: pass (`Baseline fixtures match`)
- runtime smoke: pass (`StructuredOutputSchemaRenderer` tool-call rendering)

## Blocker Status
- Blocking regressions found in focused verification: `0`
- Open schema2 blocker issues from this execution: `0`

## Known Non-Blocking Follow-ups
- `instructor-30s5.9` replace remaining generic exceptions in reflection/type-string paths.
- `instructor-30s5.10` switch public JSON renderer pipeline to descriptor implementation.

## Rollback Readiness
Rollback remains low-risk:
- root autoload mapping can be remapped back from `packages/schema2` to `packages/schema` if needed.

## Rationale
Current results show stable behavior for critical schema-dependent flows with measurable improvements (TypeInfo-native path, descriptor parser, untyped object fidelity) and no observed blockers in targeted suites.
