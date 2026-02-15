# Template Cleanup (No DI Phase)

## Goal
Strip Template to a clean, declarative core before introducing container-driven composition.

## Scope for this phase
- Keep instantiation explicit and simple.
- Do not move to DI/container wiring yet.
- Remove blueprint-based indirection that duplicates `CanInstantiateAgentLoop`.

## Target structure

### Keep
- `Template/Definitions/AgentDefinition`
- `Template/Definitions/AgentDefinitionLoader`
- `Template/Definitions/AgentDefinitionRegistry`
- `Template/Definitions/DefinitionLoopFactory`
- `Template/Definitions/DefinitionStateFactory`
- `Template/Contracts/CanInstantiateAgentLoop`
- `Template/Contracts/CanInstantiateAgentState`

### Remove
- `Template/Contracts/AgentBlueprint`
- `Template/Registry/AgentBlueprintRegistry`
- `Template/Definitions/AgentDefinitionFactory`
- Blueprint-related fields in `AgentDefinition` (`blueprint`, `blueprintClass`)
- Blueprint-only exception paths

## Why remove blueprints now
- `AgentBlueprint::fromDefinition(...)` duplicates loop-instantiation responsibility already covered by `CanInstantiateAgentLoop`.
- It introduces an imperative escape hatch that weakens data-driven definition semantics.
- It adds extra classes and failure modes without production usage.

## Updated data flow
1. `<source format>` -> array
2. array -> `AgentDefinition`
3. `DefinitionLoopFactory::instantiateAgentLoop($definition)` -> `CanControlAgentLoop`
4. `DefinitionStateFactory::instantiateAgentState($definition, $seed)` -> `AgentState`

## Next phase (explicitly deferred)
- Introduce container-aware resolver layer for capability/tool lookup.
- Keep Template definitions and state hydration container-agnostic.
