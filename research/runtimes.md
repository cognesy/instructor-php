# Runtime Layer Design - Remaining Work

Last updated: 2026-02-15

Completed items were moved to `research/runtimes-done.md`.
This document tracks only unfinished runtime migration work.

## Remaining Scope

- Finish facade runtime ergonomics (`toRuntime`, runtime factories, caching/invalidation).
- Complete DI/container wiring for runtime contracts (Laravel package).
- Complete agents/addons migration outside tool-use drivers.
- Remove compatibility bridges after one release cycle.
- Decide whether to run optional V2 request/config cleanup.

## Open Work By Phase

### Phase 0: Invariants and Regression Safety

Status: Partial.

Remaining:
- Add/verify explicit regression tests for request constructor named args:
  - `InferenceRequest` (`retryPolicy`, `responseCachePolicy`, etc.)
  - `EmbeddingsRequest`
  - `StructuredOutputRequest`
- Document precedence rule in tests/docs as a hard invariant:
  - request field override -> runtime/provider default.

### Phase 3: Facade Delegation Completion

Status: Partial.

Done already:
- Facade `create()` paths delegate through runtimes.

Remaining:
- Add explicit runtime extraction APIs:
  - `Inference::toRuntime()`
  - `Embeddings::toRuntime()`
  - `StructuredOutput::toRuntime()`
- Add runtime convenience factories:
  - `InferenceRuntime::fromConfig()/fromDsn()/using()`
  - `EmbeddingsRuntime::fromConfig()/fromDsn()/using()`
- Add runtime caching inside facades.
- Implement cache invalidation for infrastructure mutators only.
- Add dedicated tests for cache reuse + invalidation boundaries.
- Replace facade-as-contract injections in wiring with explicit runtime instances where practical
  (`InferenceRuntime` / `StructuredOutputRuntime`) once extraction/factory APIs are available.

### Phase 4: Laravel Container + Testing Surface

Status: Not started.

Remaining:
- Register runtime contracts as singletons in service provider:
  - `CanCreateInference`
  - `CanCreateStructuredOutput`
  - `CanCreateEmbeddings`
- Ensure container runtime wiring matches default preset/config resolution.
- Ensure fakes and facade helpers still work with runtime-backed flows.
- Add/adjust Laravel integration tests:
  - `app(CanCreateInference::class)->create($request)` path
  - facade + container coexistence behavior.

### Phase 5: Internal Callsite Migration Completion

Status: Partial.

Done already:
- Agents/addons tool-use drivers accept direct creator contracts.
- Primary constructor wiring updated in `AgentLoop`, `UseLLMConfig`, `DefinitionLoopFactory`, `ToolUseFactory`.

Remaining:
- Migrate remaining agents/addons components that still directly depend on facades in constructors/fields:
  - `packages/agents/src/Capability/SelfCritique/SelfCriticHook.php`
  - `packages/agents/src/Capability/Summarization/Utils/SummarizeMessages.php`
  - `packages/agents/src/Capability/StructuredOutput/StructuredOutputTool.php`
  - `packages/addons/src/Chat/Participants/LLMParticipant.php`
  - `packages/addons/src/Collaboration/Collaborators/LLMCollaborator.php`
  - `packages/addons/src/Chat/Selectors/LLMSelector/LLMBasedCoordinator.php`
  - `packages/addons/src/Collaboration/Selectors/LLMSelector/LLMBasedCoordinator.php`
  - `packages/addons/src/Chat/Utils/SummarizeMessages.php`
  - `packages/addons/src/Collaboration/Utils/SummarizeMessages.php`
  - `packages/addons/src/Image/Image.php`
- Narrow/remove compatibility bridge in instructor:
  - `packages/instructor/src/Core/InferenceProvider.php` currently accepts `CanCreateInference|LLMProvider`; target `CanCreateInference` only.
- Complete `CanCreateStructuredOutput` wiring at callsites using ReAct drivers, not only `CanCreateInference`.
- Keep/document current expectation: injected `CanCreateStructuredOutput` must be preconfigured with
  desired `outputMode` and `maxRetries` (these are not request fields in V1).
- Deprecate then remove fallback paths in tool-use drivers (`new Inference()` / `new StructuredOutput()` branches).
- Plan deprecation/removal of `CanAcceptLLMProvider` from new runtime-first paths.

Acceptance target for this phase:
- No runtime-critical hot paths in agents/addons instantiate `Inference` or `StructuredOutput` facades directly.

### Phase 6: Optional V2 Request/Config Cleanup (Separate ADR)

Status: Not started (optional).

Open decision:
- Keep V1 split as-is, or move more behavior fields to request layer.

Potential scope if approved:
- Revisit grouped request config VOs only if duplication remains painful.
- Revisit moving StructuredOutput behavior fields from config to request where it simplifies per-call variation.

## Current Risks

- Facade runtime recreation on each call still leaves performance on the table until Phase 3 caching is done.
- Partial migration means both runtime-first and facade-first patterns coexist, increasing maintenance overhead.
- Compatibility bridges (`LLMProvider` fallback unions and fallback constructor paths) can hide migration regressions if not explicitly deprecated with tests.

## Quality Gates For Remaining Work

- Run package-focused tests at each phase boundary (`polyglot`, `instructor`, `laravel`, `agents`, `addons`).
- Run static analysis (`phpstan`, `psalm`) on changed files each phase.
- Do not remove compatibility bridges until runtime-first paths are fully covered by tests.
