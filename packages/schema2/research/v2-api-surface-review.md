# Schema2 V2 API Surface Review (instructor-8e35)

Date: 2026-03-01

## Context

Current `Cognesy\Schema` surface is still wider than the domain boundary should allow.
Schema should be focused on:

- structural type modeling
- schema derivation/introspection
- JSON Schema parsing/rendering

Schema should not be responsible for:

- tool-call envelope assembly (`type=function`, function metadata wrapping)
- generic reflection helper toolkit for other packages
- package-specific attribute metadata extraction convenience

## How this review was built (reproducible)

1. Enumerated current public classes:
- `find packages/schema2/src -type f -name '*.php'`

2. Mapped external imports:
- `rg 'use Cognesy\\Schema\\' -n packages examples docs-build --glob '!packages/schema/**' --glob '!packages/schema2/**'`

3. Focused leakage check for internals:
- `rg 'use Cognesy\\Schema\\Utils\\|use Cognesy\\Schema\\Reflection\\|use Cognesy\\Schema\\Visitors\\|use Cognesy\\Schema\\Factories\\ToolCallBuilder' -n packages --glob '!packages/schema/**' --glob '!packages/schema2/**'`

## External usage snapshot (runtime packages)

Most imported symbols in runtime packages:

- `Data\TypeDetails` (dynamic/instructor heavy)
- `Data\Schema\Schema` (instructor/dynamic/experimental/addons)
- `Factories\SchemaFactory`
- `Contracts\CanProvideSchema`

Internal leakage currently used by runtime packages:

- `Reflection\ClassInfo` (dynamic, experimental)
- `Reflection\FunctionInfo` (dynamic, addons)
- `Reflection\PropertyInfo` (experimental)
- `Utils\AttributeUtils` (experimental)
- `Visitors\SchemaToJsonSchema` (instructor, dynamic)
- `Factories\ToolCallBuilder` (instructor)

## Public API Split

## Group A: Solid foundation for 2.0

These align with schema domain and should remain public/stable.

- `Contracts\CanProvideSchema`
- `Data\Schema\Schema`
- `Data\Schema\ObjectSchema`
- `Data\Schema\ScalarSchema`
- `Data\Schema\EnumSchema`
- `Data\Schema\CollectionSchema`
- `Data\Schema\ArraySchema` (keep, but tighten semantics)
- `Data\TypeDetails` (keep for now; may be slimmed)
- `Factories\SchemaFactory`
- `Factories\JsonSchemaToSchema` -> name needs change, contract is solid
- `Visitors\SchemaToJsonSchema` (through a contract/facade, see below)
- `Attributes\Description`
- `Attributes\Instructions`

Rationale:
- These symbols represent the core input/output vocabulary expected by `instructor`, `dynamic`, `addons`, and examples.
- Removing them immediately would force broad high-risk rewrites without architectural payoff.

## Group B: Questionable / must-change for cleaner API

These should be refactored out of the public API boundary or moved to domain-specific packages.

- `Cognesy\Inference\Factories\ToolCallBuilder`
- `Cognesy\Utils\Reflection\ClassInfo`
- `Cognesy\Utils\Reflection\FunctionInfo`
- `Cognesy\Utils\Reflection\PropertyInfo`
- `Cognesy\Utils\AttributeUtils`
- `Cognesy\Utils\Descriptions`
- `Cognesy\Utils\DocstringUtils`
- `Attributes\InputField` (should be moved to experimental if retained at all)
- `Attributes\OutputField` (should be moved to experimental if retained at all)
- `Attributes\SignatureField` (should be moved to experimental if retained at all)
- `Data\Schema\ArrayShapeSchema` (should be internal, not a public contract)
- `Data\Schema\ObjectRefSchema` (should be internal, not a public contract)
- granular exception classes as direct dependencies (`ReflectionException`, `SchemaMappingException`, `SchemaParsingException`, `TypeResolutionException`) for downstream packages

