# Schema2 V2 API Refactoring Plan (Final)

Date: 2026-03-01  
Scope: `packages/schema2` + downstream/upstream runtime packages

## 1) Decision Lock (based on current analysis + feedback)

### D1. Type system decision (`TypeDetails` vs Symfony TypeInfo)

Decision:

- **Do not fully replace `TypeDetails` in 2.0.**
- Use Symfony TypeInfo as the **internal resolution engine**, but keep `TypeDetails` as the **public compatibility type model** for 2.0.

Why:

- `TypeDetails` is deeply used across runtime packages (`dynamic`, `instructor`, schema nodes, factories).
- Current capabilities required by runtime are not just raw type identity:
  - enum backing values
  - JSON-type mapping helpers
  - permissive/legacy union behavior (`int|float`, nullable normalization, fallback to mixed)
  - conversion from JSON-schema-derived model to runtime type model
- A hard replacement in 2.0 would cause broad breakage and force large callsite rewrites without guaranteed net simplification.

2.0 target:

- TypeInfo-powered internals, `TypeDetails`-compatible external API.

Post-2.0 target:

- evaluate replacing `TypeDetails` with a thinner type descriptor once downstream imports are reduced.

Constraint note:

- Runtime `TypeDetails` usage outside schema2 is currently limited to 10 files:
  - `packages/dynamic/src` (8 files)
  - `packages/instructor/src` (2 files)
- This is migration work, but it is bounded and can be planned explicitly.

### D2. `ToolCallBuilder` decision

Decision:

- **Move `ToolCallBuilder` out of schema2 into `packages/instructor`** (do not remove the behavior).

Why:

- The behavior is not only envelope rendering.
- It currently owns stateful reference tracking (`$defs` expansion queue), which is needed for tool-call schemas.
- This is instructor/provider integration concern, not schema-domain concern.

### D3. `Schema` static convenience methods decision

Decision:

- Keep only where used; remove when unused.
- Current runtime source usage is limited (notably `packages/instructor/src/Extras/Sequence/Sequence.php`).

Plan implication:

- remove static/factory coupling from `Schema` data node as an architectural goal,
- migrate existing callsites first,
- then remove or deprecate statics based on real usage.

## 2) New First Step - TypeInfo-Native API Blueprint

This step is mandatory before implementation phases.  
Goal: lock a small, clean public API where TypeInfo is the native type engine.

### 2.1 Public API shape (target)

1. `SchemaFactory` as the main entrypoint:
- accepts runtime sources (`object`, class-string, callable, reflection objects, `Type`)
- produces `Schema`
- exposes explicit TypeInfo-native route (`fromType(Type $type): Schema`)

2. Contracts:
- `Contracts\CanRenderSchema` (`Schema -> JSON Schema array`, optional object-ref callback)
- `Contracts\CanParseJsonSchema` (`JSON Schema -> Schema`)
- `Contracts\CanProvideSchema` (unchanged)

3. Schema model:
- keep `Data\Schema\*` nodes required by runtime
- keep schema nodes as data-only objects (no internal factory/parser/renderer construction)

### 2.2 Compatibility bridge (2.0)

1. `TypeDetails` remains compatibility DTO during 2.0 migration.
2. TypeInfo is the canonical internal representation.
3. Mapping policy lives in one place (TypeInfo -> TypeDetails), including:
- union normalization (`int|float -> float`, unknown unions -> `mixed`)
- nullable handling policy
- enum metadata extraction needed by current schema rendering paths

### 2.3 Explicit non-goals for public API

- No public `Schema\Reflection\*` surface for runtime packages.
- No public `Schema\Utils\*` surface for runtime packages.
- No provider-specific builders in schema (`ToolCallBuilder` ownership moves to instructor).

### 2.4 Blueprint deliverables

1. API inventory table: keep / deprecate / move / internalize.
2. Minimal signature sketch for `SchemaFactory`, renderer, parser.
3. Migration map for the 10 external `TypeDetails` callsites.
4. Gate checklist that blocks implementation phases until blueprint is locked.

## 3) Core Problems to Solve

1. `Schema` node self-coupling:
- `Schema` data class currently instantiates factory/parser/renderer internally.
- This bypasses contract boundaries.

2. Schema package boundary leakage:
- non-schema packages import `Schema\Reflection\*`, `Schema\Utils\*`, `Schema\Factories\ToolCallBuilder`, concrete visitor classes.

3. Dynamic/schema entanglement:
- `dynamic` uses schema reflection internals directly.
- runtime representation in dynamic remains mutable field graph.

4. Experimental-specific metadata mixed into schema utils:
- `Descriptions` currently reads `InputField` / `OutputField`, making cleanup and package boundaries harder.

## 4) Target API Boundary for 2.0

### Keep public/stable (2.0)

