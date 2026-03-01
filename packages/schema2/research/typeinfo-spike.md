# Symfony TypeInfo Spike for Schema2

Date: 2026-03-01  
Context: Keep composer compatibility as `^7 || ^8`, but validate design using Symfony 8 TypeInfo.

## Objective

Verify whether `symfony/type-info` can replace `TypeDetails` as the core internal type engine, and identify exactly what is still missing for full replacement.

## Environment

- PHP: `8.5.1`
- `symfony/type-info`: `8.0.6`
- Composer constraints remain 7/8-compatible in `composer.json`.

## Experiments Run

## 1) Property + phpdoc resolution via TypeResolver

Used `TypeResolver::create()->resolve(new ReflectionProperty(...))` on:

- scalar property (`int`)
- nullable scalar (`?int`)
- backed enum
- list with union item type (`list<int|float>`)
- generic map (`array<string, Enum>`)
- array shape (`array{street: string, zip?: int}`)
- scalar unions (`int|float`, `int|string`)

Result:

- TypeInfo resolved all of these correctly.
- Returned rich types (`NullableType`, `BackedEnumType`, `CollectionType`, `ArrayShapeType`, `UnionType`) with structured accessors.

## 2) Raw type-string parsing via TypeResolver

Resolved strings:

- `int|float`
- `int|string`
- `?int`
- `array<string, Enum>`
- `list<int|float>`
- `array{street: string, zip?: int}`
- `Foo[]`, `int[]`, `array<int>`

Result:

- Works and is stronger than current `TypeDetails::fromTypeName` parser.
- Handles array shapes and generic array/list forms that currently fail in `TypeDetails`.

## 3) Compare current `TypeDetails::fromTypeName` behavior

Current behavior highlights:

- `int|float` -> collapses to `float`
- `int|string` -> collapses to `mixed`
- `array<string, Enum>` -> unsupported
- `list<int|float>` -> unsupported
- `array{...}` -> unsupported

Result:

- Current `TypeDetails` parser is intentionally lossy / limited.
- TypeInfo provides better structural fidelity.

## 4) Adapter feasibility check (TypeInfo -> TypeDetails)

Built a small prototype converter script mapping:

- `BuiltinType` -> scalar/mixed/array
- `NullableType` -> wrapped type + nullability handled outside type identity
- `BackedEnumType` -> `TypeDetails::enum(...)`
- `CollectionType` -> `TypeDetails::collection(...)` / array fallback
- `UnionType` -> existing compatibility policy (`int|float -> float`, others -> mixed)
- `ArrayShapeType` -> array fallback

Result:

- Feasible for current runtime semantics.
- Confirms we can make TypeInfo internal while preserving `TypeDetails` compatibility behavior.

## What TypeInfo Covers Well (for us)

1. Reflection + phpdoc type extraction with modern typing features.
2. Nullable, union, enum, collection, array-shape modeling.
3. Generic collection key/value inspection.
4. Strong typed API for type traversal/inspection.

## Gaps / Missing Pieces vs full `TypeDetails` replacement

1. Enum value list metadata is not directly exposed as first-class value constraints.
- TypeInfo gives enum class + backing type (`BackedEnumType`), but not direct "allowed values" list object.
- Workaround: derive from enum reflection (`::cases()`).

2. "Option" type (string/int enums without enum class) is not a direct TypeInfo concept.
- TypeInfo parses literals, but value lists are not preserved as a dedicated constraint model for our current `enumValues` usage.

3. JSON Schema conversion helpers are not built into TypeInfo.
- We still need schema-layer mapping logic (`JsonSchema` <-> internal type model).

4. Current compatibility policies are domain-specific, not TypeInfo defaults.
- Examples: `int|float -> float`, `int|string -> mixed`, nullable treatment, fallback rules.
- Must stay in our mapping policy layer.

5. Stable serialized type metadata format is ours.
- `TypeDetails::toArray()` style payload currently acts as compatibility DTO in several flows.
- TypeInfo objects are not a drop-in serialized contract for that.

6. Some `fromValue` semantics differ.
- TypeInfo distinguishes `true`/`false` literal bool types.
- We currently normalize to `bool`.
- Requires normalization in adapter.

## Feasibility Verdict

## Can we replace `TypeDetails` entirely in 2.0?

Not safely without broad downstream refactors.

## Can we replace `TypeDetails` internals with TypeInfo now?

Yes. This is a strong and practical path.

Recommended 2.0 approach:

- **TypeInfo as internal canonical resolver**
- **TypeDetails as compatibility DTO/API**
- Conversion adapter enforces legacy compatibility policies

This gives major simplification while avoiding high-risk breakage.

## Proposed Implementation Path

### Phase A: Add adapter layer in schema2

- Introduce `TypeInfoToTypeDetailsMapper` (or equivalent name).
- Centralize policy rules (union collapse, bool normalization, collection narrowing, fallback behavior).

### Phase B: Switch reflection entrypoints

- In `Reflection\PropertyInfo` and related paths, resolve via TypeInfo directly.
- Stop string-roundtripping where possible.
- Keep returning `TypeDetails` for compatibility callsites.

### Phase C: Switch factory internals to TypeInfo-first

- `TypeDetailsFactory` becomes a compatibility factory over TypeInfo + policy.
- Minimize custom parsing logic that duplicates TypeInfo capabilities.

### Phase D: Evaluate post-2.0 type contract cleanup

- After downstream imports shrink, evaluate replacing `TypeDetails` public API with thinner descriptor model.

## Minimum Regression Test Additions

1. Reflection + phpdoc:
- list/map/array-shape/nullable/union/backed enum cases.

2. Compatibility behavior:
- `int|float -> float`
- `int|string -> mixed`
- bool literal normalization (`true`/`false` -> bool where expected).

3. Enum metadata:
- backed enum class + backing type + values list parity where required by JSON schema rendering.

4. JSON-schema roundtrip:
- no regressions in current schema generation/parsing tests.

## Final Recommendation

Proceed with **TypeInfo-first internals now**, keep `TypeDetails` as 2.0 compatibility surface, and defer full public replacement until downstream coupling is reduced.

This achieves the simplification objective without destabilizing runtime packages.
