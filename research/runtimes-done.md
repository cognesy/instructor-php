# Runtime Migration - Completed Work

Last updated: 2026-02-16

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
- Agents/addons tool-use drivers now require constructor-provided runtime creators (no facade fallback path).
- `InferenceRuntime` pricing now uses strategy contract resolution:
  - constructor accepts `?CanResolveInferencePricing`
  - default factories wire `StaticPricingResolver` from resolved `LLMConfig` pricing.
- Event handling for agent drivers is constructor-owned:
  - no automatic driver event rebinding in builder/subagent paths
  - built-in drivers (`ToolCallingDriver`, `ReActDriver`) no longer expose `withEventHandler()`
- Laravel integration package now exposes runtime creator contracts as container singletons:
  - `CanCreateInference`
  - `CanCreateStructuredOutput`
  - `CanCreateEmbeddings`

## Completed Phase Summary

### Phase 0: Invariants and Regression Safety

Done.

- Added regression coverage for constructor-owned runtime/driver event wiring behavior:
  - `packages/agents/tests/Unit/Agent/AgentBuilderEventWiringTest.php`
- Added explicit constructor named-args regression coverage for request contracts:
  - `packages/polyglot/tests/Unit/Inference/InferenceRequestNamedArgsTest.php`
  - `packages/polyglot/tests/Unit/Embeddings/EmbeddingsRequestTest.php` (extended)
  - `packages/instructor/tests/Unit/Data/StructuredOutputRequestTest.php`
- Added explicit precedence invariants (`request field override -> runtime/provider default`) at merge boundaries:
  - `packages/polyglot/tests/Unit/Drivers/OpenAI/OpenAIBodyFormatPrecedenceTest.php`
  - `packages/polyglot/tests/Unit/Embeddings/Drivers/OpenAI/OpenAIBodyFormatPrecedenceTest.php`
  - `packages/instructor/tests/Unit/RequestMaterializerTest.php` (extended)

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

Done.

- `InferenceProvider` now builds `InferenceRequest` and calls `create($request)` with
  `CanCreateInference`-only constructor wiring (no `LLMProvider` union bridge):
  - `packages/instructor/src/Core/InferenceProvider.php`
- `ResponseIteratorFactory` now depends on `CanCreateInference`:
  - `packages/instructor/src/Creation/ResponseIteratorFactory.php`
- `StructuredOutputPipelineFactory` now depends on `CanCreateInference`:
  - `packages/instructor/src/Creation/StructuredOutputPipelineFactory.php`
- `StructuredOutputRuntime::create()` is implemented and used by facade:
  - `packages/instructor/src/StructuredOutputRuntime.php`
  - `packages/instructor/src/StructuredOutput.php`

### Phase 3: Facade Delegation + Runtime Caching (Partial)

Partially done.

- `Inference::create()` delegates through `InferenceRuntime`:
  - `packages/polyglot/src/Inference/Inference.php`
- `StructuredOutput::create()` delegates through `StructuredOutputRuntime`:
  - `packages/instructor/src/StructuredOutput.php`
- `Embeddings::create()` delegates through `EmbeddingsRuntime`:
  - `packages/polyglot/src/Embeddings/Traits/HandlesInvocation.php`
- Added runtime extraction APIs on facades:
  - `Inference::toRuntime()`
  - `StructuredOutput::toRuntime()`
  - `Embeddings::toRuntime()`
- Added runtime convenience factories:
  - `InferenceRuntime::fromConfig()/fromResolver()/fromProvider()/fromDsn()/using()`
  - `EmbeddingsRuntime::fromConfig()/fromResolver()/fromProvider()/fromDsn()/using()`
  - `StructuredOutputRuntime::fromConfig()/fromResolver()/fromProvider()/fromDsn()/using()`
- Completed pricing strategy migration in inference runtime construction:
  - Added `CanResolveInferencePricing` and `StaticPricingResolver`.
  - `InferenceRuntime` constructor now accepts resolver strategy instead of raw `Pricing`.
  - `InferenceRuntime::fromConfig()/fromResolver()` and facade runtime builders now wire `StaticPricingResolver`.