- `Contracts\CanProvideSchema`
- `Data\Schema\*` core nodes needed by runtime (`Schema`, `ObjectSchema`, `ScalarSchema`, `EnumSchema`, `CollectionSchema`, `ArraySchema`)
- `Data\TypeDetails` (compatibility model for 2.0)
- `Factories\SchemaFactory`
- `Factories\JsonSchemaToSchema` (rename candidate in follow-up, keep behavior now)
- `Attributes\Description`
- `Attributes\Instructions`

### Move out or stop exposing as cross-package dependencies

- `Factories\ToolCallBuilder` -> move to `packages/instructor`
- `Reflection\ClassInfo`, `Reflection\FunctionInfo`, `Reflection\PropertyInfo` -> no non-schema imports
- `Utils\AttributeUtils`, `Utils\Descriptions`, `Utils\DocstringUtils` -> no non-schema imports
- `Attributes\InputField`, `Attributes\OutputField` -> experimental ownership (after dependency cleanup)

### New minimal contracts (2.0)

- `Contracts\CanRenderSchema` (`Schema -> JSON Schema`, supports object-ref callback or structured ref collection)
- `Contracts\CanParseJsonSchema` (`JSON Schema -> Schema`)

No additional contract explosion in 2.0.

## 5) Upstream / Downstream Change Map

## Upstream (schema2 internal)

### U1. Decouple `Schema` node from factory/parser/renderer

Files:

- `packages/schema2/src/Data/Schema/Schema.php`

Changes:

- remove direct imports/instantiations of `SchemaFactory`, `JsonSchemaToSchema`, `SchemaToJsonSchema` from node class
- migrate static constructors (`string/int/float/bool/array/object/enum/collection/fromTypeName`) away from node
- keep short-term compatibility wrappers only if still required by runtime usage

Acceptance:

- `Schema.php` is a pure data node (no concrete factory/renderer construction).

### U2. TypeInfo-first internals, `TypeDetails` compatibility

Files:

- `packages/schema2/src/Factories/TypeDetailsFactory.php`
- `packages/schema2/src/Reflection/*`

Changes:

- make TypeInfo the primary extraction path
- keep `TypeDetails` output contract stable
- document unsupported TypeInfo edge cases and fallback behavior explicitly

Acceptance:

- no capability regressions in enum/collection/nullable/union behavior currently covered by tests.

### U3. Remove experimental attribute coupling from schema utils

Files:

- `packages/schema2/src/Utils/Descriptions.php`

Changes:

- stop reading `InputField` / `OutputField` in schema utils
- keep `Description` / `Instructions` only

Acceptance:

- schema utils no longer depend on experimental-only metadata conventions.

## Downstream: Instructor

### I1. Move `ToolCallBuilder` behavior to instructor

Files:

- current: `packages/instructor/src/Creation/StructuredOutputSchemaRenderer.php`
- new: `packages/instructor/src/Creation/Schema/*` (new reference tracker/builder service)

Changes:

- move stateful reference-tracking logic (`onObjectRef`, queue, recursive `$defs` expansion) from schema package into instructor package
- keep output parity for tool-call schema payloads
- use schema renderer contract for base JSON schema rendering

Acceptance:

- same `$defs` behavior as current implementation
- schema2 no longer exports/owns tool-call envelope builder.

### I2. Remove `Schema::...` static constructor usage

Files:

- `packages/instructor/src/Extras/Sequence/Sequence.php`

Changes:

- replace `Schema::collection()` / `Schema::object()` with injected/explicit `SchemaFactory` usage

Acceptance:

- zero runtime source reliance on `Schema` static convenience constructors (outside schema2 internals).

## Downstream: Dynamic

### DYN1. Boundary-first decoupling (Phase 1)

Files:

- `packages/dynamic/src/StructureFactory.php`
- `packages/dynamic/src/Traits/Structure/ProvidesSchema.php`
- other dynamic files importing schema reflection/utils

Changes:

- eliminate imports of `Cognesy\Schema\Reflection\*` from dynamic
- eliminate imports of `Cognesy\Schema\Utils\*` from dynamic
- use `SchemaFactory` + parser/renderer contracts as boundary
- for callable/method/function metadata where `SchemaFactory` cannot currently provide callable schema: implement dynamic-local analyzer (native reflection + TypeInfo), not schema reflection imports

Acceptance:

- no dynamic runtime source imports of `Schema\Reflection\*` or `Schema\Utils\*`.

### DYN2. Simplify internal runtime representation (Phase 2)

Changes:

- introduce array-backed record path
- keep `Structure` as compatibility wrapper during transition
- reduce mutable `Field` graph reliance in runtime flows

Acceptance:

- key callsites (`instructor` fallback, ReAct normalization paths) no longer depend on mutable field object graph behavior.

## Downstream: Addons

### A1. Remove schema reflection usage in `FunctionCallFactory`

Files:

- `packages/addons/src/FunctionCall/FunctionCallFactory.php`

Changes:

- replace `Schema\Reflection\FunctionInfo` usage with native reflection or dynamic-local callable metadata service
- keep compatibility with current `FunctionCall` behavior

Acceptance:

- no addons runtime source imports of `Schema\Reflection\FunctionInfo`.

## Downstream: Experimental

### E1. Move signature-specific attribute/reflection helpers out of schema coupling

Files:

- `packages/experimental/src/Signature/Factories/SignatureFromClassMetadata.php`
- `packages/experimental/src/Module/Modules/Prediction.php`

Changes:

- remove imports of `Schema\Reflection\*` and `Schema\Utils\AttributeUtils` from runtime paths
- own signature-specific metadata extraction in experimental
- migrate `InputField` / `OutputField` ownership once schema-side `Descriptions` dependency is removed

Acceptance:

- experimental runtime source no longer imports schema utils/reflection internals.

## 6) Execution Phases

### Phase 0 - Lock TypeInfo-native API blueprint (new)

Actions:

- create and approve the API blueprint deliverables from section 2.4
- freeze 2.0 public API foundation set and mark questionable surface for deprecation/move
- define exact migration sequence for external `TypeDetails` callsites (10 files)

Acceptance:

- blueprint document approved and linked from this plan
- no implementation task starts before blueprint lock
- acceptance gates include LOC delta + monorepo `composer test`

### Phase 1 - Baseline and guards

Actions:

- freeze baseline metrics:
  - LOC (`packages/schema2/src`, `packages/dynamic/src`, combined)
  - import leakage counts for non-schema packages
- add explicit acceptance criteria to each implementation task:
  - LOC delta vs previous pass and original `packages/schema`
  - monorepo root `composer test` pass

### Phase 2 - Schema2 core boundary cleanup

Actions:

- implement `CanRenderSchema` and `CanParseJsonSchema`
- refactor `Schema` node to remove internal factory/parser/renderer construction
- keep temporary compatibility wrappers only where runtime still requires

### Phase 3 - Instructor takes `ToolCallBuilder` ownership

Actions:

- move `ToolCallBuilder` logic into instructor package
- keep reference expansion semantics
- update schema2 + instructor tests accordingly

### Phase 4 - Dynamic boundary decoupling (no full redesign yet)

Actions:

- remove schema reflection/utils imports from dynamic
- route class/json-schema paths via schema contracts
- add dynamic-local callable metadata analyzer for callable paths

### Phase 5 - Downstream cleanup (addons + experimental + instructor)

Actions:

- remove remaining non-schema imports of schema internals
- migrate remaining `Schema::...` static usages in runtime source
- resolve `InputField` / `OutputField` ownership transition

### Phase 6 - Dynamic simplification track

Actions:

- implement array-first record flow in dynamic
- reduce/remove mutable `Field` graph from hot paths
- reassess merge readiness (dynamic into schema) after simplification metrics

## 7) Acceptance Criteria (final)

### Boundary criteria

- no non-schema runtime imports of:
  - `Cognesy\Schema\Reflection\*`
  - `Cognesy\Schema\Utils\*`
  - `Cognesy\Schema\Factories\ToolCallBuilder`

### Architectural criteria

- `Schema` node is data-only (no concrete factory/parser/renderer construction)
- `ToolCallBuilder` behavior owned by instructor package
- schema package exposes schema capabilities, not provider-specific tool envelope assembly

### Compatibility criteria

- no regression in tool-call `$defs` reference expansion behavior
- no regression in schema generation for current instructor/dynamic/addons flows
- `TypeDetails` behavior parity preserved for 2.0 consumers

### Quality criteria

- monorepo root `composer test` passes
- impacted examples pass (instructor, agents, addons, experimental tracks)
- LOC delta recorded after every completed task:
  - vs prior pass
  - vs original `packages/schema`

## 8) Risks and Mitigations

1. Risk: hidden dependencies on `Schema::...` static constructors.
- Mitigation: keep temporary compatibility wrappers, remove only after usage scan reaches zero in runtime source.

2. Risk: reference-expansion behavior drifts during `ToolCallBuilder` move.
- Mitigation: snapshot tests for nested/transitive refs before move, parity tests after move.

3. Risk: callable schema path in dynamic cannot be replaced quickly.
- Mitigation: use dynamic-local analyzer first; do not block boundary cleanup on full dynamic redesign.

4. Risk: TypeInfo migration changes union/nullability edge behavior.
- Mitigation: add targeted regression tests for union normalization, enums, nullable objects, collection typing.

## 9) Summary

This plan keeps scope aggressive but sequenced:

- first, fix schema2 boundary violations and move instructor-specific behavior upstream to instructor,
- second, cut downstream imports of schema internals with pragmatic adapters,
- third, simplify dynamic internals without blocking on a big-bang merge decision.

It is designed to deliver 2.0 boundary clarity now, while preserving a controlled path to deeper simplification afterward.
