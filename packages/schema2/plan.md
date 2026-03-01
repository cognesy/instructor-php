# Schema2 Reimplementation Plan

## Objective
Reimplement `Cognesy\Schema` on a cleaner architecture while keeping essential public contracts stable, so `instructor`, `dynamic`, `addons`, and examples continue to work with minimal callsite changes.

## Scope
- In scope: `packages/schema2` only (new implementation under `Cognesy\Schema` namespace).
- Out of scope: deep refactors in `packages/dynamic` and `packages/instructor` beyond compatibility fixes.
- Target baseline: Symfony 8 `TypeInfo`-first design (no Symfony 6 compatibility).

## Constraints
- Preserve high-traffic API surface where possible:
  - `TypeDetails`
  - `SchemaFactory`
  - `Data\Schema\Schema` (and essential behavior)
  - `JsonSchemaToSchema`
  - `Visitors\SchemaToJsonSchema`
  - `ToolCallBuilder`
  - `Contracts\CanProvideSchema`
- Keep root behavior stable for:
  - Schema generation from class/type/value
  - JSON Schema rendering for structured output
  - Tool-call schema rendering with `$defs`
- Prefer additive compatibility adapters over broad downstream rewrites.

## Non-goals
- Perfect internal parity with legacy implementation details.
- Preserving dead/experimental internals (example: legacy compatibility adapters, unused abstractions).
- Solving full `dynamic` redesign in this phase.

## Working Model (Target Architecture)

### 1) Type Layer
- `TypeResolver` (new internal service): resolves runtime/property/parameter types using Symfony TypeInfo.
- `TypeDetails` remains public, but becomes a compact value object mapped from TypeInfo.
- Remove string roundtrip (`TypeInfo -> string -> parser -> TypeDetails`) from runtime resolution paths.

### 2) Schema Descriptor Layer
- Introduce a minimal internal descriptor model (DTOs or readonly objects), independent of visitor inheritance depth.
- Keep compatibility with `Data\Schema\*` public classes via thin adapters/facades.
- Normalize object forms:
  - class-bound object
  - shape object (untyped object with explicit properties)

### 3) Rendering Layer
- `SchemaToJsonSchema` rebuilt on descriptor model with explicit policy hooks:
  - `x-title`
  - `x-php-class`
  - `additionalProperties`
  - enum/option policy
- `ToolCallBuilder` + reference queue remain behaviorally compatible.

### 4) Parsing Layer
- `JsonSchemaToSchema` rebuilt with clear decision rules:
  - object with class binding
  - untyped object as shape (not lossy array fallback)
  - collection/array distinction

### 5) Reflection/Metadata Layer
- Replace dual adapter path with TypeInfo-only metadata resolver.
- Keep class/property/constructor metadata APIs only where used by runtime callsites.
- Use package-specific exceptions (no broad `\Exception` for domain errors).

## Migration Strategy

### Phase 0 - Baseline and Safety Net
- Freeze current behavioral baseline:
  - capture golden outputs for high-impact scenarios (schema rendering, defs, enum, recursive objects, nullable/union edge-cases).
- Build a parity matrix for key callsites:
  - `StructuredOutputSchemaRenderer`
  - `dynamic/StructureFactory`
  - `addons/FunctionCallFactory`

Acceptance:
- Golden fixture suite exists and fails on behavior regressions.

### Phase 1 - Skeleton and Contract Preservation
- Create `packages/schema2` package structure.
- Port essential public contracts/classes with compatibility-first stubs.
- Ensure autoloaded namespace resolves all existing imports.

Acceptance:
- Repo compiles with `Cognesy\Schema` pointing to `packages/schema2`.
- No missing class errors in static analysis/bootstrap.

### Phase 2 - TypeInfo Native Type Resolution
- Implement new `TypeResolver` and wire it into `PropertyInfo`/`ClassInfo` flows.
- Keep `TypeDetails` factory methods public; internally delegate to TypeResolver where relevant.
- Remove Symfony 6 compatibility path.

Acceptance:
- Property/class type resolution parity for core fixtures.
- No legacy v6 adapter usage.

### Phase 3 - Schema Descriptor + Renderer
- Implement descriptor model and JSON Schema renderer.
- Add compatibility adapters to existing public schema classes.
- Preserve tool-call schema shape including `$defs` behavior.

Acceptance:
- Golden snapshots for `SchemaToJsonSchema` and tool-call output pass.

### Phase 4 - JSON Schema -> Schema Rebuild
- Reimplement `JsonSchemaToSchema` on descriptor model.
- Fix known lossy conversion of untyped objects.

Acceptance:
- Roundtrip tests (`schema -> json -> schema -> json`) stable for supported patterns.

### Phase 5 - Compatibility Hardening
- Map legacy exceptions to new domain exceptions while preserving actionable messages.
- Remove deprecated internal-only classes not needed by runtime.
- Update schema package docs (README + CHEATSHEET) to match schema2 behavior.

Acceptance:
- Docs/code parity verified for public APIs.
- No references to removed internals in docs.

### Phase 6 - Cross-Package Verification
- Run focused tests for dependent packages (`instructor`, `dynamic`, `addons`).
- Run representative real examples that hit structured output/tool-call flows.
- Fix regressions or add explicit compatibility shims.

Acceptance:
- Core dependent package suites pass.
- Critical examples pass and outputs match expected shape.

### Phase 7 - Decision Gate
- Evaluate regression count, complexity, and unresolved gaps.
- Decide:
  - Proceed with schema2 as default, or
  - Roll back root mapping to legacy `packages/schema`.

Acceptance:
- Decision documented with objective metrics (open regressions, blocked scenarios, effort-to-fix).

## Rollback Plan
- Rollback is a root autoload remap:
  - `composer.json` `Cognesy\Schema` and `Cognesy\Schema\Tests` back to `packages/schema`.
- Keep schema2 work isolated so rollback is low-risk and fast.

## Deliverables
- `packages/schema2` implementation with stable essential contracts.
- Migration notes for behavioral differences.
- Test matrix and parity report.
- Follow-up task list for `dynamic` redesign on top of schema2.

## Task Breakdown (Execution Order)
1. Build baseline fixtures and parity matrix.
2. Create schema2 skeleton and contract stubs.
3. Implement TypeInfo native resolver.
4. Implement descriptor model and renderer.
5. Implement JSON Schema parser.
6. Add compatibility shims and exceptions.
7. Align docs with implemented behavior.
8. Validate across dependent packages and examples.
9. Make go/no-go decision for full switch.

## Exit Criteria (Done)
- Essential `Cognesy\Schema` public contracts are operational from `packages/schema2`.
- Structured output and tool-call schema generation are regression-clean for critical flows.
- Remaining gaps are documented and non-blocking, or rollback decision is executed.