- Added facade runtime caching and explicit typed invalidation boundaries:
  - `packages/polyglot/src/Inference/Inference.php`
  - `packages/polyglot/src/Inference/Traits/HandlesLLMProvider.php`
  - `packages/polyglot/src/Embeddings/Embeddings.php`
  - `packages/polyglot/src/Embeddings/Traits/HandlesInitMethods.php`
  - `packages/polyglot/src/Embeddings/Traits/HandlesInvocation.php`
  - `packages/instructor/src/StructuredOutput.php`
  - Infrastructure mutators invalidate caches; request-only mutators do not.
  - Facade `create()` paths delegate through cached `toRuntime()` instances.
  - Driver factories are event-bus-aware and rebuild when bus identity changes.
- Completed facade immutability/call-state boundary hardening for core facades:
  - `Inference`, `Embeddings`, `StructuredOutput` `with*()` methods now return modified clones
    instead of mutating the current instance.
  - Mutable internals are detached during mutation (`InferenceRequestBuilder`,
    `EmbeddingsProvider`, `StructuredOutputConfigBuilder`) to prevent cross-instance state bleed.
  - `StructuredOutput::stream()` now composes via immutable chaining
    (`withStreaming()->create()->stream()`), avoiding lost state under immutable semantics.
- Replaced practical remaining facade-as-contract usage with explicit runtime creator path:
  - `packages/agents/src/Capability/StructuredOutput/StructuredOutputTool.php`
    fallback now uses `StructuredOutputRuntime::fromProvider(...)->create($request)` instead of facade `get()`.
  - `packages/evals/src/Observers/Evaluate/LLMGradedCorrectnessEval.php`
    now depends on `CanCreateStructuredOutput` and defaults to preconfigured
    `StructuredOutputRuntime::fromProvider(...)`.
  - `packages/evals/src/Observers/Evaluate/LLMBooleanCorrectnessEval.php`
    now depends on `CanCreateStructuredOutput` and defaults to preconfigured
    `StructuredOutputRuntime::fromProvider(...)`.
- Removed remaining facade-default hotspot construction in legacy helpers:
  - `packages/experimental/src/Module/Core/Predictor.php`
  - `packages/experimental/src/ModPredict/Core/Predictor.php`
  - `packages/experimental/src/RLM/Drivers/StrictRlmDriver.php`
  - `packages/instructor/src/Extras/Mixin/HandlesInference.php`
  now default to explicit runtime creators (`InferenceRuntime` / `StructuredOutputRuntime`) and request-first `create($request)` calls.
- Completed breaking alias-removal sweep:
  - Removed alias methods from core surfaces:
    - `packages/polyglot/src/Inference/Traits/HandlesLLMProvider.php` (`withConfig`, `withDebugPreset`)
    - `packages/polyglot/src/Inference/LLMProvider.php` (`withConfig`)
    - `packages/polyglot/src/Embeddings/Traits/HandlesInitMethods.php` (`withDebugPreset`)
  - Removed alias exposure in Laravel integration surfaces:
    - `packages/laravel/src/Facades/Inference.php`
    - `packages/laravel/src/Facades/Embeddings.php`
    - `packages/laravel/src/Facades/StructuredOutput.php`
    - `packages/laravel/src/Testing/InferenceFake.php`
    - `packages/laravel/src/Testing/EmbeddingsFake.php`
    - `packages/laravel/src/Testing/StructuredOutputFake.php`
  - Migrated remaining internal code/docs/examples to canonical names (`withLLMConfig`, `withHttpDebugPreset`):
    - `packages/agents/src/Template/Factory/DefinitionLoopFactory.php`
    - `packages/agents/tests/Feature/Core/AgentLoopTest.php`
    - `packages/polyglot/tests/Unit/Provider/LLMProviderConfigTest.php`
    - `packages/hub/examples/B02_LLMAdvanced/CustomClientParameters/run.php`
    - `packages/hub/examples/B02_LLMAdvanced/CustomLLMDriver/run.php`
    - `packages/polyglot/docs/essentials/inference-class.md`
    - `packages/polyglot/docs/advanced/custom-config.md`
    - `packages/polyglot/docs/internals/providers.md`
    - `packages/polyglot/docs/internals/public-api.md`
    - `packages/polyglot/docs/embeddings/overview.md`
    - `packages/polyglot/docs/troubleshooting/debugging.md`
    - `packages/polyglot/docs/troubleshooting/issues-configuration.md`
    - `packages/polyglot/CHEATSHEET.md`
    - `packages/instructor/docs/essentials/configuration.md`
    - `packages/instructor/docs/internals/debugging.md`

