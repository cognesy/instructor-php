# Runtime Migration - Completed Work

Last updated: 2026-02-15

This document contains completed runtime migration work moved out of `research/runtimes.md`.

## Completed Decisions

- Runtime contracts are additive and request-first:
  - `CanCreateInference::create(InferenceRequest $request): PendingInference`
  - `CanCreateEmbeddings::create(EmbeddingsRequest $request): PendingEmbeddings`
  - `CanCreateStructuredOutput::create(StructuredOutputRequest $request): PendingStructuredOutput`
- Request shapes were preserved in V1 (`InferenceRequest`, `EmbeddingsRequest`, `StructuredOutputRequest`).
- Structured output pipeline now composes inference via `CanCreateInference` (not direct facade construction).
- Facades currently implement runtime contracts directly and delegate to runtime classes internally:
  - `Inference implements CanCreateInference`
  - `Embeddings implements CanCreateEmbeddings`
  - `StructuredOutput implements CanCreateStructuredOutput`
- Agents/addons tool-use drivers now support direct creator injection with backward-compatible fallback.

## Completed Phase Summary

### Phase 1: Runtime Contracts + Runtime Classes

Done.

- Added contracts:
  - `packages/polyglot/src/Inference/Contracts/CanCreateInference.php`
  - `packages/polyglot/src/Embeddings/Contracts/CanCreateEmbeddings.php`
  - `packages/instructor/src/Contracts/CanCreateStructuredOutput.php`
- Added runtimes:
  - `packages/polyglot/src/Inference/InferenceRuntime.php`
  - `packages/polyglot/src/Embeddings/EmbeddingsRuntime.php`
  - `packages/instructor/src/StructuredOutputRuntime.php`

### Phase 2: StructuredOutput Pipeline Decoupling

Done (with compatibility bridge).

- `InferenceProvider` now builds `InferenceRequest` and calls `create($request)`:
  - `packages/instructor/src/Core/InferenceProvider.php`
- `ResponseIteratorFactory` now depends on `CanCreateInference`:
  - `packages/instructor/src/Creation/ResponseIteratorFactory.php`
- `StructuredOutputPipelineFactory` now depends on `CanCreateInference`:
  - `packages/instructor/src/Creation/StructuredOutputPipelineFactory.php`
- `StructuredOutputRuntime::create()` is implemented and used by facade:
  - `packages/instructor/src/StructuredOutputRuntime.php`
  - `packages/instructor/src/StructuredOutput.php`

Note: `InferenceProvider` still accepts `CanCreateInference|LLMProvider` for compatibility.

### Phase 3: Facade Delegation (Partial)

Partially done.

- `Inference::create()` delegates through `InferenceRuntime`:
  - `packages/polyglot/src/Inference/Inference.php`
- `StructuredOutput::create()` delegates through `StructuredOutputRuntime`:
  - `packages/instructor/src/StructuredOutput.php`
- `Embeddings::create()` delegates through `EmbeddingsRuntime`:
  - `packages/polyglot/src/Embeddings/Traits/HandlesInvocation.php`

Not done yet in this phase:
- No `toRuntime()` methods.
- No runtime static factories (`fromConfig`, `fromDsn`, `using`).
- No runtime caching/invalidation layer in facades.

### Phase 5: Internal Migrations (Agents/Addons) - Partial

Partially done for tool-use drivers and key wiring.

- Agents `ToolCallingDriver` now supports `?CanCreateInference` and builds `InferenceRequest`:
  - `packages/agents/src/Drivers/ToolCalling/ToolCallingDriver.php`
- Agents `ReActDriver` now supports:
  - `?CanCreateInference`
  - `?CanCreateStructuredOutput`
  - and builds `InferenceRequest` / `StructuredOutputRequest`:
  - `packages/agents/src/Drivers/ReAct/ReActDriver.php`
- Addons `ToolCallingDriver` migrated similarly:
  - `packages/addons/src/ToolUse/Drivers/ToolCalling/ToolCallingDriver.php`
- Addons `ReActDriver` migrated similarly:
  - `packages/addons/src/ToolUse/Drivers/ReAct/ReActDriver.php`
- Constructor wiring updated to inject creators directly in primary entry points:
  - `packages/agents/src/AgentLoop.php`
  - `packages/agents/src/Capability/Core/UseLLMConfig.php`
  - `packages/agents/src/Template/Factory/DefinitionLoopFactory.php`
  - `packages/addons/src/ToolUse/ToolUseFactory.php`

Backward compatibility retained:
- Fallback to facade path remains when creator is not injected.
- `CanAcceptLLMProvider` / `withLLMConfig()` behavior retained.

## Completed Validation Snapshots

- Runtime/facade behavior tests added and passing in prior migration steps:
  - `packages/polyglot/tests/Feature/MockHttp/InferenceOpenAIResponseTest.php`
  - `packages/polyglot/tests/Feature/MockHttp/EmbeddingsOpenAITest.php`
  - `packages/instructor/tests/Unit/StructuredOutputTest.php`
- Focused agents/addons tests pass after tool-use migration updates (local run).

## Intentionally Deferred to Remaining Plan

- Runtime extraction APIs (`toRuntime`) and runtime convenience factories.
- Facade runtime caching + invalidation.
- Laravel container/runtime singleton wiring and fake integration.
- Full agents/addons migration beyond tool-use drivers.
- Deprecation/removal pass for legacy fallback paths and `CanAcceptLLMProvider`.

## QA Notes (2026-02-15)

Accepted findings from design QA review:

1. Wiring sites currently inject `new Inference()` as `CanCreateInference`, not `InferenceRuntime` instances.
   - This is valid but not the final runtime-performance target because facade `create()` still rebuilds runtime internals per call.
   - Explicit runtime instance injection remains open work.
2. In `ReActDriver` injected `CanCreateStructuredOutput` path, `outputMode` / `maxRetries` are not carried by `StructuredOutputRequest`.
   - This is intentional for current config split: injected creator must already be configured.
   - Fallback facade path still sets these values locally.
3. Same behavior applies in addons `ReActDriver`.
   - Inline comments were added in both drivers to document this expectation.
4. Wiring currently injects `CanCreateInference` only in primary callsites.
   - `CanCreateStructuredOutput` injection wiring for ReAct remains incomplete and is tracked in remaining work.
