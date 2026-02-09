# Review: Better Context Design

Reviewer notes on `better-context.md`. Structured as strengths, gaps, issues, and proposed improvements.

---

## Strengths

### 1. Core Thesis is Sound
The separation of data (AgentContext as pure snapshot) from policy (strategy contracts) is the right move. The current `messagesForInference()` and `withStepOutputRouted()` bake policy into the data layer, making it impossible to swap behavior without subclassing or monkey-patching the data object. Every other framework we studied keeps data dumb.

### 2. ContextTokenBudget as Value Object
Explicit, queryable budget with `remaining()` and `overSoftThreshold()` is a clear improvement over the current implicit "hooks check token count against hardcoded thresholds." Making the budget a first-class value means any strategy can reason about headroom without reimplementing the math.

### 3. Composable Compaction Pipeline
The `CanTransformContext` chain (dedupe → prune → summarize → truncate → normalize) is well-grounded in research. Opencode's prune-then-compact, Cline's dedup-then-truncate, and Gemini-CLI's multi-phase compression all validate this layered approach. The interface is minimal: `Messages × Budget → Messages`.

### 4. Tool-Trace Isolation as Explicit Policy
Promoting isolation from a hardcoded section mechanism to a named contract (`CanApplyToolTraceIsolation`) with three concrete strategies (boundary, observation-masking, transparent) directly reflects the research findings. This is the most research-informed part of the design.

### 5. Two Assembler Tiers
MinimalInputAssembler (just compile) vs PolicyDrivenInputAssembler (full pipeline) avoids forcing the heavy pipeline on simple agents. The minimal default is a good zero-cost starting point.

### 6. The "Canonical Log + Views" Model
Moving from prescribed sections to a single interaction log with projected views is architecturally cleaner. Summaries become message artifacts with metadata rather than a named section, which is more flexible and composable.

---

## Gaps

### 1. Message-Level Metadata is Assumed but Never Defined
The design relies heavily on message metadata throughout:
- Pinned messages: `metadata["pinned"] = true`
- Summary artifacts: `kind=summary, scope=historical`
- Tool-trace isolation needs to distinguish tool_call/tool_result from user/assistant
- Compaction transforms need to identify what's pruneable vs protected

But the current `Message` class has no metadata field. The entire strategy layer depends on a data capability that doesn't exist yet. **This is the biggest unstated prerequisite.** The design should specify what message-level metadata looks like and how it's set during routing.

### 2. Existing Summarization Hooks Have No Migration Path
`MoveMessagesToBufferHook` and `SummarizeBufferHook` are deeply coupled to the section mechanism:
- They reference `'buffer'` and `'summary'` sections by name
- They use `$state->store()->section($name)` to move messages between sections
- `SummarizationPolicy` defines section names as configuration

The proposal says "no phased migration" and removes sections entirely. These hooks would break with no replacement specified. The design should either:
- Show how these hooks map to the new pipeline transforms
- Or explicitly state they're replaced by `PipelineCompactor` and show the equivalence

### 3. Driver Injection is Unspecified
The proposal says "Drivers ask `CanAssembleAgentInput->assemble($state)`" but doesn't show how drivers receive the assembler. Currently:
- `ToolCallingDriver` calls `$state->context()->messagesForInference()` directly
- Drivers are created by `AgentBuilder::buildDriver()` with no assembler parameter
- `AgentLoop` receives a driver but has no assembler reference

The wiring path `AgentBuilder → Driver → Assembler` needs to be specified. Options:
- Inject assembler into the driver constructor
- Inject assembler into AgentLoop, which passes messages to the driver
- Have drivers accept a message supplier callback

This is a critical integration point that's missing.

### 4. Hook System Interaction is Unaddressed
The current hook system (`HookStack`) runs at lifecycle points and can mutate `AgentState.context` freely. With the new design:
- Do hooks still mutate context directly? If so, they bypass the strategy layer.
- Does `ApplyContextConfigHook` (which sets system prompt before each step) still work the same way?
- Can hooks interact with the assembler pipeline (e.g., inject messages mid-assembly)?