### Phase 4: Laravel Container + Testing Surface

Done.

- Registered runtime creator contracts as singletons in `InstructorServiceProvider`:
  - `packages/laravel/src/InstructorServiceProvider.php`
    - `CanCreateInference => Inference::toRuntime()`
    - `CanCreateStructuredOutput => StructuredOutput::toRuntime()`
    - `CanCreateEmbeddings => Embeddings::toRuntime()`
- Kept facade class bindings and fakes coexisting with runtime contract bindings.
- Added Laravel integration regression coverage for container-first creator paths and coexistence:
  - `packages/instructor/tests/Integration/Laravel/RuntimeContractBindingsTest.php`

### Phase 5: Internal Migrations (Agents/Addons)

Done for default/internal callsites and tool-use drivers.

- Agents `ToolCallingDriver` now requires `CanCreateInference` and builds `InferenceRequest`:
  - `packages/agents/src/Drivers/ToolCalling/ToolCallingDriver.php`
- Agents `ReActDriver` now requires:
  - `CanCreateInference`
  - `CanCreateStructuredOutput`
  - and builds `InferenceRequest` / `StructuredOutputRequest`:
  - `packages/agents/src/Drivers/ReAct/ReActDriver.php`
- Addons `ToolCallingDriver` migrated similarly:
  - `packages/addons/src/ToolUse/Drivers/ToolCalling/ToolCallingDriver.php`
- Addons `ReActDriver` migrated similarly:
  - `packages/addons/src/ToolUse/Drivers/ReAct/ReActDriver.php`
- Constructor wiring added/updated to inject creators directly in primary entry points:
  - `packages/agents/src/AgentLoop.php`
  - `packages/agents/src/Capability/Core/UseLLMConfig.php`
  - `packages/agents/src/Capability/Core/UseReActConfig.php` (added)
  - `packages/agents/src/Template/Factory/DefinitionLoopFactory.php`
  - `packages/addons/src/ToolUse/ToolUseFactory.php`
- Wiring now injects explicit `InferenceRuntime` instances in those entry points
  (not facade instances typed as `CanCreateInference`).
- Added explicit ReAct runtime-first wiring paths with both creators constructor-provided:
  - `UseReActConfig` requires both `CanCreateInference` and `CanCreateStructuredOutput` in constructor.
  - `ToolUseFactory::react(...)` requires both `CanCreateInference` and `CanCreateStructuredOutput` inputs.
- Removed automatic driver event rebinding and switched to constructor-owned event injection:
  - `packages/agents/src/Builder/AgentConfigurator.php`
  - `packages/agents/src/Capability/Subagent/SpawnSubagentTool.php`
  - `packages/agents/src/Drivers/ToolCalling/ToolCallingDriver.php` (`CanAcceptEventHandler` removed)
  - `packages/agents/src/Drivers/ReAct/ReActDriver.php` (`CanAcceptEventHandler` removed)
- Migrated initial non-driver agents callsites from facades to creator contracts:
  - `packages/agents/src/Capability/SelfCritique/SelfCriticHook.php`
  - `packages/agents/src/Capability/Summarization/Utils/SummarizeMessages.php`
  - `packages/agents/src/Capability/StructuredOutput/StructuredOutputTool.php`
- Breaking alignment update for self-critique runtime wiring:
  - `UseSelfCritique` / `SelfCriticHook` now require injected `CanCreateStructuredOutput`
  - no local `llmPreset` knob and no fallback `new StructuredOutput()` path