Rationale:
- They are utility-oriented internals, not stable schema-domain contracts.
- They encode implementation details (reflection/docstring extraction/object-ref rendering mechanics) that should be hidden behind contracts.
- Several are used by other packages only because no focused contract currently exists.

## Target public contract set (proposed)

Introduce explicit contracts and route all cross-package usage through them:

- `Contracts\CanProvideSchema` (existing)
- `Contracts\CanCreateSchema`
  - from class/type/value/callable/json-schema
- `Contracts\CanRenderSchema`
  - schema -> json schema
- `Contracts\CanParseSchema`
  - json schema -> schema
- `Contracts\CanDescribeSchema`
  - class/property/parameter descriptions (if retained at all)

Then make concrete classes (`SchemaFactory`, `JsonSchemaToSchema`, `SchemaToJsonSchema`) implementation details behind these contracts.

## Downstream impact map (must-change symbols)

## instructor

- Current dependencies:
  - `Visitors\SchemaToJsonSchema`
  - `Factories\ToolCallBuilder`
- Change:
  - use one schema rendering contract returning:
    - pure JSON schema
    - optional definitions payload
  - move function-tool envelope assembly from schema to instructor/polyglot boundary.

## dynamic

- Current dependencies:
  - `Reflection\ClassInfo`
  - `Reflection\FunctionInfo`
  - `Visitors\SchemaToJsonSchema`
- Change:
  - replace reflection helpers with:
    - Symfony reflection/TypeInfo directly, or
    - `CanCreateSchema` callable/class derivation contract
  - consume schema rendering contract, not visitor implementation.

## addons

- Current dependencies:
  - `Reflection\FunctionInfo`
- Change:
  - switch to callable schema extraction contract (or native reflection + TypeInfo).

## experimental

- Current dependencies:
  - `Reflection\ClassInfo`
  - `Reflection\PropertyInfo`
  - `Utils\AttributeUtils`
  - `Attributes\InputField` / `OutputField`
- Change:
  - move signature-specific attributes/helpers into `experimental` package.
  - use native reflection + Symfony attributes, avoid schema-utils dependency.

## agents docs/examples + cookbook examples

- Mostly `Attributes\Description` + `Instructions` usage.
- Keep as stable in 2.0 (low risk, high documentation value).

## Dynamic alignment (from `research/better-dynamic-structure-design.md`)

The dynamic redesign and schema boundary cleanup are directly aligned:

- Schema owns structure contracts and transforms.
- Dynamic owns runtime record processing pipeline (normalize/validate/hydrate/serialize), array-first.
- Dynamic should not depend on schema internals (`Reflection/*`, `Utils/*`, visitor concrete classes).

Recommended direction:

1. Keep packages separate in 2.0, but enforce contract-only dependency from dynamic -> schema.
2. Deprecate `StructureFactory` internals that rely on schema reflection utils.
3. Introduce `DynamicSchema` adapter over schema contracts while migrating away from mutable `Structure/Field`.
4. Reassess package merge only after dynamic runtime pipeline is stable and schema boundary is narrow.

## Architectural decisions suggested now

- Keep `schema` focused and narrow; do not let it remain a utility bucket.
- Move tool-call envelope construction out of schema package.
- Start deprecating direct cross-package imports of:
  - `Cognesy\Schema\Reflection\*`
  - `Cognesy\Schema\Utils\*`
  - `Cognesy\Schema\Factories\ToolCallBuilder`
- Preserve stable model-level surface (`Schema`, `TypeDetails`, factory/parser/renderer contracts).

## Acceptance mapping (instructor-8e35)

- Public inventory captured (all `packages/schema2/src` classes reviewed).
- Every class bucketed into foundation vs must-change.
- Redesign direction defined for each must-change category.
- Callsite impact mapped for `instructor`, `dynamic`, `addons`, `experimental`, `agents docs/examples`.
- Output is plan-ready and can be used directly for follow-up execution tasks.