The hook system and strategy contracts operate in parallel — the design doesn't define their boundary or interaction protocol.

### 5. forNextExecution() Semantics Change
Currently `AgentState::forNextExecution()` (line 120) clears the execution buffer section:
```php
$store = $this->context->store()->section(AgentContext::EXECUTION_BUFFER_SECTION)->clear();
```

With sections removed, what does "prepare for next execution" mean? The canonical log doesn't have a section to clear. The tool-trace isolation policy presumably handles this, but the boundary between "execution lifecycle" and "isolation policy" is blurred.

### 6. File Operation Tracking Across Compactions
The pi-mono research identified file operation tracking (which files were read/modified) as valuable context that survives compaction. The design mentions "must not erase critical execution facts" as a principle but provides no mechanism for tracking file operations separately from message content.

### 7. No Error/Retry Context Management
The design covers the happy path well but doesn't address:
- How failed tool executions are represented in the canonical log
- Whether error messages have different compaction rules (the JetBrains research suggests keeping error reasoning is valuable)
- How retry sequences appear in the conversation vs reasoning views

---

## Issues

### 1. Interface Proliferation Risk
The design introduces **10 contracts** for context management:
- CanRouteStepOutput
- CanNormalizeContext
- CanProjectContextView
- CanApplyToolTraceIsolation (introduced in §6 but absent from §2's interface list)
- CanEstimateTokens
- CanManageTokenBudget
- CanDecideCompaction
- CanCompactMessages
- CanCompileMessages
- CanAssembleAgentInput

Plus `CanTransformContext` for compaction pipeline stages.

For a v1, this is over-specified. Several of these could be collapsed without losing flexibility:

- **CanNormalizeContext + CanProjectContextView** → Both transform Messages. A single `CanTransformContext` used in a pipeline covers both.
- **CanEstimateTokens** → Could be a utility/function rather than a contract. Token estimation doesn't vary much — it's almost always chars/4 or a tokenizer call. Making it a strategy contract implies it will be swapped, which is unlikely.
- **CanDecideCompaction** → A method on CanManageTokenBudget or a closure, not a standalone interface. The decision is tightly coupled to the budget.

Recommendation: Start with **5 core contracts** (CanRouteStepOutput, CanAssembleAgentInput, CanCompactMessages, CanCompileMessages, CanManageTokenBudget) and extract more only when real usage demands it.

### 2. CanProjectContextView Combines Two Consumers
```php
interface CanProjectContextView {
    public function conversationView(Messages $messages): Messages;
    public function reasoningView(Messages $messages): Messages;
}
```

`conversationView` serves the UI/user. `reasoningView` serves the LLM. These are fundamentally different consumers with different lifecycles. Combining them forces every implementer to handle both. The LLM pipeline only needs `reasoningView` — it shouldn't carry a UI concern.

Split this into two interfaces or make `conversationView` a separate optional concern.

### 3. CanRouteStepOutput Returns AgentState, Not AgentContext
```php
interface CanRouteStepOutput {
    public function route(AgentState $state, AgentStep $step): AgentState;
}
```

This gives the router power to mutate anything on AgentState — budget, execution state, metadata — not just context. If the intent is "route step output into the canonical log," the return type should be `AgentContext` (or `Messages`), not the entire state. Returning AgentState makes the router's scope ambiguous and opens the door to side effects.

### 4. PolicyDrivenInputAssembler Has a Fixed Pipeline Order
```php
$normalized = $this->normalizer->normalize(...);
$reasoningView = $this->projector->reasoningView($normalized);
$isolated = $this->toolTraceIsolation->apply(...);
$budget = $this->budgetManager->budgetFor(...);
$prepared = $this->compactionDecision->shouldCompact($budget)
    ? $this->compactor->compact(...)
    : $isolated;
return $this->compiler->compile(...);
```

This hardcodes the order: normalize → project → isolate → budget → compact → compile. But:
- What if compaction should happen before projection? (Compact first, then project a clean view)
- What if isolation should happen after compaction? (Compact everything, then mask old tool outputs)
- What if budget needs re-checking after compaction? (Compaction may not free enough)

The pipeline order should itself be configurable, or the assembler should be the customization point (replace the whole assembler, not the individual steps).

### 5. CanDecideCompaction Receives Only Budget
```php
interface CanDecideCompaction {
    public function shouldCompact(ContextTokenBudget $budget): bool;
}
```

Some compaction decisions need more than token counts:
- Agno triggers on count of uncompressed tool results (not tokens)
- Cline checks if file read deduplication can help before compacting
- Custom policies might trigger based on step count, elapsed time, or message types

The budget alone is insufficient. The interface should receive `AgentState` or at minimum `Messages` alongside the budget.

### 6. CanCompileMessages Signature Redundancy
```php
interface CanCompileMessages {
    public function compile(AgentState $state, Messages $messages): Messages;
}
```

The compiler receives both `AgentState` (which contains messages) and a separate `Messages` parameter. This is ambiguous — which messages is it compiling? The intent is "compile these (already-processed) messages, using state for additional context" but the signature doesn't communicate that. Either:
- Remove the `Messages` parameter and let the compiler get messages from state
- Or remove the `AgentState` parameter if the compiler only needs messages

### 7. "No Phased Migration" is Risky
The document says "no phased migration — direct replacement target." Given that:
- All 3 driver implementations (ToolCallingDriver, ReActDriver, FakeAgentDriver) call `messagesForInference()` directly
- The summarization capability (2 hooks + 1 policy + 2 utils + 1 contract) is section-dependent
- Tests presumably assert on section-based behavior
- `AgentState::forNextExecution()` clears a specific section
- `AgentState::withCurrentStep()` calls `withStepOutputRouted()` directly

A big-bang replacement has a high blast radius. Consider:
- Phase 1: Add `CanAssembleAgentInput` and wire it into drivers alongside (not replacing) `messagesForInference()`. Existing behavior as default assembler.
- Phase 2: Migrate routing from AgentContext to injected `CanRouteStepOutput`.
- Phase 3: Remove section constants and `messagesForInference()`.

---

## Proposed Improvements

### 1. Define Message Metadata Contract First
Before any context strategy work, define how messages carry metadata:
```php
final readonly class MessageMeta {
    public function __construct(
        public string $origin,       // 'user' | 'assistant' | 'tool_call' | 'tool_result' | 'summary' | 'system'
        public ?string $executionId, // which execution produced this
        public ?string $toolName,    // for tool_call/tool_result
        public bool $pinned = false,
        public bool $compacted = false,  // tool output was cleared/masked
        public array $extra = [],
    ) {}
}
```
This is the foundation that projection, isolation, compaction, and deduplication all depend on.

### 2. Reduce to 5+1 Core Contracts

**Core 5** (stable, always needed):
```
CanRouteStepOutput    → Where do step messages go in the log?
CanAssembleAgentInput → How are messages prepared for inference?
CanManageTokenBudget  → What's the budget situation?
CanCompactMessages    → How do we reduce context size?
CanCompileMessages    → Final message transformation before LLM call
```

**+1 Extension** (opt-in for advanced use):
```
CanTransformContext   → A single transform interface used for:
                        normalization, projection, isolation,
                        deduplication, pruning, observation masking
```

The assembler becomes a pipeline of `CanTransformContext` stages followed by `CanCompileMessages`, with compaction triggered by `CanManageTokenBudget` at a configurable point in the pipeline.

### 3. Make the Pipeline a Data Structure, Not Hardcoded

Instead of `PolicyDrivenInputAssembler` with fixed constructor params and fixed order:

```php
final readonly class PipelineInputAssembler implements CanAssembleAgentInput
{
    /** @param CanTransformContext[] $transforms */
    public function __construct(
        private array $transforms,
        private CanManageTokenBudget $budgetManager,
        private CanCompactMessages $compactor,
        private CanCompileMessages $compiler,
    ) {}

    public function assemble(AgentState $state): Messages {
        $messages = $state->context()->events();

        foreach ($this->transforms as $transform) {
            $messages = $transform->transform($messages, $state);
        }

        $budget = $this->budgetManager->budgetFor($state, $messages);
        if ($budget->overSoftThreshold()) {
            $messages = $this->compactor->compact($state, $messages, $budget);
        }

        return $this->compiler->compile($state, $messages);
    }
}
```

Users configure the pipeline by providing transforms in order:
```php
$builder->withInputAssembler(new PipelineInputAssembler(
    transforms: [
        new NormalizeRoleOrder(),
        new DeduplicateFileReads(),
        new MaskOldToolOutputs(keepRecent: 3),
    ],
    budgetManager: new DefaultTokenBudgetManager(...),
    compactor: new PipelineCompactor([
        new PruneLargeToolOutputs(),
        new IterativeSummaryUpdate($summarizer),
    ]),
    compiler: new DefaultCompiler(),
));
```

This gives full ordering control without interface proliferation.

### 4. Narrow CanRouteStepOutput's Return Type
```php
interface CanRouteStepOutput {
    public function route(AgentContext $context, AgentStep $step): AgentContext;
}
```

Return `AgentContext`, not `AgentState`. The router's job is context mutation, not arbitrary state changes. `AgentState::withCurrentStep()` calls the router and composes the result with execution state changes.

### 5. Widen CanDecideCompaction's Input
```php
interface CanDecideCompaction {
    public function shouldCompact(AgentState $state, Messages $messages, ContextTokenBudget $budget): bool;
}
```

Or fold it into `CanManageTokenBudget`:
```php
interface CanManageTokenBudget {
    public function budgetFor(AgentState $state, Messages $messages): ContextTokenBudget;
    public function shouldCompact(ContextTokenBudget $budget): bool;
}
```

### 6. Specify the Driver Integration Point
Add to the design:
```php
// AgentLoop receives the assembler
final class AgentLoop {
    public function __construct(
        // ... existing params ...
        private CanAssembleAgentInput $inputAssembler,
    ) {}
}

// Driver.useTools receives assembled messages, not raw state
interface CanUseTools {
    public function useTools(AgentState $state, Messages $inferenceMessages, Tools $tools, CanExecuteToolCalls $executor): AgentState;
}
```

This makes it explicit: AgentLoop calls the assembler, passes result to the driver. The driver never touches context assembly.

### 7. Plan Migration in 3 Phases

**Phase 1 — Introduce assembler (additive, nothing breaks):**
- Add `CanAssembleAgentInput` interface
- Create `LegacyInputAssembler` that wraps current `messagesForInference()`
- Inject assembler into AgentLoop/drivers
- All existing behavior preserved

**Phase 2 — Migrate policies out of AgentContext:**
- Move routing logic from `withStepOutputRouted()` to `DefaultStepOutputRouter`
- Move section compilation from `messagesForInference()` to `DefaultContextCompiler`
- Deprecate the old methods

**Phase 3 — Clean up:**
- Remove section constants from AgentContext
- Remove `messagesForInference()` and `withStepOutputRouted()`
- Migrate summarization hooks to pipeline transforms
- AgentContext becomes pure data

### 8. Address forNextExecution() Explicitly
Define what "reset for next execution" means without sections:

```php
interface CanRouteStepOutput {
    public function route(AgentContext $context, AgentStep $step): AgentContext;
    public function resetForNextExecution(AgentContext $context): AgentContext;
}
```

Or add it to a lifecycle contract. The current section-clear behavior needs an explicit replacement.

---

## Summary Assessment

The design is **directionally correct and well-researched**. The core ideas — data/policy separation, composable compaction pipeline, explicit token budget, tool-trace isolation as named policy — are all validated by the cross-project research.

The main risks are:
1. **Interface proliferation** before real usage validates which abstractions earn their keep
2. **Missing data prerequisite** (message metadata) that the entire strategy layer depends on
3. **Unspecified integration points** (driver wiring, hook interaction, forNextExecution)
4. **Big-bang migration** when a phased approach would be safer

Recommendation: Reduce to 5+1 contracts, define message metadata first, specify driver injection, and migrate in 3 phases. The design philosophy is right — the implementation plan needs tightening.
