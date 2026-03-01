# Schema2 V2 API Surface Review (instructor-8e35)

Date: 2026-03-01  
Status: Revised after critical review

## Context

Current `Cognesy\Schema` is still too broad for a clean 2.0 boundary.  
Target boundary:

- schema model + schema conversion capabilities
- no generic reflection toolkit exports
- no package-specific convenience utilities
- no tool-call envelope assembly concerns



## Public API Split (revised)

### Group A: Foundation API for 2.0

Keep public and stable:

- `Contracts\CanProvideSchema`
- `Data\Schema\Schema` plus core nodes currently needed by runtime (`ObjectSchema`, `ScalarSchema`, `EnumSchema`, `CollectionSchema`, `ArraySchema`)
- `Data\TypeDetails` (temporary; keep only while downstream migration is incomplete)
- `Factories\SchemaFactory`
- `Factories\JsonSchemaToSchema` -> bad name, contract ok
- `Visitors\SchemaToJsonSchema` -> should not be public (temporary public; target migration to renderer contract)
- `Attributes\Description`
- `Attributes\Instructions`

### Group B: Must-change / deprecate for 2.0

Deprecate as public cross-package dependencies:

- `Cognesy\Schema\Reflection\ClassInfo`
- `Cognesy\Schema\Reflection\FunctionInfo`
- `Cognesy\Schema\Reflection\PropertyInfo`
- `Cognesy\Schema\Utils\AttributeUtils`
- `Cognesy\Schema\Utils\Descriptions`
- `Cognesy\Schema\Utils\DocstringUtils`
- `Cognesy\Schema\Factories\ToolCallBuilder`
- `Data\Schema\ArrayShapeSchema` (internal representation detail)
- `Data\Schema\ObjectRefSchema` (internal representation detail)
- `Attributes\InputField` and `Attributes\OutputField` (experimental-specific)
- direct downstream coupling to schema-internal exception classes

## Contract Strategy (minimal, not over-designed)

Do not introduce many new contracts at once.  
Use only two new contracts to constrain coupling:

1. `CanRenderSchema`  
Purpose: `Schema -> JSON Schema` with optional reference callback.

2. `CanParseJsonSchema`  
Purpose: `JSON Schema -> Schema`.

Everything else should continue through `SchemaFactory` for now.  
No new `CanDescribeSchema` contract in 2.0 (would reintroduce utility scope creep).

## Callsite Replacement Matrix (required)

### 1) Instructor

File: `packages/instructor/src/Creation/StructuredOutputSchemaRenderer.php`

Current:

- directly instantiates `ToolCallBuilder`
- directly uses `SchemaToJsonSchema`

Target:

- use `CanRenderSchema` for JSON Schema rendering
- move tool-call envelope assembly out of schema package (to instructor/polyglot boundary)
- schema package should return schema, not OpenAI-specific tool envelope structures

### 2) Dynamic

File: `packages/dynamic/src/StructureFactory.php`

Current:

- uses `Reflection\ClassInfo` and `Reflection\FunctionInfo`
- uses `JsonSchemaToSchema` and schema visitors directly

Target:

- stop importing `Cognesy\Schema\Reflection\*`
- derive callable/class metadata via native reflection + Symfony TypeInfo or thin dynamic-local adapters
- consume `SchemaFactory` + renderer/parser contracts only

### 3) Addons

File: `packages/addons/src/FunctionCall/FunctionCallFactory.php`

Current:

- imports `Reflection\FunctionInfo`

Target:

- use native reflection and/or dynamic-local callable analyzer
- remove schema reflection dependency

### 4) Experimental

Files:

- `packages/experimental/src/Signature/Factories/SignatureFromClassMetadata.php`
- `packages/experimental/src/Module/Modules/Prediction.php`

Current:

- imports `Reflection\ClassInfo`, `Reflection\PropertyInfo`, `Utils\AttributeUtils`
- uses schema attributes `InputField` / `OutputField`

Target:

- move attribute/reflection helpers to `experimental`
- if schema attributes remain, keep only generic schema attributes in schema package
- remove `Schema\Utils\*` imports from experimental runtime

## Dynamic Alignment

Align with `research/better-dynamic-structure-design.md`:

- schema defines structural model and conversion contracts
- dynamic owns record lifecycle (normalize, validate, hydrate, serialize)
- runtime value representation should be array-first
- dynamic should not require schema internals (`Reflection/*`, `Utils/*`, concrete visitor classes)

## Measurable Acceptance Gates

The study is only execution-ready if these gates are enforced:

1. Import bans in non-schema packages:
- no `use Cognesy\Schema\Reflection\*`
- no `use Cognesy\Schema\Utils\*`
- no `use Cognesy\Schema\Factories\ToolCallBuilder`

2. Boundary checks:
- tool-call envelope creation removed from schema package responsibility
- schema package exports schema capabilities, not provider-specific request wrappers

3. Reduction checks:
- LOC delta recorded per phase for `packages/schema2`
- LOC compared against previous pass and original `packages/schema` after each completed task

4. Verification checks:
- monorepo root `composer test` passes
- examples impacted by schema rendering/reflection pass

## Execution Order (pragmatic)

1. Replace `ToolCallBuilder` usage in instructor with local envelope assembler.
2. Introduce and adopt `CanRenderSchema`/`CanParseJsonSchema` facades.
3. Remove dynamic/addons/experimental imports of schema reflection and schema utils.
4. Move or delete now-unused schema internals.
5. Re-run tests/examples and validate LOC reduction gate.

## Final Assessment

The architecture direction remains valid, but the revised plan is intentionally narrower:

- fewer new abstractions
- explicit callsite-by-callsite replacements
- hard acceptance gates with measurable outcomes

This version is execution-ready for radical simplification work.



---


## Corrections to prior version

The previous draft had incorrect class namespaces in Group B. Correct forms:

- `Cognesy\Schema\Factories\ToolCallBuilder` (not `Cognesy\Inference\Factories\ToolCallBuilder`)
- `Cognesy\Schema\Reflection\ClassInfo` (not `Cognesy\Utils\Reflection\ClassInfo`)
- `Cognesy\Schema\Reflection\FunctionInfo` (not `Cognesy\Utils\Reflection\FunctionInfo`)
- `Cognesy\Schema\Reflection\PropertyInfo` (not `Cognesy\Utils\Reflection\PropertyInfo`)
- `Cognesy\Schema\Utils\AttributeUtils` (not `Cognesy\Utils\AttributeUtils`)
- `Cognesy\Schema\Utils\Descriptions` (not `Cognesy\Utils\Descriptions`)
- `Cognesy\Schema\Utils\DocstringUtils` (not `Cognesy\Utils\DocstringUtils`)

## Evidence Snapshot (runtime source imports)

Snapshot from current `packages/{instructor,dynamic,addons,experimental}/src`:

- `dynamic` imports `TypeDetails`, `Schema`, `SchemaFactory`, `JsonSchemaToSchema`, `SchemaToJsonSchema`, `Reflection\ClassInfo`, `Reflection\FunctionInfo`
- `instructor` imports `SchemaFactory`, `JsonSchemaToSchema`, `SchemaToJsonSchema`, `ToolCallBuilder`, `TypeDetails`, `Schema`
- `addons` imports `Reflection\FunctionInfo`, `Schema`, `CanProvideSchema`, `Description`
- `experimental` imports `Reflection\ClassInfo`, `Reflection\PropertyInfo`, `Utils\AttributeUtils`, `SchemaFactory`, `JsonSchemaToSchema`, `Schema`, `InputField`, `OutputField`

This confirms strong coupling to schema internals in `dynamic`, `addons`, and `experimental`.