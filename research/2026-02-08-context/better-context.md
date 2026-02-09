# Better Context Design (Clean Solution)

## Decision

`CanCompileMessages` is the foundation of context handling.
It is an essential runtime contract and should live in `Cognesy\Agents\Core\Contracts`.
`AgentLoop` tool-use drivers depend on this contract to obtain inference messages for a given `AgentState`.

`AgentContext` becomes a pure immutable data snapshot.
Context policies stay optional and composable, but they are internal collaborators of compiler implementations, not the runtime boundary.

No phased migration. The new design is a direct replacement target.

## Why This Is Necessary

Current `AgentContext` mixes at least three concerns:

1. Context data (`MessageStore`, metadata, system prompt, response format)
2. Policy (`messagesForInference()` hardcoded section order)
3. State transition (`withStepOutputRouted()` hardcoded routing rules)

This makes context rigid, hard to swap, and internally inconsistent with existing extension points (`CanCompileMessages`).

## What We Learned From Other Agents

Cross-project patterns from `pi-mono`, `pydantic-ai`, `agno`, `codex`, `gemini-cli`, `opencode`, `cline`:

1. Context data is simple; policy is pluggable.
2. Token control is explicit and computed before inference.
3. Compaction is policy-driven (thresholds + reserve budget), not hardcoded into storage.
4. Compaction is multi-stage in mature systems:
   - cheap transforms first (dedupe/prune)
   - expensive summarization only when needed
5. Extension points are first-class for custom behavior (hooks/processors/plugins).

The main lesson: keep state dumb, keep behavior composable.

## Critical Correction: Preserve Tool-Trace Isolation As A View

The previous version over-indexed on a single flat transcript view.
That would risk losing a useful property of the current design: isolating the main user-facing conversation from dense tool-call chains.

Corrected principle:

1. Keep one canonical interaction log.
2. Project multiple views from that log:
   - `conversation view` (clean thread: user <-> assistant outcomes)
   - `reasoning view` (conversation + selected tool trace for inference)
3. Compaction may reduce old tool trace detail, but must not erase critical execution facts.

This is the practical middle ground used by strong systems: hide complexity in UI/main thread, keep enough execution trace for model reasoning.

## Clean Architecture

### 1. Foundation Contract: `CanCompileMessages` (`Core\Contracts`)

This is the essential contract for runtime inference input.
Drivers and `AgentLoop` should depend on this contract only.

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Contracts;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;

interface CanCompileMessages
{
    public function compile(AgentState $state): Messages;
}
```

### 2. Data Layer: `AgentContext`

`AgentContext` is only immutable data:

- `Messages $events` (canonical interaction log: user/assistant/tool/error/summary artifacts)
- `Metadata $metadata`
- `string $systemPrompt`
- `ResponseFormat $responseFormat`

No section constants, no routing logic, no compilation order, no compaction logic.

### 3. Optional Policy Contracts (Used Inside Compiler Implementations)

These contracts are not runtime entry points.
They are optional collaborators for concrete `CanCompileMessages` implementations.

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Context;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Messages\Messages;

interface CanRouteStepOutput
{
    public function route(AgentState $state, AgentStep $step): AgentState;
}

interface CanNormalizeContext
{
    public function normalize(Messages $messages): Messages;
}

interface CanProjectContextView
{
    public function conversationView(Messages $messages): Messages;
    public function reasoningView(Messages $messages): Messages;
}

interface CanEstimateTokens
{
    public function estimate(Messages $messages): int;
}

interface CanComputeTokenBudget
{
    public function budgetFor(AgentState $state, int $usedTokens): ContextTokenBudget;
}

interface CanDecideCompaction
{
    public function shouldCompact(ContextTokenBudget $budget): bool;
}

interface CanCompactMessages
{
    public function compact(AgentState $state, Messages $messages, ContextTokenBudget $budget): Messages;
}
```

### 4. Compiler Profiles (Pragmatic First, Maximal Optional)

To avoid policy proliferation, define compiler profiles.
Only one contract is mandatory (`CanCompileMessages`), complexity is selected by implementation.

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Context;

use Cognesy\Agents\Core\Contracts\CanCompileMessages;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;

final readonly class PragmaticMessageCompiler implements CanCompileMessages
{
    public function __construct(
        private CanProjectContextView $projector,
    ) {}

    public function compile(AgentState $state): Messages {
        return $this->projector->reasoningView($state->context()->events());
    }
}

final readonly class BudgetAwareMessageCompiler implements CanCompileMessages
{
    public function __construct(
        private CanProjectContextView $projector,
        private CanEstimateTokens $tokenEstimator,
        private CanComputeTokenBudget $tokenBudget,
        private CanDecideCompaction $compactionDecision,
        private CanCompactMessages $compactor,
    ) {}

    public function compile(AgentState $state): Messages {
        $reasoningView = $this->projector->reasoningView($state->context()->events());
        $usedTokens = $this->tokenEstimator->estimate($reasoningView);
        $budget = $this->tokenBudget->budgetFor($state, $usedTokens);

        return match (true) {
            $this->compactionDecision->shouldCompact($budget) => $this->compactor->compact($state, $reasoningView, $budget),
            default => $reasoningView,
        };
    }
}