- Migrated remaining non-driver addons callsites from facades to creator contracts:
  - `packages/addons/src/Chat/Participants/LLMParticipant.php`
  - `packages/addons/src/Collaboration/Collaborators/LLMCollaborator.php`
  - `packages/addons/src/Chat/Selectors/LLMSelector/LLMBasedCoordinator.php`
  - `packages/addons/src/Collaboration/Selectors/LLMSelector/LLMBasedCoordinator.php`
  - `packages/addons/src/Chat/Utils/SummarizeMessages.php`
  - `packages/addons/src/Collaboration/Utils/SummarizeMessages.php`
  - `packages/addons/src/Image/Image.php`
- Removed remaining public compatibility fallback bridges in helper APIs:
  - `packages/addons/src/Image/Image.php`
    - `Image::toData(...)` now requires `CanCreateStructuredOutput` and no longer builds runtime fallback internally.
  - `packages/auxiliary/src/Web/Filters/EmbeddingsSimilarityFilter.php`
    - constructor now requires `CanCreateEmbeddings` and no longer defaults to preset-based runtime creation.
  - `packages/polyglot/src/Embeddings/Utils/EmbedUtils.php`
    - `findSimilar(...)` now accepts `CanCreateEmbeddings` only (provider union bridge removed).
- Enforced required creator injection in remaining addons constructor-resolved classes:
  - `packages/addons/src/Chat/Participants/LLMParticipant.php` (`CanCreateInference` required)
  - `packages/addons/src/Collaboration/Collaborators/LLMCollaborator.php` (`CanCreateInference` required)
  - `packages/addons/src/Chat/Utils/SummarizeMessages.php` (`CanCreateInference` required)
  - `packages/addons/src/Collaboration/Utils/SummarizeMessages.php` (`CanCreateInference` required)
- Removed facade fallback branches in tool-use drivers (`new Inference()` / `new StructuredOutput()`) and made creators constructor-required:
  - `packages/agents/src/Drivers/ToolCalling/ToolCallingDriver.php`
  - `packages/agents/src/Drivers/ReAct/ReActDriver.php`
  - `packages/addons/src/ToolUse/Drivers/ToolCalling/ToolCallingDriver.php`
  - `packages/addons/src/ToolUse/Drivers/ReAct/ReActDriver.php`
- Removed `CanAcceptLLMProvider` from runtime-first agent drivers; subagent rebinding now uses `CanAcceptLLMConfig`:
  - `packages/agents/src/Drivers/ToolCalling/ToolCallingDriver.php`
  - `packages/agents/src/Drivers/ReAct/ReActDriver.php`
  - `packages/agents/src/Capability/Subagent/UseSubagents.php`
  - `packages/agents/src/Capability/Subagent/SpawnSubagentTool.php`

Breaking alignment applied:
- Tool-use drivers no longer instantiate `Inference` / `StructuredOutput` facades as fallback.
- `withLLMConfig()` behavior remains available where implemented.
- Event bus ownership remains constructor-first (no hidden rebinding).

## Completed Validation Snapshots

- Runtime/facade behavior tests added and passing in prior migration steps:
  - `packages/polyglot/tests/Feature/MockHttp/InferenceOpenAIResponseTest.php`
  - `packages/polyglot/tests/Feature/MockHttp/EmbeddingsOpenAITest.php`
  - `packages/instructor/tests/Unit/StructuredOutputTest.php`
- Full addons suite passes after non-driver addons migration:
  - `vendor/bin/pest packages/addons/tests`
  - Result snapshot: `160 passed`, `1 skipped`
- Instructor pipeline tests pass after removing `InferenceProvider` union bridge:
  - `vendor/bin/pest packages/instructor/tests/Unit/SyncUpdateGeneratorTest.php packages/instructor/tests/Unit/AttemptIteratorTest.php packages/instructor/tests/Feature/ModularPipeline/ModularUpdateGeneratorTest.php`
  - Result snapshot: `20 passed`
- Full agents suite passes after runtime-first driver contract cleanup:
  - `vendor/bin/pest packages/agents/tests`
  - Result snapshot: `317 passed`
