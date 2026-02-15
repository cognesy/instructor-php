# Agents Refactor Plan: Remove AgentInterface/BaseAgent Boundary Smell

## Objective

Refactor `packages/agents` so runtime loop control and template metadata are cleanly separated.

Current pain points to eliminate:
- `AgentInterface` mixes runtime behavior (`CanControlAgentLoop`) with metadata accessor (`descriptor()`).
- `BaseAgent` introduces inheritance ceremony and event-wiring edge cases.
- Template pipeline (`AgentBlueprint` / `AgentDefinitionFactory`) is coupled to this mixed runtime+metadata contract.

This is a clean-slate refactor (no backward compatibility layer).

## Scope

In-scope:
- All code under `packages/agents/src` and `packages/agents/tests`.
- Agents package docs/examples (`packages/agents/docs`, `examples/D01_Agents`, `examples/D02_AgentBuilder`).

Out-of-scope for this pass:
- `packages/addons`, `packages/hub`, and other package APIs (can be handled in follow-up migration PR).

## Target Architecture

### 1) Runtime layer
- Primary runtime contract: `CanControlAgentLoop`.
- `AgentLoop` remains the runtime execution primitive.
- Composition remains capability-driven via `AgentBuilder` (`withCapability(...)->build()`).

### 2) Template layer
- `AgentDefinition` is the metadata source of truth (name, description, tools, capabilities, limits, etc.).
- `AgentDescriptor` is removed.

### 3) Blueprint/factory boundary
- `AgentBlueprint::fromDefinition(...)` returns `CanControlAgentLoop`.
- `AgentDefinitionFactory::create(...)` returns `CanControlAgentLoop`.
- No runtime `descriptor()` requirement at this boundary.

### 4) Remove inheritance adapter
- Remove `BaseAgent`.
- Blueprints/factories produce loop instances directly (typically via `AgentBuilder`).

## Design Decisions

1. Keep event APIs on loop objects (`wiretap`, `onEvent`, `withEventHandler`) where needed.
2. Do not replace `BaseAgent` with another inheritance base class.
3. If a higher-level facade is needed later, introduce it as composition-only, not as core contract.

## Execution Plan

## Phase 1: Contract and Type Cleanup

1. Delete:
- `src/AgentBuilder/Contracts/AgentInterface.php`
- `src/AgentBuilder/Data/AgentDescriptor.php`
- `src/AgentBuilder/Support/BaseAgent.php`

2. Update template contracts:
- `src/AgentTemplate/Contracts/AgentBlueprint.php`
  - return type -> `CanControlAgentLoop`

3. Update factory:
- `src/AgentTemplate/Definitions/AgentDefinitionFactory.php`
  - return type -> `CanControlAgentLoop`

Acceptance criteria:
- No compile references to `AgentInterface`, `AgentDescriptor`, `BaseAgent` in `packages/agents/src`.

## Phase 2: Blueprint/Test Refactor

Refactor unit tests that currently rely on `BaseAgent` subclasses:
- `tests/Unit/Agent/AgentDefinitionFactoryTest.php`
- `tests/Unit/Agent/AgentBlueprintRegistryTest.php`
- `tests/Unit/Agent/AgentDefinitionEventsTest.php`
- `tests/Unit/Agent/AgentDescriptorTest.php` (remove/replace with definition-centric assertions)

Approach:
- Replace test blueprint return values with direct `AgentBuilder::base()->...->build()` loops.
- Where tests asserted `descriptor()->name`, assert against expected factory/registry behavior without descriptor coupling.

Acceptance criteria:
- All impacted tests compile and pass.

## Phase 3: Template Metadata Consistency

1. Ensure `AgentDefinition` fully covers metadata needs formerly represented by `AgentDescriptor` in tests/docs.
2. Remove any residual descriptor-based assumptions.

Acceptance criteria:
- No metadata pathway depends on runtime loop contract.

## Phase 4: Docs and Examples Cleanup (Agents Package)

Update docs/examples under scope so they do not mention removed APIs/types:
- remove mentions of `AgentInterface`, `BaseAgent`, `AgentDescriptor`.
- describe template output as `CanControlAgentLoop` / loop instance.

Acceptance criteria:
- `rg` over scoped docs/examples shows no stale references.

## Phase 5: Quality Gates

Run and fix until green:
1. `vendor/bin/pest packages/agents/tests`
2. targeted static analysis for changed files:
   - `vendor/bin/phpstan analyse <changed agents paths> --no-progress --memory-limit=1G`

Note:
- Full-package phpstan currently reports many pre-existing issues; we will enforce no new violations on touched files.

## Migration Map (Old -> New)

- `AgentBlueprint::fromDefinition(...): AgentInterface`
  -> `AgentBlueprint::fromDefinition(...): CanControlAgentLoop`

- `AgentDefinitionFactory::create(...): AgentInterface`
  -> `AgentDefinitionFactory::create(...): CanControlAgentLoop`

- `BaseAgent` subclass
  -> direct loop composition via `AgentBuilder`

- `descriptor()` runtime accessor
  -> metadata via `AgentDefinition` / template domain only

## Risks and Mitigations

1. Risk: hidden references in tests/docs.
- Mitigation: exhaustive `rg` sweeps after each phase.

2. Risk: event-wiring expectations encoded in tests built around `BaseAgent`.
- Mitigation: move tests to assert loop-level event behavior directly.

3. Risk: downstream packages (`addons`, `hub`) still referencing removed types.
- Mitigation: keep this PR scoped to `packages/agents`; create follow-up migration issue.

## Definition of Done

1. `AgentInterface`, `BaseAgent`, `AgentDescriptor` removed from `packages/agents/src`.
2. `AgentTemplate` contracts/factory return `CanControlAgentLoop`.
3. Agents tests pass.
4. Scoped docs/examples updated.
5. No stale references in `packages/agents` scope.
