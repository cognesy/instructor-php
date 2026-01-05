# Refactoring Plan: Clean AgentBuilder

## Objective
Decouple `AgentBuilder` from specific capability implementations by removing hardcoded helper methods (`withBash`, `withFileTools`, etc.) and migrating all code to use the dynamic `withCapability()` method.

## Proposed Changes

### 1. AgentBuilder.php
- **Remove Hardcoded Helpers:** Delete `withBash`, `withFileTools`, `withSkills`, `withTaskPlanning`, and `withSubagents`.
- **Introduce `base()` Static Method:** Create `AgentBuilder::base()` which returns a builder pre-configured with:
    - Default continuation criteria (StepsLimit, TokenUsageLimit, etc.)
    - Default state processors (AccumulateTokenUsage, AppendContextMetadata, etc.)
- **Keep `new()` Static Method:** Keep it for a completely blank slate builder.

### 2. Migration of Usages
All occurrences of hardcoded helper calls will be replaced with `withCapability(new Use...())`.

#### Capabilities Mapping:
- `->withBash(...)` -> `->withCapability(new UseBash(...))`
- `->withFileTools(...)` -> `->withCapability(new UseFileTools(...))`
- `->withSkills(...)` -> `->withCapability(new UseSkills(...))`
- `->withTaskPlanning(...)` -> `->withCapability(new UseTaskPlanning(...))`
- `->withSubagents(...)` -> `->withCapability(new UseSubagents(...))`

#### Target Files:
- **Examples:**
    - `examples/B05_LLMExtras/AgentSubagents/run.php`
    - `examples/B05_LLMExtras/AgentOodaCycle/run.php`
    - `examples/B05_LLMExtras/AgentSearch/run.php`
    - `examples/B05_LLMExtras/AgentFileSys/run.php`
    - `examples/X01_Other/AgentOodaCycle/run.php`
    - `packages/addons/examples/agent-subagents.php`
- **Tests:**
    - `packages/addons/tests/Feature/Agent/AgentCapabilitiesTest.php` (will be updated to test `withCapability` primarily)
    - `packages/addons/tests/Feature/Agent/AgentWithBashToolTest.php` (if it uses builder helpers)
    - `packages/addons/tests/Feature/Agent/AgentWithFileToolsTest.php`
    - `packages/addons/tests/Feature/Agent/CodingAgentWorkflowTest.php`
- **Documentation:**
    - `packages/addons/AGENT.md`

### 3. Documentation Update
Update `AGENT.md` to:
- Reflect the new `withCapability` pattern as the primary way to extend agents.
- Document `AgentBuilder::base()` for common use cases.
- Remove references to hardcoded builder methods from the API tables.

## Verification
- Run all Agent feature tests: `vendor/bin/pest packages/addons/tests/Feature/Agent/`
- Verify examples run correctly (where feasible).
- Ensure no hardcoded capability dependencies remain in `AgentBuilder.php`.