- Added and validated explicit regression guard for contract boundary:
  - `packages/agents/tests/Unit/Agent/RuntimeFirstDriverContractsTest.php`
  - Asserts runtime-first drivers no longer implement `CanAcceptLLMProvider`
- Added and validated explicit Phase 0 request + precedence regression tests:
  - `vendor/bin/pest packages/polyglot/tests/Unit/Inference/InferenceRequestNamedArgsTest.php packages/polyglot/tests/Unit/Embeddings/EmbeddingsRequestTest.php packages/polyglot/tests/Unit/Drivers/OpenAI/OpenAIBodyFormatPrecedenceTest.php packages/polyglot/tests/Unit/Embeddings/Drivers/OpenAI/OpenAIBodyFormatPrecedenceTest.php packages/instructor/tests/Unit/Data/StructuredOutputRequestTest.php packages/instructor/tests/Unit/RequestMaterializerTest.php`
  - Result snapshot: `14 passed`
- Validated runtime-path replacements in agents/evals:
  - `vendor/bin/pest packages/agents/tests/Feature/Capabilities/StructuredOutputCapabilityTest.php packages/agents/tests/Unit/Agent/ToolExecutionErrorMessageTest.php`
  - Result snapshot: `3 passed`
  - `vendor/bin/pest packages/evals/tests`
  - Result snapshot: `50 passed`
- Validated legacy hotspot runtime migration:
  - `vendor/bin/pest packages/experimental/tests/ModuleTest.php packages/instructor/tests/Feature/Instructor/Extras/MixinTest.php packages/instructor/tests/Feature/Instructor/DataExtraction/BasicDataExtractionModesTest.php`
  - Result snapshot: `28 passed`
- Validated Laravel runtime container bindings and coexistence:
  - `vendor/bin/pest packages/instructor/tests/Integration/Laravel/RuntimeContractBindingsTest.php`
  - Result snapshot: `2 passed`
- Validated facade runtime cache reuse/invalidation boundaries:
  - `vendor/bin/pest packages/polyglot/tests/Unit/Inference/InferenceRuntimeCacheTest.php packages/polyglot/tests/Unit/Embeddings/EmbeddingsRuntimeCacheTest.php packages/instructor/tests/Unit/StructuredOutputRuntimeCacheTest.php`
  - Result snapshot: `15 passed`
- Revalidated facade/runtime extraction behavior and StructuredOutput baseline after caching:
  - `vendor/bin/pest packages/polyglot/tests/Feature/MockHttp/InferenceOpenAIResponseTest.php packages/polyglot/tests/Feature/MockHttp/EmbeddingsOpenAITest.php packages/instructor/tests/Unit/StructuredOutputTest.php`
  - Result snapshot: `11 passed`
- Validated immutable facade behavior across package suites:
  - `vendor/bin/pest packages/polyglot/tests packages/instructor/tests packages/agents/tests packages/addons/tests`
  - Result snapshot: `1138 passed`, `1 skipped`
- Rechecked previously reported failing test:
  - `vendor/bin/pest packages/experimental/tests/ModPredict/ManagedExecutorTest.php`
  - Result snapshot: `2 passed`
- Validated breaking alias-removal sweep:
  - `vendor/bin/pest packages/polyglot/tests/Unit/Inference/InferenceRuntimeCacheTest.php packages/polyglot/tests/Unit/Embeddings/EmbeddingsRuntimeCacheTest.php packages/polyglot/tests/Unit/Provider/LLMProviderConfigTest.php packages/instructor/tests/Integration/Laravel`
  - Result snapshot: `15 passed`
- Validated post-release compatibility bridge removals and contract locking:
  - `vendor/bin/pest packages/addons/tests/Regression/ImageToDataTest.php packages/addons/tests/Unit/Tools/ToolUseFactoryReActRuntimeTest.php packages/agents/tests/Unit/Agent/RuntimeFirstDriverContractsTest.php packages/polyglot/tests/Unit/Embeddings/EmbedUtilsRuntimeContractTest.php packages/auxiliary/tests/Unit/Web/EmbeddingsSimilarityFilterRuntimeContractTest.php`
  - Result snapshot: `9 passed`
