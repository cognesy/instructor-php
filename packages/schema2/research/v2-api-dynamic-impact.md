# V2 API Dynamic Impact

Date: 2026-03-01  
Scope: `packages/schema2` + `packages/dynamic` + runtime dependents

## Goal

Define a realistic path to either:

1. merge `packages/dynamic` into the new schema design, or  
2. keep `packages/dynamic` but reduce it to a thin, focused compatibility layer.

The target is a radically simpler, coherent API with clear ownership boundaries.

## Current State (evidence)

### Size snapshot

- `packages/dynamic/src`: 18 PHP files, ~1137 LOC
- `packages/schema2/src`: 30 PHP files, ~2772 LOC

### Coupling snapshot

`dynamic` currently depends on schema internals and concrete implementations:

- `Cognesy\Schema\Reflection\ClassInfo`
- `Cognesy\Schema\Reflection\FunctionInfo`
- `Cognesy\Schema\Visitors\SchemaToJsonSchema`
- `Cognesy\Schema\Factories\SchemaFactory`
- `Cognesy\Schema\Factories\JsonSchemaToSchema`
- `Cognesy\Schema\Data\TypeDetails` / `Data\Schema\*`

`dynamic` is also used in runtime paths:

- `instructor` (`ResponseModelFactory`)
- `agents` (ReAct tool-call normalization paths, reflective schemas)
- `addons` (`FunctionCall`, ToolUse ReAct paths)
- `experimental` (`Signature`, RLM protocol structures)

## Design Problem

`dynamic` currently mixes:

- schema definition
- schema reflection
- runtime value container
- validation
- transformation
- serialization

This causes ambiguous ownership between `schema2` and `dynamic`, and keeps both packages larger than needed.

## Option A: Merge Dynamic into Schema2

## What this means

Move dynamic capabilities under `Cognesy\Schema` as schema-adjacent runtime modules and remove `packages/dynamic` as a separate domain package.

Suggested internal split:

- `Schema\Model\*` (existing schema nodes / type metadata)
- `Schema\Runtime\Record\*` (array-backed record container)
- `Schema\Runtime\Normalize\*`
- `Schema\Runtime\Validate\*`
- `Schema\Runtime\Hydrate\*`
- `Schema\Runtime\Legacy\*` (temporary `Structure`/`Field` adapter layer)

## Pros

- one package boundary for schema + schema-driven record behavior
- easier removal of duplicate reflection/type logic
- strongest path to large LOC reduction by deleting bridging layers

## Cons

- highest migration blast radius (autoload + package identity + callsites)
- higher short-term regression risk in `instructor/agents/addons/experimental`
- harder rollback if merge and redesign happen simultaneously

## When to choose

Choose Option A only if:

- we accept larger one-time migration risk in exchange for fastest simplification
- we can commit to aggressive cross-package callsite updates in the same window

## Option B: Keep Dynamic, Drastically Simplify It (recommended first step)

## What this means

Keep `packages/dynamic`, but make it a thin compatibility facade over schema2 contracts and array-first runtime processing.

Target design for `dynamic`:

- keep only public compatibility surface needed by runtime callsites
- remove direct imports of `Schema\Reflection\*`, `Schema\Utils\*`, concrete visitors
- represent runtime data as associative arrays, not mutable field graphs
- keep `Structure` API as deprecated adapter during transition

## What should remain public in dynamic

- `StructureFactory` (compat entrypoint, internally delegated)
- `Structure` (compat wrapper, deprecated)
- minimal adapter helpers required by existing callsites

## What should be removed or internalized in dynamic

- `Field` as primary runtime model (replace with record/map backing)
- trait-heavy mutable internals that duplicate schema/runtime concerns
- schema reflection logic duplicated in dynamic

## Pros

- lower migration risk than full merge
- clear path to remove schema internal dependencies immediately
- rollback-friendly and incremental

## Cons

- temporary two-package setup remains during migration
- requires discipline to avoid new logic entering dynamic

## When to choose

Choose Option B when:

- we need fast risk-managed progress to 2.0
- we want measurable reduction before deciding on final merge

## Recommended Strategy

Use Option B now, keep Option A as Phase-2 consolidation decision.

Reason:

- fastest path to enforce clean boundaries
- smallest regression envelope for runtime packages
- preserves optional later merge once compatibility pressure is reduced

## Implementation Plan

### Phase 1: Boundary hardening

- ban new non-schema imports of `Cognesy\Schema\Reflection\*` from dynamic
- replace dynamic reflection usage with native reflection + TypeInfo in dynamic-local adapters
- replace direct `SchemaToJsonSchema` usage with schema rendering contract

Acceptance:

- no `use Cognesy\Schema\Reflection\*` in `packages/dynamic/src`
- no `use Cognesy\Schema\Utils\*` in `packages/dynamic/src`

### Phase 2: Runtime model simplification

- introduce array-backed record representation in dynamic
- route normalization/validation through modular processors
- keep `Structure` methods as compatibility wrappers over record processors

Acceptance:

- core runtime flows no longer rely on mutable per-field object state
- `Structure` remains functional but delegates internally

### Phase 3: Downstream migration

- `instructor`: JSON-schema fallback path uses record pipeline, not legacy structure mutation
- `agents` / `addons`: ReAct arg normalization uses schema+record processors
- `experimental`: move signature-specific metadata helpers out of schema internals

Acceptance:

- runtime packages stop depending on dynamic internals beyond compatibility API

### Phase 4: Consolidation decision

Evaluate:

- remaining dynamic LOC
- remaining dynamic runtime ownership
- regression and maintenance cost

Decision:

- if dynamic reduced to thin wrapper only -> either keep as compatibility package or merge into schema with minimal risk
- if meaningful unique domain remains -> keep as separate package with strict scope

## Merge Readiness Criteria (if we choose Option A later)

Before merge:

- dynamic contains no schema-internal reflection dependencies
- dynamic data model is array-first and modularized
- downstream callsites consume stable schema/runtime contracts

Only then merge package boundaries. Do not merge while internals are still entangled.

## Success Metrics

Track after each phase:

- LOC delta (`dynamic`, `schema2`, and combined total)
- import bans compliance
- monorepo `composer test` pass
- impacted examples pass (`instructor`, `agents`, `addons`, `experimental`)

## Decision Summary

- Immediate path: keep `dynamic` package, simplify aggressively, enforce clean schema boundary.
- Deferred path: merge into schema only after simplification removes coupling and shrinks compatibility surface.

This sequencing gives the best chance of radical simplification with controlled delivery risk.