final readonly class PolicyDrivenMessageCompiler implements CanCompileMessages
{
    public function __construct(
        private CanNormalizeContext $normalizer,
        private CanProjectContextView $projector,
        private CanApplyToolTraceIsolation $toolTraceIsolation,
        private CanEstimateTokens $tokenEstimator,
        private CanComputeTokenBudget $tokenBudget,
        private CanDecideCompaction $compactionDecision,
        private CanCompactMessages $compactor,
    ) {}

    public function compile(AgentState $state): Messages {
        $normalized = $this->normalizer->normalize($state->context()->events());
        $reasoningView = $this->projector->reasoningView($normalized);
        $isolated = $this->toolTraceIsolation->apply($state, $reasoningView);
        $usedTokens = $this->tokenEstimator->estimate($isolated);
        $budget = $this->tokenBudget->budgetFor($state, $usedTokens);

        return match (true) {
            $this->compactionDecision->shouldCompact($budget) => $this->compactor->compact($state, $isolated, $budget),
            default => $isolated,
        };
    }
}
```

Default should be `PragmaticMessageCompiler`.
`PolicyDrivenMessageCompiler` stays available for advanced workloads.

## Token Budget Model (Explicit)

Define a dedicated value object:

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Context;

final readonly class ContextTokenBudget
{
    public function __construct(
        public int $contextWindow,
        public int $usedTokens,
        public int $reservedOutputTokens,
        public int $reservedSystemTokens,
        public int $softCompactThreshold,
    ) {}

    public function remaining(): int {
        $reserved = $this->reservedOutputTokens + $this->reservedSystemTokens;
        return max(0, $this->contextWindow - $this->usedTokens - $reserved);
    }

    public function overSoftThreshold(): bool {
        return $this->usedTokens >= $this->softCompactThreshold;
    }
}
```

Decision rule:

- compact if `overSoftThreshold()` is true
- compact if `remaining()` is below minimum headroom
- hard-stop only if compaction cannot recover enough room

This aligns with how robust agents avoid overflow loops.

## Compaction Model (Composable, Not Section-Based)

Compaction acts on message stream selectors and message metadata, not section names.

### Required invariants

1. Preserve system prompt and initial task framing
2. Preserve tool-call and tool-result pair integrity
3. Preserve latest N turns (recency window)
4. Preserve pinned/critical messages (`metadata["pinned"] = true`)
5. Preserve unresolved/failed tool executions until explicitly resolved
6. Never orphan a tool-result from its call after any transform
7. Keep tool-call visibility within current execution loop; isolate or compact mostly at execution boundaries

### Default compaction pipeline

1. `DeduplicateReadsTransform` (optional)
2. `PruneLargeToolOutputsTransform` (token-based)
3. `IterativeSummaryUpdateTransform` (LLM update using previous summary artifact)
4. `AdaptiveTruncationFallbackTransform` (only if still above budget)
5. `NormalizeAfterCompactionTransform`

Each transform implements:

```php
interface CanTransformContext
{
    public function transform(Messages $messages, ContextTokenBudget $budget): Messages;
}
```

`CanCompactMessages` is implemented as a pipeline of `CanTransformContext`.

## Tool-Trace Isolation Policy (From Research)

Based on `research/2026-02-08-context/tool-isolation.md`, isolation is not binary.
Best practice is layered:

1. During current execution loop, keep tool call/result context visible to reasoning.
2. At execution boundary, prevent old raw tool outputs from polluting the main conversation view.
3. Prefer observation masking over full deletion when possible.

Use an explicit contract:

```php
interface CanApplyToolTraceIsolation
{
    public function apply(AgentState $state, Messages $reasoningView): Messages;
}
```

Recommended strategies:

1. `BoundaryIsolationPolicy`
   - preserve current execution trace
   - on completion, keep only final response + compacted/masked trace artifact
2. `ObservationMaskingPolicy`
   - keep reasoning and action records
   - replace large historical tool outputs with placeholders
3. `TransparentPolicy`
   - keep full trace (debug/eval modes)

## Custom Context Management Strategies

Primary customization point is replacing the compiler implementation:

- `withContextCompiler(Core\Contracts\CanCompileMessages $compiler)`

Most teams should only swap compiler profile (`PragmaticMessageCompiler` vs `BudgetAwareMessageCompiler` vs `PolicyDrivenMessageCompiler`).
Only advanced use cases should override individual policy contracts.

Secondary policy-level customization in `AgentBuilder`:

- `withContextRouter(CanRouteStepOutput $router)`
- `withContextNormalizer(CanNormalizeContext $normalizer)`
- `withContextProjector(CanProjectContextView $projector)`
- `withTokenEstimator(CanEstimateTokens $estimator)`
- `withTokenBudget(CanComputeTokenBudget $budget)`
- `withCompactionDecision(CanDecideCompaction $decision)`
- `withCompactor(CanCompactMessages $compactor)`
- `withToolTraceIsolation(CanApplyToolTraceIsolation $isolation)`

This keeps extension power while limiting policy proliferation in normal usage.

## What Context Consists Of In This Model

Context is a transcript plus policy-derived views.

Core components:

1. Canonical interaction log (`Messages`)
2. System prompt (separate field)
3. Response format constraints
4. Metadata (including pinning/annotations)

Derived components (runtime only):

1. Normalized interaction log
2. Conversation view (clean main thread)
3. Reasoning view (for inference)
4. Compacted reasoning view
5. Token budget snapshot

No hardcoded `buffer` or `summary` sections are required.
Summary becomes a message artifact with explicit metadata (`kind=summary`, `scope=historical`).

## Direct Improvements To `AgentContext`

`AgentContext` should be changed to:

1. Remove section constants:
   - `DEFAULT_SECTION`
   - `BUFFER_SECTION`
   - `SUMMARY_SECTION`
   - `EXECUTION_BUFFER_SECTION`
2. Remove `messagesForInference()`
3. Remove `withStepOutputRouted()`
4. Keep only data accessors, `with*()` mutators, and serialization
5. Expose canonical log accessor (for example `events()`) instead of section-bound accessors
6. Hold one canonical `Messages` interaction log instead of prescribed section mechanism

This cleanly separates data from policy.

## Direct Improvements To Core Contracts And Drivers

1. Move compiler contract to `Core\Contracts` as a first-class runtime dependency:
   - `packages/agents/src/Core/Contracts/CanCompileMessages.php`
2. Update tool-use drivers to depend on `Core\Contracts\CanCompileMessages` when building inference input.
3. `AgentLoop` keeps orchestrating steps but remains agnostic to context policy details.
4. Concrete compiler implementation is injected from `AgentBuilder` and can be swapped per agent.

## Direct Improvements To Runtime Behavior

1. Drivers no longer call `context()->messagesForInference()`.
2. Drivers ask `Core\Contracts\CanCompileMessages->compile($state)`.
3. `AgentState::withCurrentStep()` no longer routes output itself.
4. Output routing is applied by injected `CanRouteStepOutput` policy.
5. UI/main thread rendering uses projected `conversationView`, not raw execution trace.

This removes hidden coupling and makes context behavior replaceable.

## Default Implementations (Recommended)

1. `DefaultStepOutputRouter`
   - append assistant/tool output into canonical interaction log with stable metadata tags
2. `DefaultContextProjector`
   - expose both conversation view and reasoning view
3. `PragmaticMessageCompiler` (default)
   - minimal dependency surface, good default for most agents
4. `BudgetAwareMessageCompiler` (optional)
   - adds token-aware compaction with limited policy surface
5. `PolicyDrivenMessageCompiler` (advanced)
   - full policy chain for strict control needs
6. `DefaultContextNormalizer`
   - ensure valid role order and tool-call/result integrity
7. `BoundaryIsolationPolicy`
   - keep current tool loop context, isolate historical raw tool output at boundary
8. `DefaultTokenEstimator`
   - tokenizer-based count over compiled transcript
9. `DefaultTokenBudget`
   - compute `ContextTokenBudget` from model window and reserves
10. `ThresholdCompactionDecision`
   - compact when soft threshold or low headroom reached
11. `PipelineCompactor`
   - dedupe, prune, iterative summary update, fallback truncation, normalize

## Non-Negotiable Design Rules

1. `AgentContext` has zero policy logic.
2. Foundation runtime contract is `Core\Contracts\CanCompileMessages`.
3. Drivers and `AgentLoop` depend on compiler contract, not on policy contracts.
4. Token budgeting is explicit and queryable at runtime.
5. Compaction is proactive and policy-driven.
6. Compaction must preserve tool-pair integrity and pinned context.
7. Custom strategies must be injectable without subclassing `AgentLoop`.
8. Conversation cleanliness and reasoning completeness are separate concerns and must be represented as separate views.
9. Prefer compiler profiles to individual policy overrides to avoid policy proliferation.
10. Do not introduce parallel `*Manager` or assembler runtime contracts.

## Expected Outcome

This design makes context management:

1. Flexible: behavior is fully swappable
2. Predictable: token decisions are explicit
3. Safer: integrity invariants enforced centrally
4. Extensible: custom context strategies become first-class
5. Simpler: `AgentContext` is just immutable state