- Validated pricing strategy contract migration:
  - `vendor/bin/pest packages/polyglot/tests/Unit/Inference/Pricing/StaticPricingResolverTest.php packages/polyglot/tests/Unit/Inference/InferenceRuntimeCacheTest.php packages/instructor/tests/Integration/Laravel/RuntimeContractBindingsTest.php`
  - Result snapshot: `10 passed`
- Static analysis on changed files:
  - `vendor/bin/phpstan analyse packages/addons/src/Image/Image.php packages/auxiliary/src/Web/Filters/EmbeddingsSimilarityFilter.php packages/polyglot/src/Embeddings/Utils/EmbedUtils.php packages/agents/tests/Unit/Agent/RuntimeFirstDriverContractsTest.php packages/addons/tests/Unit/Tools/ToolUseFactoryReActRuntimeTest.php packages/addons/tests/Regression/ImageToDataTest.php packages/polyglot/tests/Unit/Embeddings/EmbedUtilsRuntimeContractTest.php packages/auxiliary/tests/Unit/Web/EmbeddingsSimilarityFilterRuntimeContractTest.php packages/polyglot/tests/Unit/Inference/Pricing/StaticPricingResolverTest.php packages/polyglot/src/Inference/Pricing/StaticPricingResolver.php packages/polyglot/src/Inference/InferenceRuntime.php --no-progress`
  - Result snapshot: `No errors`

## Decision Log: Pricing Constructor

- Decision date: 2026-02-16
- Decision: replace `InferenceRuntime(..., ?Pricing $pricing)` with pricing strategy contract
  `InferenceRuntime(..., ?CanResolveInferencePricing $pricingResolver)`.
- Rationale:
  - Removes config-value coupling from runtime constructor while preserving current behavior.
  - Keeps pricing resolution extensible for per-request pricing in future providers.
  - Runtime factory and Laravel wiring paths remain stable (`fromProvider`/`fromResolver` carry resolver wiring).
- Follow-up:
  - If dynamic pricing rules are needed, implement additional `CanResolveInferencePricing` strategies.

## QA Notes (2026-02-15)

Accepted findings from design QA review:

1. Wiring sites currently inject `new Inference()` as `CanCreateInference`, not `InferenceRuntime` instances.
   - Update: resolved for primary tool-calling wiring sites (`AgentLoop`, `UseLLMConfig`, `DefinitionLoopFactory`, `ToolUseFactory`) by injecting explicit `InferenceRuntime` instances.
   - ReAct-oriented convenience wiring now also requires explicit creator injection (`UseReActConfig`, `ToolUseFactory::react(...)`).
   - Event-bus note: `AgentLoop::default()`, `ToolUseFactory::default()`, `DefinitionLoopFactory`, and `UseLLMConfig` now pass shared agent events into runtime factories.
   - Event wiring model update (breaking): agent builder/subagent paths no longer rebind driver event handlers; events are constructor-owned.
   - `UseLLMConfig` uses new `CanConfigureAgent::events()` accessor to avoid isolated runtime buses.
2. In `ReActDriver` injected `CanCreateStructuredOutput` path, `outputMode` / `maxRetries` are not carried by `StructuredOutputRequest`.
   - This is intentional for current config split: injected creator must already be configured.
   - Driver fallback runtime-construction path was later removed in breaking alignment.
3. Same behavior applies in addons `ReActDriver`.
   - Inline comments were added in both drivers to document this expectation.
4. Wiring currently injects `CanCreateInference` only in primary callsites.
   - Update: agents and addons now have dedicated ReAct runtime-wired paths via `UseReActConfig` and `ToolUseFactory::react(...)`.
   - Internal/default callsites were migrated; external/manual callsites are expected to pass explicit creators.
5. `StructuredOutputRuntime` now has static convenience factories (`fromConfig` / `fromResolver` / `fromProvider` / `fromDsn` / `using`).
   - `StructuredOutput::toRuntime()` remains supported and is still the canonical facade-to-runtime extraction path.
